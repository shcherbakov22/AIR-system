# Companion Notes

Operational notes for the Windows companion app.

## Recent Changes And Context

- Companion packaging must produce a valid Windows `.zip`; invalid archives caused Windows install/update failures.
- Local companion logs should record app closing reasons, update checks, extension status, and repair attempts.
- The planned `AIRRepairService` should be separate from the companion, installed alongside it, and able to recover/update the companion.

## Gotchas

- Do not add Windows permission hardening or broad `icacls` changes unless explicitly requested.
- The user explicitly prefers not touching permissions at all for the repair service path.
- Companion version shown on the server must come from the running executable/runtime report, not stale config.
- App/browser closing behavior needs clear local logs explaining why a process was closed.
