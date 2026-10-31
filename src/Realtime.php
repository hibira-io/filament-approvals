<?php

namespace Hibira\FilamentApprovals;

use Hibira\Laravel\Hibira;
use Throwable;

/**
 * Talks to Hibira: events for the inbox, and who is in a presence channel.
 *
 * A failed call is reported but never fails the agent or the reviewer's
 * request: everything is stored first, and the inbox fetches again on
 * reconnect.
 */
class Realtime
{
    public function __construct(protected Hibira $hibira) {}

    /** @param  array<string, mixed>  $data */
    public function trigger(string $channel, string $event, array $data): void
    {
        try {
            $this->hibira->realtime()->trigger($channel, $event, $data);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * User ids in a presence channel, or null when Hibira can't be asked.
     *
     * @return list<string>|null
     */
    public function presentUserIds(string $channel): ?array
    {
        try {
            $response = $this->hibira->realtime()->get('/channels/'.$channel.'/users', [], true);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! is_array($response) || ! isset($response['users']) || ! is_array($response['users'])) {
            return null;
        }

        return array_values(array_map(fn ($user) => (string) ($user['id'] ?? ''), $response['users']));
    }
}
