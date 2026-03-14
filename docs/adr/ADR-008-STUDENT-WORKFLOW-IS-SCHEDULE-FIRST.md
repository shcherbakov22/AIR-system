# ADR-008: Student Workflow Is Schedule-First, Not Assignment-First

Status: Accepted

Date: 2026-03-08

## Why This ADR Exists

The rewrite drifted toward an assignment inbox model for students.

That is not the real product.

The user clarified that the student workflow needed right now is:

- set schedule
- start schedule
- manually start and finish schedule blocks
- interrupt or pause a schedule
- set a custom timer
- receive violations

If I keep treating assigned work and recent work as the center of the student portal, I will keep building the wrong runtime.

## Context

- The current rewrite already has task templates, schedules, task sessions, violations, and mentor monitoring.
- That does not mean assignments should drive the student UX.
- The student-facing runtime is about executing a schedule and handling interruptions, not browsing an inbox of assigned items.
- Violations are part of the student experience because students need to understand why work is blocked or interrupted.

## Decision

The student product is schedule-first.

The current core student workflow is:

- manage personal schedule
- start a schedule run
- work through scheduled tasks
- pause or interrupt a running schedule
- create and run a custom timed task
- view received rules and active violations

Assigned work and recent-work history are not required primary student features.

If `task_assignments` remain in the system, treat them as import scaffolding, admin tooling, or optional metadata unless the product explicitly changes later.

## Implementation Rules

- The student portal should center on current schedule state, current timed task, interruption controls, and penalty visibility.
- Student-facing runtime should not require an assignment inbox before work can begin.
- Task sessions may be linked to schedule runs, schedule entries, or custom timed tasks.
- Student schedule management is allowed unless a later product rule explicitly restricts it.
- Violation information should be visible to students, and open violations can block continued work.

## Do Not Do

- Do not make the student home page an assignment inbox.
- Do not prioritize recent-work history over schedule execution controls.
- Do not assume every task start must originate from `task_assignments`.
- Do not let already-built assignment code dictate the future student product shape.

## Consequences

- Some already-built assignment-focused UI should be treated as temporary and reduced or removed.
- The next important runtime slices are schedule execution, pause/resume, custom timers, and monitoring.
- Mentor features may still reference tasks, schedules, rules, and violations without forcing an assignment-first student experience.

## Revisit Only If

- the product owner explicitly decides that students need an assignment inbox as a first-class workflow

Until then, the student runtime is schedule-first.
