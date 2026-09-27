# Competency

ENKKU Competency & IDP System is a Laravel/Inertia/Vue application for competency assessment, reviewer approval, competency gap analysis, and Individual Development Plan (IDP) workflows.

## Stack

- PHP 8.3+
- Laravel 13
- Inertia Laravel 2
- Vue 3
- Vite 8
- PostgreSQL

## Prerequisites

- PHP 8.3+
- Composer
- Node.js and npm
- Docker Desktop

## Local Setup

Install dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Start PostgreSQL:

```bash
docker compose up -d
```

Run migrations:

```bash
php artisan migrate
```

To rebuild the local database from scratch:

```bash
php artisan migrate:fresh --seed
```

## Development

Start Laravel and Vite in separate terminals:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev -- --host 127.0.0.1
```

Open:

```text
http://127.0.0.1:8000
```

To enable the local mock SSO explicitly, set:

```env
MOCK_SSO_ENABLED=true
```

It will then be available at:

```text
http://127.0.0.1:8000/mock-sso
```

Email notifications are disabled by default. Enable them only after configuring a safe recipient:

```env
MAIL_NOTIFICATIONS_ENABLED=true
MAIL_SEND_TO_REAL_USERS=false
MAIL_DEV_TO=developer@example.test
```

## Database

The local PostgreSQL database runs in Docker with these default credentials:

```env
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=competency
DB_USERNAME=sail
DB_PASSWORD=password
```

Check migration state with:

```bash
php artisan migrate:status
```

## Test And Build

Run the PHP test suite:

```bash
php artisan test
```

Run a focused test file:

```bash
php artisan test tests/Feature/AdminUserControllerTest.php
```

Build frontend assets:

```bash
npm run build
```

## Project Structure

- `routes/web.php`: web routes and named route entry points.
- `app/Http/Controllers`: dashboard, admin, HR, employee, assessment, and approval controllers.
- `app/Services`: reviewer chain, expected-level, IDP workflow, notification, and sync services.
- `database/migrations`: PostgreSQL schema history.
- `resources/js/Pages`: Inertia Vue screens by role.
- `resources/js/data.ts`: role navigation and page titles.
- `tests`: feature and unit tests.
- `docs`: project documentation.

## Documentation

- `AGENTS.md`: AI coding-agent working rules.
- `docs/project/deployment.md`: production environment, release, and smoke-test checklist.
- `docs/project/architecture.md`: current implementation architecture.
- `docs/project/requirements.md`: canonical business/domain rules.
- `docs/project/current-state.md`: implemented features, gaps, and discrepancies.
- `docs/project/decisions.md`: important architecture decisions.
