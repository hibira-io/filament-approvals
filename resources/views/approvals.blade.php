<x-filament-panels::page>
    <style>
        .ha-inbox { --ha-line: #e5e7eb; --ha-muted: #6b7280; --ha-soft: #f3f4f6; --ha-panel: #fff; --ha-warn: #fef3c7; --ha-warn-ink: #92400e; --ha-ok: #dcfce7; --ha-ok-ink: #166534; --ha-no: #fee2e2; --ha-no-ink: #991b1b; --ha-edit: #e0e7ff; --ha-edit-ink: #3730a3; display: grid; grid-template-columns: 21rem 1fr; height: max(30rem, calc(100vh - 13rem)); border: 1px solid var(--ha-line); border-radius: 0.75rem; overflow: hidden; background: var(--ha-panel) }
        .dark .ha-inbox { --ha-line: #3f3f46; --ha-muted: #a1a1aa; --ha-soft: #27272a; --ha-panel: #18181b; --ha-warn: #451a03; --ha-warn-ink: #fde68a; --ha-ok: #052e16; --ha-ok-ink: #bbf7d0; --ha-no: #450a0a; --ha-no-ink: #fecaca; --ha-edit: #1e1b4b; --ha-edit-ink: #c7d2fe }
        .ha-side { display: flex; flex-direction: column; min-height: 0; border-inline-end: 1px solid var(--ha-line) }
        .ha-tabs { display: flex; gap: 0.25rem; padding: 0.5rem; border-bottom: 1px solid var(--ha-line) }
        .ha-tab { flex: 1; padding: 0.375rem 0.5rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; color: var(--ha-muted) }
        .ha-tab[aria-selected=true] { background: var(--ha-soft); color: inherit }
        .ha-list { flex: 1; overflow-y: auto }
        .ha-item { display: grid; grid-template-columns: 1fr auto; gap: 0.25rem 0.5rem; width: 100%; padding: 0.625rem 0.75rem; text-align: start; border-bottom: 1px solid var(--ha-line) }
        .ha-item:hover, .ha-item[aria-current=true] { background: var(--ha-soft) }
        .ha-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.875rem; font-weight: 600 }
        .ha-time { font-size: 0.75rem; color: var(--ha-muted) }
        .ha-row { grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem; min-width: 0; font-size: 0.8125rem; color: var(--ha-muted) }
        .ha-row > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100% }
        .ha-pill { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.0625rem 0.5rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 600; background: var(--ha-soft); color: var(--ha-muted); white-space: nowrap }
        .ha-pill.is-pending, .ha-pill.is-resuming { background: var(--ha-warn); color: var(--ha-warn-ink) }
        .ha-pill.is-resumed, .ha-pill.is-approve { background: var(--ha-ok); color: var(--ha-ok-ink) }
        .ha-pill.is-failed, .ha-pill.is-reject { background: var(--ha-no); color: var(--ha-no-ink) }
        .ha-pill.is-edit { background: var(--ha-edit); color: var(--ha-edit-ink) }
        .ha-tool { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem }
        .ha-main { display: flex; flex-direction: column; min-width: 0; min-height: 0 }
        .ha-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; padding: 0.75rem 1rem; border-bottom: 1px solid var(--ha-line) }
        .ha-head-who { flex: 1; min-width: 10rem }
        .ha-sub { font-size: 0.75rem; color: var(--ha-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap }
        .ha-faces { display: flex; align-items: center }
        .ha-faces img { width: 1.75rem; height: 1.75rem; border-radius: 9999px; border: 2px solid var(--ha-panel); margin-inline-start: -0.375rem; object-fit: cover }
        .ha-faces img:first-child { margin-inline-start: 0 }
        .ha-calls { flex: 1; overflow-y: auto; padding: 1rem; display: grid; align-content: start; gap: 0.75rem }
        .ha-call { border: 1px solid var(--ha-line); border-radius: 0.75rem; padding: 0.75rem; display: grid; gap: 0.5rem }
        .ha-call-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem }
        .ha-call-head .ha-tool { font-size: 0.875rem; font-weight: 600; flex: 1 }
        .ha-reason { padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: var(--ha-warn); color: var(--ha-warn-ink); font-size: 0.8125rem }
        .ha-label { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ha-muted) }
        .ha-json { margin: 0; max-height: 16rem; overflow: auto; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: var(--ha-soft); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem; white-space: pre-wrap; overflow-wrap: anywhere }
        .ha-input { width: 100%; resize: vertical; padding: 0.5rem 0.625rem; border: 1px solid var(--ha-line); border-radius: 0.5rem; background: transparent; color: inherit; font-size: 0.8125rem }
        .ha-input.is-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem; min-height: 8rem }
        .ha-buttons { display: flex; flex-wrap: wrap; gap: 0.375rem; align-items: center }
        .ha-note { font-size: 0.75rem; color: var(--ha-muted) }
        .ha-error { font-size: 0.8125rem; color: var(--ha-no-ink); background: var(--ha-no); padding: 0.5rem 0.625rem; border-radius: 0.5rem; overflow-wrap: anywhere }
        .ha-foot { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: flex-end; padding: 0.75rem; border-top: 1px solid var(--ha-line) }
        .ha-foot .ha-note { flex: 1; min-width: 8rem }
        .ha-empty { margin: auto; padding: 2rem; text-align: center; color: var(--ha-muted); font-size: 0.875rem; display: grid; gap: 0.75rem; justify-items: center }
        .ha-online { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.8125rem; color: var(--ha-muted, #6b7280) }
        .ha-back { display: none }
        @media (max-width: 767px) {
            .ha-inbox { grid-template-columns: 1fr; height: calc(100dvh - 14rem) }
            .ha-inbox.has-active .ha-side, .ha-inbox:not(.has-active) .ha-main { display: none }
            .ha-back { display: inline-flex }
        }
    </style>

    <div
        x-data="{
            inbox: @js($inbox),
            requestChannel: @js($requestChannel),
            me: @js($me),
            requests: @js($requests),
            filter: 'pending',
            active: null,
            current: null,
            viewers: [],
            reviewers: window.hibiraApprovalsReviewers ?? [],
            editing: null,
            rejecting: null,
            text: '',
            error: null,
            busy: false,
            timer: null,
            listener: null,
            joined: null,
            strings: @js(trans('hibira-approvals::approvals')),
            init() {
                window.hibiraApprovalsInbox = true
                this.listener = (event) => this.changed(event)
                const listen = () => {
                    if (! window.Echo) return false
                    window.Echo.private(this.inbox).listen('.approval', this.listener)
                    return true
                }
                if (! listen()) window.addEventListener('EchoLoaded', listen, { once: true })
                this.onReviewers = (event) => (this.reviewers = event.detail)
                window.addEventListener('hibira-approvals-reviewers', this.onReviewers)

                // Without a connection, look for new requests now and then.
                this.timer = setInterval(() => {
                    if (window.Echo?.connector?.pusher?.connection?.state !== 'connected') this.refresh()
                }, 10000)

                const wanted = new URLSearchParams(location.search).get('request')
                if (wanted) this.open(wanted)
            },
            destroy() {
                clearInterval(this.timer)
                window.hibiraApprovalsInbox = false
                window.removeEventListener('hibira-approvals-reviewers', this.onReviewers)
                window.Echo?.private(this.inbox).stopListening('.approval', this.listener)
                this.leave()
            },
            get others() {
                return this.reviewers.filter((u) => String(u.id) !== this.me)
            },
            get otherViewers() {
                return this.viewers.filter((u) => String(u.id) !== this.me)
            },
            get undecided() {
                return this.current ? this.current.calls.filter((c) => ! c.decision).length : 0
            },
            async refresh() {
                this.requests = await $wire.loadRequests(this.filter)
                if (this.active) this.show(await $wire.openRequest(this.active))
            },
            async setFilter(filter) {
                this.filter = filter
                this.requests = await $wire.loadRequests(filter)
            },
            async changed(event) {
                this.requests = await $wire.loadRequests(this.filter)
                if (event.id === this.active) this.show(await $wire.openRequest(this.active))
            },
            async open(id) {
                this.leave()
                this.active = id
                this.editing = this.rejecting = this.error = null
                this.show(await $wire.openRequest(id))
                if (window.Echo && this.current) {
                    this.joined = this.requestChannel.replace('__ID__', id)
                    window.Echo.join(this.joined)
                        .here((users) => (this.viewers = users))
                        .joining((user) => (this.viewers = [...this.viewers.filter((u) => u.id !== user.id), user]))
                        .leaving((user) => (this.viewers = this.viewers.filter((u) => u.id !== user.id)))
                }
            },
            leave() {
                if (this.joined) window.Echo?.leave(this.joined)
                this.joined = null
                this.viewers = []
            },
            close() {
                this.leave()
                this.active = this.current = null
            },
            show(data) {
                if (! data || data.error && ! data.id) {
                    this.error = data?.error ?? this.strings.not_found
                    return
                }
                this.error = data.error ?? null
                this.current = data
                const index = this.requests.findIndex((r) => r.id === data.id)
                if (index >= 0) this.requests[index] = { ...this.requests[index], status: data.status, decided: data.decided }
            },
            async call(method, ...args) {
                if (this.busy) return
                this.busy = true
                try {
                    const data = await $wire[method](...args)
                    this.show(data)
                    return data
                } finally {
                    this.busy = false
                }
            },
            async decide(c, decision) {
                const data = await this.call('decide', c.id, decision, decision === 'edit' ? this.text : null, decision === 'reject' ? this.text : null)
                if (! data || data.error) return
                this.editing = this.rejecting = null
                this.text = ''
                // One call: deciding it is sending it.
                if (data.count === 1 && data.status === 'pending') await this.call('send', data.id)
            },
            startEdit(c) {
                this.rejecting = null
                this.editing = c.id
                this.text = c.edited_arguments ?? c.arguments
            },
            startReject(c) {
                this.editing = null
                this.rejecting = c.id
                this.text = ''
            },
            label(decision) {
                return this.strings['decision_' + decision] ?? decision
            },
            status(s) {
                return this.strings['status_' + s] ?? s
            },
            time(at) {
                if (! at) return ''
                const date = new Date(at)
                return date.toDateString() === new Date().toDateString()
                    ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    : date.toLocaleDateString([], { month: 'short', day: 'numeric' })
            },
        }"
    >
        <div class="ha-online" x-show="others.length > 0" x-cloak>
            <div class="ha-faces">
                <template x-for="u in others.slice(0, 6)" :key="u.id">
                    <img :src="u.avatar" :alt="u.name" :title="u.name" x-tooltip="u.name" />
                </template>
            </div>
            <span x-text="strings.online.replace(':count', others.length)"></span>
        </div>

        <div class="ha-inbox" :class="{ 'has-active': active }">
            <aside class="ha-side">
                <div class="ha-tabs" role="tablist">
                    @foreach (['pending', 'history'] as $tab)
                        <button type="button" class="ha-tab" role="tab" :aria-selected="filter === @js($tab)" x-on:click="setFilter(@js($tab))">
                            {{ __('hibira-approvals::approvals.filter_'.$tab) }}
                        </button>
                    @endforeach
                </div>

                <div class="ha-list">
                    <template x-for="r in requests" :key="r.id">
                        <button type="button" class="ha-item" :aria-current="active === r.id" x-on:click="open(r.id)">
                            <span class="ha-name" x-text="r.agent"></span>
                            <span class="ha-time" x-text="time(r.at)"></span>
                            <span class="ha-row">
                                <span x-text="r.user ?? strings.no_user"></span>
                            </span>
                            <span class="ha-row">
                                <template x-for="t in r.tools" :key="t">
                                    <span class="ha-pill ha-tool" x-text="t"></span>
                                </template>
                                <span class="ha-pill" :class="'is-' + r.status" x-text="status(r.status)"></span>
                                <span class="ha-note" x-show="r.status === 'pending' && r.count > 1" x-text="strings.progress.replace(':done', r.decided).replace(':total', r.count)"></span>
                            </span>
                        </button>
                    </template>

                    <p class="ha-empty" x-show="requests.length === 0" x-text="filter === 'pending' ? strings.empty : strings.empty_history"></p>
                </div>
            </aside>

            <section class="ha-main">
                <template x-if="current">
                    <div class="ha-head">
                        <x-filament::icon-button class="ha-back" icon="heroicon-o-arrow-left" color="gray" x-on:click="close()" :label="__('hibira-approvals::approvals.back')" />
                        <div class="ha-head-who">
                            <div class="ha-name" x-text="current.agent"></div>
                            <div class="ha-sub">
                                <span x-text="current.user ?? strings.no_user"></span>
                                <span> · </span>
                                <span x-text="time(current.at)"></span>
                                <template x-if="current.conversation_id">
                                    <span> · <span class="ha-tool" x-text="current.conversation_id" :title="strings.conversation"></span></span>
                                </template>
                            </div>
                        </div>
                        <div class="ha-faces" x-show="otherViewers.length > 0">
                            <template x-for="u in otherViewers.slice(0, 4)" :key="u.id">
                                <img :src="u.avatar" :alt="u.name" x-tooltip="strings.viewing.replace(':name', u.name)" />
                            </template>
                        </div>
                        <span class="ha-pill" :class="'is-' + current.status" x-text="status(current.status)"></span>
                    </div>
                </template>

                <div class="ha-calls" x-show="current">
                    <template x-if="current && otherViewers.length > 0 && current.status === 'pending'">
                        <p class="ha-note" x-text="strings.also_reviewing.replace(':names', otherViewers.map((u) => u.name).join(', '))"></p>
                    </template>

                    <template x-if="error">
                        <p class="ha-error" x-text="error"></p>
                    </template>

                    <template x-if="current && current.status === 'failed'">
                        <div class="ha-error">
                            <strong x-text="strings.failed"></strong>
                            <div x-text="current.error"></div>
                        </div>
                    </template>

                    <template x-for="c in (current?.calls ?? [])" :key="c.id">
                        <article class="ha-call">
                            <div class="ha-call-head">
                                <span class="ha-tool" x-text="c.tool"></span>
                                <template x-if="c.decision">
                                    <span class="ha-pill" :class="'is-' + c.decision" x-text="label(c.decision) + (c.decided_by ? ' · ' + c.decided_by : '')"></span>
                                </template>
                                <template x-if="c.decision && current.status === 'pending'">
                                    <x-filament::icon-button icon="heroicon-o-arrow-uturn-left" color="gray" size="sm" x-on:click="call('undo', c.id)" :tooltip="__('hibira-approvals::approvals.undo')" :label="__('hibira-approvals::approvals.undo')" />
                                </template>
                            </div>

                            <template x-if="c.reason">
                                <p class="ha-reason" x-text="c.reason"></p>
                            </template>

                            <span class="ha-label" x-text="c.edited_arguments ? strings.original_arguments : strings.arguments"></span>
                            <pre class="ha-json" x-text="c.arguments"></pre>

                            <template x-if="c.edited_arguments">
                                <div>
                                    <span class="ha-label" x-text="strings.edited_arguments"></span>
                                    <pre class="ha-json" x-text="c.edited_arguments"></pre>
                                </div>
                            </template>

                            <template x-if="c.rejection">
                                <p class="ha-note" x-text="strings.rejection_reason + ': ' + c.rejection"></p>
                            </template>

                            <template x-if="editing === c.id">
                                <div class="ha-buttons">
                                    <textarea class="ha-input is-code" x-model="text" spellcheck="false" :aria-label="strings.edited_arguments"></textarea>
                                    <x-filament::button size="sm" x-on:click="decide(c, 'edit')" x-bind:disabled="busy">{{ __('hibira-approvals::approvals.save_edit') }}</x-filament::button>
                                    <x-filament::button size="sm" color="gray" x-on:click="editing = null">{{ __('hibira-approvals::approvals.cancel') }}</x-filament::button>
                                </div>
                            </template>

                            <template x-if="rejecting === c.id">
                                <div class="ha-buttons">
                                    <textarea class="ha-input" rows="2" x-model="text" :placeholder="strings.rejection_placeholder" :aria-label="strings.rejection_reason"></textarea>
                                    <x-filament::button size="sm" color="danger" x-on:click="decide(c, 'reject')" x-bind:disabled="busy">{{ __('hibira-approvals::approvals.reject') }}</x-filament::button>
                                    <x-filament::button size="sm" color="gray" x-on:click="rejecting = null">{{ __('hibira-approvals::approvals.cancel') }}</x-filament::button>
                                </div>
                            </template>

                            <template x-if="current.status === 'pending' && ! c.decision && editing !== c.id && rejecting !== c.id">
                                <div class="ha-buttons">
                                    <x-filament::button size="sm" color="success" icon="heroicon-o-check" x-on:click="decide(c, 'approve')" x-bind:disabled="busy">{{ __('hibira-approvals::approvals.approve') }}</x-filament::button>
                                    <x-filament::button size="sm" color="danger" icon="heroicon-o-x-mark" x-on:click="startReject(c)">{{ __('hibira-approvals::approvals.reject') }}</x-filament::button>
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-pencil-square" x-on:click="startEdit(c)">{{ __('hibira-approvals::approvals.edit') }}</x-filament::button>
                                </div>
                            </template>
                        </article>
                    </template>
                </div>

                <template x-if="current && (current.status !== 'pending' || current.count > 1)">
                    <div class="ha-foot">
                        <span class="ha-note" x-show="current.status === 'pending' && current.count > 1" x-text="strings.progress.replace(':done', current.decided).replace(':total', current.count)"></span>
                        <span class="ha-note" x-show="current.status === 'resuming'" x-text="strings.resuming_note"></span>
                        <span class="ha-note" x-show="current.status === 'resumed' || current.status === 'failed'" x-text="current.decided_by ? strings.decided_by.replace(':name', current.decided_by) : ''"></span>
                        <span class="ha-note" x-show="current.status === 'resolved'" x-text="strings.resolved_note"></span>

                        <template x-if="current.status === 'pending' && current.count > 1 && undecided > 0">
                            <div class="ha-buttons">
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" x-on:click="call('decideAll', current.id, 'reject')" x-bind:disabled="busy">{{ __('hibira-approvals::approvals.reject_all') }}</x-filament::button>
                                <x-filament::button size="sm" color="success" icon="heroicon-o-check" x-on:click="call('decideAll', current.id, 'approve')" x-bind:disabled="busy">{{ __('hibira-approvals::approvals.approve_all') }}</x-filament::button>
                            </div>
                        </template>
                        <template x-if="current.status === 'pending' && current.count > 1 && undecided === 0">
                            <x-filament::button size="sm" icon="heroicon-o-paper-airplane" x-on:click="call('send', current.id)" x-bind:disabled="busy">{{ __('hibira-approvals::approvals.send') }}</x-filament::button>
                        </template>
                        <template x-if="current.status === 'failed'">
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" x-on:click="call('retry', current.id)" x-bind:disabled="busy">{{ __('hibira-approvals::approvals.retry') }}</x-filament::button>
                        </template>
                    </div>
                </template>

                <div class="ha-empty" x-show="! current">
                    <x-filament::icon icon="heroicon-o-shield-check" class="h-10 w-10" />
                    <span>{{ __('hibira-approvals::approvals.pick') }}</span>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
