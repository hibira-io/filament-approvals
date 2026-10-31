<?php

namespace Hibira\FilamentApprovals\Tests\Fixtures;

use Laravel\Ai\Storage\DatabaseConversationStore;

/** Says which tool calls a conversation still waits on. */
class PendingStore extends DatabaseConversationStore
{
    /** @var array<string, list<\Laravel\Ai\Approvals\PendingApproval>> */
    public static array $pending = [];

    public function pendingApprovalsFor(string $conversationId): array
    {
        return static::$pending[$conversationId] ?? [];
    }
}
