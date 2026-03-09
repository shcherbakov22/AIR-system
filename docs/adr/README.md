# ADR Index

These ADRs are written as implementation guardrails for this rewrite.

They are not generic architecture notes. Their job is to stop me from drifting back toward the legacy shape while building.

## Current ADRs

- [ADR-001-ACTOR-MODEL.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-001-ACTOR-MODEL.md)
  The product is `admin + student`, not `parent + child`.
- [ADR-002-SYSTEM-SHAPE.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-002-SYSTEM-SHAPE.md)
  Build a Laravel modular monolith with sidecar clients, not multiple backend services.
- [ADR-003-DATABASE-STRATEGY.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-003-DATABASE-STRATEGY.md)
  Use a strict new schema on PostgreSQL and import into that schema intentionally.
- [ADR-004-PENALTY-MODEL.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-004-PENALTY-MODEL.md)
  Treat legacy `money` as an admin-controlled penalty balance, not a wallet.
- [ADR-005-LEGACY-PARENTS-MAPPING.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-005-LEGACY-PARENTS-MAPPING.md)
  Interpret the legacy `parents` table as student settings / legacy metadata, not as a product role model.
- [ADR-006-IMPLEMENTATION-ORDER.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-006-IMPLEMENTATION-ORDER.md)
  Build the core platform first; monitoring and hardware come after the basics are stable.
- [ADR-007-SCHEMA-FIRST-IMPORT-LATER.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-007-SCHEMA-FIRST-IMPORT-LATER.md)
  Design the new PostgreSQL schema from the new domain model first; only design legacy conversion after that shape is stable.
- [ADR-008-STUDENT-WORKFLOW-IS-SCHEDULE-FIRST.md](C:\Users\user\codex\school-system-redo\docs\adr\ADR-008-STUDENT-WORKFLOW-IS-SCHEDULE-FIRST.md)
  The student portal centers on schedule execution, interruptions, ad hoc timers, and penalties, not an assignment inbox.

## How To Use These During Implementation

- Before adding a new module, check whether one of these ADRs already constrains it.
- If code starts violating an ADR, stop and either:
  - bring the code back into line, or
  - write a replacement ADR intentionally
- Do not silently drift.
