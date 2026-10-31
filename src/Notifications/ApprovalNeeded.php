<?php

namespace Hibira\FilamentApprovals\Notifications;

use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Hibira\Laravel\Push\HibiraPushMessage;
use Illuminate\Notifications\Notification;

/**
 * A Hibira Push notification to an approver's phones and browsers. It names
 * the agent and the tools only; the arguments stay in the panel.
 */
class ApprovalNeeded extends Notification
{
    public function __construct(public ApprovalRequest $request, public string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['hibira-push'];
    }

    public function toHibiraPush(object $notifiable): HibiraPushMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : null;

        return HibiraPushMessage::create()
            ->title(__('hibira-approvals::approvals.notification_title', ['agent' => $this->request->agentName()], $locale))
            ->body(__('hibira-approvals::approvals.notification_body', ['tools' => $this->request->calls->pluck('tool')->unique()->implode(', ')], $locale))
            ->link($this->url)
            ->data(['type' => 'hibira-approval', 'request' => $this->request->id]);
    }
}
