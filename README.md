# autoPMR

GitHub pull request monitoring platform. Connects to your GitHub App installation, listens to PR webhook events, and sends rich email notifications to linked users based on their preferences.

## Stack

- **API**: Symfony 8 (PHP 8.4), Doctrine ORM, Symfony Messenger (async)
- **Frontend**: Vue 3 + Vite + TypeScript SPA
- **Database**: PostgreSQL
- **Cache**: Redis (configurable via `MESSENGER_TRANSPORT_DSN` / Symfony Cache)
- **Email**: Symfony Mailer (Mailpit locally)
- **Observability**: Prometheus + Grafana
- **Local orchestration**: Docker Compose

## Project Structure

```text
autoPMR/
├── api/                          # Symfony 8 backend
│   ├── src/
│   │   ├── Controller/           # HTTP layer only — thin, delegates to services
│   │   ├── Service/
│   │   │   ├── Github/
│   │   │   │   ├── GithubApiClient.php         # All GitHub HTTP calls
│   │   │   │   ├── GithubAppJwtService.php     # RS256 JWT for GitHub App auth
│   │   │   │   ├── GithubWebhookService.php    # HMAC verification + idempotency
│   │   │   │   └── GithubInstallationRepositoriesService.php
│   │   │   ├── Account/
│   │   │   │   └── AccountService.php          # User deletion + installation removal
│   │   │   └── CacheKeys.php                   # Central cache key registry
│   │   ├── Entity/               # Doctrine entities
│   │   ├── Repository/           # Doctrine repositories (DB queries only)
│   │   ├── Message/              # Async message classes
│   │   ├── MessageHandler/       # Async message handlers
│   │   └── Security/             # GitHub OAuth authenticator
│   ├── templates/
│   │   └── email/
│   │       └── pr_notification.html.twig   # PR alert email template
│   └── config/
├── frontend/                     # Vue 3 SPA
│   └── src/
│       ├── views/                # Page components
│       ├── router/               # Vue Router (auth guards)
│       └── api/                  # API client functions
├── docker-compose.yml
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

### Pull Request Webhook Flow

1. GitHub POSTs to `/webhooks/github` with HMAC signature
2. `GithubWebhookService` verifies the signature and checks idempotency
3. Controller dispatches `ReviewPullRequestMessage` to the async bus
4. Worker (`ReviewPullRequestMessageHandler`) processes the message:
   - Finds all users linked to the installation
   - Filters by per-user notification preferences (event type + repo whitelist)
   - Sends HTML email (rendered from Twig template) + text fallback
   - Busts affected caches

## API Endpoints

| Method | Path | Description |
| ------ | ---- | ----------- |
| `GET` | `/api/ping` | Health check |
| `GET` | `/api/me` | Current user + GitHub installations |
| `GET` | `/api/dashboard` | Dashboard KPIs and recent PRs |
| `GET` | `/api/github/repositories` | List user's accessible repositories |
| `GET` | `/api/github/repositories/{id}` | Repository details (branches, PRs, insights) |
| `GET` | `/api/github/pull-requests/{id}` | Pull request details |
| `GET` | `/api/github/pull-requests/{id}/changes` | PR file diffs |
| `POST` | `/api/github/repositories/cache/clear` | Bust user's repo cache |
| `DELETE` | `/api/account` | Delete account and all data |
| `DELETE` | `/api/account/installations/{id}` | Remove a GitHub installation |
| `GET` | `/api/account/notification-preferences` | Get notification preferences |
| `PATCH` | `/api/account/notification-preferences` | Update notification preferences |
| `PATCH` | `/api/account/notifications` | Toggle email notifications on/off |
| `POST` | `/api/logout` | Logout (invalidates session) |
| `GET` | `/connect/github` | Start GitHub OAuth flow |
| `GET` | `/connect/github/check` | GitHub OAuth callback |
| `GET` | `/connect/github/app/install` | Redirect to GitHub App install page |
| `GET` | `/connect/github/app/setup` | Post-install setup callback |
| `POST` | `/webhooks/github` | GitHub webhook receiver |
| `GET` | `/unsubscribe/{token}` | One-click email unsubscribe |

## Notification Preferences

Each user can configure which PR events trigger an email and which repositories to monitor:

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

GitHub API responses are cached server-side to reduce latency and stay within GitHub's rate limits (5 000 req/hour per installation). All cache keys are defined in `CacheKeys`.

| Data | TTL |
| ---- | --- |
| Repository list | 120 s (10 s if empty) |
| Repository details | 120 s |
| Pull requests | 120 s |
| Branches | 120 s |
| Insights | 120 s |
| PR details / changes | 120 s |
| Dashboard payload | 90 s |
| Latest PR webhook event | 1 hour |

Cache is busted automatically on new webhook events, installation changes, and manual cache-clear requests.

## Local Setup

### Prerequisites

- Docker + Docker Compose
- A GitHub App with a webhook secret and private key

### Environment variables (`.env.local` in `api/`)

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
```

### Start everything

```bash
docker compose up --build
# or in the background:
docker compose up -d --build
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
make csfix   # Run PHP CS Fixer
```

## Local URLs

| Service | URL |
| ------- | --- |
| Frontend | <http://localhost:5173> |
| API | <http://localhost:8000> |
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

Change all of these for any shared, staging, or production environment.

## Current Status

### Implemented

- GitHub OAuth login/logout
- GitHub App install/setup flow
- PR webhook ingestion with HMAC verification and idempotency
- Async email notifications with per-user event + repository filters
- Rich HTML email template (Twig) with unsubscribe link
- Repository list, details, branches, pull requests, insights
- Pull request details and file diffs
- Dashboard with KPIs and recent PRs
- Server-side caching with automatic invalidation
- Settings page: notification preferences + email toggle
- Account deletion and installation removal
- Landing page + Vue Router auth guards

### Not Implemented Yet

- Multi-provider support (GitLab stub only)
- Persistent domain storage for PR snapshots and review history
- Background AI analysis pipeline and results UI
- Tenant/workspace model and role-based permissions
- Billing / subscription features
- Full automated test coverage and CI quality gates
- Production hardening (audit logs, structured observability)
