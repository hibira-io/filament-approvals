<?php

namespace Hibira\FilamentApprovals\Tests;

use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Hibira\FilamentApprovals\Notifications\ApprovalNeeded;
use Hibira\FilamentApprovals\Tests\Fixtures\RefundAgent;
use Hibira\FilamentApprovals\Tests\Fixtures\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Events\ToolApprovalResolved;
use Laravel\Ai\Responses\Data\ToolResult;

class RecordingTest extends TestCase
{
    public function test_a_paused_turn_is_stored_and_the_inbox_hears_about_it(): void
    {
        Notification::fake();
        $customer = User::query()->create(['name' => 'Mia Chen', 'email' => 'mia@example.com', 'password' => 'x']);

        $this->pause($customer);

        $request = ApprovalRequest::query()->with('calls')->sole();
        $this->assertSame(RefundAgent::class, $request->agent);
        $this->assertSame('Refund Agent', $request->agentName());
        $this->assertSame('conv_1', $request->conversation_id);
        $this->assertSame('Mia Chen', $request->user_name);
        $this->assertTrue($customer->is($request->user()));
        $this->assertSame(ApprovalRequest::PENDING, $request->status);

        $call = $request->calls->sole();
        $this->assertSame(['call_1', 'IssueRefund', ['order_id' => 4192, 'amount' => 4999], 'Refunds over $20 need a manager.'], [$call->tool_call_id, $call->tool, $call->arguments, $call->reason]);

        $events = $this->realtime->on('private-hibira-approvals.admin.inbox', 'approval');
        $this->assertCount(1, $events);
        $data = $events[0]['data'];
        $this->assertStringContainsString($request->id, $data['url']);
        unset($data['url']);
        $this->assertSame(['v' => 1, 'id' => $request->id, 'reason' => 'requested', 'status' => 'pending', 'agent' => 'Refund Agent', 'tools' => ['IssueRefund'], 'by' => null], $data);
    }

    public function test_the_same_pause_is_stored_once(): void
    {
        $this->pause();
        $this->pause();

        $this->assertSame(1, ApprovalRequest::query()->count());
        $this->assertCount(1, $this->realtime->on('private-hibira-approvals.admin.inbox'));
    }

    public function test_only_the_configured_agents_are_stored(): void
    {
        config(['hibira-approvals.agents' => [\stdClass::class]]);
        $this->pause();
        $this->assertSame(0, ApprovalRequest::query()->count());

        config(['hibira-approvals.agents' => [RefundAgent::class]]);
        $this->pause();
        $this->assertSame(1, ApprovalRequest::query()->count());
    }

    public function test_push_goes_only_to_approvers_who_dont_have_the_panel_open(): void
    {
        Notification::fake();
        $this->enablePush();
        $online = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);
        $offline = User::query()->create(['name' => 'Ben', 'email' => 'ben@example.com', 'password' => 'x']);
        $intern = User::query()->create(['name' => 'Ivy', 'email' => 'intern@example.com', 'password' => 'x']);
        $this->realtime->present['presence-hibira-approvals.admin.reviewers'] = [(string) $online->id];

        $this->pause();

        Notification::assertSentTo($offline, ApprovalNeeded::class, function (ApprovalNeeded $notification) use ($offline) {
            $push = $notification->toHibiraPush($offline)->toArray();

            $this->assertSame('Refund Agent needs your approval', $push['web']['notification']['title']);
            $this->assertSame('Wants to run: IssueRefund', $push['web']['notification']['body']);
            $this->assertStringContainsString('/admin/ai-approvals?request=', $push['web']['notification']['deep_link']);
            // The arguments stay in the panel.
            $this->assertStringNotContainsString('4999', json_encode($push));

            return true;
        });
        Notification::assertNotSentTo($online, ApprovalNeeded::class);
        Notification::assertNotSentTo($intern, ApprovalNeeded::class);
    }

    public function test_no_push_without_hibira_push_settings(): void
    {
        Notification::fake();
        User::query()->create(['name' => 'Ben', 'email' => 'ben@example.com', 'password' => 'x']);

        $this->pause();

        Notification::assertNotSentTo(User::query()->sole(), ApprovalNeeded::class);
    }

    public function test_approvers_get_a_filament_database_notification(): void
    {
        $this->enablePush();
        $ada = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);
        $this->realtime->present['presence-hibira-approvals.admin.reviewers'] = [(string) $ada->id];

        $this->pause();

        $notification = $ada->notifications()->sole();
        $this->assertSame('Refund Agent needs your approval', $notification->data['title']);
    }

    public function test_a_turn_answered_elsewhere_is_marked_resolved(): void
    {
        $this->pause();

        event(new ToolApprovalResolved('inv_2', new RefundAgent, new Collection([
            new ToolResult('call_1', 'IssueRefund', [], 'Refunded.'),
        ]), 'conv_1'));

        $this->assertSame(ApprovalRequest::RESOLVED, ApprovalRequest::query()->sole()->status);
    }

    public function test_a_failing_inbox_never_fails_the_agent(): void
    {
        $this->app->instance(\Hibira\FilamentApprovals\ApprovalDesk::class, new class extends \Hibira\FilamentApprovals\ApprovalDesk
        {
            public function __construct() {}

            public function record(\Laravel\Ai\Events\ToolApprovalRequested $event): ?ApprovalRequest
            {
                throw new \RuntimeException('database down');
            }
        });

        $this->pause(approvals: [new PendingApproval('call_9', 'DeleteUser', ['id' => 1])]);

        $this->assertSame(0, ApprovalRequest::query()->count());
    }
}
