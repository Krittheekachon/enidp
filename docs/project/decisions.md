# Decisions

This file records meaningful architecture decisions visible in the repository. Historical context is recorded only when available from repository files.

## Use `user_reviewer_steps` As Runtime Reviewer Source

Decision: Runtime approval permissions use ordered rows in `user_reviewer_steps`.

Status: current implementation.

Implications:

- Missing rows mean the user has no configured chain.
- Reviewer chains can have flexible length.
- Approval steps are not tied to fixed role slots.
- `users.supervisor_id_1`, `supervisor_id_2`, and `supervisor_id_3` must not be reintroduced.

Historical context not available in the repository.

## Split Reviewer Chains By Workflow Type

Decision: Reviewer chains and reviewer templates are separated by `chain_type`.

Known values:

- `assessment`
- `idp`

Status: current implementation via `2026_06_30_000000_split_reviewer_chains_by_flow_type.php`.

Implications:

- Changing an assessment chain must not remove IDP chain data.
- Changing an IDP chain must not remove assessment chain data.
- UI and backend code must pass the intended chain type.

Historical context not available in the repository.

## Treat Templates As Configuration Helpers

Decision: `reviewer_chain_templates` are helpers for filling chains, not runtime permission authority.

Status: current implementation.

Implications:

- Applying a template writes rows to `user_reviewer_steps`.
- Runtime checks read `ReviewerChainResolver`, not templates directly.
- Template assignment metadata is useful for admin management and display.

Historical context not available in the repository.

## Use `ReviewerChainResolver`

Decision: Centralize reviewer chain reads in `App\Services\ReviewerChainResolver`.

Status: current implementation.

Implications:

- Assessment, FC topic approval, IDP approval, dashboards, and notifications should share the same resolver semantics.
- Do not duplicate reviewer lookup rules across controllers.

Historical context not available in the repository.

## Use Canonical Gap Calculation

Decision: Store gap as `actual_level - expected_level`.

Status: current implementation and business invariant.

Implications:

- Negative values require IDP.
- Indicator counts may explain detail but must not replace the canonical gap.

Historical context not available in the repository.

## Create One IDP Item Per Competency Gap

Decision: A negative competency gap creates one IDP item, and activities sit under that item.

Status: partially implemented; tables support it and repository guidance requires it.

Implications:

- Do not create one IDP item per failed behavior indicator.
- Multiple development activities should remain possible under the same competency item.

Historical context not available in the repository.

## Preserve Canonical And Legacy Status Compatibility

Decision: Use canonical `approved` for completed assessment writes, while reading legacy `dean_approved` where needed.

Status: current compatibility behavior.

Implications:

- New code should write `approved`.
- Existing read paths may map `dean_approved` to approved.

Historical context not available in the repository.

## Filter Formal Learning By Competency

Decision: Formal Learning choices for IDP must be filtered through `learning_catalog_competency`.

Status: current requirement and implementation boundary.

Implications:

- Employees should not see every catalog item for every failed competency.
- The filter path is `idp_item -> competency_gap -> competency -> learning_catalog_competency -> learning_catalog`.

Historical context not available in the repository.
