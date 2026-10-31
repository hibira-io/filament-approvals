<?php

namespace Hibira\FilamentApprovals;

use Hibira\FilamentApprovals\Exceptions\CannotResume;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;

/**
 * Carries a paused agent turn on with the reviewers' decisions.
 */
class Resumer
{
    public function resume(ApprovalRequest $request, Decisions $decisions): void
    {
        $user = $request->user();

        if ($custom = HibiraApprovals::resumer()) {
            $custom($request, $decisions, $user);

            return;
        }

        if (! class_exists($request->agent) || ! is_a($request->agent, Agent::class, true)) {
            throw CannotResume::missingAgent($request->agent);
        }

        $agent = app($request->agent);

        if ($request->conversation_id === null || ! method_exists($agent, 'continue')) {
            throw CannotResume::notRemembered($request->agent);
        }

        $agent = $agent->continue($request->conversation_id, as: $user);

        $channels = HibiraApprovals::broadcastChannels($request, $user);

        // We are on the queue already, so the turn runs here either way.
        if ($channels !== null) {
            $agent->broadcastNow($decisions, $channels);
        } else {
            $agent->prompt($decisions);
        }
    }
}
