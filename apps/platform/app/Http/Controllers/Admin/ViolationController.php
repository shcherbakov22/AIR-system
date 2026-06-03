<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveViolationRequest;
use App\Http\Requests\Admin\StoreViolationRequest;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\Violation;
use App\Models\ViolationResolution;
use App\Services\SpeechAnnouncementService;
use App\Services\AutomaticViolationDismissalService;
use App\Services\StudentPushUpCounterService;
use App\Services\TaskSessionUnfinishService;
use App\Services\ViolationFalsePositiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ViolationController extends Controller
{
    protected function studentOptions(): array
    {
        return Student::query()
            ->with('user')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
            ])
            ->all();
    }

    protected function ruleDefinitionOptions(): array
    {
        return RuleDefinition::query()
            ->with('student.user')
            ->where('is_active', true)
            ->orderBy('scope')
            ->orderBy('title')
            ->get()
            ->map(fn (RuleDefinition $ruleDefinition) => [
                'id' => $ruleDefinition->id,
                'title' => $ruleDefinition->title,
                'scope' => $ruleDefinition->scope,
                'description' => $ruleDefinition->description,
                'student' => $ruleDefinition->student
                    ? [
                        'id' => $ruleDefinition->student->id,
                        'display_name' => $ruleDefinition->student->display_name,
                        'username' => $ruleDefinition->student->user->username,
                    ]
                    : null,
            ])
            ->all();
    }

    protected function latestResolution(Violation $violation): ?ViolationResolution
    {
        return $violation->resolutions
            ->sortByDesc('recorded_at')
            ->first();
    }

    protected function toPayload(Violation $violation): array
    {
        $violation->loadMissing([
            'student.user',
            'ruleDefinition',
            'resolutions.createdBy',
        ]);

        $latestResolution = $this->latestResolution($violation);

        return [
            'id' => $violation->id,
            'status' => $violation->status,
            'rule_title' => $violation->rule_title_snapshot,
            'occurred_at_label' => $violation->occurred_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
            'notes' => $violation->notes,
            'push_up_count' => $violation->effectivePenaltyUnits(),
            'student' => [
                'id' => $violation->student->id,
                'display_name' => $violation->student->display_name,
                'username' => $violation->student->user->username,
            ],
            'rule_definition' => $violation->ruleDefinition
                ? [
                    'id' => $violation->ruleDefinition->id,
                    'scope' => $violation->ruleDefinition->scope,
                ]
                : null,
            'latest_resolution' => $latestResolution
                ? [
                    'id' => $latestResolution->id,
                    'action' => $latestResolution->action,
                    'notes' => $latestResolution->notes,
                    'recorded_at_label' => $latestResolution->recorded_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                    'created_by' => $latestResolution->createdBy
                        ? [
                            'id' => $latestResolution->createdBy->id,
                            'name' => $latestResolution->createdBy->name,
                            'username' => $latestResolution->createdBy->username,
                            'role' => $latestResolution->createdBy->role?->value,
                        ]
                        : null,
                ]
                : null,
            'start_push_up_url' => $violation->status === 'open'
                ? route('admin.violations.push-up-sessions.store', $violation)
                : null,
            'false_positive_url' => $violation->status === 'open'
                ? route('admin.violations.false-positive', $violation)
                : null,
        ];
    }

    public function index(): Response
    {
        $students = Student::query()
            ->with(['user', 'consequenceProfile'])
            ->orderBy('display_name')
            ->get();

        $ruleDefinitions = RuleDefinition::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        $openViolations = Violation::query()
            ->with(['student.user', 'ruleDefinition', 'resolutions.createdBy'])
            ->where('status', 'open')
            ->latest('occurred_at')
            ->get();

        $openViolationCounts = $openViolations
            ->groupBy(fn (Violation $violation) => $violation->student_id.':'.$violation->rule_definition_id)
            ->map(fn ($group) => $group->count());

        return Inertia::render('Admin/Violations/Index', [
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'current_push_up_count' => $student->consequenceProfile?->current_push_up_count ?? StudentPushUpCounterService::DEFAULT_COUNT,
            ])->all(),
            'ruleDefinitions' => $ruleDefinitions->map(fn (RuleDefinition $ruleDefinition) => [
                'id' => $ruleDefinition->id,
                'title' => $ruleDefinition->title,
            ])->all(),
            'openViolationCounts' => $openViolationCounts,
            'openViolations' => $openViolations
                ->take(24)
                ->map(fn (Violation $violation) => $this->toPayload($violation)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Violations/Create', [
            'students' => $this->studentOptions(),
            'ruleDefinitions' => $this->ruleDefinitionOptions(),
        ]);
    }

    public function store(
        StoreViolationRequest $request,
        SpeechAnnouncementService $speechAnnouncementService,
        StudentPushUpCounterService $pushUpCounterService,
        TaskSessionUnfinishService $taskSessionUnfinishService,
    ): RedirectResponse
    {
        $ruleDefinition = RuleDefinition::query()->findOrFail((int) $request->input('rule_definition_id'));
        $studentId = (int) $request->input('student_id');
        $toggle = $request->boolean('toggle');
        $result = DB::transaction(function () use ($request, $ruleDefinition, $studentId, $toggle, $pushUpCounterService) {
            // Serialize manual violation actions per student to avoid duplicate
            // open violations when requests arrive nearly at the same time.
            $student = Student::query()
                ->whereKey($studentId)
                ->lockForUpdate()
                ->firstOrFail();

            $existingViolation = Violation::query()
                ->where('student_id', $studentId)
                ->where('rule_definition_id', $ruleDefinition->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->latest('occurred_at')
                ->first();

            if ($existingViolation) {
                if (! $toggle) {
                    return [
                        'type' => 'already_open',
                    ];
                }

                $existingViolation->resolutions()->delete();
                $existingViolation->delete();

                return [
                    'type' => 'removed',
                    'rule_title' => $existingViolation->rule_title_snapshot,
                ];
            }

            $pushUpCount = $pushUpCounterService->allocateForViolation($student);

            $violation = Violation::create([
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'status' => 'open',
                'rule_title_snapshot' => $ruleDefinition->title,
                'penalty_units' => $pushUpCount,
                'occurred_at' => $request->date('occurred_at'),
                'notes' => $request->input('notes'),
                'reported_by_user_id' => $request->user()->id,
            ]);

            return [
                'type' => 'created',
                'student' => $student,
                'violation' => $violation,
            ];
        });

        if ($result['type'] === 'already_open') {
            return redirect()
                ->back()
                ->with('error', "Violation {$ruleDefinition->title} is already open for this student.");
        }

        if ($result['type'] === 'removed') {
            return redirect(route('admin.violations.index'))
                ->with('success', "Violation {$result['rule_title']} removed.");
        }

        /** @var \App\Models\Student $student */
        $student = $result['student'];
        /** @var \App\Models\Violation $violation */
        $violation = $result['violation'];

        $taskSessionUnfinishService->interruptActiveTaskForStudent($student, $request->user()->id);
        $speechAnnouncementService->queueViolation($violation);

        $redirectRoute = $request->boolean('return_to_dashboard')
            ? route('admin.dashboard')
            : route('admin.violations.index');

        return redirect($redirectRoute)
            ->with('success', "Violation {$violation->rule_title_snapshot} created.");
    }

    public function show(Violation $violation): Response
    {
        return Inertia::render('Admin/Violations/Show', [
            'violation' => $this->toPayload($violation),
            'resolutions' => $violation->resolutions()
                ->with('createdBy')
                ->latest('recorded_at')
                ->get()
                ->map(fn (ViolationResolution $resolution) => [
                    'id' => $resolution->id,
                    'action' => $resolution->action,
                    'notes' => $resolution->notes,
                    'recorded_at_label' => $resolution->recorded_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                    'created_by' => $resolution->createdBy
                        ? [
                            'id' => $resolution->createdBy->id,
                            'name' => $resolution->createdBy->name,
                            'username' => $resolution->createdBy->username,
                            'role' => $resolution->createdBy->role?->value,
                        ]
                        : null,
                ]),
        ]);
    }

    public function resolve(
        ResolveViolationRequest $request,
        Violation $violation,
        AutomaticViolationDismissalService $automaticViolationDismissalService,
    ): RedirectResponse
    {
        $result = DB::transaction(function () use ($request, $violation, $automaticViolationDismissalService) {
            $lockedViolation = Violation::query()
                ->whereKey($violation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedViolation->status !== 'open') {
                return [
                    'success' => false,
                    'message' => 'This violation is already closed.',
                ];
            }

            $action = $request->string('action')->toString();

            $lockedViolation->update([
                'status' => $action,
            ]);

            $automaticViolationDismissalService->dismiss($lockedViolation, $request->user()->id);

            $resolution = $lockedViolation->resolutions()->create([
                'action' => $action,
                'notes' => $request->input('notes'),
                'recorded_at' => now(),
                'created_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => $action === 'waived'
                    ? "Violation {$lockedViolation->rule_title_snapshot} marked as waived."
                    : "Violation {$lockedViolation->rule_title_snapshot} marked as resolved.",
                'resolution_id' => $resolution->id,
            ];
        });

        if (! $result['success']) {
            return redirect()
                ->route('admin.violations.show', $violation)
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('admin.violations.show', $violation)
            ->with('success', $result['message']);
    }

    public function falsePositive(Violation $violation, ViolationFalsePositiveService $service): RedirectResponse
    {
        try {
            $service->markFalsePositive($violation, request()->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Violation {$violation->rule_title_snapshot} marked as a false positive.");
    }

    public function destroy(Violation $violation, AutomaticViolationDismissalService $automaticViolationDismissalService): RedirectResponse
    {
        $violationTitle = $violation->rule_title_snapshot;

        DB::transaction(function () use ($violation, $automaticViolationDismissalService) {
            $lockedViolation = Violation::query()
                ->whereKey($violation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $automaticViolationDismissalService->dismiss($lockedViolation, auth()->id());

            $lockedViolation->resolutions()->delete();
            $lockedViolation->delete();
        });

        $redirectRoute = request()->boolean('return_to_dashboard')
            ? route('admin.dashboard')
            : route('admin.violations.index');

        return redirect($redirectRoute)
            ->with('success', 'Violation '.$violationTitle.' deleted.');
    }
}
