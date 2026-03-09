# ADR-006: Build The Core Platform Before Monitoring And Hardware

Status: Accepted

Date: 2026-03-06

## Why This ADR Exists

The browser extension and pushup hardware are visually interesting and technically tempting.

They are also the easiest way to burn time before the core product is stable.

If I start there, I risk rebuilding the flashy edges while the essential task/schedule/violation model remains unclear.

## Context

Core stable value comes from:

- auth
- admin/student model
- student settings
- task catalog
- schedules
- schedule execution
- ad hoc student timer
- task sessions
- rules
- violations
- penalty ledger
- admin dashboard

Riskier or more integration-heavy pieces are:

- browser extension
- attention events
- monitoring policy automation
- hardware bridge
- consequence device execution

## Decision

Implementation order is fixed like this unless explicitly changed:

### Phase 1

- auth
- users and roles
- student settings
- task catalog
- schedules
- schedule execution
- ad hoc timed tasks
- task sessions
- rules
- violations
- penalty ledger
- admin dashboard
- imports and reconciliation

### Phase 2

- monitoring APIs
- browser extension
- attention event ingestion
- policy profile enforcement

### Phase 3

- hardware bridge
- consequence queue execution
- simulator and device integration

### Phase 4

- goals
- reading/resources
- secondary learning modules

## Implementation Rules

- Do not start extension code before the core platform data model is stable.
- Do not start hardware integration before violations and penalty flows are implemented and tested.
- Do not let a legacy integration dictate the order of the rewrite.
- Core imports and reconciliation are part of phase 1, not optional cleanup.

## Do Not Do

- Do not spend the first implementation sprint on Arduino or extension code.
- Do not wire external clients to unstable or placeholder APIs if the core domain is still moving.
- Do not treat monitoring/hardware as prerequisites for proving the rewrite architecture.

## Consequences

- The earliest shipped milestone is useful even without the extension or hardware.
- Core domain mistakes are discovered sooner.
- Later integrations have a cleaner API target.

## Revisit Only If

- the project goal changes from "full stable rewrite" to "replace a specific integration immediately"

If that happens, write a new ADR explicitly. Do not quietly reorder the project.
