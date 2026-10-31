<?php

namespace Hibira\FilamentApprovals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tool call the agent wants to make, and what a reviewer decided.
 *
 * @property int $id
 * @property string $request_id
 * @property string $tool_call_id
 * @property string $tool
 * @property array<string, mixed> $arguments
 * @property string|null $reason
 * @property string|null $decision approve, reject or edit
 * @property array<string, mixed>|null $edited_arguments
 * @property string|null $rejection
 * @property string|null $decided_by
 * @property string|null $decided_by_name
 * @property \Illuminate\Support\Carbon|null $decided_at
 */
class ToolCall extends Model
{
    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public const EDIT = 'edit';

    public const DECISIONS = [self::APPROVE, self::REJECT, self::EDIT];

    protected $table = 'hibira_approval_tool_calls';

    protected $guarded = [];

    protected $casts = [
        'arguments' => 'array',
        'edited_arguments' => 'array',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }
}
