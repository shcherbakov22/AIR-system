# Work Log

Chronological operational notes for meaningful changes. This complements Git history by recording why work happened, what was verified, and deployment gotchas.

## 2026-06-03 - AI Skip Requests Restored

Changed:
- Restored schedule block skip targets in the student AI chat sidebar.
- Restored existing `skip_task` conversations in the AI chat conversation list.
- Changed final AI overseer messages to be written after side effects are applied, so student-facing text matches the actual final status/action.

Verified:
- `php artisan test tests/Feature/AiOverseerDecisionTest.php` passed.
- `npm run build` passed.

Notes:
- If an AI recommends removing a violation but the system cannot auto-apply it, the student now sees mentor-review wording instead of a false removal message.

## 2026-06-03 - Private Local Notes Added

Changed:
- Added an untracked private notes file at `docs/PRIVATE_LOCAL_NOTES.md` for local credentials, passwords, and environment-specific operational details.
- Added `docs/PRIVATE_LOCAL_NOTES.md` to `.gitignore`.
- Updated `AGENTS.md` so future agents must read the private notes file when present and must not commit or quote its secrets.

Verified:
- Documentation-only change; no runtime tests needed.

Notes:
- Actual secrets stay out of Git. Keep public operational lessons in this work log and `docs/ISSUES_AND_GOTCHAS.md`.

## 2026-06-03 - Work Log System Added

Changed:
- Added project-wide work log and issues/gotchas documents.
- Added folder notes for platform, companion, extension, and hardware bridge.
- Added `AGENTS.md` instructions requiring future agents to read and maintain these logs.

Verified:
- Documentation-only change; no runtime tests needed.

Notes:
- Keep entries concise. This log should capture operational memory, not duplicate every diff.

## 2026-06-03 - Repair Service Plan Documented

Changed:
- Added `docs/REPAIR_SERVICE_PLAN.md`.
- Planned separate `AIRRepairService` installed with the companion, with no custom permission changes.
- Planned 60-minute break-glass reverse SSH sessions through `192.168.11.228`.

Verified:
- Documentation committed and pushed as `7881ed6`.

Notes:
- Repair service should use monitoring and remote recovery rather than permission locking.

## 2026-06-03 - Chat Message Limit Raised

Changed:
- Chat message body limit raised from 5,000 to 50,000 characters.
- Chat textarea cap raised to 50,000.
- Added migration to widen chat body storage.
- Added boundary tests for 50,000 accepted and 50,001 rejected.

Verified:
- `php artisan test tests/Feature/ChatFlowTest.php` passed.
- `npm run build` passed.
- Deployed to `192.168.11.228`.

Notes:
- The shared chat request may also affect announcement body length.

## 2026-06-03 - Custom Timer Fixed During Active Non-Schedule Tasks

Changed:
- Custom timer can be opened while a non-schedule task is active.
- Active non-schedule task is marked unfinished before starting the custom timer.
- Schedule tasks still use the schedule pause route.

Verified:
- Focused student task/session tests passed.
- `npm run build` passed.
- Deployed to `192.168.11.228`.

Notes:
- Keep schedule interruption behavior separate from ad-hoc/custom task behavior.

## 2026-06-03 - Open Violation Pushup Counts Made Time-Based

Changed:
- Open violations now add one temporary pushup per full minute while open.
- Server payloads and student home display use effective penalty count.

Verified:
- Deployed to `192.168.11.228`.

Notes:
- The elapsed count is temporary for that specific open violation.
