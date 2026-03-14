# Development Guide

## Local App

The active app lives in [apps/platform](C:\Users\user\codex\school-system-redo\apps\platform).

Run all app commands from that directory unless noted otherwise.

## Basic Setup

1. Install PHP dependencies:
   `composer install`
2. Install frontend dependencies:
   `npm install`
3. Configure `.env`
4. Run migrations:
   `php artisan migrate`
5. Start the app:
   `php artisan serve`

## Useful Commands

- tests:
  `php artisan test`
- frontend build:
  `npm run build`
- clear Laravel caches:
  `php artisan optimize:clear`

## Testing Guidance

The app relies mostly on feature tests under:

- [apps/platform/tests/Feature](C:\Users\user\codex\school-system-redo\apps\platform\tests\Feature)

When changing major product flows, the most important areas to re-run are:

- mentor dashboard routing and payloads
- student schedule management
- student schedule run flow
- task session flow
- capture upload compatibility

## Frontend Notes

Important frontend surfaces:

- [apps/platform/resources/js/Pages/Admin](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin)
- [apps/platform/resources/js/Pages/Student](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student)
- [apps/platform/resources/js/Layouts](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Layouts)

Current UI priorities:

- dense monitor layout for mentors
- compact runtime controls for students
- minimal wasted padding

## Documentation Rule

Keep docs aligned with the live product. If a feature is removed or deprioritized, update docs in the same change instead of letting stale planning linger.

