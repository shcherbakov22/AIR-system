# Operations Guide

## Main Servers

### Production App Server

- `192.168.11.228`

This server runs the rewrite app and serves:

- the main web UI
- modern capture upload endpoints
- legacy-compatible uploader endpoints

### Legacy Reference Server

- `192.168.11.66`

This machine is still useful as the live legacy source for migration and comparison work.

## Web Serving

The production stack uses Caddy in front of Laravel.

Important behavior:

- internal HTTPS support remains available for features that require it
- plain HTTP compatibility is also available for older uploader clients that do not tolerate redirects well

## Capture Retention

Student monitor captures are purged daily.

Implementation pieces:

- command:
  [apps/platform/app/Console/Commands/PurgeStudentMonitorCapturesCommand.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Console\Commands\PurgeStudentMonitorCapturesCommand.php)
- deployment timer:
  `monitor-purge.timer` on `192.168.11.228`

## Deploying App Code

Typical deployment actions used in this project:

- sync changed files to `192.168.11.228`
- rebuild frontend assets:
  `npm run build`
- clear Laravel caches:
  `php artisan optimize:clear`

For service changes on the server, prefer `systemctl`.

## Mentor Monitor Dependencies

The mentor monitor depends on:

- up-to-date schedule/task state in the app database
- incoming monitor captures
- browser speech support on the mentor’s machine

If the monitor looks stale, check:

- browser auto-refresh state
- recent capture rows in `student_monitor_captures`
- speech-announcement queue state

