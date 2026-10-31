<?php

namespace Hibira\FilamentApprovals\Jobs;

use Hibira\FilamentApprovals\ApprovalDesk;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Hibira\FilamentApprovals\Models\ToolCall;
use Hibira\FilamentApprovals\Resumer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\ResolvesPendingApprovals;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Throwable;

/**
 * Resumes a decided agent turn.
 *
 * Runs once: an approved tool may have run before a failure, so trying the
 * same decisions again could run it twice. A reviewer retries from the
 * inbox, which first checks the turn still waits on these calls.
 */
class ResumeAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $requestId) {}

    public function handle(Resumer $resumer, ApprovalDesk $desk): void
    {
        $request = ApprovalRequest::query()->with('calls')->find($this->requestId);

        if ($request === null || $request->status !== ApprovalRequest::RESUMING) {
            return;
        }

        if (! $this->stillWaiting($request)) {
            $desk->finish($request, ApprovalRequest::RESOLVED);

            return;
        }

        try {
            $resumer->resume($request, $request->decisions());
        } catch (ApprovalMismatchException $e) {
            // The turn no longer waits on these calls: someone answered it elsewhere.
            $desk->finish($request->refresh(), ApprovalRequest::RESOLVED);

            return;
        } catch (Throwable $e) {
            report($e);
            $desk->finish($request->refresh(), ApprovalRequest::FAILED, $e->getMessage());

            return;
        }

        // ToolApprovalResolved usually marked it resumed already.
        $request->refresh();

        if ($request->status === ApprovalRequest::RESUMING) {
            $desk->finish($request, ApprovalRequest::RESUMED);
        }
    }

    /** False when the turn was answered somewhere else meanwhile. */
    protected function stillWaiting(ApprovalRequest $request): bool
    {
        if ($request->conversation_id === null || ! app()->bound(ConversationStore::class)) {
            return true;
        }

        $store = app(ConversationStore::class);

        if (! $store instanceof ResolvesPendingApprovals) {
            return true;
        }

        $pending = collect($store->pendingApprovalsFor($request->conversation_id))
            ->map(fn (PendingApproval $approval) => $approval->id);

        // Agents that bring their own history leave the store empty; the SDK
        // still refuses stale decisions with ApprovalMismatchException.
        if ($pending->isEmpty()) {
            return true;
        }

        return $request->calls->every(fn (ToolCall $call) => $pending->contains($call->tool_call_id));
    }
}
