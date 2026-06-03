# Issues And Gotchas

Persistent bugs, traps, fragile areas, and lessons learned. Keep this current when a fix exposes a new failure mode or invalid assumption.

## Windows Permission Hardening Broke Companion Repair

Status: watching

Details:
- Previous installer/repair attempts changed permissions too aggressively.
- This caused access denied failures for companion files, logs, enrollment, and helper executables.
- The user explicitly does not want future repair service work to touch permissions.

Current guidance:
- Do not add custom ACL lockdown, deny rules, or broad `icacls` changes.
- Install files normally and rely on monitoring/repair rather than hard prevention.
- Permission repair should be an explicit manual action only when requested.

## Companion Archive Must Be A Valid Windows Zip

Status: watching

Details:
- Windows rejected earlier companion archives as invalid format.
- Tar-style archives or malformed zips are not acceptable for the Windows install/update path.

Current guidance:
- Build/package companion artifacts as valid `.zip` files.
- Verify archive integrity after packaging.
- Prefer testing extraction on a Windows-compatible unzip path before release.

## Browser Extension Enterprise Policy Can Conflict Or Be Locally Overridden

Status: watching

Details:
- Chrome policy showed conflicts between local and cloud-managed values.
- Invalid extension IDs such as `[BLOCKED]...` break `ExtensionSettings` schema validation.
- Enterprise Core enrollment state can look managed in one place while `chrome://policy` rejects force install behavior.

Current guidance:
- Check both Chrome Admin policy and `chrome://policy`.
- Avoid writing local force-install policy if cloud policy is expected to own it.
- Do not add unknown properties to extension policy without checking Chrome policy docs.
- Extension ID must match the signed CRX key-derived ID.

## Website/Companion Reliability Needs Active Health Monitoring

Status: open

Details:
- Many failures only become visible after students report symptoms.
- Moving parts include Laravel scheduler/queue, companion, extension, Chrome policy, screenshots, camera, pushup station, uploads, and device commands.

Current guidance:
- Add central health/status dashboard before adding more fragile behavior.
- Require heartbeats from companion, repair service, extension, and hardware devices.
- Treat stale/broken subsystem detection as admin reliability alerts, not student violations by default.

## Remote Repair Service Should Not Create Violations

Status: planned

Details:
- Repair/diagnostic bugs should not punish students.
- The repair service is for restoring system health and should only log/admin-alert.

Current guidance:
- Repair commands and break-glass shell actions should create audit logs.
- Student-facing violations remain owned by the normal enforcement/overseer logic.

## AI Overseer Messages Must Match Applied Action

Status: watching

Details:
- The model can return student-facing wording such as "violation removed" even when the normalized decision is escalated or the server refuses to auto-apply the action.
- Saving the assistant message before applying side effects can produce false feedback for students.

Current guidance:
- Generate final decision messages after server-side actions run.
- For `mentor_review`, preserve only messages that clearly mention mentor/review/escalation/unavailability; otherwise replace with explicit mentor-review wording.
- Do not trust model text as the source of truth for whether a skip/removal actually happened.

## Chat Body Storage And Validation Must Match

Status: resolved

Details:
- Validator was raised to 50,000 characters.
- Storage needed to be widened as well so the limit is not only a frontend/backend validation number.

Current guidance:
- When raising request limits, verify the database column, frontend controls, tests, and deployed schema.
