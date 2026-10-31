<?php

namespace Hibira\FilamentApprovals\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Hibira\FilamentApprovals\ApprovalDesk;
use Hibira\FilamentApprovals\HibiraApprovalsPlugin;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Hibira\FilamentApprovals\Models\ToolCall;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Attributes\Renderless;

/**
 * Tool calls your agents are waiting on, live. The page renders once; after
 * that the browser fetches with renderless calls whenever Hibira says
 * something changed.
 */
class Approvals extends Page
{
    protected static ?string $slug = 'ai-approvals';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationLabel(): string
    {
        return __('hibira-approvals::approvals.inbox');
    }

    public static function getNavigationGroup(): ?string
    {
        return HibiraApprovalsPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return HibiraApprovalsPlugin::get()->getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ApprovalRequest::query()->where('status', ApprovalRequest::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return HibiraApprovalsPlugin::get()->canReview(Filament::auth()->user());
    }

    public function getTitle(): string
    {
        return __('hibira-approvals::approvals.inbox');
    }

    public function getView(): string
    {
        return 'hibira-approvals::approvals';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $panelId = Filament::getCurrentPanel()->getId();

        return [
            'requests' => $this->loadRequests('pending'),
            'inbox' => HibiraApprovalsPlugin::inboxChannel($panelId),
            'requestChannel' => HibiraApprovalsPlugin::requestChannel($panelId, '__ID__'),
            'me' => (string) $this->me()->getAuthIdentifier(),
        ];
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function loadRequests(string $filter = 'pending'): array
    {
        return ApprovalRequest::query()
            ->with('calls')
            ->when($filter === 'pending', fn ($q) => $q->whereIn('status', [ApprovalRequest::PENDING, ApprovalRequest::RESUMING, ApprovalRequest::FAILED]))
            ->when($filter === 'history', fn ($q) => $q->whereIn('status', [ApprovalRequest::RESUMED, ApprovalRequest::RESOLVED]))
            ->latest()
            ->limit(100)
            ->get()
            ->filter(fn (ApprovalRequest $request) => $this->allowed($request))
            ->map(fn (ApprovalRequest $request) => $this->summary($request))
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    #[Renderless]
    public function openRequest(string $id): ?array
    {
        $request = $this->find($id);

        return $request === null ? null : $this->present($request);
    }

    /**
     * @return array<string, mixed> the request, or an error to show
     */
    #[Renderless]
    public function decide(int $callId, string $decision, ?string $arguments = null, ?string $rejection = null): array
    {
        $call = ToolCall::query()->with('request')->find($callId);

        if ($call === null || ! $this->allowed($call->request) || ! in_array($decision, ToolCall::DECISIONS, true)) {
            return ['error' => __('hibira-approvals::approvals.not_found')];
        }

        $edited = null;

        if ($decision === ToolCall::EDIT) {
            $edited = json_decode((string) $arguments, true);

            if (! is_array($edited) || (array_is_list($edited) && $edited !== [])) {
                return ['error' => __('hibira-approvals::approvals.invalid_json')];
            }
        }

        $me = $this->me();
        app(ApprovalDesk::class)->decide($call, $decision, $me, Filament::getUserName($me), $edited, $rejection);

        return $this->present($call->request->refresh());
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function undo(int $callId): array
    {
        $call = ToolCall::query()->with('request')->find($callId);

        if ($call === null || ! $this->allowed($call->request)) {
            return ['error' => __('hibira-approvals::approvals.not_found')];
        }

        app(ApprovalDesk::class)->undo($call, Filament::getUserName($this->me()));

        return $this->present($call->request->refresh());
    }

    /**
     * Decides every undecided call the same way, then sends.
     *
     * @return array<string, mixed>
     */
    #[Renderless]
    public function decideAll(string $id, string $decision, ?string $rejection = null): array
    {
        $request = $this->find($id);

        if ($request === null || ! in_array($decision, [ToolCall::APPROVE, ToolCall::REJECT], true)) {
            return ['error' => __('hibira-approvals::approvals.not_found')];
        }

        $me = $this->me();
        $name = Filament::getUserName($me);

        foreach ($request->calls->whereNull('decision') as $call) {
            app(ApprovalDesk::class)->decide($call, $decision, $me, $name, null, $rejection);
        }

        return $this->send($id);
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function send(string $id): array
    {
        $request = $this->find($id);

        if ($request === null) {
            return ['error' => __('hibira-approvals::approvals.not_found')];
        }

        $me = $this->me();

        if (! app(ApprovalDesk::class)->send($request, $me, Filament::getUserName($me))) {
            return ['error' => __('hibira-approvals::approvals.not_sent')] + $this->present($request->refresh());
        }

        return $this->present($request->refresh());
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function retry(string $id): array
    {
        $request = $this->find($id);

        if ($request === null) {
            return ['error' => __('hibira-approvals::approvals.not_found')];
        }

        app(ApprovalDesk::class)->retry($request, Filament::getUserName($this->me()));

        return $this->present($request->refresh());
    }

    protected function find(string $id): ?ApprovalRequest
    {
        $request = ApprovalRequest::query()->with('calls')->find($id);

        return $request !== null && $this->allowed($request) ? $request : null;
    }

    protected function allowed(ApprovalRequest $request): bool
    {
        return HibiraApprovalsPlugin::get()->canReview($this->me(), $request);
    }

    /** @return array<string, mixed> */
    protected function summary(ApprovalRequest $request): array
    {
        return [
            'id' => $request->id,
            'agent' => $request->agentName(),
            'user' => $request->user_name,
            'tools' => $request->calls->pluck('tool')->unique()->values()->all(),
            'status' => $request->status,
            'decided' => $request->calls->whereNotNull('decision')->count(),
            'count' => $request->calls->count(),
            'at' => $request->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    protected function present(ApprovalRequest $request): array
    {
        $request->loadMissing('calls');

        return $this->summary($request) + [
            'agent_class' => $request->agent,
            'conversation_id' => $request->conversation_id,
            'decided_by' => $request->decided_by_name,
            'decided_at' => $request->decided_at?->toIso8601String(),
            'finished_at' => $request->finished_at?->toIso8601String(),
            'error' => $request->error,
            'calls' => $request->calls->map(fn (ToolCall $call) => [
                'id' => $call->id,
                'tool' => $call->tool,
                'reason' => $call->reason,
                'arguments' => $this->json($call->arguments),
                'decision' => $call->decision,
                'edited_arguments' => $call->edited_arguments === null ? null : $this->json($call->edited_arguments),
                'rejection' => $call->rejection,
                'decided_by' => $call->decided_by_name,
            ])->all(),
        ];
    }

    /** @param  array<mixed>  $value */
    protected function json(array $value): string
    {
        return (string) json_encode($value === [] ? new \stdClass : $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function me(): Authenticatable
    {
        return Filament::auth()->user();
    }
}
