<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrowserAccessRequest;
use App\Models\BrowserPolicyRule;
use App\Models\BrowserVisitLog;
use App\Models\Student;
use App\Services\BrowserAccountabilityPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BrowserAccountabilityController extends Controller
{
    public function updateMode(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:whitelist,blacklist'],
        ]);

        $student->devices()
            ->whereNull('revoked_at')
            ->update(['internet_access_mode' => $validated['mode']]);

        return back()->with('success', "Browser mode set to {$validated['mode']}.");
    }

    public function storeRule(
        Request $request,
        Student $student,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): RedirectResponse {
        $validated = $request->validate([
            'effect' => ['required', 'in:allow,block'],
            'value' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $rule = $browserPolicyService->upsertRule(
            $student,
            $validated['effect'],
            $validated['value'],
            $request->user(),
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
        );

        return back()->with('success', "{$rule->value} and subdomains {$rule->effect} rule saved.");
    }

    public function destroyRule(Student $student, BrowserPolicyRule $browserPolicyRule): RedirectResponse
    {
        abort_unless($browserPolicyRule->student_id === $student->id, 404);

        $value = $browserPolicyRule->value;
        $browserPolicyRule->delete();

        return back()->with('success', "Browser rule for {$value} removed.");
    }

    public function approveRequest(
        Request $request,
        Student $student,
        BrowserAccessRequest $browserAccessRequest,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): RedirectResponse {
        abort_unless($browserAccessRequest->student_id === $student->id, 404);

        $validated = $request->validate([
            'expires_at' => ['nullable', 'date'],
            'mentor_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $rule = $browserPolicyService->approveRequest(
            $browserAccessRequest,
            $request->user(),
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
            $validated['mentor_note'] ?? null,
        );

        return back()->with('success', "Allowed {$rule->value} and subdomains.");
    }

    public function denyRequest(
        Request $request,
        Student $student,
        BrowserAccessRequest $browserAccessRequest,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): RedirectResponse {
        abort_unless($browserAccessRequest->student_id === $student->id, 404);

        $validated = $request->validate([
            'mentor_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $browserPolicyService->denyRequest(
            $browserAccessRequest,
            $request->user(),
            $validated['mentor_note'] ?? null,
        );

        return back()->with('success', 'Browser access request denied.');
    }

    public function destroyRequest(
        Request $request,
        Student $student,
        BrowserAccessRequest $browserAccessRequest,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): RedirectResponse {
        abort_unless($browserAccessRequest->student_id === $student->id, 404);

        $domain = $browserAccessRequest->registrable_domain;

        if ($browserAccessRequest->status === 'pending') {
            $browserPolicyService->denyRequest(
                $browserAccessRequest,
                $request->user(),
                'Denied and hidden from the extension page.',
            );
        }

        $browserAccessRequest->delete();

        return back()->with('success', "Denied and hid {$domain} request.");
    }

    public function destroyHistoryLog(Student $student, BrowserVisitLog $browserVisitLog): RedirectResponse
    {
        abort_unless($browserVisitLog->student_id === $student->id, 404);

        $host = $browserVisitLog->host;
        $browserVisitLog->delete();

        return back()->with('success', "Removed {$host} from browser history.");
    }

    public function destroyHistory(Student $student): RedirectResponse
    {
        $deleted = BrowserVisitLog::query()
            ->where('student_id', $student->id)
            ->delete();

        return back()->with('success', "Cleared {$deleted} browser history entr".($deleted === 1 ? 'y' : 'ies').'.');
    }
}
