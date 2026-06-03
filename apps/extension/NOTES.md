# Extension Notes

Operational notes for the browser accountability/attention extension.

## Recent Changes And Context

- Extension install and update behavior depends on Chrome policy, CRX signing identity, update XML, and Chrome Enterprise Core state.
- File URL handling is fragile and affected by Chrome policy settings and extension permissions.
- Approved per-task requests must apply to the task that was active when the request was created.

## Gotchas

- Invalid extension IDs such as `[BLOCKED]...` in `ExtensionSettings` break policy schema validation.
- Local policy can conflict with cloud policy and override expected force-install behavior.
- Enterprise-managed UI indications are not enough; check actual `chrome://policy` values and warnings.
- Extension should report unconfigured/disabled states after it had been installed before, but first-time absence should not automatically punish students.
