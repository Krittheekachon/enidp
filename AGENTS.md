# ENKKU Competency & IDP System

This repository is a Laravel/Inertia/Vue application for competency assessment and Individual Development Plan (IDP) workflows for the Faculty of Engineering, Khon Kaen University.

Use this file as the quick operating guide for AI coding agents. Deeper project knowledge lives in:

- `docs/project/architecture.md`
- `docs/project/requirements.md`
- `docs/project/current-state.md`
- `docs/project/decisions.md`

## Stack

- PHP 8.3+
- Laravel 13
- Inertia Laravel 2
- Vue 3
- Vite 8
- PostgreSQL

## Entry Points

- Routes: `routes/web.php`
- Main dashboard read model: `app/Http/Controllers/DashboardController.php`
- Role navigation and page IDs: `resources/js/data.ts`
- Admin shell: `resources/js/Pages/Admin/Dashboard.vue`
- HR shell: `resources/js/Pages/HR/Dashboard.vue`
- Employee shell: `resources/js/Pages/Employee/Dashboard.vue`
- Reviewer/manager shells:
  - `resources/js/Pages/Super/Dashboard.vue`
  - `resources/js/Pages/Head/Dashboard.vue`
  - `resources/js/Pages/Executive/Dashboard.vue`
- Reusable employee pages: `resources/js/Pages/Employee/`

## How To Work Safely

DO:

- Read the affected route, controller, migration/schema, service/model, Inertia props, Vue component, and closest test before editing.
- Trace data end to end: database table -> controller/service -> Inertia prop -> Vue state/computed value -> submitted payload.
- Search with `rg` for table names, status strings, route names, and prop names before changing shared behavior.
- Keep changes scoped to the requested behavior.
- Preserve unrelated dirty worktree changes.
- Use `DB::transaction` for multi-table writes.
- Use Laravel validation for write requests and preserve Thai user-facing validation/success messages.
- Keep named routes through Ziggy in Vue with `route('...')`.
- Keep camelCase in Inertia/UI payloads and snake_case in database/request fields.

DO NOT:

- Reintroduce `users.supervisor_id_1`, `users.supervisor_id_2`, or `users.supervisor_id_3`.
- Read runtime reviewer permissions from reviewer chain templates.
- Hard-code one reviewer role to one fixed approval step.
- Write canonical approved assessment status as `dean_approved`; write `approved`.
- Read checked assessment behavior from `assessment_evidences`.
- Create IDP work from unapproved or non-negative competency gaps.
- Edit an already-shared migration solely to change a live schema; add a follow-up migration.
- Hide required missing migrations behind permanent schema guards.
- Add new role aliases in only one controller or frontend map.

## Critical Domain Invariants

- Reviewer chains are stored in `user_reviewer_steps`.
- `chain_type = assessment` controls competency assessment approval and FC topic approval routing.
- `chain_type = idp` controls IDP item submission and approval.
- `ReviewerChainResolver` is the runtime source of truth for reviewer steps.
- Reviewer chain templates are helpers for configuring user chains, not runtime approval authority.
- Gap is always `actual_level - expected_level`.
- `requires_idp = true` only when the approved gap is negative.
- Use one `idp_item` per failed competency gap and many `idp_activities` under that item.
- Formal Learning choices must be filtered through `learning_catalog_competency`.
- CC and MC are assessed directly. FC may require pre-selection and first-reviewer approval before self-assessment opens.

See `docs/project/requirements.md` for the full business rules.

## Roles And Compatibility

Canonical role keys:

- `employee`
- `supervisor`
- `dept_head`
- `dean`
- `hr`
- `admin`

Legacy aliases:

- `manager` -> `dean`
- `manager_dept` -> `dept_head`

Normalize aliases consistently wherever role keys are interpreted.

## Database Rules

- Check existing migrations and `php artisan migrate:status` before assuming a table exists.
- Make PostgreSQL-safe migrations and preserve foreign keys/indexes.
- Use forward migrations for schema changes that teammates may already have run.
- The legacy supervisor columns were dropped by `database/migrations/2026_07_01_000000_drop_legacy_supervisor_columns_from_users_table.php`.
- Compatibility logic may read old migration history, but application runtime must not depend on removed columns.

## Frontend Rules

- Keep admin and operational screens compact, scan-friendly, and consistent with existing UI classes: `card`, `btn`, `btn-p`, `btn-s`, `inp`, `sel`, `tbl`.
- Keep page IDs aligned with `NAV_CONFIG`, `PAGE_TITLES`, and dashboard component conditionals.
- Prefer server-owned data over duplicated frontend state unless optimistic behavior is required.
- Preserve Inertia scroll/state behavior where existing code uses `preserveScroll`, `useRemember`, or session storage.
- Show backend validation near the relevant field.
- Verify substantial UI changes around the existing `900px` breakpoint.

## Verification

Choose focused checks first, then broaden for shared behavior.

```bash
php artisan test tests/Feature/RelevantTest.php
npm run build
php artisan migrate:status
```

Run the full suite when changing shared assessment, role, user, dashboard, or schema behavior:

```bash
php artisan test
```

For local visual verification:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev -- --host 127.0.0.1
```

Set `MOCK_SSO_ENABLED=true`, then open `/mock-sso`, choose the required role, and verify visible layout, empty states, validation, disabled states, save behavior, refreshed server data, and mobile/desktop overflow.

## Common Failure Modes

- Updating Vue without adding the required prop in `DashboardController`.
- Updating one controller while tests or migrations still target a legacy table.
- Treating `manager` or `manager_dept` as canonical persisted roles.
- Writing `dean_approved` instead of `approved`.
- Reading checked indicators from `assessment_evidences`.
- Allowing IDP creation before assessment approval.
- Editing a migration and expecting an already-migrated PostgreSQL database to change.
- Passing SQLite-like tests while missing PostgreSQL-specific migration behavior.
- Verifying only `npm run build` without checking the authenticated role page for UI changes.

## Completion Checklist

- Behavior is traced across database, backend, Inertia props, and Vue.
- Role and status compatibility are preserved.
- Required migrations are applied or clearly reported.
- Focused tests were run.
- `npm run build` was run for frontend changes.
- Real role page was inspected for visible UI changes when practical.
- Final response lists files changed, checks run, and remaining risks.
