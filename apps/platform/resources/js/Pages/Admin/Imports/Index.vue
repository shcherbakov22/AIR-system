<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelImportIssueSeverity, labelImportRunStatus } from '@/lib/labels';
import { Head } from '@inertiajs/vue3';

defineProps<{
    summary: {
        runs_total: number;
        runs_active: number;
        issues_open: number;
    };
    importRuns: Array<{
        id: number;
        source_system: string;
        source_label: string;
        status: string;
        started_at?: string | null;
        started_at_label?: string | null;
        finished_at?: string | null;
        finished_at_label?: string | null;
        notes?: string | null;
        summary?: Record<string, unknown> | null;
        legacy_record_links_count: number;
        reconciliation_issues_count: number;
        started_by_user?: {
            id: number;
            username: string;
            name: string;
        } | null;
    }>;
    openIssues: Array<{
        id: number;
        severity: string;
        status: string;
        summary: string;
        details?: string | null;
        legacy_system?: string | null;
        legacy_table?: string | null;
        legacy_key?: string | null;
        import_run?: {
            id: number;
            source_system: string;
            source_label: string;
        } | null;
    }>;
}>();
</script>

<template>
    <Head title="Импорт" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Панель администратора
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Импорт
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="grid gap-5 md:grid-cols-3">
                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Запуски импорта
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        {{ summary.runs_total }}
                    </h3>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Зафиксированные попытки импорта и этапы сверки данных.
                    </p>
                </section>

                <section class="rounded-[2rem] bg-stone-950 p-8 text-white shadow-sm">
                    <p class="text-xs uppercase tracking-[0.25em] text-amber-300/70">
                        Активные запуски
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-white">
                        {{ summary.runs_active }}
                    </h3>
                    <p class="mt-3 text-sm leading-7 text-stone-300">
                        Импорт, который ещё выполняется или ждёт внимания.
                    </p>
                </section>

                <section class="rounded-[2rem] bg-amber-50 p-8 shadow-sm ring-1 ring-amber-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-amber-800/70">
                        Открытые проблемы
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        {{ summary.issues_open }}
                    </h3>
                    <p class="mt-3 text-sm leading-7 text-stone-700">
                        Проблемы сверки, по которым ещё нужно принять решение.
                    </p>
                </section>
            </div>

            <section class="mt-5 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-3xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                        История импорта
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Отслеживаемые запуски
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Это слой сверки для переноса старых данных. Он отслеживает запуски,
                        связи записей и нерешённые проблемы импорта, не влияя на основную схему продукта.
                    </p>
                </div>

                <div v-if="importRuns.length === 0" class="mt-8 rounded-[1.5rem] bg-stone-100 px-6 py-8">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Запусков импорта пока нет
                    </p>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Таблицы аудита импорта готовы, но запуски переноса старых данных ещё не зафиксированы.
                    </p>
                </div>

                <div v-else class="mt-8 space-y-5">
                    <article
                        v-for="importRun in importRuns"
                        :key="importRun.id"
                        class="rounded-[1.75rem] bg-stone-100 p-6"
                    >
                        <div class="flex flex-col gap-4 border-b border-stone-200 pb-5 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    {{ importRun.source_system }}
                                </p>
                                <h4 class="mt-2 text-2xl font-semibold text-stone-950">
                                    {{ importRun.source_label }}
                                </h4>
                                <p class="mt-3 text-sm leading-7 text-stone-600">
                                    {{ importRun.notes || 'Заметки по импорту не указаны.' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-800">
                                    {{ labelImportRunStatus(importRun.status) }}
                                </span>
                                <span class="text-sm text-stone-600">
                                    {{ importRun.started_at_label || 'Не запущено' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <div class="rounded-[1.25rem] bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Связанные записи
                                </p>
                                <p class="mt-3 text-2xl font-semibold text-stone-950">
                                    {{ importRun.legacy_record_links_count }}
                                </p>
                            </div>

                            <div class="rounded-[1.25rem] bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Проблемы сверки
                                </p>
                                <p class="mt-3 text-2xl font-semibold text-stone-950">
                                    {{ importRun.reconciliation_issues_count }}
                                </p>
                            </div>

                            <div class="rounded-[1.25rem] bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Запустил
                                </p>
                                <p class="mt-3 text-lg font-semibold text-stone-950">
                                    {{ importRun.started_by_user?.username || 'Система / неизвестно' }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="mt-5 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                    Сверка
                </p>
                <h3 class="mt-4 font-serif text-3xl text-stone-950">
                    Открытые проблемы
                </h3>

                <div v-if="openIssues.length === 0" class="mt-8 rounded-[1.5rem] bg-stone-100 px-6 py-8">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Открытых проблем нет
                    </p>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Сейчас нет нерешённых записей сверки.
                    </p>
                </div>

                <div v-else class="mt-8 space-y-4">
                    <article
                        v-for="issue in openIssues"
                        :key="issue.id"
                        class="rounded-[1.5rem] bg-stone-100 px-5 py-5"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-800">
                                {{ labelImportIssueSeverity(issue.severity) }}
                            </span>
                            <span class="text-xs uppercase tracking-[0.2em] text-stone-500">
                                {{ issue.import_run?.source_label || 'Неизвестный запуск' }}
                            </span>
                        </div>
                        <h4 class="mt-3 text-lg font-semibold text-stone-950">
                            {{ issue.summary }}
                        </h4>
                        <p class="mt-2 text-sm leading-7 text-stone-600">
                            {{ issue.details || 'Дополнительные детали проблемы не указаны.' }}
                        </p>
                        <p class="mt-3 text-xs uppercase tracking-[0.2em] text-stone-500">
                            {{ issue.legacy_system || 'неизвестно' }} / {{ issue.legacy_table || 'неизвестно' }} / {{ issue.legacy_key || 'неизвестно' }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
