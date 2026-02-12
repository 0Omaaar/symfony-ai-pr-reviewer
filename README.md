# AI PR Reviewer Monorepo

Monorepo for an AI-assisted pull request reviewer platform.

Current setup includes:
- Symfony 8 API (`/api`)
- Vue 3 + Vite frontend (`/frontend`)
- PostgreSQL, pgAdmin, Mailpit
- Prometheus + Grafana for observability
- Docker Compose for local orchestration

## Project Status
This repository is an active starter for the AI PR Reviewer product.

Implemented today:
- Frontend shell and navigation (`Dashboard`, `Repositories`)
- Repositories view with search and responsive table/cards
- API health-style endpoint: `GET /api/ping`

Not implemented yet:
- Real repository integrations (GitHub/GitLab APIs)
- Auth and user/workspace management
- End-to-end PR analysis pipeline

## Architecture
- `api/`: Symfony app (PHP 8.4), Doctrine-ready, CORS bundle installed
- `frontend/`: Vue 3 SPA with Vue Router
- `docker-compose.yml`: local dev stack wiring all services
- `prometheus/`: Prometheus configuration

## Services and URLs
After startup, services are available at:

- Frontend: `http://localhost:5173`
- API: `http://localhost:8000`
- API ping endpoint: `http://localhost:8000/api/ping`
- pgAdmin: `http://localhost:5050`
- Mailpit UI: `http://localhost:8025`
- Prometheus: `http://localhost:9090`
- Grafana: `http://localhost:3000`

## Default Credentials (Local Dev)
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

Change these before any shared/staging deployment.

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

## Notes About Docker Volumes
The API service uses:
- `./api:/app` (bind mount for live code edits)
- `api_vendor:/app/vendor` (named volume for Composer dependencies)

The `api_vendor` volume prevents host mounts from hiding `/app/vendor` inside the container.

## Frontend Development (Optional, outside Docker)
```bash
cd frontend
npm install
npm run dev
```

## API Development (Optional, outside Docker)
If running API outside Docker, install dependencies first:
```bash
cd api
composer install
```

## High-Level Roadmap
- Replace mocked repository data with backend API data
- Add repository/provider connection flow
- Add policy pack management
- Introduce background jobs for PR review execution
- Add authentication and role-based access
