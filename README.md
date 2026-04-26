# autoPMR

GitHub pull request monitoring platform. Connects to your GitHub App installation, listens to PR webhook events, and sends rich email notifications to linked users based on their preferences.

## Stack

- **API**: Symfony 8 (PHP 8.4), Doctrine ORM, Symfony Messenger (async)
- **Frontend**: Vue 3 + Vite + TypeScript SPA
- **Database**: PostgreSQL
- **Cache**: Redis (configurable via `MESSENGER_TRANSPORT_DSN` / Symfony Cache)
- **Email**: Symfony Mailer (Gmail SMTP in dev, configurable per environment)
- **Workflow Automation**: n8n (webhook splitter / orchestration layer)
- **Observability**: Prometheus + Grafana
- **Local orchestration**: Docker Compose

## Project Structure

```text
autoPMR/
├── api/
│   ├── src/
│   │   ├── Controller/
│   │   │   ├── Api/
│   │   │   │   ├── Dashboard/            # Dashboard KPI endpoint
│   │   │   │   ├── Github/               # GitHub OAuth & webhook controllers
│   │   │   │   ├── WorkspaceController.php        # Workspace CRUD + repo membership
│   │   │   │   ├── TeamDashboardController.php    # Team PR dashboard endpoints
│   │   │   │   ├── SubscriptionController.php     # Branch monitoring subscriptions
│   │   │   │   └── WebhookController.php          # n8n internal webhook receiver
│   │   │   └── Admin/                    # Admin backoffice API
│   │   ├── Entity/
│   │   │   ├── User.php
│   │   │   ├── GithubInstallation.php
│   │   │   ├── UserGithubInstallation.php
│   │   │   ├── RepoSubscription.php
│   │   │   ├── PullRequestSnapshot.php
│   │   │   ├── Workspace.php             # Personal workspace
│   │   │   ├── WorkspaceRepository_.php  # Workspace ↔ repo membership
│   │   │   ├── PlatformSetting.php
│   │   │   ├── AdminLog.php
│   │   │   └── ProcessedWebhookDelivery.php
│   │   ├── Repository/
│   │   │   ├── WorkspaceRepository.php              # Workspace queries
│   │   │   ├── WorkspaceRepositoryEntryRepository.php
│   │   │   ├── PullRequestSnapshotRepository.php    # Supports workspace repo filter
│   │   │   └── …
│   │   ├── Service/
│   │   │   ├── Github/
│   │   │   │   ├── GithubApiClient.php              # All GitHub HTTP calls
│   │   │   │   ├── GithubAppJwtService.php
│   │   │   │   ├── GithubWebhookService.php
│   │   │   │   └── GithubInstallationRepositoriesService.php
│   │   │   └── CacheKeys.php
│   │   ├── Message/
│   │   └── MessageHandler/
│   └── migrations/
│       └── Version20260425000001.php     # workspace + workspace_repository tables
├── frontend/
│   └── src/
│       ├── api/
│       │   ├── workspaces.ts             # Workspace API client
│       │   ├── teamDashboard.ts          # Team dashboard API (workspaceId support)
│       │   └── …
│       ├── components/
│       │   ├── WorkspaceSwitcher.vue     # Scope dropdown (URL-driven)
│       │   └── …
│       ├── views/
│       │   ├── WorkspacesView.vue        # Workspace management page
│       │   ├── DashboardView.vue         # Workspace-scoped dashboard
│       │   ├── TeamDashboardView.vue     # Workspace-scoped team PRs
│       │   └── …
│       └── router/
├── n8n/
│   └── workflows/
│       └── github-pr-review.json
└── prometheus/
```

## Key Workflows

### GitHub OAuth Login

1. User clicks "Sign in with GitHub" → redirects to `/connect/github`
2. GitHub OAuth callback hits `/connect/github/check`
3. `GithubAuthenticator` creates/updates the `User` entity
4. Redirects back to the frontend

### GitHub App Installation

1. User clicks "Install GitHub App" → redirects to GitHub
2. GitHub redirects to `/connect/github/app/setup?installation_id=XXX`
3. Backend records the `GithubInstallation` and links it to the user
4. User's repo and dashboard caches are busted immediately

