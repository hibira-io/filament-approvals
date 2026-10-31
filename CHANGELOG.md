# Changelog

## 0.1.1

- README: cover image and screenshots.

## 0.1.0

- An AI approvals page in the Filament panel for the Laravel AI SDK's tool
  approvals: waiting and history tabs, live over Hibira, with a badge and a
  notification anywhere in the panel.
- Approve, reject with a reason, or edit the arguments of each tool call;
  approve or reject all at once. The agent resumes on the queue once every
  call is decided, and streams to hibira-io/laravel's AI bridge channel when
  it is installed.
- Presence: reviewers online, and who else has a request open.
- Hibira Push and Filament database notifications to approvers, pushed only
  to those without the panel open.
- `HibiraApprovals::resumeUsing()` and `broadcastUsing()` for agents that
  resume another way.
- English, 繁體中文, 简体中文, 日本語, 한국어, ไทย and Tiếng Việt.
