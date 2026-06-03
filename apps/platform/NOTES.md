# Platform Notes

Operational notes for the Laravel/Inertia platform app.

## Recent Changes

- 2026-06-03: Chat message body limit raised to 50,000 characters, with frontend cap, migration, and boundary tests.
- 2026-06-03: Custom timer behavior fixed for active non-schedule tasks.
- 2026-06-03: Open violation pushup counts now include one temporary pushup per full minute while the violation remains open.

## Gotchas

- Shared request classes can affect multiple surfaces. `StoreChatMessageRequest` is used for chat and may also affect announcement-like flows.
- For frontend changes, deploy needs `npm run build` on `192.168.11.228`.
- For migrations, deploy needs `php artisan migrate --force` before cache clear.
- Reliability failures should generally become admin alerts/logs, not student violations.
