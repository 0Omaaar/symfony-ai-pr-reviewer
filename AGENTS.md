# Repository Guidelines

## Purpose
`autoPMR` is a GitHub pull request monitoring platform. It connects to a GitHub App installation, receives PR-related webhook events, and sends email notifications to linked users based on per-user preferences.

## Core Architecture
- `api/`: Symfony 8 backend. Owns OAuth, webhook validation, persistence, cache invalidation, and message dispatch.
- `messenger_worker`: async worker for `ReviewPullRequestMessage`, snapshot refresh, cleanup, and PR processing.
- `frontend/`: Vue 3 SPA for user and admin flows.
- `n8n/`: orchestration layer. GitHub sends webhooks to n8n first; n8n forwards internal requests to Symfony and can split flows.

Rule: keep synchronous controllers thin. Expensive webhook and notification work belongs in Messenger handlers, not request/response code.

## Main Workflows
### OAuth and installation
User signs in with GitHub, Symfony creates or updates `User`, then GitHub App installation setup links a `GithubInstallation` to that user and clears relevant caches.

### Webhook flow
GitHub posts to n8n, n8n forwards to Symfony, `GithubWebhookService` verifies authenticity and idempotency, then the API dispatches async messages. The worker resolves linked users, applies notification preferences, sends emails, and clears affected caches.

### Notifications
Email delivery is preference-driven by event type and repo scope. Preserve HTML + text fallback behavior and unsubscribe flow.

## Important Constraints
- Idempotency is mandatory for webhook processing. Never introduce duplicate side effects for the same delivery.
- Cache behavior is part of correctness, not just performance. Update `CacheKeys` usage and bust caches when installation, webhook, dashboard, repo, or PR data changes.
- n8n internal webhook handling differs from raw GitHub HMAC flow; do not collapse them into one unchecked path.
- Email logic must respect user preference filters and linked-installation ownership.

## Modification Rules
### Backend
Keep repositories query-only, services for domain logic, and handlers for async side effects. Do not move heavy GitHub API calls or mail sending into controllers. Preserve security boundaries for OAuth, admin JWT, and webhook auth.

### Frontend
Keep API calls in `frontend/src/api/`, routing in `frontend/src/router/`, and views/components separated. Do not hardcode backend behavior in views; reflect backend contracts and admin/user auth boundaries.

## What NOT to do
- Do not process webhooks synchronously
- Do not send emails outside Messenger handlers
- Do not bypass idempotency checks
- Do not duplicate GitHub API calls
- Do not modify cache logic without invalidation

