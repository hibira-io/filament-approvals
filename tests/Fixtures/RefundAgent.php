<?php

namespace Hibira\FilamentApprovals\Tests\Fixtures;

use Illuminate\Broadcasting\Channel;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\AgentInput;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;
use Stringable;

/** An agent that records how it was resumed instead of calling a model. */
class RefundAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    /** @var list<array{conversation: ?string, user: ?object, decisions: mixed, channels: mixed}> */
    public static array $resumed = [];

    public static ?string $fails = null;

    public function instructions(): Stringable|string
    {
        return 'You help with refunds.';
    }

    public function prompt(AgentInput|UserMessage|Decisions|string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): AgentResponse
    {
        $this->record($prompt, null);

        return new AgentResponse('inv_resumed', 'Done.', new TextUsage, new Meta);
    }

    public function broadcastNow(AgentInput|UserMessage|Decisions|string $prompt, Channel|array $channels, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): StreamableAgentResponse
    {
        $this->record($prompt, $channels);

        return new StreamableAgentResponse('inv_resumed', fn () => yield from []);
    }

    protected function record(mixed $prompt, mixed $channels): void
    {
        if (static::$fails !== null) {
            throw new RuntimeException(static::$fails);
        }

        static::$resumed[] = [
            'conversation' => $this->currentConversation(),
            'user' => $this->conversationParticipant(),
            'decisions' => $prompt,
            'channels' => $channels,
        ];
    }
}