### Repository Discovery

The app fetches repositories from two sources and merges them by repo ID:

1. **GitHub App installation** — `GET /installation/repositories` returns repos explicitly granted to the app during installation (owned repos, selected org repos).
2. **User OAuth token** — `GET /user/repos?affiliation=owner,collaborator,organization_member` returns every repo the authenticated user can access, including repos they are a **collaborator** on and org repos they belong to.

Installation-sourced entries take priority (they carry a confirmed `installation_id`). Collaborator repos not in the installation list are assigned the user's first available installation ID as a fallback.

> Note: webhook events for collaborator repos on orgs that haven't installed the GitHub App won't arrive until the app is installed on that org.

### Pull Request Webhook Flow (via n8n)

1. GitHub POSTs to n8n webhook endpoint (`/webhook/github-pr`)
2. n8n forwards the raw payload to Symfony (`/webhooks/github`) with an internal token
3. `GithubWebhookService` verifies the token and checks idempotency
4. Controller dispatches `ReviewPullRequestMessage` to the async bus
5. Worker processes the message: finds linked users, applies notification preferences, sends email, busts caches
6. In parallel, n8n calls `/api/webhooks/github` for PR snapshot processing

### Team Dashboard

Real-time PR monitoring across all monitored repositories. Supports three layouts (table, kanban, focus), ownership views, filters, and auto-polling stats every 60 seconds. Can be scoped to a workspace via `?workspaceId=`.

### Personal Workspaces

Users can create named groups of repositories to use as a focused scope for the dashboard and team PR views. Workspace selection lives in the URL query (`?workspaceId=N`) so it survives page refreshes. Workspace membership is independent of monitoring subscriptions — adding a repo to a workspace does not activate monitoring.

### Admin Backoffice

1. Admin logs in at `/admin/login` with email/password credentials
2. Backend issues a JWT token (HS256, 8h TTL)
3. All `/api/admin/*` endpoints require the JWT via `Authorization: Bearer` header

## API Endpoints

| Method | Path | Description |
| ------ | ---- | ----------- |
| `GET` | `/api/ping` | Health check |
| `GET` | `/api/me` | Current user + GitHub installations |
| `GET` | `/api/dashboard` | Dashboard KPIs and recent PRs (optional `?workspaceId=`) |
| `GET` | `/api/github/repositories` | List user's accessible repositories (owned + collaborator) |
| `GET` | `/api/github/repositories/{id}` | Repository details |
| `GET` | `/api/github/pull-requests/{id}` | Pull request details |
| `GET` | `/api/github/pull-requests/{id}/changes` | PR file diffs |
| `POST` | `/api/github/repositories/cache/clear` | Bust user's repo cache |
| `GET` | `/api/subscriptions` | List active branch subscriptions |
| `POST` | `/api/subscriptions` | Activate a branch subscription |
| `DELETE` | `/api/subscriptions` | Deactivate a branch subscription |
| `GET` | `/api/team-dashboard` | Open PRs list (filters + pagination, optional `?workspaceId=`) |
| `GET` | `/api/team-dashboard/stats` | PR stats (optional `?workspaceId=`) |
| `GET` | `/api/team-dashboard/activity` | Recent PR activity feed (optional `?workspaceId=`) |
| `GET` | `/api/team-dashboard/pr/{repo}/{number}` | PR sidebar preview |
| `POST` | `/api/team-dashboard/refresh` | Trigger snapshot refresh |
| `GET` | `/api/workspaces` | List user's workspaces |
| `POST` | `/api/workspaces` | Create a workspace |
| `GET` | `/api/workspaces/{id}` | Get a workspace |
| `PATCH` | `/api/workspaces/{id}` | Update workspace name/description |
| `DELETE` | `/api/workspaces/{id}` | Delete a workspace |
| `PUT` | `/api/workspaces/{id}/repositories` | Replace workspace repository membership |
| `DELETE` | `/api/account` | Delete account and all data |
| `DELETE` | `/api/account/installations/{id}` | Remove a GitHub installation |
| `GET` | `/api/account/notification-preferences` | Get notification preferences |
| `PATCH` | `/api/account/notification-preferences` | Update notification preferences |
| `PATCH` | `/api/account/notifications` | Toggle email notifications on/off |
| `POST` | `/api/logout` | Logout |
| `GET` | `/connect/github` | Start GitHub OAuth flow |
| `GET` | `/connect/github/check` | GitHub OAuth callback |
| `GET` | `/connect/github/app/install` | Redirect to GitHub App install page |
| `GET` | `/connect/github/app/setup` | Post-install setup callback |
| `POST` | `/webhooks/github` | GitHub webhook receiver |
| `POST` | `/api/webhooks/github` | n8n internal webhook (GitHub) |
| `GET` | `/unsubscribe/{token}` | One-click email unsubscribe |
| | | |
| **Admin** | | |
| `POST` | `/api/admin/auth/login` | Admin login (returns JWT) |
| `GET` | `/api/admin/stats` | Dashboard KPIs |
| `GET` | `/api/admin/users` | List users |
| `GET` | `/api/admin/users/{id}` | User detail |
| `GET` | `/api/admin/repos` | List repositories |
| `GET` | `/api/admin/webhook-events` | List webhook events |
| `GET` | `/api/admin/notifications` | List sent notifications |
| `GET` | `/api/admin/logs` | Admin audit logs (paginated, CSV export) |
| `GET` | `/api/admin/settings` | Platform settings |
| `PATCH` | `/api/admin/settings` | Update platform settings |

