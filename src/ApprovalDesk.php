<?php

namespace Hibira\FilamentApprovals;

use Filament\Notifications\Actions\Action as NotificationActionV3;
use Filament\Notifications\Notification as FilamentNotification;
use Hibira\FilamentApprovals\Jobs\ResumeAgent;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Hibira\FilamentApprovals\Models\ToolCall;
use Hibira\FilamentApprovals\Notifications\ApprovalNeeded;
use Hibira\FilamentApprovals\Pages\Approvals;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Laravel\Ai\Responses\Data\ToolResult;
use Throwable;

/**
 * Everything that changes a request: stores it first, then tells the
 * panels' inboxes over Hibira.
 */
class ApprovalDesk
{
    public function __construct(protected Realtime $realtime) {}

    /** Stores a paused turn and tells the reviewers. */
    public function record(ToolApprovalRequested $event): ?ApprovalRequest
    {
        if (! $this->wanted($event->agent::class) || $event->pendingApprovals->isEmpty()) {
            return null;
        }

        $user = $event->conversationUser;

        $request = DB::transaction(function () use ($event, $user) {
            $request = ApprovalRequest::query()->firstOrCreate(['invocation_id' => $event->invocationId], [
                'agent' => $event->agent::class,
                'conversation_id' => $event->conversationId,
                'user_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'user_id' => $user instanceof Model ? (string) $user->getKey() : null,
                'user_name' => $user === null ? null : $this->nameOf($user),
            ]);

            if (! $request->wasRecentlyCreated) {
                return null;
            }

            foreach ($event->pendingApprovals as $pending) {
                /** @var PendingApproval $pending */
                $request->calls()->create([
                    'tool_call_id' => $pending->id,
                    'tool' => $pending->tool,
                    'arguments' => $pending->arguments,
                    'reason' => $pending->reason,
                ]);
            }

            return $request;
        });

        if ($request === null) {
            return null;
        }

        $this->toInbox($request, 'requested');
        $this->notifyApprovers($request);

        return $request;
    }

    /** A turn was resumed, by this package or by your app's own chat. */
    public function resolved(ToolApprovalResolved $event): void
    {
        $ids = $event->toolResults->map(fn (ToolResult $result) => $result->id)->all();

        if ($ids === []) {
            return;
        }

        ApprovalRequest::query()
            ->whereIn('status', [ApprovalRequest::PENDING, ApprovalRequest::RESUMING])
            ->when($event->conversationId, fn ($q, $id) => $q->where('conversation_id', $id))
            ->whereHas('calls', fn ($q) => $q->whereIn('tool_call_id', $ids))
            ->get()
            ->each(function (ApprovalRequest $request) {
                $resumedHere = $request->status === ApprovalRequest::RESUMING;

                $this->finish($request, $resumedHere ? ApprovalRequest::RESUMED : ApprovalRequest::RESOLVED);
            });
    }

    /**
     * Saves one reviewer's decision on one tool call. Nothing runs until
     * send().
     *
     * @param  array<string, mixed>|null  $arguments  the edited arguments, for edit
     */
    public function decide(ToolCall $call, string $decision, Authenticatable $by, string $name, ?array $arguments = null, ?string $rejection = null): ToolCall
    {
        if (! in_array($decision, ToolCall::DECISIONS, true)) {
            throw new InvalidArgumentException("Unknown decision [{$decision}].");
        }

        $rejection = $decision === ToolCall::REJECT && filled($rejection)
            ? mb_substr(trim($rejection), 0, (int) config('hibira-approvals.reason_characters', 1000))
            : null;

        $updated = ToolCall::query()
            ->whereKey($call->getKey())
            ->whereHas('request', fn ($q) => $q->where('status', ApprovalRequest::PENDING))
            ->update([
                'decision' => $decision,
                'edited_arguments' => $decision === ToolCall::EDIT ? json_encode($arguments ?? []) : null,
                'rejection' => $rejection,
                'decided_by' => (string) $by->getAuthIdentifier(),
                'decided_by_name' => $name,
                'decided_at' => now(),
            ]);

        $call->refresh();

        if ($updated > 0) {
            $this->toInbox($call->request, 'decided', $name);
        }

        return $call;
    }

    /** Takes a decision back while the request still waits. */
    public function undo(ToolCall $call, string $name): ToolCall
    {
        $updated = ToolCall::query()
            ->whereKey($call->getKey())
            ->whereHas('request', fn ($q) => $q->where('status', ApprovalRequest::PENDING))
            ->update(['decision' => null, 'edited_arguments' => null, 'rejection' => null, 'decided_by' => null, 'decided_by_name' => null, 'decided_at' => null]);

        $call->refresh();

        if ($updated > 0) {
            $this->toInbox($call->request, 'decided', $name);
        }

        return $call;
    }

    /**
     * Resumes the agent once every call has a decision. Returns false when
     * someone else sent it first or a call is still undecided.
     */
    public function send(ApprovalRequest $request, Authenticatable $by, string $name): bool
    {
        $sent = DB::transaction(function () use ($request, $by, $name) {
            /** @var ApprovalRequest|null $locked */
            $locked = ApprovalRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->isPending() || ! $locked->load('calls')->allDecided()) {
                return false;
            }

            return ApprovalRequest::query()
                ->whereKey($locked->getKey())
                ->where('status', ApprovalRequest::PENDING)
                ->update([
                    'status' => ApprovalRequest::RESUMING,
                    'decided_by' => (string) $by->getAuthIdentifier(),
                    'decided_by_name' => $name,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]) === 1;
        });

