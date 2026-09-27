# Architecture

## System Overview

The ENKKU Competency & IDP System is a Laravel 13 application with Inertia Laravel 2 and Vue 3 screens. PostgreSQL stores organization structure, competencies, assessments, reviewer chains, competency gaps, IDP plans, IDP activities, and learning catalog data.

The normal request path is:

```text
route
  -> controller
  -> service/query/model
  -> PostgreSQL
  -> Inertia props
  -> Vue page/component
  -> form payload back to controller
```

## Application Layers

- `routes/web.php` defines authenticated web routes, admin/HR writes, employee assessment and IDP writes, reviewer decisions, and local mock SSO routes.
- Controllers assemble read models and validate/write domain data.
- Services centralize reusable domain logic:
  - `ReviewerChainResolver`
  - `ReviewerTemplateResolver`
  - `ExpectedLevelResolver`
  - `IdpItemReviewWorkflow`
  - `CompetencyAssessmentSyncService`
  - `NotificationService`
  - `NotificationDigestService`
- Vue pages under `resources/js/Pages` render role dashboards and operational screens.
- Shared navigation labels and page IDs live in `resources/js/data.ts`.

## Major Domains And Tables

| Domain | Tables |
| --- | --- |
| Organization | `users`, `user_reviewer_steps`, `roles`, `worklines`, `job_families`, `positions`, `levels`, `support_departments`, `support_works`, `support_units` |
| Competency setup | `competency_types`, `competencies`, `competency_levels`, `comp_level_indicators`, `position_competencies`, `position_fc_selection_rules`, `hr_expectations` |
| FC topic approval | `fc_topic_selections`, `fc_topic_selection_items` |
| Assessment | `assessments`, `assessment_indicator_results`, `scores` |
| Gap | `competency_gaps` |
| IDP plan | `idps`, `idp_items`, `idp_item_reviews` |
| IDP activities | `idp_activities`, `idp_activity_updates` |
| Learning choices | `learning_method_types`, `idp_learning_methods`, `learning_catalogs`, `learning_catalog_competency`, `learning_catalog_delivery_types` |

## Role Dashboard Architecture

`DashboardController@index` is the main dashboard read-model assembler. It resolves the authenticated user, normalizes the role key, builds shared props, and renders the role-specific dashboard shell.

Role shells include:

- Admin: `resources/js/Pages/Admin/Dashboard.vue`
- HR: `resources/js/Pages/HR/Dashboard.vue`
- Employee: `resources/js/Pages/Employee/Dashboard.vue`
- Head/unit reviewer: `resources/js/Pages/Head/Dashboard.vue`
- Supervisor/department reviewer: `resources/js/Pages/Super/Dashboard.vue`
- Executive: `resources/js/Pages/Executive/Dashboard.vue`

Navigation is driven by `NAV_CONFIG` and titles by `PAGE_TITLES` in `resources/js/data.ts`.

## Reviewer Chain Architecture

Runtime reviewer permissions are stored in `user_reviewer_steps`.

Each row represents:

- reviewed user
- workflow type through `chain_type`
- ordered step through `step_order`
- reviewer user through `reviewer_id`

`chain_type = assessment` controls competency assessment routing, FC topic approval routing, role dashboard reviewer permissions, and assessment notifications.

`chain_type = idp` controls IDP item submission, IDP approval routing, IDP approval dashboard rows, and IDP reviewer permission checks.

`ReviewerChainResolver` is the central read path. Missing rows mean the user has no configured chain.

## Reviewer Chain Template Architecture

`reviewer_chain_templates`, `reviewer_chain_template_steps`, and `reviewer_chain_template_assignments` store reusable configuration helpers.

Templates are separated by `chain_type`:

- `assessment`
- `idp`

Applying a template copies or resolves the reviewer IDs into `user_reviewer_steps` for that user and chain type. Runtime approval checks read the copied user steps, not the template directly.

`ReviewerTemplateResolver` resolves template steps such as fixed users, same department roles, same workline roles, or any active user with a role.

## Assessment Architecture

`AssessmentController` owns self-assessment writes, draft/save/submit behavior, reviewer approval, reviewer rejection, checked indicator persistence, and gap updates.

`DashboardController` assembles the employee assessment payload from position assignments, expected levels, saved assessment data, gap rows, and FC topic selection state.

Checked behavior indicators are stored in `assessment_indicator_results`. Reviewer decisions are stored in `scores`.

## FC Topic Selection Architecture

HR assigns competencies to positions through `position_competencies` and configures the required FC count through `position_fc_selection_rules`.

When a positive FC count exists for the user position, the employee must select FC topics first. The request is stored in `fc_topic_selections` and `fc_topic_selection_items`, then routed to the first assessment reviewer.

`Employee\FcTopicSelectionController` handles employee submission. `FcTopicSelectionApprovalController` handles first-reviewer approval and rejection.

## Competency Gap Architecture

Approved assessment results produce or update `competency_gaps`.

The canonical gap is:

```text
actual_level - expected_level
```

Negative gaps set `requires_idp = true`. IDP work must come from approved competency gaps.

## IDP Architecture

`Employee\IdpController` owns IDP draft and submit validation. `IdpItemReviewWorkflow` controls item review steps and parent IDP status synchronization.

The implemented model is:

```text
one user/year plan in idps
  -> one idp_item per negative competency gap
  -> one or more idp_activities per item
  -> many idp_activity_updates after approval
```

`IdpApprovalController` handles reviewer approve/reject actions and writes append-only decisions to `idp_item_reviews`.

## Learning And Development Architecture

The IDP form separates learning choices into:

- experiential and social choices from `idp_learning_methods`
- formal learning choices from `learning_catalogs`
- formal learning competency filtering through `learning_catalog_competency`
- delivery formats from `learning_catalog_delivery_types`

The removed legacy table `idp_delivery_type_settings` should only appear in migration compatibility logic.

## Integration Boundaries

- Mock SSO exists through `/mock-sso` only when the environment is `local` and `MOCK_SSO_ENABLED=true`.
- Email notifications require `MAIL_NOTIFICATIONS_ENABLED=true`; delivery recipients remain controlled by the mail environment settings.
- Email notifications are handled through mailables and `NotificationService`/`NotificationDigestService`.
- File and URL evidence for IDP progress are stored through `idp_activity_updates`.
