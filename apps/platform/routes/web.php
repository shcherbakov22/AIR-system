<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BrowserAccountabilityController as AdminBrowserAccountabilityController;
use App\Http\Controllers\Admin\ExtensionController as AdminExtensionController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AssignmentController as AdminAssignmentController;
use App\Http\Controllers\Admin\AdminerController as AdminAdminerController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\PushUpSessionController as AdminPushUpSessionController;
use App\Http\Controllers\Admin\RuleDefinitionController as AdminRuleDefinitionController;
use App\Http\Controllers\Admin\ScheduleTemplateController as AdminScheduleTemplateController;
use App\Http\Controllers\Admin\SpeechAnnouncementController as AdminSpeechAnnouncementController;
use App\Http\Controllers\Admin\StudentMonitorCaptureController as AdminStudentMonitorCaptureController;
use App\Http\Controllers\Admin\StudentProgressController as AdminStudentProgressController;
use App\Http\Controllers\Admin\HiddenScheduleBlockTimeController as AdminHiddenScheduleBlockTimeController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\StudentDeviceController as AdminStudentDeviceController;
use App\Http\Controllers\Admin\StudentAppPolicyController as AdminStudentAppPolicyController;
use App\Http\Controllers\Admin\TaskSessionController as AdminTaskSessionController;
use App\Http\Controllers\Admin\TaskTemplateController as AdminTaskTemplateController;
use App\Http\Controllers\Admin\ViolationController as AdminViolationController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\CompanionBrowserLoginController;
use App\Http\Controllers\CompanionRootCertificateController;
use App\Http\Controllers\Api\CompanionUpdateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyCaptureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushUpStationController;
use App\Http\Controllers\Student\CompanionEnrollmentController as StudentCompanionEnrollmentController;
use App\Http\Controllers\Student\ChatController as StudentChatController;
use App\Http\Controllers\Student\AnnouncementController as StudentAnnouncementController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\AttentionEventController as StudentAttentionEventController;
use App\Http\Controllers\Student\HomeController as StudentHomeController;
use App\Http\Controllers\Student\PushUpSessionController as StudentPushUpSessionController;
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

$companionRootCertificateRoute = trim(
    trim((string) config('services.local_tls.root_ca_route', '/companion/root-ca.crt')),
    '/',
);

if ($companionRootCertificateRoute === '') {
    $companionRootCertificateRoute = 'companion/root-ca.crt';
}

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');

Route::get($companionRootCertificateRoute, CompanionRootCertificateController::class)
    ->name('companion.root-ca');
Route::get('/companion/downloads/windows/installer', [CompanionUpdateController::class, 'installerBundle'])
    ->name('companion.installer.download');
Route::get('/companion/downloads/chrome/extension', [CompanionUpdateController::class, 'browserExtensionBundle'])
    ->name('companion.browser-extension.download');
