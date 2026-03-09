<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePenaltyTransactionRequest;
use App\Models\PenaltyAccount;
use App\Models\PenaltyTransaction;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PenaltyLedgerController extends Controller
{
    protected function ensurePenaltyAccount(Student $student): PenaltyAccount
    {
        return PenaltyAccount::query()->firstOrCreate([
            'student_id' => $student->id,
        ]);
    }

    protected function toStudentLedgerPayload(Student $student): array
    {
        $student->loadMissing(['user', 'penaltyAccount.transactions']);

        $penaltyAccount = $student->penaltyAccount;
        $transactions = $penaltyAccount?->transactions ?? collect();

        return [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'status' => $student->status,
                'is_active' => $student->user->is_active,
            ],
            'current_balance_units' => (int) $transactions->sum('delta_units'),
            'transaction_count' => $transactions->count(),
            'has_account' => $penaltyAccount !== null,
        ];
    }

    protected function toTransactionPayload(PenaltyTransaction $transaction): array
    {
        $transaction->loadMissing('createdBy');

        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'delta_units' => $transaction->delta_units,
            'recorded_at_label' => $transaction->recorded_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
            'notes' => $transaction->notes,
            'created_by' => $transaction->createdBy
                ? [
                    'id' => $transaction->createdBy->id,
                    'name' => $transaction->createdBy->name,
                    'username' => $transaction->createdBy->username,
                ]
                : null,
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Penalties/Index', [
            'studentLedgers' => Student::query()
                ->with(['user', 'penaltyAccount.transactions'])
                ->orderBy('display_name')
                ->get()
                ->map(fn (Student $student) => $this->toStudentLedgerPayload($student)),
        ]);
    }

    public function show(Student $student): Response
    {
        $penaltyAccount = $this->ensurePenaltyAccount($student);

        $student->loadMissing(['user']);
        $penaltyAccount->load([
            'transactions' => fn ($query) => $query->with('createdBy')->latest('recorded_at'),
        ]);

        return Inertia::render('Admin/Penalties/Show', [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'status' => $student->status,
                'is_active' => $student->user->is_active,
            ],
            'penaltyAccount' => [
                'id' => $penaltyAccount->id,
                'current_balance_units' => $penaltyAccount->currentBalanceUnits(),
                'transaction_count' => $penaltyAccount->transactions->count(),
            ],
            'transactions' => $penaltyAccount->transactions
                ->map(fn (PenaltyTransaction $transaction) => $this->toTransactionPayload($transaction)),
        ]);
    }

    public function store(StorePenaltyTransactionRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student) {
            $accountId = PenaltyAccount::query()->firstOrCreate([
                'student_id' => $student->id,
            ])->id;

            $penaltyAccount = PenaltyAccount::query()
                ->whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentBalanceUnits = (int) $penaltyAccount->transactions()->sum('delta_units');
            $transactionType = $request->string('transaction_type')->toString();
            $amountUnits = (int) $request->input('amount_units');

            if ($transactionType === 'manual_credit' && $amountUnits > $currentBalanceUnits) {
                throw ValidationException::withMessages([
                    'amount_units' => 'Сумма списания не может превышать текущий штрафной баланс.',
                ]);
            }

            $deltaUnits = $transactionType === 'manual_charge'
                ? $amountUnits
                : -$amountUnits;

            $penaltyAccount->transactions()->create([
                'type' => $transactionType,
                'delta_units' => $deltaUnits,
                'notes' => $request->input('notes'),
                'recorded_at' => now(),
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        $amountUnits = (int) $request->input('amount_units');
        $transactionType = $request->string('transaction_type')->toString();

        $message = $transactionType === 'manual_charge'
            ? "Начисление штрафа на {$amountUnits} ед. проведено."
            : "Списание штрафа на {$amountUnits} ед. проведено.";

        return redirect()
            ->route('admin.penalties.show', $student)
            ->with('success', $message);
    }
}
