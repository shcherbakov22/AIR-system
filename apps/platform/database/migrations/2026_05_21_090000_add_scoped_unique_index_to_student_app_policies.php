<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];
        $duplicateIds = [];

        DB::table('student_app_policies')
            ->select(['id', 'student_id', 'task_template_id', 'app_key'])
            ->orderBy('id')
            ->each(function ($policy) use (&$seen, &$duplicateIds) {
                $key = implode(':', [
                    $policy->student_id,
                    $policy->task_template_id ?? 0,
                    $policy->app_key,
                ]);

                if (isset($seen[$key])) {
                    $duplicateIds[] = $policy->id;
                    return;
                }

                $seen[$key] = true;
            });

        collect($duplicateIds)
            ->chunk(500)
            ->each(fn ($ids) => DB::table('student_app_policies')->whereIn('id', $ids)->delete());

        $driver = DB::getDriverName();
        $scopeExpression = $driver === 'mysql'
            ? '(coalesce(task_template_id, 0))'
            : 'coalesce(task_template_id, 0)';

        DB::statement(<<<SQL
            create unique index student_app_policies_student_scope_app_unique
            on student_app_policies (student_id, {$scopeExpression}, app_key)
        SQL);
    }

    public function down(): void
    {
        DB::statement('drop index if exists student_app_policies_student_scope_app_unique');
    }
};