## Personal Workspaces

Workspaces are personal repository groups that scope the dashboard and team PR views without affecting monitoring subscriptions.

### Data model

| Table | Purpose |
| ----- | ------- |
| `workspace` | One row per workspace; unique name per user |
| `workspace_repository` | Join table; unique repo per workspace |

### Workspace API

```
GET    /api/workspaces                      → list all workspaces
POST   /api/workspaces                      → create  { name, description? }
GET    /api/workspaces/{id}                 → get one
PATCH  /api/workspaces/{id}                 → update  { name?, description? }
DELETE /api/workspaces/{id}                 → delete (cascades membership)
PUT    /api/workspaces/{id}/repositories    → replace membership
                                              body: { repositories: [{ repoFullName, repoId, installationId }] }
```

All repos submitted to the membership endpoint are validated against the user's accessible GitHub repos. Submitting an empty array clears the workspace.

### Workspace scoping

Pass `?workspaceId=N` to any of these endpoints to restrict results to that workspace's repos:

- `GET /api/dashboard`
- `GET /api/team-dashboard`
- `GET /api/team-dashboard/stats`
- `GET /api/team-dashboard/activity`

An empty workspace returns valid empty shapes — no errors.

### Frontend

- New route `/workspaces` with a **Workspaces** sidebar entry
- `WorkspacesView` — create/edit/delete workspaces, multi-select repo editor, monitored/not-monitored badge per repo, "Open in Dashboard" / "Open in Team PRs" CTAs
- `WorkspaceSwitcher` component on `DashboardView` and `TeamDashboardView` — dropdown that reads/writes `workspaceId` in the URL; selecting "All repositories" removes the param

## Notification Preferences

```json
{
  "events": {
    "opened": true,
    "closed": true,
    "synchronize": true,
    "ready_for_review": true,
    "converted_to_draft": true
  },
  "repos": {
    "mode": "all",
    "allowed": ["owner/repo1", "owner/repo2"]
  }
}
```

`mode: "all"` notifies for every accessible repository. `mode: "specific"` restricts to the `allowed` list.

## Caching

| Data | TTL |
| ---- | --- |
| Repository list | 120 s (10 s if empty) |
| Repository details | 120 s |
| Pull requests | 120 s |
| Branches | 120 s |
| Insights | 120 s |
| PR details / changes | 120 s |
| Dashboard payload (unscoped) | 90 s |
| Team dashboard stats (unscoped) | 60 s |
| Latest PR webhook event | 1 hour |

Workspace-scoped dashboard requests bypass the cache and are computed fresh. Cache is busted automatically on webhook events, installation changes, and manual cache-clear requests.

## Local Setup

### Prerequisites

- Docker + Docker Compose
- A GitHub App with a webhook secret and private key

### Environment variables (`api/.env.local`)

