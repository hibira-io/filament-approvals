<?php

namespace Hibira\FilamentApprovals\Tests;

use Filament\Facades\Filament;
use Hibira\FilamentApprovals\HibiraApprovals;
use Hibira\FilamentApprovals\Jobs\ResumeAgent;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Hibira\FilamentApprovals\Models\ToolCall;
use Hibira\FilamentApprovals\Pages\Approvals;
use Hibira\FilamentApprovals\Tests\Fixtures\PendingStore;
use Hibira\FilamentApprovals\Tests\Fixtures\RefundAgent;
use Hibira\FilamentApprovals\Tests\Fixtures\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Livewire\Livewire;

class PanelTest extends TestCase
{
    protected User $ada;

    protected User $ben;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);
        $this->ben = User::query()->create(['name' => 'Ben', 'email' => 'ben@example.com', 'password' => 'x']);
        $this->customer = User::query()->create(['name' => 'Mia', 'email' => 'mia@example.com', 'password' => 'x']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_inbox_page_renders_with_its_live_channels(): void
    {
        $this->pause($this->customer);

        $this->actingAs($this->ada)
            ->get('/admin/ai-approvals')
            ->assertOk()
            ->assertSee('hibira-approvals.admin.inbox', false)
            ->assertSee('hibira-approvals.admin.reviewers', false)
            ->assertSee('IssueRefund')
            ->assertSee('Refund Agent');
    }

    public function test_approving_a_single_call_resumes_the_agent_with_the_decision(): void
    {
        $this->pause($this->customer);
        $request = ApprovalRequest::query()->sole();
        $call = $request->calls->sole();

        $this->actingAs($this->ada);
        $page = Livewire::test(Approvals::class);

        $opened = $page->call('openRequest', $request->id)->effects['returns'][0];
        $this->assertStringContainsString('"amount": 4999', $opened['calls'][0]['arguments']);

        $decided = $page->call('decide', $call->id, 'approve')->effects['returns'][0];
        $this->assertSame('approve', $decided['calls'][0]['decision']);
        $this->assertSame('Ada', $decided['calls'][0]['decided_by']);
        $this->assertSame('pending', $decided['status']);

        $sent = $page->call('send', $request->id)->effects['returns'][0];
        $this->assertSame('resumed', $sent['status']);
        $this->assertSame('Ada', $sent['decided_by']);

        $this->assertCount(1, RefundAgent::$resumed);
        $resumed = RefundAgent::$resumed[0];
        $this->assertSame('conv_1', $resumed['conversation']);
        $this->assertTrue($this->customer->is($resumed['user']));
        $this->assertInstanceOf(Decisions::class, $resumed['decisions']);
        $this->assertTrue($resumed['decisions']->get('call_1')->isApproved());
        $this->assertNull($resumed['channels']);

        $reasons = array_column(array_column($this->realtime->on('private-hibira-approvals.admin.inbox'), 'data'), 'reason');
        $this->assertSame(['requested', 'decided', 'resuming', 'resumed'], $reasons);
    }

    public function test_reject_and_edit_reach_the_agent(): void
    {
        $this->pause($this->customer, [
            new PendingApproval('call_a', 'IssueRefund', ['order_id' => 1, 'amount' => 9000]),
            new PendingApproval('call_b', 'EmailCustomer', ['subject' => 'Refund']),
        ]);
        $request = ApprovalRequest::query()->with('calls')->sole();
        [$refund, $email] = $request->calls->all();

        $this->actingAs($this->ada);
        $page = Livewire::test(Approvals::class);

        $bad = $page->call('decide', $refund->id, 'edit', '[1, 2]')->effects['returns'][0];
        $this->assertSame('The arguments must be a JSON object.', $bad['error']);

        $page->call('decide', $refund->id, 'edit', '{"order_id": 1, "amount": 2000}');
        $early = $page->call('send', $request->id)->effects['returns'][0];
        $this->assertArrayHasKey('error', $early);
        $this->assertSame([], RefundAgent::$resumed);

        $page->call('decide', $email->id, 'reject', null, '  Not yet, wait for the refund.  ');
        $page->call('send', $request->id);

        $decisions = RefundAgent::$resumed[0]['decisions'];
        $this->assertTrue($decisions->get('call_a')->isEdited());
        $this->assertSame(['order_id' => 1, 'amount' => 2000], $decisions->get('call_a')->arguments);
        $this->assertTrue($decisions->get('call_b')->isRejected());
        $this->assertSame('Not yet, wait for the refund.', $decisions->get('call_b')->result);
    }

    public function test_approve_all_decides_the_rest_and_sends(): void
    {
        $this->pause(null, [
            new PendingApproval('call_a', 'IssueRefund', ['amount' => 1]),
            new PendingApproval('call_b', 'IssueRefund', ['amount' => 2]),
        ]);
        $request = ApprovalRequest::query()->sole();

        $this->actingAs($this->ada);
        $done = Livewire::test(Approvals::class)->call('decideAll', $request->id, 'approve')->effects['returns'][0];

        $this->assertSame('resumed', $done['status']);
        $this->assertSame(['call_a', 'call_b'], array_keys(RefundAgent::$resumed[0]['decisions']->all()));
        $this->assertNull(RefundAgent::$resumed[0]['user']);
    }

    public function test_two_reviewers_sending_at_once_resume_the_agent_once(): void
    {
        Queue::fake();
        $this->pause();
        $request = ApprovalRequest::query()->sole();

        $this->actingAs($this->ada);
        Livewire::test(Approvals::class)->call('decide', $request->calls->sole()->id, 'approve')->call('send', $request->id);

        $this->actingAs($this->ben);
        $second = Livewire::test(Approvals::class)->call('send', $request->id)->effects['returns'][0];
        $this->assertSame('Not sent: another reviewer sent it first, or a call is still undecided.', $second['error']);

        // Nor can a decision change once it is sent.
        Livewire::test(Approvals::class)->call('decide', $request->calls->sole()->id, 'reject');
        $this->assertSame('approve', ToolCall::query()->sole()->decision);

        Queue::assertPushed(ResumeAgent::class, 1);
    }

    public function test_undo_takes_a_decision_back(): void
    {
        $this->pause(null, [new PendingApproval('call_a', 'A', []), new PendingApproval('call_b', 'B', [])]);
        $call = ToolCall::query()->where('tool', 'A')->sole();

        $this->actingAs($this->ada);
        $result = Livewire::test(Approvals::class)->call('decide', $call->id, 'approve')->call('undo', $call->id)->effects['returns'][0];

        $this->assertNull($result['calls'][0]['decision']);
        $this->assertNull($call->refresh()->decided_by);
    }

    public function test_a_turn_answered_meanwhile_is_not_resumed_again(): void
    {
        $this->pause();
        $request = ApprovalRequest::query()->sole();
        // Someone answered in the app's own chat, and the agent paused again on another call.
        PendingStore::$pending = ['conv_1' => [new PendingApproval('call_2', 'IssueRefund', [])]];

        $this->actingAs($this->ada);
        Livewire::test(Approvals::class)->call('decide', $request->calls->sole()->id, 'approve')->call('send', $request->id);

        $this->assertSame([], RefundAgent::$resumed);
        $this->assertSame(ApprovalRequest::RESOLVED, $request->refresh()->status);
    }

    public function test_decisions_the_sdk_calls_stale_mark_the_request_answered_elsewhere(): void
    {
        $this->pause();
        $request = ApprovalRequest::query()->sole();
        HibiraApprovals::resumeUsing(fn () => throw new ApprovalMismatchException('There are no tool calls pending approval.', collect()));

        $this->actingAs($this->ada);
        Livewire::test(Approvals::class)->call('decide', $request->calls->sole()->id, 'approve')->call('send', $request->id);

        $this->assertSame(ApprovalRequest::RESOLVED, $request->refresh()->status);
    }

    public function test_a_failed_resume_is_shown_and_can_be_retried(): void
    {
        RefundAgent::$fails = 'Provider timed out';
        $this->pause();
        $request = ApprovalRequest::query()->sole();

        $this->actingAs($this->ada);
        $page = Livewire::test(Approvals::class);
        $failed = $page->call('decide', $request->calls->sole()->id, 'approve')->call('send', $request->id)->effects['returns'][0];

        $this->assertSame('failed', $failed['status']);
        $this->assertSame('Provider timed out', $failed['error']);

        RefundAgent::$fails = null;
        $retried = $page->call('retry', $request->id)->effects['returns'][0];
        $this->assertSame('resumed', $retried['status']);
        $this->assertCount(1, RefundAgent::$resumed);
    }

    public function test_the_resumed_turn_streams_to_the_channel_you_choose(): void
    {
        HibiraApprovals::broadcastUsing(fn (ApprovalRequest $request, ?object $user) => new PrivateChannel('hibira-ai.user.'.$user->getKey()));
        $this->pause($this->customer);
        $request = ApprovalRequest::query()->sole();

        $this->actingAs($this->ada);
        Livewire::test(Approvals::class)->call('decide', $request->calls->sole()->id, 'approve')->call('send', $request->id);

        $channel = RefundAgent::$resumed[0]['channels'];
        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-hibira-ai.user.'.$this->customer->id, $channel->name);
    }

    public function test_a_custom_resumer_replaces_the_default(): void
    {
        $seen = [];
        HibiraApprovals::resumeUsing(function (ApprovalRequest $request, Decisions $decisions, ?object $user) use (&$seen) {
            $seen = [$request->conversation_id, $decisions->get('call_1')->isRejected(), $user?->getKey()];
        });
        $this->pause($this->customer);
        $request = ApprovalRequest::query()->sole();

        $this->actingAs($this->ada);
        Livewire::test(Approvals::class)->call('decide', $request->calls->sole()->id, 'reject');
        Livewire::test(Approvals::class)->call('send', $request->id);

        $this->assertSame(['conv_1', true, $this->customer->id], $seen);
        $this->assertSame([], RefundAgent::$resumed);
    }

    public function test_reviewers_see_only_what_they_may_decide(): void
    {
        $intern = User::query()->create(['name' => 'Ivy', 'email' => 'intern@example.com', 'password' => 'x']);
        $this->pause(invocation: 'inv_refund', conversation: 'conv_refund');
        $this->pause(approvals: [new PendingApproval('call_x', 'SendNewsletter', [])], invocation: 'inv_news', conversation: 'conv_news');
        $refund = ApprovalRequest::query()->where('invocation_id', 'inv_refund')->sole();

        $this->actingAs($intern);
        $page = Livewire::test(Approvals::class);

        $listed = $page->call('loadRequests', 'pending')->effects['returns'][0];
        $this->assertSame([['SendNewsletter']], array_column($listed, 'tools'));
        $this->assertSame('That request is gone or isn\'t yours to decide.', $page->call('decide', $refund->calls->sole()->id, 'approve')->effects['returns'][0]['error']);
        $this->assertNull($refund->calls->sole()->refresh()->decision);
    }

    public function test_presence_channels_admit_reviewers_with_their_avatar(): void
    {
        $this->pause();
        $request = ApprovalRequest::query()->sole();

        $response = $this->actingAs($this->ada)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-hibira-approvals.admin.request.'.$request->id,
        ])->assertOk();

        $data = json_decode($response->json('channel_data'), true);
        $this->assertSame('Ada', $data['user_info']['name']);

        $this->actingAs($this->ada)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-hibira-approvals.admin.reviewers',
        ])->assertOk();

        $this->actingAs($this->ada)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-hibira-approvals.admin.request.01missing',
        ])->assertForbidden();
    }
}
