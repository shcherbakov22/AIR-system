# Product Overview

## Summary

AIR System is a mentor-and-student web app for guided schedule execution and live oversight.

The student side is schedule-first:

- build schedules
- start a schedule run
- start blocks manually
- pause into a custom timer
- resume the schedule
- finish blocks or finish the schedule early
- view rules and active violations

The mentor side is operations-first:

- manage students
- manage task templates
- manage schedule templates
- manage rules
- issue and resolve violations
- watch the live monitor board
- review student progress

## Current Roles

### Mentor

Mentors can:

- view the live monitor dashboard
- manage students and student passwords
- create and edit task templates
- create and edit schedule templates
- create, edit, and delete rules
- create, review, resolve, and delete violations
- view student progress history

### Student

Students can:

- view their dashboard
- create and edit their own schedules
- start a schedule
- start any unfinished block
- pause into a custom timer
- resume a paused schedule block
- finish the current task
- finish a schedule early
- view active rules

## Discipline Model

The live app uses violations as the discipline mechanism.

Important behaviors:

- open violations can block schedule continuation and custom timer starts
- automatic `Observe the time` violations are created when:
  - a student is idle for too long during a started schedule
  - a task runs too far beyond its planned duration
- the same automatic incident is only created once

## Monitor Features

The mentor dashboard is a live monitor board with:

- one column per student
- active schedule visibility
- per-block timing visibility
- latest screen and camera previews
- fullscreen capture modal
- same-day capture history
- browser TTS announcements for key events

## Legacy Compatibility

The rewrite also exposes legacy-compatible upload endpoints so old screenshot and camera uploader clients can keep working against the new server.

