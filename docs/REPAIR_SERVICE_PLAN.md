# AIR Repair Service Implementation Plan

## Goal

Add a separate Windows service installed with AIR Companion that can diagnose and repair student PCs remotely, including a 60-minute break-glass SSH shell, without changing Windows file permissions.

## Rules

- Do not make custom ACL or permission changes.
- Do not open inbound ports on student PCs.
- Do not allow password shell login.
- Do not expose a student-facing UI.
- Do not create student violations from repair tools.
- Log all remote repair actions server-side.
- Keep the repair service separate from the companion so it can recover the companion when the companion is broken.

## Architecture

- `AIR Companion`: normal enforcement, screenshots, webcam, app closing, extension checks, and task state.
- `AIR Repair Service`: recovery, diagnostics, common repair commands, and break-glass access.
- Student PCs initiate outbound HTTPS connections to `192.168.11.228`.
- Break-glass shell uses a temporary reverse SSH/tunnel through `192.168.11.228`.
- Break-glass session duration is 60 minutes maximum.

## Server Work

Add database tables for:

- `repair_agent_heartbeats`
- `repair_commands`
- `repair_command_results`
- `repair_shell_sessions`
- `repair_shell_events`

Repair heartbeat data should include:

- student and device
- repair service version
- companion version
- OS version
- active user
- companion service status
- companion process status
- last companion heartbeat age
- extension policy summary
- browser and extension status summary
- latest local repair error
- timestamps

Repair command data should include:

- device
- command type
- payload
- status: `pending`, `running`, `succeeded`, `failed`, `expired`, or `cancelled`
- requesting admin
- started and completed timestamps
- stdout, stderr, result summary
- timeout

Supported repair commands:

- collect diagnostics
- restart companion
- restart browser
- force companion update
- force extension policy refresh
- repair Chrome policy
- check camera devices
- check network/server connectivity
- reboot PC
- start break-glass shell
- stop break-glass shell

## Admin UI

Add a `Remote Repair` section on student device pages with:

- repair service status
- last repair heartbeat
- companion status
- extension/policy status
- recent repair errors
- recent command results
- buttons for common repairs
- diagnostics viewer/download
- break-glass shell controls

Break-glass shell UI:

- requires mentor confirmation or re-authentication
- starts a 60-minute session
- shows a countdown
- shows tunnel/session state
- has an `End session` button
- provides connection instructions or a browser terminal
- logs start/end and command activity where available

## Repair Service Work

Create a separate Windows service:

- service name: `AIRRepairService`
- runs as `LocalSystem`
- auto-starts with Windows
- uses Windows service recovery restart on failure
- installed with the companion
- updated with companion releases
- logs to `C:\ProgramData\AIRRepair\Logs`
- does not modify file or folder permissions

Main loop:

1. Send heartbeat every 15-30 seconds.
2. Poll server for pending repair commands.
3. Validate command belongs to this enrolled device.
4. Execute allowlisted command.
5. Upload result, stdout, stderr, and exit code.
6. Rotate local logs.

## Break-Glass SSH Flow

1. Mentor starts a session in the admin UI.
2. Server creates a `repair_shell_session` valid for 60 minutes.
3. Server generates or assigns ephemeral SSH credentials/token.
4. Repair service receives `start_shell_session`.
5. Repair service starts temporary SSH/tunnel process.
6. Student PC opens an outbound reverse tunnel to `192.168.11.228`.
7. Mentor connects through `192.168.11.228`.
8. On expiry or manual end, server marks the session ending.
9. Repair service kills the tunnel/SSH process and removes ephemeral credentials.
10. Server logs the session as closed.

First version can show connection instructions through `192.168.11.228`. A later version can add a browser terminal.

## Installer And Packaging

Update companion packaging to include:

- companion binary
- repair service binary
- repair service config
- updater helper if needed

Installer behavior:

- install/register companion service
- install/register repair service
- set both services to auto-start
- configure service recovery restart
- write server URL and device identity
- do not call `icacls`
- do not harden permissions
- validate generated ZIP/archive

## Autoupdate

Repair service should support:

- checking latest repair/companion versions
- downloading update artifacts
- verifying checksum
- staging update
- restarting companion after update
- self-update through a helper process
- reporting update result

## Testing Plan

Server tests:

- heartbeat ingestion
- command queue lifecycle
- command authorization
- command result upload
- shell session creation/expiry
- shell session manual close
- admin UI permissions
- audit log creation

Repair service tests:

- starts as Windows service
- sends heartbeat
- runs diagnostics command
- restarts companion
- handles command timeout
- truncates oversized output
- survives server unavailable
- resumes after reboot
- does not change permissions

Break-glass tests:

- starts session
- reverse tunnel connects
- mentor can open shell through `192.168.11.228`
- session expires at 60 minutes
- manual end kills tunnel
- password login does not work
- stale session cannot reconnect
- commands/events are logged where possible

## Deployment Order

1. Server schema/API/UI.
2. Repair service heartbeat only.
3. Installer includes repair service.
4. Diagnostics command.
5. Restart/update repair commands.
6. Chrome/extension policy repair commands.
7. Break-glass reverse SSH.
8. Autoupdate/self-update.
9. Roll out to one test student PC.
10. Roll out to all devices after 24 hours stable.
