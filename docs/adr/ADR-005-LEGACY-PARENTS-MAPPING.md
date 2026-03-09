# ADR-005: Legacy `parents` Maps To Student Settings, Not Parent Users

Status: Accepted

Date: 2026-03-06

## Why This ADR Exists

The table name `parents` is misleading.

If I take it literally, I will reintroduce a parent model the user explicitly said not to build.

## Context

The legacy `parents` table contains:

- `p_id`
- `ch_id`
- `push_ups`
- `time`

The most obviously useful fields are consequence defaults:

- pushup count
- rest time / duration

That looks more like student consequence configuration than proof of an active parent-facing product role.

## Decision

In the rewrite, the legacy `parents` table maps primarily to:

- `student_settings`
- `student_consequence_profiles`

If `p_id` matters for traceability, it should be stored as legacy metadata only, for example:

- `legacy_owner_user_id`

It is not a product-facing parent relationship.

## Implementation Rules

- Import `push_ups` into the student consequence profile.
- Import `time` into the student consequence profile or related settings.
- Preserve `p_id` only as legacy metadata if needed for audits or import traceability.
- Do not expose `p_id` as a user-facing relation in the new UI.

## Do Not Do

- Do not create parent user accounts from this table.
- Do not create guardian/student relationship tables from this table.
- Do not infer permissions from `p_id` unless there is later evidence that it represented real admin scoping.

## Consequences

- The rewrite stays aligned with the clarified product scope.
- Useful operational settings are preserved.
- Legacy semantics remain traceable without distorting the new design.

## Revisit Only If

- later analysis of real usage proves that `p_id` had an essential permissions meaning that admins still need

If that happens, introduce a scoped admin assignment model intentionally. Do not call it a parent model unless it truly is one.
