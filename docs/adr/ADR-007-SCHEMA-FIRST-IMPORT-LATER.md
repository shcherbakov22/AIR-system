# ADR-007: Design The New Schema First, Plan Legacy Conversion Later

Status: Accepted

Date: 2026-03-06

## Why This ADR Exists

The legacy database is tempting because it already exists.

That also makes it dangerous.

If I start with legacy table mapping too early, I will quietly let the old schema design the new system.
That would defeat the point of the rewrite.

## Context

- The legacy schema is overloaded and misleading in multiple places.
- Some legacy names do not match the real product semantics.
- Important behavior lives in PHP scripts and frontend code, not only in tables.
- The rewrite is supposed to produce a clean new platform, not a translated DLE/MyISAM-era schema.

Examples of why the legacy DB is not a safe starting point:

- `parents` does not justify a parent-facing role model
- `money` does not justify a wallet or payment system
- historical tables mix state, audit, flags, and workflow shortcuts

## Decision

The rewrite order for data design is:

1. define the new domain model
2. design the new PostgreSQL schema for that model
3. implement core flows against the new schema
4. only after that, design legacy import and conversion rules

Legacy field mapping is an import concern, not a schema design concern.

## Implementation Rules

- Design new tables from the rewritten product model only.
- Use legacy data only as evidence of what the product used to do, not as the shape to copy.
- Do not create new tables just because a legacy table existed.
- Do not preserve legacy column groupings unless they are still valid in the new model.
- Delay detailed field-by-field mapping until the new schema is stable enough that imports have a real target.

## Do Not Do

- Do not start by writing a legacy-to-new column map before the new schema exists.
- Do not let importer convenience decide table boundaries.
- Do not mirror legacy names like `dle_users`, `log_action`, or `shed_name_id` into the rewrite schema.
- Do not design around the assumption that every legacy field must survive one-to-one.

## Consequences

- The new schema stays product-driven instead of migration-driven.
- Import design becomes a separate, cleaner problem.
- Some legacy data may later be dropped, normalized, or archived rather than imported directly.

## Revisit Only If

- the project goal changes from "clean rewrite" to "fastest possible compatibility clone"

If that happens, replace this ADR explicitly.
Do not weaken it by starting legacy mapping work early out of convenience.
