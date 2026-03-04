# AI PMR Reviewer Monorepo

Monorepo for an AI-assisted pull request reviewer platform.

## Stack
- Symfony 8 API (`/api`)
- Vue 3 + Vite frontend (`/frontend`)
- PostgreSQL, pgAdmin, Mailpit
- Prometheus + Grafana for observability
- Docker Compose for local orchestration

## Current Status (March 2026)
### Implemented
- GitHub OAuth login/logout flow
- GitHub App install/setup flow
- Session endpoint: `GET /api/me` (includes auth state and GitHub App installation state)
- Repositories list from GitHub App installations
- Repository details page:
  - branches
  - pull requests
  - repository insights (commits, participants, stars, forks, issues, topics, etc.)
- Pull request details page
- Pull request changes endpoint with:
  - files changed
  - additions/deletions/commits/comments summary
  - patch/diff per changed file
- Webhook endpoint for GitHub `pull_request` events
- Server-side caching for repositories, repository details, PR details, and PR changes
- Frontend loading and error states (alerts) on data-fetching views

### Not Implemented Yet
- Multi-provider support (GitLab not wired end-to-end)
- Persistent domain storage for repositories/PR snapshots (currently API + cache first)
- Background analysis pipeline completion and review results UI
- Tenant/workspace model and role-based permissions
- Billing/subscription features
- Production hardening (rate limiting, audit logs, full test coverage, CI quality gates)

## Project Structure
- `api/`: Symfony app (PHP 8.4), Doctrine-ready
- `frontend/`: Vue 3 SPA with Vue Router
- `docker-compose.yml`: local development stack
- `prometheus/`: Prometheus configuration

## Key Endpoints
- `GET /api/ping`
- `GET /api/me`
- `GET /api/github/repositories`
- `GET /api/github/repositories/{id}`
- `GET /api/github/pull-requests/{id}`
- `GET /api/github/pull-requests/{id}/changes`
- `POST /webhooks/github`
- `GET /connect/github`
- `GET /connect/github/check`
- `GET /connect/github/app/install`
- `GET /connect/github/app/setup`
- `POST /logout`

## Local URLs
After startup:
- Frontend: `http://localhost:5173`
- API: `http://localhost:8000`
- pgAdmin: `http://localhost:5050`
- Mailpit UI: `http://localhost:8025`
- Prometheus: `http://localhost:9090`
- Grafana: `http://localhost:3000`

## Default Local Credentials
- PostgreSQL
  - DB: `app`
  - User: `app`
  - Password: `!ChangeMe!`
- pgAdmin
  - Email: `admin@example.com`
  - Password: `admin`
- Grafana
  - User: `admin`
  - Password: `admin`

Change these for any shared/staging/production environment.

## Quick Start
```bash
docker compose up --build
```

Run in background:
```bash
docker compose up -d --build
```

Stop services:
```bash
docker compose down
```

## Common Commands
Rebuild specific services:
```bash
docker compose build api
docker compose build frontend
```

Run PHP CS Fixer via Docker:
```bash
docker compose run --rm csfixer fix --config=api/.php-cs-fixer.php
```

Makefile shortcuts:
```bash
make up
make down
make build
make csfix
```

## API Caching Notes
GitHub API data is cached server-side (short TTL) to reduce latency and API calls:
- repositories list
- repository details
- pull request details
- pull request changes

When cached repository details are missing meaningful data, the API refreshes details once and returns fresh data.

## SaaS Readiness Gaps (High Priority)
1. Add robust automated tests (API integration + frontend unit/component tests).
2. Add retry/backoff + rate-limit handling around GitHub API calls.
3. Add persistent storage for analysis outputs and historical review events.
4. Add CI pipeline with lint/test/security checks.
5. Add tenant/workspace boundaries and authorization checks per resource.
