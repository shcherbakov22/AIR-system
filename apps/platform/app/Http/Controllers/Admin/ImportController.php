<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportReconciliationIssue;
use App\Models\ImportRun;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Imports/Index', [
            'summary' => [
                'runs_total' => ImportRun::count(),
                'runs_active' => ImportRun::whereIn('status', ['pending', 'running'])->count(),
                'issues_open' => ImportReconciliationIssue::where('status', 'open')->count(),
            ],
            'importRuns' => ImportRun::query()
                ->withCount(['legacyRecordLinks', 'reconciliationIssues'])
                ->with(['startedByUser:id,username,name'])
                ->latest('id')
                ->get()
                ->map(fn (ImportRun $importRun) => [
                    'id' => $importRun->id,
                    'source_system' => $importRun->source_system,
                    'source_label' => $importRun->source_label,
                    'status' => $importRun->status,
                    'started_at' => $importRun->started_at?->toAtomString(),
                    'started_at_label' => $importRun->started_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                    'finished_at' => $importRun->finished_at?->toAtomString(),
                    'finished_at_label' => $importRun->finished_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                    'notes' => $importRun->notes,
                    'summary' => $importRun->summary,
                    'legacy_record_links_count' => $importRun->legacy_record_links_count,
                    'reconciliation_issues_count' => $importRun->reconciliation_issues_count,
                    'started_by_user' => $importRun->startedByUser ? [
                        'id' => $importRun->startedByUser->id,
                        'username' => $importRun->startedByUser->username,
                        'name' => $importRun->startedByUser->name,
                    ] : null,
                ]),
            'openIssues' => ImportReconciliationIssue::query()
                ->with('importRun:id,source_system,source_label')
                ->where('status', 'open')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (ImportReconciliationIssue $issue) => [
                    'id' => $issue->id,
                    'severity' => $issue->severity,
                    'status' => $issue->status,
                    'summary' => $issue->summary,
                    'details' => $issue->details,
                    'legacy_system' => $issue->legacy_system,
                    'legacy_table' => $issue->legacy_table,
                    'legacy_key' => $issue->legacy_key,
                    'import_run' => $issue->importRun ? [
                        'id' => $issue->importRun->id,
                        'source_system' => $issue->importRun->source_system,
                        'source_label' => $issue->importRun->source_label,
                    ] : null,
                ]),
        ]);
    }
}
