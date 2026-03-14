# Student Workflow

## Dashboard Purpose

The student dashboard is a runtime surface, not a reporting page.

It is optimized around:

- what is running now
- what can be started next
- whether work is blocked

## Main Flow

### 1. Create a Schedule

Students can create and edit their own schedules from the schedules section.

Each schedule is made of ordered blocks, but the runtime no longer forces strict in-order completion.

### 2. Start a Schedule

Starting a schedule creates a schedule run.

The schedule run is the live execution record for that session of work.

### 3. Start a Block

Students can start any unfinished block from the current run.

The active block becomes the live timed task.

### 4. Pause Into a Custom Timer

If allowed by student settings and not blocked by an open violation, a student can pause the schedule and start a custom timer.

The paused schedule block is preserved server-side.

### 5. Resume the Schedule

Resuming from a custom timer ends that temporary task and returns the student to the paused schedule block rather than restarting it from scratch.

### 6. Finish the Current Task

Finishing the current task completes the active task session.

If it was the last unfinished block in the schedule, the run completes automatically.

### 7. Finish the Schedule Early

Students can also finish a schedule even when some blocks remain unfinished.

## Rules and Blocking

Students can view active rules.

Open violations matter operationally because they can block:

- starting a schedule block
- pausing into a custom timer

## Automatic `Observe the Time`

This violation can be generated automatically when:

- a schedule has started and the student stays idle too long without an active task
- a task runs too far beyond its planned duration

The system creates it only once per incident.

## What The Student Side Does Not Try To Be

The student product is not currently:

- an assignment inbox
- a general task history browser
- a reporting dashboard

