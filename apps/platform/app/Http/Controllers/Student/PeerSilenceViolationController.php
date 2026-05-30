<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\Violation;
use App\Services\SpeechAnnouncementService;
use App\Services\StudentPushUpCounterService;
use App\Services\TaskSessionUnfinishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeerSilenceViolationController extends Controller
{
    public function store(
        Request $request,
        Student $student,
        SpeechAnnouncementService $speechAnnouncementService,
        StudentPushUpCounterService $pushUpCounterService,
        TaskSessionUnfinishService $taskSessionUnfinishService,
    ): RedirectResponse {
        $reporter = $request->user()?->student;

        abort_unless($reporter !== null, 403);
        abort_if($reporter->id === $student->id || $student->status !== 'active', 404);

        $ruleDefinition = RuleDefinition::query()->firstOrCreate(
            [
                'title' => 'Keep silence',
                'scope' => 'global',
                'student_id' => null,
            ],
            [
                'description' => 'Student-reported violation: keep quiet during work time.',
                'default_penalty_units' => 50,
                'is_active' => true,
                'created_by_user_id' => $request->user()->id,
            ],
        );

        if (! $ruleDefinition->is_active) {
            $ruleDefinition->update(['is_active' => true]);
        }

        $result = DB::transaction(function () use ($request, $student, $reporter, $ruleDefinition, $pushUpCounterService) {
            $target = Student::query()
                ->whereKey($student->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $existingViolation = Violation::query()
                ->where('student_id', $target->id)
                ->where('rule_definition_id', $ruleDefinition->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingViolation) {
                return [
                    'type' => 'already_open',
                    'student' => $target,
                ];
            }

            $pushUpCount = $pushUpCounterService->allocateForViolation($target);

            $violation = Violation::create([
                'student_id' => $target->id,
                'rule_definition_id' => $ruleDefinition->id,
                'status' => 'open',
                'rule_title_snapshot' => $ruleDefinition->title,
                'penalty_units' => $pushUpCount,
                'occurred_at' => now(),
                'notes' => 'Reported by '.$reporter->display_name.' using the student silence button.',
                'reported_by_user_id' => $request->user()->id,
            ]);

            return [
                'type' => 'created',
                'student' => $target,
                'violation' => $violation,
            ];
        });

        /** @var \App\Models\Student $target */
        $target = $result['student'];

        if ($result['type'] === 'already_open') {
            return redirect()
                ->route('student.home')
                ->with('error', "{$target->display_name} already has an open Keep silence violation.");
        }

        /** @var \App\Models\Violation $violation */
        $violation = $result['violation'];

        $taskSessionUnfinishService->interruptActiveTaskForStudent($target, $request->user()->id);
        $speechAnnouncementService->queueViolation($violation);

        return redirect()
            ->route('student.home')
            ->with('success', "Keep silence violation created for {$target->display_name}.");
    }
}
