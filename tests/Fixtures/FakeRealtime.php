<?php

namespace Hibira\FilamentApprovals\Tests\Fixtures;

use Hibira\FilamentApprovals\Realtime;

class FakeRealtime extends Realtime
{
    /** @var list<array{channel: string, event: string, data: array<string, mixed>}> */
    public array $sent = [];

    /** @var array<string, list<string>> user ids in each presence channel */
    public array $present = [];

    public function __construct() {}

    public function trigger(string $channel, string $event, array $data): void
    {
        $this->sent[] = compact('channel', 'event', 'data');
    }

    public function presentUserIds(string $channel): ?array
    {
        return $this->present[$channel] ?? [];
    }

    /** @return list<array{channel: string, event: string, data: array<string, mixed>}> */
    public function on(string $channel, ?string $event = null): array
    {
        return array_values(array_filter($this->sent, fn ($s) => $s['channel'] === $channel && ($event === null || $s['event'] === $event)));
    }
}
