<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\RuleDefinitionController as AdminRuleDefinitionController;
use App\Http\Controllers\Admin\ScheduleTemplateController as AdminScheduleTemplateController;
use App\Http\Controllers\Admin\SpeechAnnouncementController as AdminSpeechAnnouncementController;
use App\Http\Controllers\Admin\StudentMonitorCaptureController as AdminStudentMonitorCaptureController;
use App\Http\Controllers\Admin\StudentProgressController as AdminStudentProgressController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\TaskTemplateController as AdminTaskTemplateController;
use App\Http\Controllers\Admin\ViolationController as AdminViolationController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyCaptureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\ChatController as StudentChatController;
use App\Http\Controllers\Student\AnnouncementController as StudentAnnouncementController;
use App\Http\Controllers\Student\HomeController as StudentHomeController;
use App\Http\Controllers\Student\RuleController as StudentRuleController;
use App\Http\Controllers\Student\ScheduleRunController as StudentScheduleRunController;
use App\Http\Controllers\Student\ScheduleRunTaskSessionController as StudentScheduleRunTaskSessionController;
use App\Http\Controllers\Student\ScheduleController as StudentScheduleController;
use App\Http\Controllers\Student\TaskSessionController as StudentTaskSessionController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');

Route::prefix('ss')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        VerifyCsrfToken::class,
    ])
    ->group(function () {
    Route::match(['get', 'post'], '/upl1.php', [LegacyCaptureController::class, 'check']);
    Route::post('/uplcam.php', [LegacyCaptureController::class, 'storeCamera']);
    Route::post('/uplscr.php', [LegacyCaptureController::class, 'storeScreen']);
    });

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/chat-messages/{chatMessage}/attachment', [ChatAttachmentController::class, 'show'])->name('chat-messages.attachment.show');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/{student}', [AdminAnnouncementController::class, 'show'])->name('announcements.show');
        Route::post('/announcements/{student}', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/chats', [AdminChatController::class, 'index'])->name('chats.index');
        Route::get('/chats/{student}', [AdminChatController::class, 'show'])->name('chats.show');
        Route::post('/chats/{student}', [AdminChatController::class, 'store'])->name('chats.store');
        Route::get('/rule-definitions', [AdminRuleDefinitionController::class, 'index'])->name('rule-definitions.index');
        Route::get('/rule-definitions/create', [AdminRuleDefinitionController::class, 'create'])->name('rule-definitions.create');
        Route::post('/rule-definitions', [AdminRuleDefinitionController::class, 'store'])->name('rule-definitions.store');
        Route::get('/rule-definitions/{ruleDefinition}/edit', [AdminRuleDefinitionController::class, 'edit'])->name('rule-definitions.edit');
        Route::put('/rule-definitions/{ruleDefinition}', [AdminRuleDefinitionController::class, 'update'])->name('rule-definitions.update');
        Route::delete('/rule-definitions/{ruleDefinition}', [AdminRuleDefinitionController::class, 'destroy'])->name('rule-definitions.destroy');
        Route::get('/violations', [AdminViolationController::class, 'index'])->name('violations.index');
        Route::get('/violations/create', [AdminViolationController::class, 'create'])->name('violations.create');
        Route::post('/violations', [AdminViolationController::class, 'store'])->name('violations.store');
        Route::get('/violations/{violation}', [AdminViolationController::class, 'show'])->name('violations.show');
        Route::patch('/violations/{violation}/resolve', [AdminViolationController::class, 'resolve'])->name('violations.resolve');
        Route::delete('/violations/{violation}', [AdminViolationController::class, 'destroy'])->name('violations.destroy');
        Route::get('/schedule-templates', [AdminScheduleTemplateController::class, 'index'])->name('schedule-templates.index');
        Route::get('/schedule-templates/create', [AdminScheduleTemplateController::class, 'create'])->name('schedule-templates.create');
        Route::post('/schedule-templates', [AdminScheduleTemplateController::class, 'store'])->name('schedule-templates.store');
        Route::get('/schedule-templates/{scheduleTemplate}/edit', [AdminScheduleTemplateController::class, 'edit'])->name('schedule-templates.edit');
        Route::put('/schedule-templates/{scheduleTemplate}', [AdminScheduleTemplateController::class, 'update'])->name('schedule-templates.update');
        Route::delete('/schedule-templates/{scheduleTemplate}', [AdminScheduleTemplateController::class, 'destroy'])->name('schedule-templates.destroy');
        Route::delete('/task-templates/{taskTemplate}', [AdminTaskTemplateController::class, 'destroy'])->name('task-templates.destroy');
        Route::get('/task-templates', [AdminTaskTemplateController::class, 'index'])->name('task-templates.index');
        Route::get('/task-templates/create', [AdminTaskTemplateController::class, 'create'])->name('task-templates.create');
        Route::post('/task-templates', [AdminTaskTemplateController::class, 'store'])->name('task-templates.store');
        Route::get('/task-templates/{taskTemplate}/edit', [AdminTaskTemplateController::class, 'edit'])->name('task-templates.edit');
        Route::put('/task-templates/{taskTemplate}', [AdminTaskTemplateController::class, 'update'])->name('task-templates.update');
        Route::get('/students/create', [AdminStudentController::class, 'create'])->name('students.create');
        Route::post('/students', [AdminStudentController::class, 'store'])->name('students.store');
        Route::get('/students/{student}/progress', [AdminStudentProgressController::class, 'show'])->name('students.progress');
        Route::get('/students/{student}/edit', [AdminStudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [AdminStudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [AdminStudentController::class, 'destroy'])->name('students.destroy');
        Route::patch('/students/{student}/password', [AdminStudentController::class, 'updatePassword'])->name('students.password.update');
        Route::get('/students', [AdminStudentController::class, 'index'])->name('students.index');
        Route::get('/student-monitor-captures/{studentMonitorCapture}/day-history', [AdminStudentMonitorCaptureController::class, 'dayHistory'])->name('student-monitor-captures.day-history');
        Route::get('/student-monitor-captures/{studentMonitorCapture}', [AdminStudentMonitorCaptureController::class, 'show'])->name('student-monitor-captures.show');
        Route::get('/speech-announcements/history', [AdminSpeechAnnouncementController::class, 'history'])->name('speech-announcements.history');
        Route::get('/speech-announcements/next', [AdminSpeechAnnouncementController::class, 'next'])->name('speech-announcements.next');
        Route::patch('/speech-announcements/state', [AdminSpeechAnnouncementController::class, 'updateState'])->name('speech-announcements.state.update');
    });

    Route::prefix('student')->name('student.')->middleware('student')->group(function () {
        Route::get('/home', StudentHomeController::class)->name('home');
        Route::get('/announcements', [StudentAnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('/chat', [StudentChatController::class, 'show'])->name('chat.show');
        Route::post('/chat', [StudentChatController::class, 'store'])->name('chat.store');
        Route::get('/rules', StudentRuleController::class)->name('rules.index');
        Route::get('/schedules', [StudentScheduleController::class, 'index'])->name('schedules.index');
        Route::get('/schedules/create', [StudentScheduleController::class, 'create'])->name('schedules.create');
        Route::post('/schedules', [StudentScheduleController::class, 'store'])->name('schedules.store');
        Route::get('/schedules/{scheduleTemplate}/edit', [StudentScheduleController::class, 'edit'])->name('schedules.edit');
        Route::put('/schedules/{scheduleTemplate}', [StudentScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('/schedules/{scheduleTemplate}', [StudentScheduleController::class, 'destroy'])->name('schedules.destroy');
        Route::post('/schedules/{scheduleTemplate}/runs', [StudentScheduleRunController::class, 'store'])->name('schedule-runs.store');
        Route::post('/schedule-runs/{scheduleRun}/pause', [StudentScheduleRunController::class, 'pause'])->name('schedule-runs.pause');
        Route::post('/schedule-runs/{scheduleRun}/resume', [StudentScheduleRunController::class, 'resume'])->name('schedule-runs.resume');
        Route::post('/schedule-runs/{scheduleRun}/complete', [StudentScheduleRunController::class, 'complete'])->name('schedule-runs.complete');
        Route::post('/schedule-runs/{scheduleRun}/blocks/{scheduleRunBlock}/start', [StudentScheduleRunTaskSessionController::class, 'store'])->name('schedule-run-blocks.start');
        Route::patch('/task-sessions/{taskSession}/stop', [StudentTaskSessionController::class, 'stop'])->name('task-sessions.stop');
    });
});

require __DIR__.'/auth.php';
