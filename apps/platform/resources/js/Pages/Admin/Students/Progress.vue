<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    serverNow: string;
    student: {
        id: number;
        display_name: string;
        username: string;
        status: string;
    };
    summary: {
        schedule_runs: number;
        completed_blocks: number;
        total_blocks: number;
        time_in_schedule_seconds: number;
        time_in_schedule_label: string;
        active_schedule_name?: string | null;
    };
    runs: Array<{
        id: number;
        status: string;
        schedule_name: string;
        weekday_label?: string | null;
        started_at?: string | null;
        started_at_label?: string | null;
        completed_at?: string | null;
        completed_at_label?: string | null;
        total_blocks: number;
        completed_blocks: number;
        total_planned_minutes: number;
        total_planned_duration_label: string;
        total_actual_duration_seconds: number;
        total_actual_duration_label: string;
        blocks: Array<{
            id: number;
            position: number;
            status: string;
            task_title: string;
            planned_duration_minutes: number;
            planned_duration_label: string;
            actual_duration_seconds: number;
            actual_duration_label: string;
            delta_seconds: number;
            delta_label: string;
            actual_started_at_label?: string | null;
            actual_ended_at_label?: string | null;
            session_logs: Array<{
                id: number;
                status: string;
                started_at_label?: string | null;
                ended_at_label?: string | null;
                duration_label: string;
            }>;
        }>;
    }>;
}>();
</script>

