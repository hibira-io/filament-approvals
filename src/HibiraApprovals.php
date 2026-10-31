<?php

namespace Hibira\FilamentApprovals;

use Closure;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Illuminate\Broadcasting\Channel;
use Laravel\Ai\Approvals\Decisions;

/**
 * App-wide settings, read by the queue worker as well as the panel. Call
 * these from a service provider's boot().
 */
class HibiraApprovals
{
    /** @var (Closure(ApprovalRequest, Decisions, ?object): mixed)|null */
    protected static ?Closure $resumeUsing = null;

    /** @var (Closure(ApprovalRequest, ?object): (Channel|list<Channel>|null))|null */
    protected static ?Closure $broadcastUsing = null;

    /**
     * Replaces how a decided agent turn is resumed. Gets the request, the
     * decisions and the user the agent was talking to. Use it when an agent
     * can't be made with app($class), or isn't resumed with continue().
     *
     * @param  (Closure(ApprovalRequest, Decisions, ?object): mixed)|null  $callback
     */
    public static function resumeUsing(?Closure $callback): void
    {
        static::$resumeUsing = $callback;
    }

    /**
     * Where the resumed turn is streamed. By default, to the user's
     * channel from hibira-io/laravel's AI bridge when it is installed;
     * return null to resume without streaming.
     *
     * @param  (Closure(ApprovalRequest, ?object): (Channel|list<Channel>|null))|null  $callback
     */
    public static function broadcastUsing(?Closure $callback): void
    {
        static::$broadcastUsing = $callback;
    }

    /** @return (Closure(ApprovalRequest, Decisions, ?object): mixed)|null */
    public static function resumer(): ?Closure
    {
        return static::$resumeUsing;
    }

    /** @return Channel|list<Channel>|null */
    public static function broadcastChannels(ApprovalRequest $request, ?object $user): Channel|array|null
    {
        if (static::$broadcastUsing !== null) {
            return (static::$broadcastUsing)($request, $user);
        }

        $bridge = 'Hibira\\Laravel\\Ai\\AgentChannel';

        if ($user !== null && class_exists($bridge) && method_exists($bridge, 'user')) {
            return $bridge::user($user);
        }

        return null;
    }

    public static function flush(): void
    {
        static::$resumeUsing = null;
        static::$broadcastUsing = null;
    }
}
