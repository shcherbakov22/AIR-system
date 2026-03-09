# ADR-003: Use A Strict New Schema On PostgreSQL

Status: Accepted

Date: 2026-03-06

## Why This ADR Exists

The legacy schema is loose, overloaded, and mixed with historical artifacts.

If I copy that shape forward, I will reimplement the confusion instead of the product.

## Context

- Legacy tables use MyISAM and weak constraints.
- Legacy data contains overloaded semantics, zero dates, and columns that bundle unrelated behavior.
- The rewrite needs reliable transactional behavior and clearer domain boundaries.

## Decision

The rewrite targets PostgreSQL as the primary database.

The rewrite uses a strict new schema designed around the new domain model.

Legacy data is imported into the new schema intentionally.
Legacy table names and shapes are not the design baseline.

MariaDB is only an escape hatch if a hard migration blocker appears later. It is not the default plan.

## Implementation Rules

- Design new tables for the rewrite domain first.
- Use proper foreign keys where the domain relationship is real.
- Replace legacy zero dates with `NULL` or explicit states.
- Preserve legacy IDs in import mapping tables, not as the main design driver.
- Add import audit tables so imported rows remain traceable.

## Do Not Do

- Do not recreate legacy MyISAM-style tables.
- Do not copy `dle_*` and similar names into the rewrite unless the meaning is still correct.
- Do not import legacy data directly into half-modernized legacy tables.
- Do not let the import process define the long-term schema.

## Consequences

- Migrations and imports require more thought up front.
- Querying becomes safer and clearer.
- Domain logic becomes easier to enforce transactionally.

## Revisit Only If

- PostgreSQL creates a concrete blocker that materially delays the rewrite and cannot be solved reasonably

Even then, keep the strict new schema idea. Only the engine would change.
