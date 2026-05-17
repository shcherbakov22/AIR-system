<?php

namespace App\Providers;

use App\Models\ChatMessage;
use App\Models\AiOverseerDecision;
use App\Models\AiOverseerMessage;
use App\Models\BrowserVisitLog;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\StudentAssignment;
use App\Models\DeviceActivityEvent;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\Violation;
use App\Models\ViolationResolution;
use App\Services\ActivityLogService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootActivityLogging();
    }

    private function bootActivityLogging(): void
    {
        TaskSession::created(function (TaskSession $taskSession) {
            app(ActivityLogService::class)->log(
                'tasks',
                $taskSession->resumed_from_task_session_id ? 'task_resumed' : 'task_started',
                ($taskSession->resumed_from_task_session_id ? 'Task resumed: ' : 'Task started: ').$taskSession->task_title_snapshot,
                $taskSession->student_id,
                $taskSession->started_by_user_id,
                $taskSession,
                [
                    'title' => $taskSession->task_title_snapshot,
                    'status' => $taskSession->status,
                    'schedule_run_id' => $taskSession->schedule_run_id,
                    'schedule_run_block_id' => $taskSession->schedule_run_block_id,
                    'planned_duration_minutes' => $taskSession->planned_duration_minutes,
                ],
            );
        });

        TaskSession::updated(function (TaskSession $taskSession) {
            if (! $taskSession->wasChanged('status')) {
                return;
            }

            $action = match ($taskSession->status) {
                'completed' => 'task_ended',
                'paused' => 'task_paused',
                'unfinished' => 'task_marked_unfinished',
                default => 'task_status_changed',
            };

            app(ActivityLogService::class)->log(
                'tasks',
                $action,
                ucfirst(str_replace('_', ' ', $action)).': '.$taskSession->task_title_snapshot,
                $taskSession->student_id,
                $taskSession->stopped_by_user_id ?: $taskSession->started_by_user_id,
                $taskSession,
                [
                    'title' => $taskSession->task_title_snapshot,
                    'old_status' => $taskSession->getOriginal('status'),
                    'new_status' => $taskSession->status,
                    'duration_seconds' => $taskSession->duration_seconds,
                    'completion_notes' => $taskSession->completion_notes,
                ],
            );
        });

        ScheduleRun::created(function (ScheduleRun $scheduleRun) {
            app(ActivityLogService::class)->log(
                'schedules',
                'schedule_started',
                'Schedule started: '.$scheduleRun->schedule_name_snapshot,
                $scheduleRun->student_id,
                $scheduleRun->started_by_user_id,
                $scheduleRun,
                ['name' => $scheduleRun->schedule_name_snapshot],
            );
        });

        ScheduleRun::updated(function (ScheduleRun $scheduleRun) {
            if (! $scheduleRun->wasChanged('status')) {
                return;
            }

            $action = match ($scheduleRun->status) {
                'paused' => 'schedule_paused',
                'active' => 'schedule_resumed',
                'completed' => 'schedule_completed',
                default => 'schedule_status_changed',
            };

            app(ActivityLogService::class)->log(
                'schedules',
                $action,
                ucfirst(str_replace('_', ' ', $action)).': '.$scheduleRun->schedule_name_snapshot,
                $scheduleRun->student_id,
                $scheduleRun->completed_by_user_id ?: $scheduleRun->started_by_user_id,
                $scheduleRun,
                [
                    'name' => $scheduleRun->schedule_name_snapshot,
                    'old_status' => $scheduleRun->getOriginal('status'),
                    'new_status' => $scheduleRun->status,
                ],
            );
        });

        Violation::created(function (Violation $violation) {
            app(ActivityLogService::class)->log(
                'violations',
                'violation_created',
                'Violation created: '.$violation->rule_title_snapshot,
                $violation->student_id,
                $violation->reported_by_user_id,
                $violation,
                [
                    'rule_title' => $violation->rule_title_snapshot,
                    'status' => $violation->status,
                    'penalty_units' => $violation->penalty_units,
                    'auto_generated_key' => $violation->auto_generated_key,
                ],
            );
        });

        Violation::updated(function (Violation $violation) {
            if (! $violation->wasChanged('status')) {
                return;
            }

            app(ActivityLogService::class)->log(
                'violations',
                'violation_'.$violation->status,
                'Violation '.$violation->status.': '.$violation->rule_title_snapshot,
                $violation->student_id,
                auth()->id(),
                $violation,
                [
                    'rule_title' => $violation->rule_title_snapshot,
                    'old_status' => $violation->getOriginal('status'),
                    'new_status' => $violation->status,
                ],
            );
        });

        Violation::deleted(function (Violation $violation) {
            app(ActivityLogService::class)->log(
                'violations',
                'violation_deleted',
                'Violation deleted: '.$violation->rule_title_snapshot,
                $violation->student_id,
                auth()->id(),
                $violation,
                ['rule_title' => $violation->rule_title_snapshot],
            );
        });

        ViolationResolution::created(function (ViolationResolution $resolution) {
            $resolution->loadMissing('violation');

            app(ActivityLogService::class)->log(
                'violations',
                'violation_resolution_created',
                'Violation resolution recorded: '.$resolution->action,
                $resolution->violation?->student_id,
                $resolution->created_by_user_id,
                $resolution,
                [
                    'action' => $resolution->action,
                    'violation_id' => $resolution->violation_id,
                    'notes' => $resolution->notes,
                ],
            );
        });

        StudentAssignment::created(function (StudentAssignment $assignment) {
            app(ActivityLogService::class)->log(
                'assignments',
                'assignment_created',
                'Assignment created: '.$assignment->title,
                $assignment->student_id,
                $assignment->created_by_user_id,
                $assignment,
                [
                    'title' => $assignment->title,
                    'status' => $assignment->status,
                    'has_attachment' => $assignment->hasAttachment(),
                ],
            );
        });

        StudentAssignment::updated(function (StudentAssignment $assignment) {
            if (! $assignment->wasChanged('status')) {
                return;
            }

            app(ActivityLogService::class)->log(
                'assignments',
                'assignment_'.$assignment->status,
                'Assignment '.$assignment->status.': '.$assignment->title,
                $assignment->student_id,
                auth()->id() ?: $assignment->created_by_user_id,
                $assignment,
                [
                    'title' => $assignment->title,
                    'old_status' => $assignment->getOriginal('status'),
                    'new_status' => $assignment->status,
                ],
            );
        });

        StudentAssignment::deleted(function (StudentAssignment $assignment) {
            app(ActivityLogService::class)->log(
                'assignments',
                'assignment_deleted',
                'Assignment deleted: '.$assignment->title,
                $assignment->student_id,
                auth()->id(),
                $assignment,
                ['title' => $assignment->title],
            );
        });

        ChatMessage::created(function (ChatMessage $message) {
            $isAnnouncement = $message->isAnnouncement();

            app(ActivityLogService::class)->log(
                $isAnnouncement ? 'announcements' : 'messages',
                $isAnnouncement ? 'announcement_sent' : 'message_sent',
                $isAnnouncement ? 'Announcement sent' : 'Message sent',
                $message->student_id,
                $message->sender_user_id,
                $message,
                [
                    'channel' => $message->channel,
                    'has_attachment' => $message->hasAttachment(),
                    'body_preview' => str((string) $message->body)->limit(80)->toString(),
                ],
            );
        });

        ChatMessage::deleted(function (ChatMessage $message) {
            $isAnnouncement = $message->isAnnouncement();

            app(ActivityLogService::class)->log(
                $isAnnouncement ? 'announcements' : 'messages',
                $isAnnouncement ? 'announcement_deleted' : 'message_deleted',
                $isAnnouncement ? 'Announcement deleted' : 'Message deleted',
                $message->student_id,
                auth()->id(),
                $message,
                ['channel' => $message->channel],
            );
        });

        AiOverseerDecision::created(function (AiOverseerDecision $decision) {
            app(ActivityLogService::class)->log(
                'overseer',
                'overseer_conversation_started',
                'AI overseer conversation started: '.$decision->request_type,
                $decision->student_id,
                $decision->requested_by_user_id,
                $decision,
                [
                    'request_type' => $decision->request_type,
                    'status' => $decision->status,
                    'violation_id' => $decision->violation_id,
                    'task_session_id' => $decision->task_session_id,
                    'schedule_run_block_id' => $decision->schedule_run_block_id,
                ],
            );
        });

        AiOverseerDecision::updated(function (AiOverseerDecision $decision) {
            if ($decision->wasChanged('status')) {
                app(ActivityLogService::class)->log(
                    'overseer',
                    'overseer_status_'.$decision->status,
                    'AI overseer status changed to '.$decision->status.': '.$decision->request_type,
                    $decision->student_id,
                    $decision->reviewed_by_user_id ?: $decision->requested_by_user_id,
                    $decision,
                    [
                        'request_type' => $decision->request_type,
                        'old_status' => $decision->getOriginal('status'),
                        'new_status' => $decision->status,
                        'decision' => $decision->decision,
                        'confidence' => $decision->confidence,
                        'reason' => $decision->reason,
                    ],
                );
            }

            if ($decision->wasChanged('action_taken') && $decision->action_taken) {
                app(ActivityLogService::class)->log(
                    'overseer',
                    'overseer_action_taken',
                    'AI overseer action taken: '.$decision->action_taken,
                    $decision->student_id,
                    $decision->reviewed_by_user_id ?: $decision->requested_by_user_id,
                    $decision,
                    [
                        'request_type' => $decision->request_type,
                        'decision' => $decision->decision,
                        'action_taken' => $decision->action_taken,
                        'confidence' => $decision->confidence,
                    ],
                );
            }

            if ($decision->wasChanged('mentor_notified_at') && $decision->mentor_notified_at) {
                app(ActivityLogService::class)->log(
                    'overseer',
                    'overseer_mentor_notified',
                    'AI overseer escalated to mentor: '.$decision->request_type,
                    $decision->student_id,
                    $decision->requested_by_user_id,
                    $decision,
                    [
                        'request_type' => $decision->request_type,
                        'decision' => $decision->decision,
                        'confidence' => $decision->confidence,
                    ],
                );
            }

            if ($decision->wasChanged('reviewed_at') && $decision->reviewed_at) {
                app(ActivityLogService::class)->log(
                    'overseer',
                    'overseer_reviewed',
                    'AI overseer reviewed by mentor: '.$decision->status,
                    $decision->student_id,
                    $decision->reviewed_by_user_id,
                    $decision,
                    [
                        'request_type' => $decision->request_type,
                        'status' => $decision->status,
                        'decision' => $decision->decision,
                    ],
                );
            }
        });

        AiOverseerMessage::created(function (AiOverseerMessage $message) {
            $message->loadMissing('decision');

            app(ActivityLogService::class)->log(
                'overseer',
                $message->sender === 'assistant' ? 'overseer_assistant_message' : 'overseer_student_message',
                $message->sender === 'assistant' ? 'AI overseer replied' : 'Student messaged AI overseer',
                $message->decision?->student_id,
                $message->user_id,
                $message,
                [
                    'sender' => $message->sender,
                    'decision_id' => $message->ai_overseer_decision_id,
                    'request_type' => $message->decision?->request_type,
                    'is_final_decision' => $message->is_final_decision,
                    'body_preview' => str((string) $message->body)->limit(120)->toString(),
                ],
            );
        });

        BrowserVisitLog::created(function (BrowserVisitLog $visit) {
            app(ActivityLogService::class)->log(
                'websites',
                'website_'.$visit->decision,
                ucfirst($visit->decision).' website: '.($visit->host ?: $visit->url),
                $visit->student_id,
                null,
                $visit,
                [
                    'url' => $visit->url,
                    'host' => $visit->host,
                    'registrable_domain' => $visit->registrable_domain,
                    'page_title' => $visit->page_title,
                    'mode' => $visit->mode,
                    'decision' => $visit->decision,
                    'source' => $visit->meta['source'] ?? null,
                    'student_device_id' => $visit->student_device_id,
                ],
            );
        });

        DeviceActivityEvent::created(function (DeviceActivityEvent $event) {
            $event->loadMissing('studentDevice');
            $studentId = $event->studentDevice?->student_id;

            if ($event->event_type === 'focused_app') {
                $previousFocusedEvent = DeviceActivityEvent::query()
                    ->where('student_device_id', $event->student_device_id)
                    ->where('event_type', 'focused_app')
                    ->whereKeyNot($event->id)
                    ->latest('observed_at')
                    ->latest('id')
                    ->first();

                if ($this->activityLogFocusedAppState($event) === $this->activityLogFocusedAppState($previousFocusedEvent)) {
                    return;
                }

                app(ActivityLogService::class)->log(
                    'apps',
                    'app_focused',
                    'Focused app: '.($event->app_name ?: 'Unknown app'),
                    $studentId,
                    null,
                    $event,
                    [
                        'app_name' => $event->app_name,
                        'window_title' => $event->window_title,
                        'browser_domain' => $event->browser_domain,
                        'student_device_id' => $event->student_device_id,
                    ],
                );

                return;
            }

            if ($event->event_type !== 'open_apps') {
                return;
            }

            $previousEvent = DeviceActivityEvent::query()
                ->where('student_device_id', $event->student_device_id)
                ->where('event_type', 'open_apps')
                ->whereKeyNot($event->id)
                ->latest('observed_at')
                ->latest('id')
                ->first();

            $currentApps = $this->activityLogAppNames($event->payload['apps'] ?? []);
            $previousApps = $this->activityLogAppNames($previousEvent?->payload['apps'] ?? []);

            foreach (array_values(array_diff($currentApps, $previousApps)) as $appName) {
                app(ActivityLogService::class)->log(
                    'apps',
                    'app_opened',
                    'App opened: '.$appName,
                    $studentId,
                    null,
                    $event,
                    [
                        'app_name' => $appName,
                        'student_device_id' => $event->student_device_id,
                        'open_app_count' => count($currentApps),
                    ],
                );
            }

            foreach (array_values(array_diff($previousApps, $currentApps)) as $appName) {
                app(ActivityLogService::class)->log(
                    'apps',
                    'app_closed',
                    'App closed: '.$appName,
                    $studentId,
                    null,
                    $event,
                    [
                        'app_name' => $appName,
                        'student_device_id' => $event->student_device_id,
                        'open_app_count' => count($currentApps),
                    ],
                );
            }
        });

        Student::created(fn (Student $student) => app(ActivityLogService::class)->log(
            'admin',
            'student_created',
            'Student created: '.$student->display_name,
            $student->id,
            auth()->id(),
            $student,
        ));

        foreach ([TaskTemplate::class, RuleDefinition::class, ScheduleTemplate::class] as $modelClass) {
            $modelClass::created(fn ($model) => app(ActivityLogService::class)->log(
                'admin',
                str(class_basename($model))->snake()->toString().'_created',
                class_basename($model).' created: '.($model->title ?? $model->name ?? 'Item'),
                $model->student_id ?? null,
                $model->created_by_user_id ?? auth()->id(),
                $model,
            ));

            $modelClass::deleted(fn ($model) => app(ActivityLogService::class)->log(
                'admin',
                str(class_basename($model))->snake()->toString().'_deleted',
                class_basename($model).' deleted: '.($model->title ?? $model->name ?? 'Item'),
                $model->student_id ?? null,
                auth()->id(),
                $model,
            ));
        }
    }

    private function activityLogFocusedAppState(?DeviceActivityEvent $event): ?array
    {
        if (! $event) {
            return null;
        }

        return [
            'app_name' => mb_strtolower(trim((string) $event->app_name)),
        ];
    }

    private function activityLogAppNames(mixed $apps): array
    {
        if (! is_array($apps)) {
            return [];
        }

        return collect($apps)
            ->map(function ($app) {
                if (is_string($app)) {
                    return trim($app);
                }

                if (! is_array($app)) {
                    return null;
                }

                return trim((string) ($app['app_name'] ?? $app['name'] ?? $app['title'] ?? $app['window_title'] ?? ''));
            })
            ->filter()
            ->map(fn (string $appName) => mb_strtolower($appName))
            ->unique()
            ->values()
            ->all();
    }
}
