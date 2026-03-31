# Platform App

This is the active Laravel/Inertia application for AIR System.

## Path

- app root:
  - [C:\Users\user\codex\school-system-redo\apps\platform](C:\Users\user\codex\school-system-redo\apps\platform)

## Main surfaces

- mentor dashboard
- student home and schedules
- assignments
- announcements and chat
- rules and violations
- push-up station page
- companion API and update endpoints
- Adminer database entrypoint through the app sidebar

## Important files

- routes:
  - [C:\Users\user\codex\school-system-redo\apps\platform\routes\web.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\web.php)
  - [C:\Users\user\codex\school-system-redo\apps\platform\routes\api.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\api.php)
- mentor dashboard:
  - [C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin\Dashboard.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin\Dashboard.vue)
- student home:
  - [C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student\Home.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student\Home.vue)
- companion policies and violations:
  - [C:\Users\user\codex\school-system-redo\apps\platform\app\Services](C:\Users\user\codex\school-system-redo\apps\platform\app\Services)

## Local commands

```powershell
composer install
npm install
php artisan migrate
php artisan test
npm run build
```

## Live server

- URL:
  - [https://192.168.11.228](https://192.168.11.228)
- live root:
  - `/var/www/school-system-redo/platform`

## Deployment note

The live host has a stale nested copy under `/var/www/school-system-redo/platform/apps/platform/...`. Build and file sync work only when changes land in the real root:

- `/var/www/school-system-redo/platform`

## More context

- repo README:
  - [C:\Users\user\codex\school-system-redo\README.md](C:\Users\user\codex\school-system-redo\README.md)
- consolidated handoff:
  - [C:\Users\user\codex\AIR_HANDOFF_2026-03-31.md](C:\Users\user\codex\AIR_HANDOFF_2026-03-31.md)
