<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrowserAccessRequest;
use App\Models\BrowserPolicyRule;
use App\Models\BrowserVisitLog;
use App\Models\DeviceAttentionCalibrationSession;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\TaskTemplate;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class ExtensionController extends Controller
{
    public function index(): Response
    {
        return $this->render();
    }

    public function show(Student $student): Response
    {
        return $this->render($student);
    }

    protected function render(?Student $focusedStudent = null): Response
    {
        $students = Student::query()
            ->with([
                'user',
                'devices' => fn ($query) => $query->whereNull('revoked_at')->latest('last_seen_at')->latest('id'),
            ])
            ->withCount([
                'devices as active_device_count' => fn (Builder $query) => $query->whereNull('revoked_at'),
                'browserAccessRequests as pending_access_request_count' => fn (Builder $query) => $query->where('status', 'pending'),
                'browserVisitLogs as visit_count_today' => fn (Builder $query) => $query->where('visited_at', '>=', now()->startOfDay()),
                'violations as open_look_away_count' => fn (Builder $query) => $query
                    ->where('status', 'open')
                    ->where('auto_generated_key', 'like', 'look-away:%'),
            ])
            ->orderBy('display_name')
            ->get();

        $focusedPayload = null;

        if ($focusedStudent) {
            $focusedPayload = $this->focusedStudentPayload(
                $focusedStudent->load([
                    'user',
                    'setting',
                    'devices' => fn ($query) => $query
                        ->whereNull('revoked_at')
                        ->with(['attentionCalibrationSessions' => fn ($sessionQuery) => $sessionQuery
                            ->latest('started_at')
                            ->latest('id')
                            ->limit(3),
                        ])
                        ->latest('last_seen_at')
                        ->latest('id'),
                    'browserPolicyRules' => fn ($query) => $query->latest('created_at')->latest('id'),
                    'browserAccessRequests' => fn ($query) => $query
                        ->with('taskTemplate')
                        ->latest('created_at')
                        ->limit(30),
                    'browserVisitLogs' => fn ($query) => $query
                        ->with('matchedRule')
                        ->latest('visited_at')
                        ->latest('id')
                        ->limit(80),
                    'violations' => fn ($query) => $query
                        ->where('auto_generated_key', 'like', 'look-away:%')
                        ->latest('occurred_at')
                        ->limit(20),
                ])
            );
        }

        return Inertia::render('Admin/Extension/Index', [
            'students' => $students->map(fn (Student $student) => $this->studentSummaryPayload($student))->values()->all(),
            'focusedStudent' => $focusedPayload,
            'extension_download_url' => route('companion.browser-extension.download'),
            'default_unblock_scope' => 'domain_tree',
            'task_allowlists' => $this->taskAllowlistPayload(),
        ]);
    }

    protected function studentSummaryPayload(Student $student): array
    {
        $latestDevice = $student->devices->first();

        return [
            'id' => $student->id,
            'display_name' => $student->display_name,
            'username' => $student->user?->username,
            'status' => $student->status,
            'browser_mode' => $latestDevice?->internet_access_mode ?? 'blacklist',
            'device_count' => (int) ($student->active_device_count ?? $student->devices->count()),
            'last_seen_at' => $latestDevice?->last_seen_at?->toIso8601String(),
            'last_seen_at_label' => $latestDevice?->last_seen_at?->format('d M, H:i') ?? 'Never',
            'pending_access_request_count' => (int) ($student->pending_access_request_count ?? 0),
            'visit_count_today' => (int) ($student->visit_count_today ?? 0),
            'open_look_away_count' => (int) ($student->open_look_away_count ?? 0),
            'extension_url' => route('admin.extension.show', $student),
        ];
    }

    protected function focusedStudentPayload(Student $student): array
    {
        $mode = $student->devices
            ->first(fn (StudentDevice $device) => in_array($device->internet_access_mode, ['whitelist', 'blacklist'], true))
            ?->internet_access_mode ?? 'blacklist';

        return [
            'id' => $student->id,
            'display_name' => $student->display_name,
            'username' => $student->user?->username,
            'browser_accountability' => [
                'mode' => $mode,
                'default_unblock_scope' => 'domain_tree',
                'mode_update_url' => route('admin.students.browser-mode.update', $student),
                'rule_store_url' => route('admin.students.browser-rules.store', $student),
                'history_clear_url' => route('admin.students.browser-history.destroy', $student),
                'current_visit' => $student->browserVisitLogs->first()
                    ? $this->browserVisitPayload($student, $student->browserVisitLogs->first())
                    : null,
                'rules' => $student->browserPolicyRules
                    ->map(fn (BrowserPolicyRule $rule) => [
                        'id' => $rule->id,
                        'effect' => $rule->effect,
                        'match_type' => $rule->match_type,
                        'value' => $rule->value,
                        'expires_at' => $rule->expires_at?->toIso8601String(),
                        'destroy_url' => route('admin.students.browser-rules.destroy', [$student, $rule]),
                    ])
                    ->values()
                    ->all(),
                'access_requests' => $student->browserAccessRequests
                    ->map(fn (BrowserAccessRequest $request) => [
                        'id' => $request->id,
                        'requested_url' => $request->requested_url,
                        'display_url' => $this->cleanUrlForDisplay($request->requested_url),
                        'host' => $request->host,
                        'registrable_domain' => $request->registrable_domain,
                        'reason' => $request->reason,
                        'status' => $request->status,
                        'task_title' => $request->taskTemplate?->title,
                        'created_at' => $request->created_at?->toIso8601String(),
                        'created_at_label' => $request->created_at?->format('d M, H:i'),
                        'decided_at_label' => $request->decided_at?->format('d M, H:i'),
                        'approve_url' => route('admin.students.browser-access-requests.approve', [$student, $request]),
                        'deny_url' => route('admin.students.browser-access-requests.deny', [$student, $request]),
                        'destroy_url' => route('admin.students.browser-access-requests.destroy', [$student, $request]),
                    ])
                    ->values()
                    ->all(),
                'recent_visits' => $student->browserVisitLogs
                    ->map(fn (BrowserVisitLog $visit) => $this->browserVisitPayload($student, $visit))
                    ->values()
                    ->all(),
            ],
            'devices' => $student->devices
                ->map(fn (StudentDevice $device) => [
                    'id' => $device->id,
                    'label' => $device->label,
                    'hostname' => $device->hostname,
                    'platform' => $device->platform,
                    'app_version' => $device->app_version,
                    'internet_access_mode' => $device->internet_access_mode,
                    'last_seen_at_label' => $device->last_seen_at?->format('d M, H:i') ?? 'Never',
                    'last_seen_ip' => $device->last_seen_ip,
                    'last_policy_hash' => $device->last_policy_hash,
                    'attention_calibrations' => $device->attentionCalibrationSessions
                        ->map(fn (DeviceAttentionCalibrationSession $session) => [
                            'id' => $session->id,
                            'provider' => $session->provider,
                            'status' => $session->status,
                            'sample_count' => $session->sample_count,
                            'started_at_label' => $session->started_at?->format('d M, H:i'),
                            'completed_at_label' => $session->completed_at?->format('d M, H:i'),
                            'model_ready_at_label' => $session->model_ready_at?->format('d M, H:i'),
                            'model_version' => $session->model_version,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'attention' => [
                'look_away_event_threshold' => (int) ($student->setting?->look_away_event_threshold ?? 3),
                'look_away_event_count' => (int) ($student->setting?->look_away_event_count ?? 0),
                'look_away_task_session_id' => $student->setting?->look_away_task_session_id,
                'violations' => $student->violations
                    ->map(fn (Violation $violation) => [
                        'id' => $violation->id,
                        'status' => $violation->status,
                        'rule_title' => $violation->rule_title_snapshot,
                        'penalty_units' => $violation->penalty_units,
                        'occurred_at_label' => $violation->occurred_at?->format('d M, H:i'),
                        'notes' => $violation->notes,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function browserVisitPayload(Student $student, BrowserVisitLog $visit): array
    {
        return [
            'id' => $visit->id,
            'mode' => $visit->mode,
            'decision' => $visit->decision,
            'host' => $visit->host,
            'registrable_domain' => $visit->registrable_domain,
            'url' => $visit->url,
            'display_url' => $this->cleanUrlForDisplay($visit->url),
            'page_title' => $visit->page_title,
            'visited_at' => $visit->visited_at?->toIso8601String(),
            'visited_at_label' => $visit->visited_at?->format('d M, H:i'),
            'destroy_url' => route('admin.students.browser-history.logs.destroy', [$student, $visit]),
            'matched_rule' => $visit->matchedRule ? [
                'effect' => $visit->matchedRule->effect,
                'value' => $visit->matchedRule->value,
            ] : null,
        ];
    }

    protected function cleanUrlForDisplay(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        $display = $parts['host'].$path;
        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);

            $query = collect($query)
                ->reject(fn ($value, string $key) => $this->isTrackingQueryParameter($key))
                ->map(fn ($value) => is_array($value) ? reset($value) : $value)
                ->filter(fn ($value) => filled($value))
                ->take(4)
                ->all();
        }

        if ($query !== []) {
            $display .= '?'.http_build_query($query);
        }

        return $display;
    }

    protected function isTrackingQueryParameter(string $key): bool
    {
        $normalized = strtolower($key);

        return str_starts_with($normalized, 'utm_')
            || in_array($normalized, [
                'fbclid',
                'gclid',
                'dclid',
                'gbraid',
                'wbraid',
                'msclkid',
                'mc_cid',
                'mc_eid',
                'igshid',
                'ref',
                'ref_src',
                'spm',
                'ved',
                'ei',
                'sxsrf',
                'source',
                'campaign',
            ], true);
    }

    protected function taskAllowlistPayload(): array
    {
        return TaskTemplate::query()
            ->whereHas('browserPolicyRules', fn (Builder $query) => $query->where('effect', 'allow'))
            ->with(['browserPolicyRules' => fn ($query) => $query
                ->where('effect', 'allow')
                ->orderBy('value'),
            ])
            ->orderBy('title')
            ->limit(100)
            ->get()
            ->map(fn (TaskTemplate $taskTemplate) => [
                'id' => $taskTemplate->id,
                'title' => $taskTemplate->title,
                'domains' => $taskTemplate->browserPolicyRules
                    ->pluck('value')
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
