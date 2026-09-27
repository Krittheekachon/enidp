# Current State

## Implemented

- Laravel/Inertia/Vue dashboard flow with role-specific shells.
- Opt-in local mock SSO for development login (`MOCK_SSO_ENABLED=true`).
- Admin user management with role, organization profile, reviewer chain, and IDP chain configuration.
- Reviewer chain templates for both assessment and IDP workflows.
- Runtime reviewer chains through `user_reviewer_steps`.
- Separate assessment and IDP reviewer chains with `chain_type`.
- HR position competency assignment through `position_competencies`.
- HR FC selection count configuration through `position_fc_selection_rules`.
- FC topic selection submission and first-reviewer approval.
- Self-assessment draft, submit, reviewer approve, and reviewer reject.
- Competency gap calculation and IDP requirement marking.
- IDP draft/save/submit per competency item.
- IDP item approval and rejection with review history in `idp_item_reviews`.
- Learning method and learning catalog setup.
- Formal Learning filtering through competency mappings.
- Email notification services and digest services.

## Known Incomplete Areas

- Some historical `docs/superpowers` specifications and plans still describe old supervisor-column behavior. They are historical references, not the current runtime source of truth.
- Some tests may still cover legacy compatibility behavior or older UI flows.

## Compatibility And Legacy Concerns

- `manager` and `manager_dept` may appear as legacy role aliases and must be normalized to `dean` and `dept_head`.
- `division_head` is the canonical role for หัวหน้าฝ่าย and uses the head/reviewer dashboard; actual approval authority still comes from `user_reviewer_steps`.
- `academic_department_head` is the canonical role for หัวหน้าภาควิชา and uses the same reviewer dashboard; approval authority still comes from `user_reviewer_steps`.
- Existing data may still contain `dean_approved`; the application should read it as approved where needed but write `approved`.
- `assessment_evidences` exists as a legacy table and migration source, but checked behavior indicators now belong in `assessment_indicator_results`.
- `idp_delivery_type_settings` is a removed legacy delivery configuration table and should only appear in migration compatibility logic.
- `2026_06_28_000000_create_user_reviewer_steps_table.php` originally migrated old supervisor columns into `user_reviewer_steps`.
- `2026_07_01_000000_drop_legacy_supervisor_columns_from_users_table.php` drops `users.supervisor_id_1`, `supervisor_id_2`, and `supervisor_id_3`.

## Documentation / Implementation Discrepancies

### Missing Root AGENTS.md

Documentation request says an existing root `AGENTS.md` is present and should be treated as source of truth.

Implementation currently does:

- No root `AGENTS.md` existed before this documentation refactor.
- The active project-specific agent knowledge existed in `.codex/skills/competency-idp-system/SKILL.md`.

Status: documented and corrected by creating root `AGENTS.md` from the current skill knowledge and repository implementation.

### Historical IDP Specs Reference Supervisor Columns

Documentation says in old `docs/superpowers` files that IDP reviewer resolution uses `users.supervisor_id_1` through `users.supervisor_id_3`.

Implementation currently does:

- Runtime reviewer resolution uses `user_reviewer_steps`.
- `IdpItemReviewWorkflow` calls `ReviewerChainResolver` with `chain_type = idp`.
- The supervisor columns were dropped by `2026_07_01_000000_drop_legacy_supervisor_columns_from_users_table.php`.

Status: legacy documentation; do not use for new runtime behavior.

### Assessment Approved Status

Documentation and UI references may still mention `dean_approved`.

Implementation currently does:

- Reads `dean_approved` in selected paths for compatibility.
- Writes canonical `approved` for completed workflow.

Status: legacy compatibility.

### IDP Multi-Activity Support

Business requirements say one IDP item can have many activities.

Implementation currently does:

- Tables, `Employee\IdpController`, and the employee IDP UI support many `idp_activities` per `idp_item`.
- Submission requires at least one activity and a total activity weight of 100 percent per competency item.
- `EmployeeIdpPlanTest` verifies saving one competency plan with multiple activities.

Status: implemented and covered by feature tests.

## Known Failure Modes

- Missing `user_reviewer_steps` rows block assessment or IDP submission.
- Missing `position_fc_selection_rules` table after adding code that queries it causes dashboard errors until migrations are run.
- PostgreSQL migration status must be checked against the actual local database, not inferred from migration files.
- Role dashboard expectations can break if backend role normalization and frontend navigation aliases diverge.
- Full test suite may expose older behavior not covered by focused tests.

## Important Migrations

- `2026_05_28_000003_create_assessment_tables.php`
- `2026_05_28_000004_create_idp_tables.php`
- `2026_06_16_000000_create_assessment_indicator_results_table.php`
- `2026_06_21_000000_create_idp_item_reviews_table.php`
- `2026_06_24_000001_ensure_idp_item_review_columns_exist.php`
- `2026_06_27_000000_create_fc_topic_selection_tables.php`
- `2026_06_28_000000_create_user_reviewer_steps_table.php`
- `2026_06_29_000000_create_reviewer_chain_templates_table.php`
- `2026_06_30_000000_split_reviewer_chains_by_flow_type.php`
- `2026_07_01_000000_drop_legacy_supervisor_columns_from_users_table.php`

## Verification Status

Use the commands in `AGENTS.md` and `README.md`. Documentation claims in this refactor were checked against route files, controller/service entry points, key migrations, frontend page structure, package files, and existing repository documentation.