```env
GITHUB_APP_ID=your_app_id
GITHUB_APP_INSTALL_URL=https://github.com/apps/your-app/installations/new
GITHUB_WEBHOOK_SECRET=your_webhook_secret
GITHUB_PRIVATE_KEY_PATH=/path/to/private-key.pem

FRONT_URL=http://localhost:5173

PR_ALERT_FROM_EMAIL=alerts@example.com
PR_ALERT_FROM_NAME=autoPMR
PR_ALERT_REPLY_TO=
PR_ALERT_FRONT_URL=http://localhost:5173

DATABASE_URL=postgresql://app:!ChangeMe!@127.0.0.1:5432/app
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

N8N_INTERNAL_TOKEN=changeme-internal-token

ADMIN_EMAIL=admin@admin.com
ADMIN_PASSWORD=adminadmin
ADMIN_SECRET=change-me-in-production-use-a-long-random-string
```

### Start everything

```bash
docker compose up -d --build
```

### Run migrations

```bash
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

### Stop

```bash
docker compose down
```

### Makefile shortcuts

```bash
make up
make down
make build
make csfix   # PHP CS Fixer
```

### n8n Setup

1. Start containers: `docker compose up -d`
2. Open n8n at <http://localhost:5678> and complete the setup wizard
3. Import the workflow from `n8n/workflows/github-pr-review.json`
4. Activate the workflow
5. Set your GitHub App's webhook URL to your n8n endpoint (e.g. `https://your-ngrok-url/webhook/github-pr`)

## Local URLs

| Service | URL |
| ------- | --- |
| Frontend | <http://localhost:5173> |
| API | <http://localhost:8000> |
| Admin Backoffice | <http://localhost:5173/admin> |
| n8n | <http://localhost:5678> |
| pgAdmin | <http://localhost:5050> |
| Mailpit UI | <http://localhost:8025> |
| Prometheus | <http://localhost:9090> |
| Grafana | <http://localhost:3000> |

## Default Local Credentials

| Service | Credential |
| ------- | ---------- |
| PostgreSQL | DB: `app` / User: `app` / Password: `!ChangeMe!` |
| pgAdmin | Email: `admin@example.com` / Password: `admin` |
| Grafana | User: `admin` / Password: `admin` |
| Admin Backoffice | Email: `admin@admin.com` / Password: `adminadmin` |
| n8n | Created during setup wizard |

Change all of these for any shared, staging, or production environment.

## Database Migrations

| Version | Description |
| ------- | ----------- |
| `20260219232641` | Initial schema |
| `20260228210356` | GitHub installation tables |
| `20260315000001` | Notification preferences |
| `20260315000002` | Unsubscribe token |
| `20260402000001` | Admin logs |
| `20260403000001` | Platform settings |
| `20260409000001` | Sessions, GitHub access token, repo subscriptions |
| `20260410000001` | Pull request snapshots, onboarding state |
| `20260425000001` | Personal workspaces (`workspace`, `workspace_repository`) |

## Current Status

### Implemented

- GitHub OAuth login/logout
- GitHub App install/setup flow
- Repository discovery: owned repos + collaborator repos + org member repos (merged from installation API and user OAuth token)
- PR webhook ingestion with HMAC verification and idempotency
- Async email notifications with per-user event + repository filters
- Rich HTML email template with unsubscribe link
- Repository list, details, branches, pull requests, insights
- Pull request details and file diffs
- Dashboard with KPIs, recent PRs, and top repositories
- Team dashboard with table / kanban / focus layouts, ownership views, filters, pagination, and auto-polling
- Branch monitoring subscriptions (activate/deactivate per branch)
- Pull request snapshots (persisted, refreshable)
- Personal workspaces — create named repo groups, scope dashboard and team PR views via URL query param
- Server-side caching with automatic invalidation (workspace-scoped requests bypass cache)
- Settings page: notification preferences + email toggle
- Account deletion and installation removal
- Admin backoffice (JWT auth, user/repo/log management, dashboard stats, platform settings, CSV export)
- Landing page + Vue Router auth guards

### Not Implemented Yet

- AI review pipeline (handler stub in place)
- Multi-provider support (GitLab stub only)
- Billing / subscription features
- Full automated test coverage and CI quality gates
