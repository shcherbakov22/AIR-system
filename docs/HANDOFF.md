# Handoff

## Short Version

AIR System is now a working mentor/student Laravel app with:

- student schedule creation and execution
- violations and automatic time-observance enforcement
- a live mentor monitor board
- screenshot/camera capture support
- browser speech announcements on the mentor dashboard
- legacy uploader compatibility on the production server

## Where To Start Reading

1. [Product Overview](C:\Users\user\codex\school-system-redo\docs\PRODUCT.md)
2. [Architecture](C:\Users\user\codex\school-system-redo\docs\ARCHITECTURE.md)
3. [Operations Guide](C:\Users\user\codex\school-system-redo\docs\OPERATIONS.md)
4. [Legacy Interop](C:\Users\user\codex\school-system-redo\docs\LEGACY_INTEROP.md)

## Code Areas That Matter Most

- [apps/platform/resources/js/Pages/Admin/Dashboard.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin\Dashboard.vue)
- [apps/platform/resources/js/Pages/Student/Home.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student\Home.vue)
- [apps/platform/app/Http/Controllers/Admin](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Admin)
- [apps/platform/app/Http/Controllers/Student](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Student)
- [apps/platform/app/Services](C:\Users\user\codex\school-system-redo\apps\platform\app\Services)

## Live Environment Facts

- production app server: `192.168.11.228`
- legacy reference server often used during migrations: `192.168.11.66`
- Caddy serves both compatibility HTTP and internal HTTPS use cases

## Current Documentation Intent

This docs suite was rewritten to describe the app that exists now. If product direction changes again, update these docs alongside the code instead of restoring broad planning documents.