        if (! $sent) {
            return false;
        }

        $request->refresh();
        $this->toInbox($request, 'resuming', $name);

        ResumeAgent::dispatch($request->getKey())
            ->onConnection(config('hibira-approvals.queue.connection'))
            ->onQueue(config('hibira-approvals.queue.name'));

        return true;
    }

    /** Tries a failed request again. */
    public function retry(ApprovalRequest $request, string $name): bool
    {
        $updated = ApprovalRequest::query()
            ->whereKey($request->getKey())
            ->where('status', ApprovalRequest::FAILED)
            ->update(['status' => ApprovalRequest::RESUMING, 'error' => null, 'updated_at' => now()]);

        if ($updated !== 1) {
            return false;
        }

        $request->refresh();
        $this->toInbox($request, 'resuming', $name);

        ResumeAgent::dispatch($request->getKey())
            ->onConnection(config('hibira-approvals.queue.connection'))
            ->onQueue(config('hibira-approvals.queue.name'));

        return true;
    }

    public function finish(ApprovalRequest $request, string $status, ?string $error = null): void
    {
        $request->forceFill([
            'status' => $status,
            'finished_at' => now(),
            'error' => $error === null ? null : mb_substr($error, 0, 2000),
        ])->save();

        $this->toInbox($request, $status);
    }

    /**
     * Every panel with the plugin listens on its own inbox channel. The
     * payload follows the Hibira approvals protocol v1
     * (https://github.com/hibira-io/approvals/blob/main/PROTOCOL.md).
     */
    public function toInbox(ApprovalRequest $request, string $reason, ?string $by = null): void
    {
        $tools = $request->calls->pluck('tool')->unique()->values()->all();

        foreach (HibiraApprovalsPlugin::panels() as $panelId => $plugin) {
            $this->realtime->trigger(HibiraApprovalsPlugin::inboxChannel($panelId, prefixed: true), 'approval', [
                'v' => 1,
                'id' => $request->id,
                'reason' => $reason,
                'status' => $request->status,
                'agent' => $request->agentName(),
                'tools' => $tools,
                'by' => $by,
                'url' => $this->requestUrl($request, $panelId),
            ]);
        }
    }

    protected function requestUrl(ApprovalRequest $request, string $panelId): ?string
    {
        try {
            return Approvals::getUrl(['request' => $request->id], panel: $panelId);
        } catch (Throwable) {
            return null;
        }
    }

    protected function notifyApprovers(ApprovalRequest $request): void
    {
        foreach (HibiraApprovalsPlugin::panels() as $panelId => $plugin) {
            try {
                $approvers = collect($plugin->approversFor($request))
                    ->filter(fn ($user) => is_object($user) && $plugin->canReview($user instanceof Authenticatable ? $user : null, $request));

                if ($approvers->isEmpty()) {
                    continue;
                }

                $url = Approvals::getUrl(['request' => $request->id], panel: $panelId);

                if ($plugin->sendsPushNotifications()) {
                    $offline = $plugin->pushesWhenOnline() ? $approvers : $this->offline($approvers, $panelId);

                    if ($offline->isNotEmpty()) {
                        Notification::send($offline->all(), new ApprovalNeeded($request, $url));
                    }
                }

                if ($plugin->sendsDatabaseNotifications()) {
                    $this->databaseNotification($request, $url)->sendToDatabase($approvers->all());
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Approvers who don't have the panel open; they see the request in the
     * panel already.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $approvers
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function offline($approvers, string $panelId)
    {
        $online = $this->realtime->presentUserIds(HibiraApprovalsPlugin::reviewersChannel($panelId, prefixed: true));

        if ($online === null) {
            return $approvers;
        }

        return $approvers->reject(fn ($user) => $user instanceof Authenticatable && in_array((string) $user->getAuthIdentifier(), $online, true))->values();
    }

    protected function databaseNotification(ApprovalRequest $request, string $url): FilamentNotification
    {
        // Filament 4 moved notification actions to Filament\Actions\Action.
        $action = class_exists(\Filament\Actions\Action::class) && ! class_exists(NotificationActionV3::class)
            ? \Filament\Actions\Action::make('review')
            : NotificationActionV3::make('review');

        return FilamentNotification::make()
            ->title(__('hibira-approvals::approvals.notification_title', ['agent' => $request->agentName()]))
            ->body(__('hibira-approvals::approvals.notification_body', ['tools' => $request->calls->pluck('tool')->unique()->implode(', ')]))
            ->icon('heroicon-o-shield-check')
            ->warning()
            ->actions([$action->label(__('hibira-approvals::approvals.review'))->button()->url($url)]);
    }

    protected function wanted(string $agent): bool
    {
        $only = (array) config('hibira-approvals.agents', []);

        if ($only === []) {
            return true;
        }

        foreach ($only as $class) {
            if (is_a($agent, $class, true)) {
                return true;
            }
        }

        return false;
    }

    protected function nameOf(object $user): string
    {
        foreach (['name', 'email'] as $attribute) {
            $value = $user instanceof Model ? $user->getAttribute($attribute) : ($user->{$attribute} ?? null);

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 190);
            }
        }

        return class_basename($user).($user instanceof Model ? ' #'.$user->getKey() : '');
    }
}
