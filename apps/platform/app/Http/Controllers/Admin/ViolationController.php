<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveViolationRequest;
use App\Http\Requests\Admin\StoreViolationRequest;
use App\Models\PenaltyAccount;
use App\Models\PenaltyTransaction;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\Violation;
use App\Models\ViolationResolution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ViolationController extends Controller
{
    protected function postPenaltyChargeForViolation(Violation $violation, int $createdByUserId): ?PenaltyTransaction
    {
        if ($violation->penalty_units <= 0) {
            return null;
        }

        $penaltyAccount = PenaltyAccount::query()->firstOrCreate([
            'student_id' => $violation->student_id,
        ]);

        return $penaltyAccount->transactions()->create([
            'violation_id' => $violation->id,
            'type' => 'violation_charge',
            'delta_units' => $violation->penalty_units,
            'notes' => "Автоматическое начисление по нарушению #{$violation->id}: {$violation->rule_title_snapshot}.",
            'recorded_at' => $violation->occurred_at ?? now(),
            'created_by_user_id' => $createdByUserId,
        ]);
    }

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
                'default_penalty_units' => $ruleDefinition->default_penalty_units,
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

    protected function toPayload(Violation $violation): array
    {
        $violation->loadMissing([
            'student.user',
            'ruleDefinition',
            'resolutions.createdBy',
            'penaltyTransactions.createdBy',
        ]);

        $latestResolution = $violation->resolutions
            ->sortByDesc('recorded_at')
            ->first();
        $penaltyTransaction = $violation->penaltyTransactions
            ->sortByDesc('recorded_at')
            ->first();

        return [
            'id' => $violation->id,
            'status' => $violation->status,
            'rule_title' => $violation->rule_title_snapshot,
            'penalty_units' => $violation->penalty_units,
            'occurred_at_label' => $violation->occurred_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
            'notes' => $violation->notes,
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
            'penalty_transaction' => $penaltyTransaction
                ? [
                    'id' => $penaltyTransaction->id,
                    'type' => $penaltyTransaction->type,
                    'delta_units' => $penaltyTransaction->delta_units,
                    'notes' => $penaltyTransaction->notes,
                    'recorded_at_label' => $penaltyTransaction->recorded_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                    'created_by' => $penaltyTransaction->createdBy
                        ? [
                            'id' => $penaltyTransaction->createdBy->id,
                            'name' => $penaltyTransaction->createdBy->name,
                            'username' => $penaltyTransaction->createdBy->username,
                        ]
                        : null,
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
                        ]
                        : null,
                ]
                : null,
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Violations/Index', [
            'violations' => Violation::query()
                ->with(['student.user', 'ruleDefinition', 'resolutions.createdBy'])
                ->latest('occurred_at')
                ->get()
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

    public function store(StoreViolationRequest $request): RedirectResponse
    {
        $ruleDefinition = RuleDefinition::query()->findOrFail((int) $request->input('rule_definition_id'));

        $result = DB::transaction(function () use ($request, $ruleDefinition) {
            $violation = Violation::create([
                'student_id' => (int) $request->input('student_id'),
                'rule_definition_id' => $ruleDefinition->id,
                'status' => 'open',
                'rule_title_snapshot' => $ruleDefinition->title,
                'penalty_units' => (int) $request->input('penalty_units'),
                'occurred_at' => $request->date('occurred_at'),
                'notes' => $request->input('notes'),
                'reported_by_user_id' => $request->user()->id,
            ]);

            $penaltyTransaction = $this->postPenaltyChargeForViolation($violation, $request->user()->id);

            return [
                'violation' => $violation,
                'penalty_transaction' => $penaltyTransaction,
            ];
        });

        /** @var Violation $violation */
        $violation = $result['violation'];
        /** @var PenaltyTransaction|null $penaltyTransaction */
        $penaltyTransaction = $result['penalty_transaction'];

        $message = $penaltyTransaction
            ? "Нарушение {$violation->rule_title_snapshot} создано, начислено {$penaltyTransaction->delta_units} штрафных ед."
            : "Нарушение {$violation->rule_title_snapshot} создано.";

        return redirect()
            ->route('admin.violations.index')
            ->with('success', $message);
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
                        ]
                        : null,
                ]),
        ]);
    }

    public function resolve(ResolveViolationRequest $request, Violation $violation): RedirectResponse
    {
        $result = DB::transaction(function () use ($request, $violation) {
            $lockedViolation = Violation::query()
                ->whereKey($violation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedViolation->status !== 'open') {
                return [
                    'success' => false,
                    'message' => 'Это нарушение уже закрыто.',
                ];
            }

            $action = $request->string('action')->toString();

            $lockedViolation->update([
                'status' => $action,
            ]);

            $resolution = $lockedViolation->resolutions()->create([
                'action' => $action,
                'notes' => $request->input('notes'),
                'recorded_at' => now(),
                'created_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => $action === 'waived'
                    ? "Нарушение {$lockedViolation->rule_title_snapshot} отмечено как отменённое."
                    : "Нарушение {$lockedViolation->rule_title_snapshot} отмечено как решённое.",
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
}
