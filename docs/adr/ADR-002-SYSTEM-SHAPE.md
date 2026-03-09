# ADR-002: Build A Laravel Modular Monolith With Sidecar Clients

Status: Accepted

Date: 2026-03-06

## Why This ADR Exists

The legacy system is scattered across many PHP files, template JS, utility scripts, and ad hoc integrations.

The easiest mistake after seeing that mess is to over-correct into too many services too early.

That would create a different kind of mess.

## Context

- The core business logic is tightly coupled:
  - tasks
  - schedules
  - sessions
  - violations
  - penalty balance
  - monitoring policy
  - consequences
- The system is operationally moderate, not large enough to justify service decomposition from day one.
- Two technical edges do need separation:
  - browser extension
  - hardware bridge

## Decision

The system shape is:

- one Laravel application as the primary product and system of record
- one browser extension as a client of that application
- one hardware bridge as a client of that application

The Laravel app owns:

- web UI
- admin UI
- student UI
- domain logic
- database writes
- APIs for external clients

The extension and hardware bridge are clients, not peer backends.

## Implementation Rules

- Keep domain logic in the Laravel app.
- The extension may collect and send data, but it does not decide core business rules.
- The hardware bridge may execute consequence jobs, but it does not own violation state.
- Use APIs and queues for edge integrations.
- Keep all authoritative state in the platform database.

## Do Not Do

- Do not create separate backend services for tasks, schedules, penalties, or monitoring in phase 1.
- Do not put hardware state in ad hoc files as the source of truth.
- Do not move core rules into extension code.
- Do not rebuild the old public-webroot script sprawl.

## Consequences

- Delivery is faster.
- Refactoring is easier.
- Auth and authorization remain coherent.
- The extension and hardware bridge stay replaceable.

## Revisit Only If

- the modular monolith becomes a real scaling bottleneck proven by production metrics

If that happens, split by measured pressure, not by guesswork.