Route::get('/companion/browser-login/{token}', CompanionBrowserLoginController::class)
    ->name('companion.browser-login.consume');

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
    Route::get('/push-up-station', [PushUpStationController::class, 'show'])->name('push-up-station.show');
    Route::post('/push-up-station/heartbeat', [PushUpStationController::class, 'heartbeat'])->name('push-up-station.heartbeat');
    Route::post('/push-up-station/claim-next', [PushUpStationController::class, 'claimNext'])->name('push-up-station.claim-next');
    Route::patch('/push-up-station/sessions/{pushUpSession}/start', [PushUpStationController::class, 'start'])->name('push-up-station.sessions.start');
    Route::patch('/push-up-station/sessions/{pushUpSession}/progress', [PushUpStationController::class, 'progress'])->name('push-up-station.sessions.progress');
    Route::patch('/push-up-station/sessions/{pushUpSession}/complete', [PushUpStationController::class, 'complete'])->name('push-up-station.sessions.complete');
    Route::patch('/push-up-station/sessions/{pushUpSession}/fail', [PushUpStationController::class, 'fail'])->name('push-up-station.sessions.fail');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/chat-messages/{chatMessage}/attachment', [ChatAttachmentController::class, 'show'])->name('chat-messages.attachment.show');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/extension', [AdminExtensionController::class, 'index'])->name('extension.index');
        Route::get('/students/{student}/extension', [AdminExtensionController::class, 'show'])->name('extension.show');
        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('/announcements/{chatMessage}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::get('/database', AdminAdminerController::class)->name('database');
        Route::get('/assignments', [AdminAssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/assignments', [AdminAssignmentController::class, 'store'])->name('assignments.store');
        Route::patch('/assignments/{studentAssignment}/complete', [AdminAssignmentController::class, 'complete'])->name('assignments.complete');
        Route::patch('/assignments/{studentAssignment}/incomplete', [AdminAssignmentController::class, 'incomplete'])->name('assignments.incomplete');
        Route::delete('/assignments/{studentAssignment}', [AdminAssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::get('/chats', [AdminChatController::class, 'index'])->name('chats.index');
        Route::get('/chats/{student}', [AdminChatController::class, 'show'])->name('chats.show');
        Route::post('/chats/{student}', [AdminChatController::class, 'store'])->name('chats.store');
        Route::delete('/chats/{student}/{chatMessage}', [AdminChatController::class, 'destroy'])->name('chats.destroy');
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
        Route::post('/violations/{violation}/push-up-sessions', [AdminPushUpSessionController::class, 'store'])->name('violations.push-up-sessions.store');
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
        Route::patch('/task-sessions/{taskSession}/unfinished', [AdminTaskSessionController::class, 'unfinished'])->name('task-sessions.unfinished');
        Route::get('/students/{student}/assignments', [AdminAssignmentController::class, 'show'])->name('students.assignments.show');
        Route::get('/students/{student}/devices', [AdminStudentDeviceController::class, 'index'])->name('students.devices.index');
        Route::get('/students/{student}/companion-debug', [AdminStudentDeviceController::class, 'debug'])->name('students.devices.debug');
        Route::patch('/students/{student}/chat/messages/{chatMessage}/read', [AdminChatController::class, 'markMessageRead'])->name('students.chat.messages.read');
        Route::patch('/students/{student}/devices/{studentDevice}', [AdminStudentDeviceController::class, 'update'])->name('students.devices.update');
        Route::post('/students/{student}/devices/{studentDevice}/commands', [AdminStudentDeviceController::class, 'command'])->name('students.devices.command');
        Route::patch('/students/{student}/devices/{studentDevice}/revoke', [AdminStudentDeviceController::class, 'revoke'])->name('students.devices.revoke');
        Route::patch('/students/{student}/browser-mode', [AdminBrowserAccountabilityController::class, 'updateMode'])->name('students.browser-mode.update');
        Route::post('/students/{student}/browser-rules', [AdminBrowserAccountabilityController::class, 'storeRule'])->name('students.browser-rules.store');
        Route::delete('/students/{student}/browser-rules/{browserPolicyRule}', [AdminBrowserAccountabilityController::class, 'destroyRule'])->name('students.browser-rules.destroy');
        Route::patch('/students/{student}/browser-access-requests/{browserAccessRequest}/approve', [AdminBrowserAccountabilityController::class, 'approveRequest'])->name('students.browser-access-requests.approve');
        Route::patch('/students/{student}/browser-access-requests/{browserAccessRequest}/deny', [AdminBrowserAccountabilityController::class, 'denyRequest'])->name('students.browser-access-requests.deny');
        Route::patch('/students/{student}/app-policies/{studentAppPolicy}/permit', [AdminStudentAppPolicyController::class, 'permit'])->name('students.app-policies.permit');
        Route::patch('/students/{student}/app-policies/{studentAppPolicy}/block', [AdminStudentAppPolicyController::class, 'block'])->name('students.app-policies.block');
        Route::get('/students/{student}/edit', [AdminStudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [AdminStudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [AdminStudentController::class, 'destroy'])->name('students.destroy');
        Route::patch('/students/{student}/password', [AdminStudentController::class, 'updatePassword'])->name('students.password.update');
        Route::patch('/students/{student}/push-up-counter', [AdminStudentController::class, 'updatePushUpCounter'])->name('students.push-up-counter.update');
        Route::get('/students', [AdminStudentController::class, 'index'])->name('students.index');
        Route::patch('/students/{student}/chat/read', [AdminChatController::class, 'markRead'])->name('students.chat.read');
        Route::get('/student-monitor-captures/{studentMonitorCapture}/day-history', [AdminStudentMonitorCaptureController::class, 'dayHistory'])->name('student-monitor-captures.day-history');
        Route::get('/student-monitor-captures/{studentMonitorCapture}', [AdminStudentMonitorCaptureController::class, 'show'])->name('student-monitor-captures.show');
        Route::get('/speech-announcements/history', [AdminSpeechAnnouncementController::class, 'history'])->name('speech-announcements.history');
        Route::get('/speech-announcements/next', [AdminSpeechAnnouncementController::class, 'next'])->name('speech-announcements.next');
        Route::get('/speech-announcements/latest-pending', [AdminSpeechAnnouncementController::class, 'latestPending'])->name('speech-announcements.latest-pending');
        Route::patch('/speech-announcements/{speechAnnouncement}/spoken', [AdminSpeechAnnouncementController::class, 'markSpoken'])->name('speech-announcements.mark-spoken');
        Route::patch('/speech-announcements/state', [AdminSpeechAnnouncementController::class, 'updateState'])->name('speech-announcements.state.update');
        Route::get('/_hidden/schedule-block-time', [AdminHiddenScheduleBlockTimeController::class, 'show'])->name('hidden.schedule-block-time.show');
        Route::post('/_hidden/schedule-block-time', [AdminHiddenScheduleBlockTimeController::class, 'update'])->name('hidden.schedule-block-time.update');
    });

    Route::prefix('student')->name('student.')->middleware('student')->group(function () {
        Route::get('/home', StudentHomeController::class)->name('home');
        Route::post('/violations/{violation}/push-up-sessions', [StudentPushUpSessionController::class, 'store'])->name('violations.push-up-sessions.store');
        Route::get('/announcements', [StudentAnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('/assignments', [StudentAssignmentController::class, 'index'])->name('assignments.index');
        Route::patch('/assignments/{studentAssignment}/start', [StudentAssignmentController::class, 'start'])->name('assignments.start');
        Route::patch('/assignments/{studentAssignment}/hand-in', [StudentAssignmentController::class, 'handIn'])->name('assignments.hand-in');
        Route::get('/companion/enroll', [StudentCompanionEnrollmentController::class, 'show'])->name('companion.enroll');
        Route::post('/companion/enroll/browser-extension-token', [StudentCompanionEnrollmentController::class, 'browserExtensionToken'])->name('companion.enroll.browser-extension-token');
        Route::get('/companion/enroll/bootstrap.ps1', [StudentCompanionEnrollmentController::class, 'bootstrapScript'])->name('companion.enroll.bootstrap');
        Route::post('/attention/events', [StudentAttentionEventController::class, 'store'])
            ->withoutMiddleware([VerifyCsrfToken::class])
            ->name('attention.events.store');
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
        Route::post('/task-sessions/{taskSession}/resume', [StudentTaskSessionController::class, 'resume'])->name('task-sessions.resume');
        Route::patch('/task-sessions/{taskSession}/unfinished', [StudentTaskSessionController::class, 'unfinished'])->name('task-sessions.unfinished');
        Route::patch('/task-sessions/{taskSession}/stop', [StudentTaskSessionController::class, 'stop'])->name('task-sessions.stop');
    });
});

require __DIR__.'/auth.php';
