# ADR-001: Actor Model Is Mentor + Student Only

Status: Accepted

Date: 2026-03-06

## Why This ADR Exists

The legacy code and schema contain names like `parents`, and that makes it easy to accidentally rebuild a parent-facing product.

That would be wrong.

The user explicitly clarified that parents do not use this system and no parent functionality is needed.

## Context

- The old codebase contains a `parents` table and several supervision-oriented views.
- That is not enough evidence to justify a parent-facing role in the new product.
- Reintroducing parent/guardian flows would add unnecessary auth, permissions, UI, and import complexity.

## Decision

The rewrite supports these human roles:

- `mentor`
- `student`

The codebase still uses the internal role key `admin` in a few places for compatibility, but the product role is mentor.

There is no `parent` role.
There is no `guardian` role.
There is no `household` or `family` product model in v1.

## Implementation Rules

- Use `mentor` and `student` terminology in user-facing docs and UI.
- Do not create parent login flows, parent dashboards, parent notifications, or parent-specific permissions.
- If legacy data implies ownership or grouping, treat it as legacy metadata or mentor scoping, not as a user-facing parent concept.
- Student oversight pages belong to the mentor UI.

## Do Not Do

- Do not create tables like `guardians`, `parent_profiles`, or `guardian_student_links`.
- Do not name UI sections `Parent Dashboard`, `Guardian View`, or similar.
- Do not infer product roles from legacy table names alone.

## Consequences

- Auth becomes simpler.
- Permissions become simpler.
- Import logic becomes simpler.
- The legacy `parents` table must be reinterpreted instead of migrated literally.

## Revisit Only If

- the product owner explicitly requests parent accounts and parent-facing workflows

If that happens, replace this ADR intentionally. Do not weaken it implicitly in code.
