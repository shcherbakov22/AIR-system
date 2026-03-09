# Platform App

This is the main Laravel application for the school system rewrite.

Current scope:

- admin and student auth
- Russian-localized UI shell
- admin student management
- task templates
- task assignments
- student-owned schedules
- schedule execution with ordered block start/stop
- pause/resume with ad hoc own timer
- rules, violations, and penalty ledger
- student penalties page
- import dashboard scaffolding
- edge-client heartbeat API

Current product direction:

- schedule-first student workflow
- no parent-facing product
- no Ivan/browser-monitoring rebuild
- no hardware/pushup execution rebuild for now

Primary handoff doc:

- `..\..\docs\HANDOFF.md`

Local development:

1. Ensure the repo-local PostgreSQL cluster exists and is running:
   `powershell -ExecutionPolicy Bypass -File ..\..\tools\scripts\dev\postgres.ps1 ensure`
2. Run migrations:
   `C:\Users\user\tools\php-8.5.1\php.exe artisan migrate`
3. Start the app:
   `C:\Users\user\tools\php-8.5.1\php.exe artisan serve`

The application defaults to:

- database: PostgreSQL on `127.0.0.1:55432`
- database name: `school_system_redo`
- database user: `school_system`

These credentials are for local development only and are expected to be replaced in deployed environments.

Useful local commands:

- tests:
  `C:\Users\user\tools\php-8.5.1\php.exe artisan test`
- build:
  `npm run build`
- serve:
  `C:\Users\user\tools\php-8.5.1\php.exe artisan serve`

Last verified in this workspace:

- `php artisan test` passed: `105` tests, `1157` assertions
- `npm run build` passed
