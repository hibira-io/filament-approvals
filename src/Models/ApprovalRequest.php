<?php

namespace Hibira\FilamentApprovals\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;

/**
 * An agent turn that paused before running one or more tools.
 *
 * @property string $id
 * @property string $invocation_id
 * @property class-string $agent
 * @property string|null $conversation_id
 * @property string|null $user_type
 * @property string|null $user_id
 * @property string|null $user_name
 * @property string $status
 * @property string|null $decided_by
 * @property string|null $decided_by_name
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ToolCall> $calls
 */
class ApprovalRequest extends Model
{
    use HasUlids;

    /** Waiting on reviewers. */
    public const PENDING = 'pending';

    /** Decided; the agent is being resumed. */
    public const RESUMING = 'resuming';

    /** The agent carried on with the decisions. */
    public const RESUMED = 'resumed';

    /** Resuming the agent failed. */
    public const FAILED = 'failed';

    /** Answered somewhere else, such as in your own app's chat. */
    public const RESOLVED = 'resolved';

    protected $table = 'hibira_approval_requests';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return HasMany<ToolCall, $this> */
    public function calls(): HasMany
    {
        return $this->hasMany(ToolCall::class, 'request_id')->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** The user the agent was talking to, when it was an Eloquent model. */
    public function user(): ?Model
    {
        if ($this->user_type === null || $this->user_id === null) {
            return null;
        }

        $class = Relation::getMorphedModel($this->user_type) ?? $this->user_type;

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        return $class::query()->find($this->user_id);
    }

    public function agentName(): string
    {
        return Str::headline(class_basename($this->agent));
    }

    /** The decisions to resume the agent with, keyed by tool call id. */
    public function decisions(): Decisions
    {
        return Decisions::from($this->calls->mapWithKeys(fn (ToolCall $call) => [
            $call->tool_call_id => match ($call->decision) {
                ToolCall::APPROVE => Decision::approve(),
                ToolCall::EDIT => Decision::edit($call->edited_arguments ?? []),
                default => Decision::reject($call->rejection),
            },
        ])->all());
    }

    public function allDecided(): bool
    {
        return $this->calls->every(fn (ToolCall $call) => $call->decision !== null);
    }
}
