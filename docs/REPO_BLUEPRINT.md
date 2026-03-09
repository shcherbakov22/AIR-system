# Repo Blueprint

## 1. Repo Shape

Use a monorepo with one primary Laravel application and two sidecar clients.

- Main application: web platform and API
- Browser extension: monitoring client
- Hardware bridge: consequence device worker

The Laravel app is the system of record. The extension and bridge are clients of that app, not peers with independent business logic.

## 2. Recommended Top-Level Layout

```text
school-system-redo/
  apps/
    platform/
      app/
      bootstrap/
      config/
      database/
        factories/
        migrations/
        seeders/
      public/
      resources/
        css/
        js/
          app/
            components/
            composables/
            layouts/
            pages/
            modules/
              auth/
              dashboard/
              student-settings/
              students/
              tasks/
              schedules/
              sessions/
              rules/
              violations/
              penalties/
              monitoring/
              goals/
              admin/
            lib/
            types/
        views/
      routes/
        web.php
        api.php
        console.php
      storage/
      tests/
        Unit/
        Feature/
        Integration/
        Browser/
      artisan
      composer.json
      package.json
      phpunit.xml
      vite.config.ts
      tsconfig.json
    extension/
      src/
        background/
        content/
        popup/
        options/
        shared/
      assets/
      tests/
      manifest.config.ts
      package.json
      tsconfig.json
      vite.config.ts
    hardware-bridge/
      src/
        bridge/
        adapters/
        api_client/
        simulator/
      tests/
      pyproject.toml
      README.md
  packages/
    api-contracts/
      openapi/
      jsonschema/
    shared-ui/
      src/
    shared-config/
      eslint/
      prettier/
      typescript/
  infra/
    caddy/
      Caddyfile.dev
      Caddyfile.prod.example
      snippets/
    docker/
      compose.yaml
      compose.override.example.yaml
      php/
      node/
    systemd/
      school-platform-queue.service
      school-platform-scheduler.service
      school-hardware-bridge.service
    db/
      import/
      snapshots/
  tools/
    scripts/
      import/
      dev/
      release/
    sql/
    fixtures/
  docs/
    adr/
    diagrams/
    imports/
    runbooks/
  .editorconfig
  .gitignore
  README.md
```

## 3. Why This Layout

### `apps/platform`

This is the actual product. It contains:

- the main web UI
- the internal admin UI
- the API for the browser extension
- the API for the hardware bridge
- all migrations, policies, importers, and domain services

### `apps/extension`

Keep the extension separate so:

- it can use its own build tooling
- permissions stay explicit
- browser-specific code does not leak into the web app

### `apps/hardware-bridge`

Keep the hardware bridge separate because:

- serial/device concerns are operationally different
- it may run on a different machine than the web app
- it needs simulator and device-adapter code that should not live in the Laravel app

### `packages/api-contracts`

This should hold:

- OpenAPI specs
- JSON schemas for monitoring and consequence payloads
- generated types or reference payload examples later

This keeps the web app, extension, and hardware bridge aligned.

### `packages/shared-ui`

Optional at first, but useful once:

- platform and extension begin sharing simple UI widgets
- design tokens need a single home

Do not overbuild this early. It can start as a placeholder.

## 4. Main Application Internal Structure

Inside `apps/platform/app`, use feature-first organization after the Laravel framework folders where helpful.

Recommended additions:

```text
app/
  Actions/
  Data/
  Domain/
    Auth/
    StudentSettings/
    Students/
    Tasks/
    Schedules/
    Sessions/
    Rules/
    Violations/
    Penalties/
    Monitoring/
    Consequences/
    Goals/
    Reporting/
  Enums/
  Events/
  Http/
    Controllers/
      Web/
      Api/
      Internal/
  Jobs/
  Listeners/
  Models/
  Notifications/
  Policies/
  Projectors/
  Providers/
  Queries/
  Rules/
  Services/
  Support/
  ValueObjects/
```

Guidelines:

- `Models/` stay thin
- business logic goes in `Domain/`, `Actions/`, `Services/`
- reporting queries go in `Queries/`
- request/response DTOs go in `Data/`
- policy and role checks live in `Policies/`

## 5. Frontend Structure Inside `apps/platform/resources/js`

```text
resources/js/app/
  components/
    ui/
    charts/
    tables/
    forms/
  composables/
  layouts/
  pages/
    auth/
    admin/
    student/
    admin/
  modules/
    auth/
    dashboard/
    student-settings/
    students/
    tasks/
    schedules/
    sessions/
    rules/
    violations/
    penalties/
    monitoring/
    goals/
  lib/
    http/
    date/
    penalties/
    auth/
    validation/
  types/
```

Rules:

- `pages/` only assemble screens
- `modules/` hold feature-specific UI and state logic
- `components/ui/` is reusable generic UI only
- `lib/` contains framework-agnostic helpers

## 6. Package Choices

### Backend Composer Packages

Required:

- `laravel/framework`
- `laravel/sanctum`
- `laravel/horizon`
- `laravel/pennant`
- `spatie/laravel-permission`
- `spatie/laravel-data`
- `spatie/laravel-health`
- `sentry/sentry-laravel`
- `league/csv`

Development:

- `laravel/telescope`
- `pestphp/pest`
- `pestphp/pest-plugin-laravel`
- `nunomaduro/larastan`
- `phpstan/phpstan`
- `friendsofphp/php-cs-fixer`

Packages I would avoid initially:

- event-sourcing packages
- heavy admin generators
- multitenancy packages
- GraphQL

The system needs clarity more than framework cleverness.

### Frontend Packages For `apps/platform`

Required:

