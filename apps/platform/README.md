# Platform App

This is the active Laravel application for AIR System.

## What It Covers

- mentor and student authentication
- student schedule creation and execution
- custom timers and schedule pause/resume
- task templates
- schedule templates
- rules and violations
- mentor live monitor dashboard
- student progress review
- screenshot and camera capture ingestion
- legacy-compatible `/ss/*.php` uploader endpoints

## Key Entry Points

- [routes/web.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\web.php)
- [routes/api.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\api.php)
- [resources/js/Pages/Admin/Dashboard.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin\Dashboard.vue)
- [resources/js/Pages/Student/Home.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student\Home.vue)

## Local Commands

- install PHP dependencies: `composer install`
- install frontend dependencies: `npm install`
- migrate: `php artisan migrate`
- serve: `php artisan serve`
- test: `php artisan test`
- build: `npm run build`

## Wider Project Docs

- [Root README](C:\Users\user\codex\school-system-redo\README.md)
- [Docs Index](C:\Users\user\codex\school-system-redo\docs\README.md)
