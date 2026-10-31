<div
    x-data="{
        inbox: @js($inbox),
        reviewers: @js($reviewers),
        url: @js($url),
        strings: @js(['title' => __('hibira-approvals::approvals.toast_title'), 'body' => __('hibira-approvals::approvals.notification_body'), 'review' => __('hibira-approvals::approvals.review')]),
        init() {
            window.hibiraApprovalsReviewers = window.hibiraApprovalsReviewers ?? []
            const listen = () => {
                if (! window.Echo) return false
                window.Echo.private(this.inbox).listen('.approval', (event) => this.notify(event))
                // Being in this channel is what 'online' means for push notifications.
                window.Echo.join(this.reviewers)
                    .here((users) => this.online(users))
                    .joining((user) => this.online([...window.hibiraApprovalsReviewers.filter((u) => u.id !== user.id), user]))
                    .leaving((user) => this.online(window.hibiraApprovalsReviewers.filter((u) => u.id !== user.id)))
                return true
            }
            if (! listen()) window.addEventListener('EchoLoaded', listen, { once: true })
        },
        online(users) {
            window.hibiraApprovalsReviewers = users
            window.dispatchEvent(new CustomEvent('hibira-approvals-reviewers', { detail: users }))
        },
        notify(event) {
            // The inbox lists it already.
            if (event.reason !== 'requested' || window.hibiraApprovalsInbox || typeof FilamentNotification === 'undefined') return
            const notification = new FilamentNotification()
                .title(this.strings.title.replace(':agent', event.agent))
                .body(this.strings.body.replace(':tools', (event.tools ?? []).join(', ')))
                .icon('heroicon-o-shield-check')
                .warning()
            if (typeof FilamentNotificationAction !== 'undefined') {
                notification.actions([new FilamentNotificationAction('review').label(this.strings.review).button().url(this.url + '?request=' + encodeURIComponent(event.id))])
            }
            notification.send()
        },
    }"
    hidden
></div>