<template>
    <Head :title="`Progress: ${student.display_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Mentor dashboard
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Student progress
                    </h2>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('admin.students.edit', student.id)"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Edit student
                    </Link>
                    <Link
                        :href="route('admin.students.index')"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Back to students
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Student
                    </p>
                    <p class="mt-3 text-2xl font-semibold text-stone-950">
                        {{ student.display_name }}
                    </p>
                    <p class="mt-1 text-sm text-stone-500">
                        {{ student.username }}
                    </p>
                </div>

                <div class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Current schedule
                    </p>
                    <p class="mt-3 text-2xl font-semibold text-stone-950">
                        {{ summary.active_schedule_name || 'None active' }}
                    </p>
                    <p class="mt-1 text-sm text-stone-500">
                        {{ summary.schedule_runs }} total run{{ summary.schedule_runs === 1 ? '' : 's' }}
                    </p>
                </div>

                <div class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Blocks completed
                    </p>
                    <p class="mt-3 text-2xl font-semibold text-stone-950">
                        {{ summary.completed_blocks }} / {{ summary.total_blocks }}
                    </p>
                    <p class="mt-1 text-sm text-stone-500">
                        Across all tracked schedule runs
                    </p>
                </div>

                <div class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Time in schedule
                    </p>
                    <p class="mt-3 text-2xl font-semibold text-stone-950">
                        {{ summary.time_in_schedule_label }}
                    </p>
                    <p class="mt-1 text-sm text-stone-500">
                        Time spent inside schedule blocks
                    </p>
                </div>
            </div>

            <div v-if="runs.length === 0" class="mt-6 rounded-[2rem] bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200">
                <p class="text-lg font-semibold text-stone-950">
                    No schedule runs yet.
                </p>
                <p class="mt-2 text-sm text-stone-600">
                    Start a schedule as this student and the timing history will appear here.
                </p>
            </div>

            <div v-else class="mt-6 space-y-5">
                <article
                    v-for="run in runs"
                    :key="run.id"
                    class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Schedule run
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ run.schedule_name }}
                            </h3>
                            <div class="mt-2 flex flex-wrap gap-3 text-sm text-stone-600">
                                <span v-if="run.weekday_label">{{ run.weekday_label }}</span>
                                <span v-if="run.started_at_label">Started {{ run.started_at_label }}</span>
                                <span v-if="run.completed_at_label">Finished {{ run.completed_at_label }}</span>
                            </div>
                        </div>

                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                            :class="
                                run.status === 'completed'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : run.status === 'paused'
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-sky-100 text-sky-800'
                            "
                        >
                            {{ run.status }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-4">
                        <div class="rounded-[1.25rem] bg-stone-100 px-4 py-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Blocks
                            </p>
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                {{ run.completed_blocks }} / {{ run.total_blocks }}
                            </p>
                        </div>
                        <div class="rounded-[1.25rem] bg-stone-100 px-4 py-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Planned
                            </p>
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                {{ run.total_planned_duration_label }}
                            </p>
                        </div>
                        <div class="rounded-[1.25rem] bg-stone-100 px-4 py-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Actual
                            </p>
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                {{ run.total_actual_duration_label }}
                            </p>
                        </div>
                        <div class="rounded-[1.25rem] bg-stone-100 px-4 py-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Run status
                            </p>
                            <p class="mt-2 text-lg font-semibold capitalize text-stone-950">
                                {{ run.status }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-[1.5rem] border border-stone-200">
                        <div class="hidden bg-stone-100 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-stone-500 lg:grid lg:grid-cols-[5rem_minmax(0,1.6fr)_8rem_8rem_8rem_8rem_8rem] lg:gap-3">
                            <span>Block</span>
                            <span>Task</span>
                            <span>Status</span>
                            <span>Started</span>
                            <span>Ended</span>
                            <span>Planned</span>
                            <span>Actual</span>
                        </div>

                        <div class="divide-y divide-stone-200">
                            <details
                                v-for="block in run.blocks"
                                :key="block.id"
                                class="group"
                            >
                                <summary class="cursor-pointer list-none px-4 py-4 transition hover:bg-stone-50">
                                    <div class="grid gap-2 lg:grid-cols-[5rem_minmax(0,1.6fr)_8rem_8rem_8rem_8rem_8rem] lg:items-center lg:gap-3">
                                        <div class="text-sm font-semibold text-stone-950">
                                            {{ block.position }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-stone-950">
                                                {{ block.task_title }}
                                            </p>
                                            <p class="mt-1 text-xs text-stone-500 lg:hidden">
                                                {{ block.status }} - {{ block.actual_started_at_label || 'Not started' }} - {{ block.actual_duration_label }}
                                            </p>
                                        </div>
                                        <div class="hidden text-sm capitalize text-stone-700 lg:block">
                                            {{ block.status }}
                                        </div>
                                        <div class="hidden text-sm text-stone-700 lg:block">
                                            {{ block.actual_started_at_label || '-' }}
                                        </div>
                                        <div class="hidden text-sm text-stone-700 lg:block">
                                            {{ block.actual_ended_at_label || '-' }}
                                        </div>
                                        <div class="hidden text-sm text-stone-700 lg:block">
                                            {{ block.planned_duration_label }}
                                        </div>
                                        <div class="hidden text-sm font-medium text-stone-950 lg:block">
                                            {{ block.actual_duration_label }}
                                        </div>
                                    </div>
                                </summary>

                                <div class="border-t border-stone-200 bg-stone-50 px-4 py-4">
                                    <div class="flex flex-wrap gap-4 text-sm text-stone-700">
                                        <span>Planned {{ block.planned_duration_label }}</span>
                                        <span>Actual {{ block.actual_duration_label }}</span>
                                        <span :class="block.delta_seconds > 0 ? 'text-rose-700' : 'text-emerald-700'">
                                            Delta {{ block.delta_label }}
                                        </span>
                                    </div>

                                    <div class="mt-4 overflow-hidden rounded-[1.25rem] border border-stone-200 bg-white">
                                        <div class="grid grid-cols-[8rem_8rem_8rem_1fr] gap-3 bg-stone-100 px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
                                            <span>Started</span>
                                            <span>Ended</span>
                                            <span>Duration</span>
                                            <span>Status</span>
                                        </div>

                                        <div v-if="block.session_logs.length === 0" class="px-4 py-4 text-sm text-stone-500">
                                            No task session log for this block yet.
                                        </div>

                                        <div v-else class="divide-y divide-stone-200">
                                            <div
                                                v-for="sessionLog in block.session_logs"
                                                :key="sessionLog.id"
                                                class="grid grid-cols-[8rem_8rem_8rem_1fr] gap-3 px-4 py-3 text-sm text-stone-700"
                                            >
                                                <span>{{ sessionLog.started_at_label || '-' }}</span>
                                                <span>{{ sessionLog.ended_at_label || '-' }}</span>
                                                <span>{{ sessionLog.duration_label }}</span>
                                                <span class="capitalize">{{ sessionLog.status }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
