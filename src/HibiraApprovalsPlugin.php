<?php

namespace Hibira\FilamentApprovals;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Hibira\Filament\Presence;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Hibira\FilamentApprovals\Pages\Approvals;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

/**
 * Adds the AI approvals inbox to a Filament panel.
 *
 * Use it next to HibiraPlugin from hibira-io/filament, which connects the
 * panel to Hibira.
 */
class HibiraApprovalsPlugin implements Plugin
{
    /** @var array<string, static> the plugin of each panel that has it */
    protected static array $panels = [];

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected bool $hasToasts = true;

    protected ?Closure $authorizeUsing = null;

    protected ?Closure $approversUsing = null;

    protected bool $pushNotifications = true;

    protected bool $pushWhenOnline = false;

    protected bool $databaseNotifications = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'hibira-approvals';
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /** Shows a notification anywhere in the panel when an agent waits for a decision. */
    public function toasts(bool $condition = true): static
    {
        $this->hasToasts = $condition;

        return $this;
    }

    /**
     * Who may decide. Gets the user and, when deciding, the request.
     * Everyone who can use the panel, by default.
     *
     * @param  (Closure(Authenticatable, ?ApprovalRequest): bool)|null  $callback
     */
    public function authorize(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    /**
     * Who to notify when an agent waits for a decision: users that use
     * HasHibiraPush-style routing, or any notifiable.
     *
     * @param  Closure(ApprovalRequest): iterable<object>  $callback
     */
    public function approvers(?Closure $callback): static
    {
        $this->approversUsing = $callback;

        return $this;
    }

    /**
     * Sends approvers a Hibira Push notification (iOS, Android, web).
     * By default only approvers who don't have the panel open get one;
     * the others see a notification in the panel.
     */
    public function pushNotifications(bool $condition = true, bool $evenWhenOnline = false): static
    {
        $this->pushNotifications = $condition;
        $this->pushWhenOnline = $evenWhenOnline;

        return $this;
    }

    /** Also stores a Filament database notification for each approver. */
    public function databaseNotifications(bool $condition = true): static
    {
        $this->databaseNotifications = $condition;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function canReview(?Authenticatable $user, ?ApprovalRequest $request = null): bool
    {
        return $user !== null && ($this->authorizeUsing === null || (bool) ($this->authorizeUsing)($user, $request));
    }

    /** @return iterable<object> */
    public function approversFor(ApprovalRequest $request): iterable
    {
        return $this->approversUsing === null ? [] : ($this->approversUsing)($request);
    }

    public function sendsPushNotifications(): bool
    {
        return $this->pushNotifications && filled(config('hibira.push.instance_id')) && filled(config('hibira.push.secret_key'));
    }

    public function pushesWhenOnline(): bool
    {
        return $this->pushWhenOnline;
    }

    public function sendsDatabaseNotifications(): bool
    {
        return $this->databaseNotifications;
    }

    public function register(Panel $panel): void
    {
        static::$panels[$panel->getId()] = $this;

        $panel->pages([Approvals::class]);

        $panelId = $panel->getId();

        // Authorized on /broadcasting/auth, outside the panel, so registered for every request.
        app()->booted(function () use ($panel, $panelId) {
            $options = $panel->getAuthGuard() ? ['guards' => [$panel->getAuthGuard()]] : [];

            $member = fn ($user) => $this->canReview($user) ? Presence::member($panelId, $user) : false;

            Broadcast::channel(static::inboxChannel($panelId), $member, $options);
            Broadcast::channel(static::reviewersChannel($panelId), $member, $options);
            Broadcast::channel(static::requestChannel($panelId, '{request}'), function ($user, string $request) use ($panelId) {
                $approval = ApprovalRequest::query()->find($request);

                return $approval !== null && $this->canReview($user, $approval) ? Presence::member($panelId, $user) : false;
            }, $options);
        });
    }

    public function boot(Panel $panel): void
    {
        if (! $this->hasToasts || blank(config('hibira.key'))) {
            return;
        }

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, function () use ($panel): string {
            if (! $this->canReview(Filament::auth()->user())) {
                return '';
            }

            return view('hibira-approvals::toasts', [
                'inbox' => static::inboxChannel($panel->getId()),
                'reviewers' => static::reviewersChannel($panel->getId()),
                'url' => Approvals::getUrl(panel: $panel->getId()),
            ])->render();
        });
    }

    /** @return array<string, static> */
    public static function panels(): array
    {
        return static::$panels;
    }

    /** Where the inbox hears about new and decided requests. */
    public static function inboxChannel(string $panelId, bool $prefixed = false): string
    {
        return ($prefixed ? 'private-' : '')."hibira-approvals.{$panelId}.inbox";
    }

    /** Everyone with the panel open who may decide. */
    public static function reviewersChannel(string $panelId, bool $prefixed = false): string
    {
        return ($prefixed ? 'presence-' : '')."hibira-approvals.{$panelId}.reviewers";
    }

    /** Everyone looking at one request right now. */
    public static function requestChannel(string $panelId, string $requestId, bool $prefixed = false): string
    {
        return ($prefixed ? 'presence-' : '')."hibira-approvals.{$panelId}.request.{$requestId}";
    }
}
