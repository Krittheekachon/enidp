# IDP Workbench UI Design

## Goal

Redesign the employee IDP page so users can distinguish each part of the plan quickly while preserving the existing IDP content, validation, persistence, and background auto-save behavior.

## Layout

Use a workbench layout:

- Left rail: employee summary and competency plans.
- Main workspace: the selected competency plan.
- Sticky bottom action bar: plan readiness and submit action.
- On mobile, stack the rail above the workspace and keep fields single-column.

The first eligible competency is selected automatically. Each rail item shows competency code, name, activity count, total weight, and readiness.

## Visual Hierarchy

The competency header is a bordered white panel containing:

- competency type, code, and name;
- evaluator note;
- Expected, Actual, and Gap score blocks.

Each plan section is a separate bordered panel with a colored header:

- Orange: behaviors requiring development.
- Blue: plan goal and success criteria.
- Green: development activities.

Colors identify sections, but form fields remain neutral and readable. Borders, spacing, and header backgrounds must clearly show where each section begins and ends.

## Behaviors Section

Show missing behavior indicators as locked rows inside the orange panel. Each row displays its level, indicator code, and description. Users cannot edit or remove these rows.

## Goal Section

Show the following fields inside the blue panel:

- development goal;
- success criteria.

Both fields belong to the competency plan and apply to every activity within that plan.

## Activities Section

Show every activity as its own bordered sub-panel inside the green section. Each activity includes:

- learning focus;
- experiential/social development tool or formal learning catalog;
- activity name;
- activity description;
- document reference number;
- weight percentage;
- start date;
- end date;
- remove activity control.

The section header shows total activity weight. Users can add multiple activities. Formal Learning options remain filtered by the selected competency.

## Saving And Submission

Preserve background auto-save after the user stops editing. Auto-save must not reload or rehydrate the page. Continue displaying:

- saving;
- saved time;
- save error.

The submit action remains explicit. Submission is enabled only when every competency plan is complete and each plan's activity weights total 100%.

Submitted plans remain read-only.

## Data And Backend Scope

Do not change the approved data model:

```text
idps
  -> idp_items: one item per competency gap
      -> idp_activities: multiple activities per item
```

Do not change validation rules, catalog filtering, workflow statuses, route names, or auto-save endpoint behavior. This redesign is presentation-only except for small frontend state changes required by the layout.

## Verification

- Production frontend build succeeds.
- Employee IDP route returns HTTP 200.
- Existing plans and activities render without data loss.
- Background auto-save does not reload the page.
- Desktop and mobile layouts do not overlap or overflow.
- Section colors and borders remain distinguishable with readable text contrast.