- `vue`
- `@inertiajs/vue3`
- `@inertiajs/progress`
- `typescript`
- `vite`
- `tailwindcss`
- `@tailwindcss/forms`
- `@vueuse/core`
- `@tanstack/vue-query`
- `zod`
- `dayjs`
- `clsx`

Testing / quality:

- `vitest`
- `@testing-library/vue`
- `playwright`
- `eslint`
- `eslint-plugin-vue`
- `typescript-eslint`
- `prettier`

### Browser Extension Packages

Required:

- `typescript`
- `vite`
- `@crxjs/vite-plugin`
- `zod`
- `webextension-polyfill`
- `vitest`

### Hardware Bridge Packages

Use Python 3.12 with `uv`.

Packages:

- `fastapi`
- `uvicorn`
- `pydantic`
- `pyserial`
- `httpx`
- `tenacity`
- `pytest`

This is the one deliberate non-PHP exception. It is justified because serial/device integration and simulator workflows are materially easier and safer in Python.

## 7. API Contract Strategy

The repo should treat external contracts as first-class.

Store in `packages/api-contracts/openapi`:

- `monitoring.yaml`
- `consequences.yaml`
- `auth-devices.yaml`

Generated types later:

- TypeScript client types for the extension
- Python client models for the bridge

## 8. Domain Modules To Implement In The Laravel App

Initial module directories:

- `Domain/Auth`
- `Domain/StudentSettings`
- `Domain/Students`
- `Domain/Tasks`
- `Domain/Schedules`
- `Domain/Sessions`
- `Domain/Rules`
- `Domain/Violations`
- `Domain/Penalties`
- `Domain/Monitoring`
- `Domain/Consequences`
- `Domain/Goals`
- `Domain/Reporting`

Phase 1 should implement only:

- Auth
- StudentSettings
- Students
- Tasks
- Schedules
- Sessions
- Rules
- Violations
- Penalties
- Dashboard reporting

Monitoring, consequences, and goals should be scaffolded in the design but not fully built first.

## 9. Routes Plan

### Web

- `routes/web.php`
  Main authenticated UI

### API

- `routes/api.php`
  Token-authenticated external APIs and JSON endpoints

### Console

- `routes/console.php`
  scheduler tasks and operational commands

Do not split dozens of tiny route files early. Keep route registration simple until modules stabilize.

## 10. Testing Layout

### `tests/Unit`

- value objects
- small domain rules
- formatting and parsing helpers

### `tests/Feature`

- auth flows
- schedule management flows
- schedule runtime flows
- ad hoc timer flows
- rule/violation/penalty flows

### `tests/Integration`

- imports
- queue jobs
- cross-module interactions
- monitoring policy decisions

### `tests/Browser`

- admin dashboard flows
- student schedule and penalty flows

## 11. Migrations And Seeds

Inside `apps/platform/database`:

- `migrations/`
  New authoritative schema only
- `seeders/`
  role seeder, demo data seeder, development policy seeder
- `factories/`
  factories for core entities

Do not put legacy import SQL in Laravel migrations.

Legacy import artifacts belong in:

- `infra/db/import/`
- `tools/scripts/import/`
- `docs/imports/`

## 12. Importer Placement

Importer code belongs in:

```text
apps/platform/app/Actions/Imports/
apps/platform/app/Data/Imports/
tools/scripts/import/
docs/imports/
```

Suggested importer classes:

- `ImportUsersAction`
- `ImportStudentSettingsAction`
- `ImportTaskTemplatesAction`
- `ImportAssignmentsAction`
- `ImportSchedulesAction`
- `ImportTaskSessionsAction`
- `ImportRulesAction`
- `ImportViolationsAction`
- `ImportPenaltyLedgerAction`

## 13. Coding Conventions

- PHP: strict types everywhere
- IDs: UUIDs externally, bigint or ULID internally based on Laravel preference
- Penalty amounts: integer units only
- Times: UTC in database, user timezone in UI
- Soft deletes only where product requirements justify them
- All state transitions expressed explicitly in code

## 14. Release / Branching Convention

- `main`
- `develop` optional only if the team wants it
- feature branches: `feature/<area>-<slug>`
- import branches: `import/<domain>`
- release tags: `v0.x.y`

Keep it simple. Do not recreate the chaos of the old tree.

## 15. Recommended Initial Non-Code Files

These should exist before implementation begins:

- `README.md`
- `docs/REIMPLEMENTATION_OUTLINE.md`
- `docs/REPO_BLUEPRINT.md`
- `docs/ENVIRONMENT_LAYOUT.md`
- `docs/PHASE1_BACKLOG.md`
- `docs/adr/ADR-001-ACTOR-MODEL.md`
- `docs/adr/ADR-002-SYSTEM-SHAPE.md`
- `docs/adr/ADR-003-DATABASE-STRATEGY.md`
- `docs/adr/ADR-004-PENALTY-MODEL.md`
- `docs/adr/ADR-005-LEGACY-PARENTS-MAPPING.md`
- `docs/adr/ADR-006-IMPLEMENTATION-ORDER.md`
- `docs/adr/ADR-007-SCHEMA-FIRST-IMPORT-LATER.md`
- `docs/imports/IMPORT_STRATEGY.md`
- `docs/imports/NEW_SCHEMA_NOTES.md`
- `docs/runbooks/LOCAL_DEVELOPMENT.md`
- `docs/runbooks/DEPLOYMENT.md`

## 16. Recommended First Code To Land Later

When implementation starts, the first commit should be:

- repo scaffolding
- Laravel app bootstrap
- Caddy local config
- Docker compose
- lint/test configuration

The second commit should be:

- auth
- roles
- student and student-settings core schema

Do not start by rebuilding monitoring or hardware code.
