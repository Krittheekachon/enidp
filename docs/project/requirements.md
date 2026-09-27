# Requirements

This file is the canonical home for business and domain rules. It preserves confirmed rules from the repository documentation and behavior verified from the implementation.

## Confirmed Rules

### Roles

Canonical role keys are:

- `employee`
- `supervisor`
- `dept_head`
- `division_head`
- `academic_department_head`
- `dean`
- `hr`
- `admin`

Legacy aliases must be normalized:

- `manager` -> `dean`
- `manager_dept` -> `dept_head`

The Thai role naming currently used in UI is:

- `supervisor`: หัวหน้าหน่วย
- `dept_head`: หัวหน้างาน
- `division_head`: หัวหน้าฝ่าย
- `academic_department_head`: หัวหน้าภาควิชา
- `dean`: ผู้บริหารคณะ
- `hr`: งานทรัพยากรบุคคล
- `admin`: ผู้ดูแลระบบ
- `employee`: บุคลากร

### Reviewer Chains

- A user's active reviewer workflow is stored in `user_reviewer_steps`.
- `chain_type = assessment` is for competency assessment approval.
- `chain_type = idp` is for IDP approval.
- Reviewer templates are configuration helpers only.
- Applying a template must write active reviewer rows into `user_reviewer_steps`.
- Missing active reviewer rows mean the user has no configured chain.
- The removed `users.supervisor_id_1`, `supervisor_id_2`, and `supervisor_id_3` columns must not be used for runtime behavior.
- Do not require one hard-coded role for one fixed step. The ordered reviewer IDs decide who can act.

### Competency Setup

- HR links competencies to positions through `position_competencies`.
- Assigned CC, MC, and FC competencies form the base assessment topics for a user in that position.
- Expected levels are configured in `hr_expectations`.
- Expected-level reads must go through `App\Services\ExpectedLevelResolver`.

### FC Topic Selection

- FC topic pre-selection applies only when `position_fc_selection_rules.required_fc_count > 0`.
- Missing rule or `required_fc_count = 0` means no FC pre-selection flow.
- The required FC count must not exceed the number of FC competencies assigned to that position.
- Users may select FC topics only from FC competencies already assigned to their position.
- Users must select exactly the configured count before submitting for topic approval.
- Submitted selections are stored in `fc_topic_selections` and `fc_topic_selection_items`.
- The first configured assessment reviewer approves or rejects the FC topic selection.
- A rejected FC topic selection requires a comment and returns to `revision_required`.
- If a position requires FC selection, the whole self-assessment remains locked until FC selection is approved.
- CC and MC continue through the normal assessment flow and do not require topic pre-selection.
- After FC approval, the assessment includes CC, MC, and only approved FC topics.

### Assessment Workflow

Self-assessment can be edited only when the assessment status is `draft` or `revision_required`.

The canonical assessment status flow is:

```text
draft / revision_required
  -> self_submitted
  -> unit_evaluated
  -> dept_evaluated
  -> review_step_N
  -> approved
```

Rules:

- A non-admin user must have at least one active assessment reviewer before self-assessment.
- Draft saves and final submit go through `AssessmentController`.
- Checked behavior indicators are stored in `assessment_indicator_results`.
- A reviewer may approve or reject only when the assessment is in that reviewer's pending step.
- Rejection requires a comment and returns the assessment/gap to `revision_required`.
- `dean_approved` may be read as legacy approved data, but new writes should use `approved`.
- `assessments` and `competency_gaps` must be updated together in a transaction.
- Reviewer scores, comments, decisions, and submit times are stored in `scores`.

### Competency Gap

The canonical gap calculation is:

```text
gap = actual_level - expected_level
requires_idp = gap < 0
```

Indicator counts can support UI display, but they must not replace the stored gap calculation.

IDP work can be created only when:

- the gap belongs to the authenticated user;
- `requires_idp` is true;
- the gap is negative; and
- the assessment status is `approved` or legacy `dean_approved`.

### IDP Plan And Item Workflow

- Store one current IDP plan in `idps` for a user and active assessment year.
- Use one `idp_item` per competency gap requiring development.
- Allow many `idp_activities` under one `idp_item`.
- Require activity weights to total 100% before submitting an item.
- Permit one complete item to be submitted while other competency items remain draft.
- Auto-save only editable draft or revision-required items.
- Lock under-review and approved items against employee editing and background auto-save.

IDP item status flow:

```text
draft / revision_required
  -> review_step_1
  -> review_step_2
  -> review_step_3
  -> review_step_N
  -> approved
```

Rules:

- IDP reviewer steps come from `user_reviewer_steps` where `chain_type = idp`.
- Missing IDP reviewer rows block IDP submission.
- Only the reviewer assigned to the current step may approve or reject.
- Rejection requires a comment and returns the item to `revision_required`.
- Resubmission increments `submission_version` and restarts at the first configured reviewer.
- Store every decision in `idp_item_reviews` with submission version, review step, reviewer, decision, comment, and decision time.
- Derive the parent `idps.status` from its item statuses.
- Allow activity progress updates only after the item is finally approved.

### Learning Choices

Every activity under an IDP item must have one learning focus:

- Experiential Learning: active `idp_learning_methods` with `focus_type = experiential`.
- Social Learning: active `idp_learning_methods` with `focus_type = social`.
- Formal Learning: active `learning_catalogs`.

Formal Learning must be filtered by failed competency:

```text
idp_items.competency_gap_id
  -> competency_gaps.competency_id
  -> learning_catalog_competency.competency_id
  -> learning_catalogs.id
```

Do not show every Learning Catalog entry across all competencies.

## Behavior Verified From Implementation

- `DashboardController` exposes reviewer chain payloads through `ReviewerChainResolver`.
- `NotificationService` resolves assessment notification recipients from reviewer chains.
- `IdpItemReviewWorkflow` reads `chain_type = idp` through `ReviewerChainResolver`.
- `ReviewerChainTemplateController` supports create, update, delete, add users, and remove users for assessment and IDP templates.
- The admin user management warning checks missing assessment and IDP chains rather than old supervisor columns.
- The employee IDP controller and UI support multiple activities per competency item and enforce a total weight of 100 percent on submission.

## Unknown / Needs Verification

- Whether every historical `docs/superpowers` plan has been fully implemented.
- Whether all legacy statuses in old production data have been normalized.
