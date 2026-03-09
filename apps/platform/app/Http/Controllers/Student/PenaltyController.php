<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PenaltyTransaction;
use App\Models\Violation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PenaltyController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()
            ->loadMissing([
                'student.penaltyAccount.transactions.violation',
                'student.violations.ruleDefinition',
                'student.violations.penaltyTransactions',
            ])
            ->student;

        $transactions = $student?->penaltyAccount?->transactions
            ? $student->penaltyAccount->transactions
                ->sortByDesc('recorded_at')
                ->values()
            : collect();

        $violations = $student?->violations
            ? $student->violations
                ->sortByDesc('occurred_at')
                ->values()
            : collect();

        return Inertia::render('Student/Penalties/Index', [
            'student' => [
                'display_name' => $student?->display_name ?? $request->user()->name,
                'status' => $student?->status ?? 'pending',
            ],
            'penaltySummary' => [
                'current_balance_units' => $student?->penaltyAccount?->currentBalanceUnits() ?? 0,
                'open_violations' => $violations->where('status', 'open')->count(),
                'transaction_count' => $transactions->count(),
            ],
            'violations' => $violations
                ->map(fn (Violation $violation) => $this->toViolationPayload($violation))
                ->all(),
            'transactions' => $transactions
                ->map(fn (PenaltyTransaction $transaction) => $this->toTransactionPayload($transaction))
                ->all(),
        ]);
    }

    protected function toViolationPayload(Violation $violation): array
    {
        return [
            'id' => $violation->id,
            'status' => $violation->status,
            'rule_title' => $violation->rule_title_snapshot,
            'penalty_units' => $violation->penalty_units,
            'occurred_at_label' => $violation->occurred_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
            'notes' => $violation->notes,
            'rule_scope' => $violation->ruleDefinition?->scope,
            'posted_units' => (int) $violation->penaltyTransactions->sum('delta_units'),
        ];
    }

    protected function toTransactionPayload(PenaltyTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'delta_units' => $transaction->delta_units,
            'notes' => $transaction->notes,
            'recorded_at_label' => $transaction->recorded_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
            'violation' => $transaction->violation
                ? [
                    'id' => $transaction->violation->id,
                    'rule_title' => $transaction->violation->rule_title_snapshot,
                    'status' => $transaction->violation->status,
                ]
                : null,
        ];
    }
}
