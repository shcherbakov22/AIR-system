# AIR System

AIR System is the active Laravel/Inertia platform for the school workflow, monitoring, and companion-device stack.

## Repositories

- Platform repo:
  - [C:\Users\user\codex\school-system-redo](C:\Users\user\codex\school-system-redo)
- Companion repo:
  - [C:\Users\user\codex\air-companion](C:\Users\user\codex\air-companion)
- Consolidated handoff:
  - [C:\Users\user\codex\AIR_HANDOFF_2026-03-31.md](C:\Users\user\codex\AIR_HANDOFF_2026-03-31.md)

## Current product scope

- mentor and student authentication
- student schedules and schedule runs
- manual tasks, custom timers, unfinished tasks, and sleeping fallback tasks
- automatic `Observe the time` violations
- mentor dashboard with live task/schedule visibility
- chat, announcements, violations, assignments, and push-up station flow
- Windows companion integration for screenshots, camera captures, apps, remote control, and updates
- Adminer-based database access from the sidebar `Database` link

## Live environment

- app URL:
  - [https://192.168.11.228](https://192.168.11.228)
- live platform root on server:
  - `/var/www/school-system-redo/platform`
- database viewer:
  - [https://192.168.11.228/adminer.php](https://192.168.11.228/adminer.php)
- important deploy note:
  - the real live source root is `/var/www/school-system-redo/platform`
  - there is also a stale nested `/var/www/school-system-redo/platform/apps/platform/...` tree from earlier bad syncs
  - deploy to the real root, not the nested copy

## Local platform app

- app root:
  - [C:\Users\user\codex\school-system-redo\apps\platform](C:\Users\user\codex\school-system-redo\apps\platform)
- key entry points:
  - [C:\Users\user\codex\school-system-redo\apps\platform\routes\web.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\web.php)
  - [C:\Users\user\codex\school-system-redo\apps\platform\routes\api.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\api.php)
  - [C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin\Dashboard.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin\Dashboard.vue)
  - [C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student\Home.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student\Home.vue)

## Local development

- install PHP dependencies:
  - `composer install`
- install frontend dependencies:
  - `npm install`
- migrate:
  - `php artisan migrate`
- run tests:
  - `php artisan test`
- build frontend:
  - `npm run build`

## Deployment pattern currently in use

There is no clean Git checkout deployment on `228`. The current workflow is direct file sync plus on-server rebuild/cache clear.

Typical platform deploy steps:

```powershell
sshpass -p 0 scp -O -o PreferredAuthentications=password -o PubkeyAuthentication=no -o StrictHostKeyChecking=no <local-file> root@192.168.11.228:/var/www/school-system-redo/platform/<target-path>
sshpass -p 0 ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no -o StrictHostKeyChecking=no root@192.168.11.228 "cd /var/www/school-system-redo/platform && npm run build && php artisan optimize:clear"
```

Notes:

- `php artisan test` is not installed/available on the live box
- verify changed files landed in the real live root
- after UI changes, do a real browser check against the live site

## Documentation

- platform app README:
  - [C:\Users\user\codex\school-system-redo\apps\platform\README.md](C:\Users\user\codex\school-system-redo\apps\platform\README.md)
- docs index:
  - [C:\Users\user\codex\school-system-redo\docs\README.md](C:\Users\user\codex\school-system-redo\docs\README.md)

## Neighboring inputs

- `C:\Users\user\codex\school-system-extract`
- `C:\Users\user\codex\legacy-db-analysis`
