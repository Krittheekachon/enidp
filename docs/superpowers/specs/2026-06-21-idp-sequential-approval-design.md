# Sequential IDP Approval Design

## Scope

Implement sequential approval for each competency-level IDP item.

This design does not implement a new competency assessment after a position-level promotion. That workflow remains undecided and will be designed after customer confirmation.

## Business Rules

- One negative competency gap produces one `idp_item`.
- An employee may submit each `idp_item` independently.
- Reviewers follow `users.supervisor_id_1`, `supervisor_id_2`, and `supervisor_id_3` in order.
- Empty reviewer slots are skipped.
- Each reviewer approves or rejects one competency item independently.
- Approval comments are optional.
- Rejection comments are required.
- A rejected item returns to the employee and must restart approval from the first configured reviewer after resubmission.
- Final approval allows the employee to update activity progress.
- Every reviewer decision remains available as history.

## Status Flow

Use explicit item statuses:

```text
draft / revision_required
  -> review_step_1
  -> review_step_2
  -> review_step_3
  -> approved
```

Only configured steps are used. For example, when only reviewers 1 and 3 are configured:

```text
draft -> review_step_1 -> review_step_3 -> approved
```

Rejection from any step:

```text
review_step_N
  -> revision_required
  -> employee edits and resubmits
  -> first configured review step
```

## Data Model

### `idp_items`

Keep the current state required for fast dashboard queries:

- `status`
- `submission_version`: integer, incremented each time an employee resubmits after rejection
- `current_review_step`: nullable integer from 1 to 3
- `submitted_at`
- `approved_at`
- `reject_comment`: latest rejection reason shown to the employee

`submission_version` is not an assessment cycle or annual review round. It identifies revisions of the same submitted IDP item.

### `idp_item_reviews`

Add a new append-only decision-history table:

- `id`
- `idp_item_id`: foreign key to `idp_items.id`
- `submission_version`
- `review_step`
- `reviewer_id`: foreign key to `users.id`
- `decision`: `approved` or `rejected`
- `comment`: nullable for approval and required for rejection
- `decided_at`
- timestamps

Relationship:

```text
idp_items.id
  -> idp_item_reviews.idp_item_id
  one IDP item to many review decisions
```

Use a uniqueness rule for one decision per reviewer step and submitted version:

```text
unique(idp_item_id, submission_version, review_step)
```

## Submission Flow

1. Validate that the item belongs to the authenticated employee and is editable.
2. Validate all required plan fields, activities, dates, learning choices, and total activity weight of 100%.
3. Find the first configured reviewer slot.
4. Set `status` to the corresponding `review_step_N`.
5. Set `current_review_step`, clear the latest rejection fields, and set `submitted_at`.
6. Lock the item against employee editing and background auto-save.
7. Leave all other competency items unchanged.

For the first submission, use `submission_version = 1`. Increment it only when an item in `revision_required` is resubmitted.

## Reviewer Decision Flow

Allow a decision only when:

- the authenticated user matches the reviewer assigned to `current_review_step`;
- the item status matches `review_step_N`; and
- the reviewer has not already decided the current submission version.

On approval:

1. Insert an `approved` row into `idp_item_reviews`.
2. Find the next configured reviewer after the current step.
3. Move the item to the next `review_step_N`.
4. If there is no next reviewer, set the item to `approved`, clear `current_review_step`, and set `approved_at`.

On rejection:

1. Require a non-empty comment.
2. Insert a `rejected` row into `idp_item_reviews`.
3. Set the item to `revision_required`.
4. Clear `current_review_step`.
5. Store the latest comment in `reject_comment`.
6. Unlock the item for employee editing.

## Parent IDP Status

Derive `idps.status` from all child item statuses:

- `approved`: every item is approved
- `partially_submitted`: at least one item is in a review step
- `revision_required`: at least one item requires revision and none is currently under review
- `in_progress`: at least one item is approved while other items remain draft
- `draft`: all items are draft

The parent status must not block independent submission or approval of individual competency items.

## User Interface

### Employee

- Show the current status for each competency item.
- Show which reviewer step is pending.
- Disable fields and auto-save while the item is under review or approved.
- Show the latest rejection comment on returned items.
- Keep other draft competency items editable.

### Reviewer

- Show only items currently waiting for that authenticated reviewer.
- Display employee, competency, goal, success criteria, activities, dates, weights, and document reference numbers.
- Provide approve and reject actions per competency item.
- Make approval comment optional.
- Require rejection comment.
- Show review history by submission version and reviewer step.

## Assessment Lifecycle Boundary

Do not use calendar year or `submission_version` as a new competency assessment cycle.

When a future position-level promotion requires reassessment, create a separate assessment instance and a separate IDP while preserving the old assessment, gaps, IDP, activities, and review history. The trigger and ownership of that future reassessment remain outside this implementation.

## Error Handling

- Reject decisions from users who are not the assigned reviewer for the current step.
- Reject stale or duplicate decisions.
- Reject employee edits to items under review or already approved.
- Reject submission when no reviewer is configured.
- Perform item state changes and review-history inserts in one database transaction.

## Verification

Add feature coverage for:

- sequential approval through configured reviewers;
- skipping empty reviewer slots;
- independent approval of different competency items;
- rejection at each step;
- required rejection comments;
- resubmission starting from the first configured reviewer;
- `submission_version` increments after rejection;
- previous review history remains unchanged;
- unauthorized and duplicate decisions are rejected;
- final approval enables progress updates;
- submitted items cannot be overwritten by auto-save.

Run the focused feature tests and `npm run build`.
