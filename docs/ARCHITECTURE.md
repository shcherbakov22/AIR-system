# Architecture

## Application Shape

The active product is a Laravel monolith in [apps/platform](C:\Users\user\codex\school-system-redo\apps\platform).

Stack:

- Laravel 12
- Inertia
- Vue 3
- TypeScript
- PostgreSQL
- Caddy in deployed environments

## Important Runtime Areas

### Web Routes

- [apps/platform/routes/web.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\web.php)
- [apps/platform/routes/api.php](C:\Users\user\codex\school-system-redo\apps\platform\routes\api.php)

`web.php` contains:

- public landing page
- auth-protected mentor and student routes
- legacy-compatible `/ss/*.php` upload routes

`api.php` contains:

- modern capture upload endpoints
- edge heartbeat endpoint

### Mentor Surface

Main controllers:

- [apps/platform/app/Http/Controllers/Admin/DashboardController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Admin\DashboardController.php)
- [apps/platform/app/Http/Controllers/Admin/StudentController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Admin\StudentController.php)
- [apps/platform/app/Http/Controllers/Admin/StudentProgressController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Admin\StudentProgressController.php)
- [apps/platform/app/Http/Controllers/Admin/RuleDefinitionController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Admin\RuleDefinitionController.php)
- [apps/platform/app/Http/Controllers/Admin/ViolationController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Admin\ViolationController.php)

Main frontend page:

- [apps/platform/resources/js/Pages/Admin/Dashboard.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Admin\Dashboard.vue)

### Student Surface

Main controllers:

- [apps/platform/app/Http/Controllers/Student/HomeController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Student\HomeController.php)
- [apps/platform/app/Http/Controllers/Student/ScheduleController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Student\ScheduleController.php)
- [apps/platform/app/Http/Controllers/Student/ScheduleRunController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Student\ScheduleRunController.php)
- [apps/platform/app/Http/Controllers/Student/ScheduleRunTaskSessionController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Student\ScheduleRunTaskSessionController.php)
- [apps/platform/app/Http/Controllers/Student/TaskSessionController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Student\TaskSessionController.php)

Main frontend page:

- [apps/platform/resources/js/Pages/Student/Home.vue](C:\Users\user\codex\school-system-redo\apps\platform\resources\js\Pages\Student\Home.vue)

## Capture Flow

There are two upload modes:

### Modern API Upload

- `POST /api/student-monitor-captures/screen`
- `POST /api/student-monitor-captures/camera`

These are handled by:

- [apps/platform/app/Http/Controllers/Api/StudentMonitorCaptureController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\Api\StudentMonitorCaptureController.php)

### Legacy-Compatible Upload

- `GET|POST /ss/upl1.php`
- `POST /ss/uplcam.php`
- `POST /ss/uplscr.php`

These are handled by:

- [apps/platform/app/Http/Controllers/LegacyCaptureController.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Http\Controllers\LegacyCaptureController.php)

## Background-Like Behaviors

The app has a few important periodic or queued behaviors:

- automatic `Observe the time` enforcement
- mentor speech announcement queueing
- daily purge of old monitor captures

Key implementation files:

- [apps/platform/app/Services/AutomaticObserveTheTimeViolationService.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Services\AutomaticObserveTheTimeViolationService.php)
- [apps/platform/app/Services/SpeechAnnouncementService.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Services\SpeechAnnouncementService.php)
- [apps/platform/app/Console/Commands/PurgeStudentMonitorCapturesCommand.php](C:\Users\user\codex\school-system-redo\apps\platform\app\Console\Commands\PurgeStudentMonitorCapturesCommand.php)

