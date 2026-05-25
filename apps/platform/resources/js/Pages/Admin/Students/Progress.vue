<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const markTaskSessionUnfinished = (unfinishedUrl?: string | null) => {
    if (!unfinishedUrl) {
        return;
    }

    router.patch(unfinishedUrl, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

type LookAwayEvent = {
    id: number;
    occurred_at?: string | null;
    occurred_at_label?: string | null;
    reason?: string | null;
    score?: number | string | null;
    away_seconds?: number | string | null;
};

const lookAwayEventLabel = (event: LookAwayEvent) => {
    const details = [
        event.away_seconds !== null && event.away_seconds !== undefined ? `${event.away_seconds}s` : null,
        event.reason,
        event.score !== null && event.score !== undefined ? `score ${event.score}` : null,
    ].filter(Boolean);

    return [event.occurred_at_label ?? 'Time unknown', details.join(' · ')].filter(Boolean).join(' · ');
};

defineProps<{
    serverNow: string;
    student: {
        id: number;
        display_name: string;
        username: string;
        status: string;
    };
    filters: {
        mode: 'runs' | 'summary';
        day: string;
    };
    available_days: Array<{
        value: string;
        label: string;
    }>;
    summary: {
        schedule_runs: number;
        completed_blocks: number;
        total_blocks: number;
        time_in_schedule_seconds: number;
        time_in_schedule_label: string;
        active_schedule_name?: string | null;
    };
    task_summary: Array<{
        task_title: string;
        blocks: number;
        completed_blocks: number;
        total_planned_minutes: number;
        total_planned_duration_label: string;
        total_actual_duration_seconds: number;
        total_actual_duration_label: string;
        look_away_event_count: number;
    }>;
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
        task_sequence: Array<{
            id: number | string;
            kind: 'task' | 'idle_gap';
            status: string;
            task_title: string;
            planned_duration_minutes: number;
            planned_duration_label: string;
            actual_duration_seconds: number;
            actual_duration_label: string;
            delta_seconds: number;
            delta_label: string;
            started_at_label?: string | null;
            ended_at_label?: string | null;
            was_in_schedule: boolean;
            block_position?: number | null;
            look_away_event_count: number;
            look_away_events: LookAwayEvent[];
            unfinished_url?: string | null;
        }>;
    }>;
}>();
</script>

<template>
    <Head :title="`Progress: ${student.display_name}`" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('admin.students.progress', { student: student.id, day: filters.day, mode: 'runs' })"
                        class="inline-flex rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="filters.mode === 'runs' ? 'bg-stone-950 text-white' : 'bg-white text-stone-700 ring-1 ring-stone-200 hover:bg-stone-50'"
                    >
                        Runs
                    </Link>
                    <Link
                        :href="route('admin.students.progress', { student: student.id, day: filters.day, mode: 'summary' })"
                        class="inline-flex rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="filters.mode === 'summary' ? 'bg-stone-950 text-white' : 'bg-white text-stone-700 ring-1 ring-stone-200 hover:bg-stone-50'"
                    >
                        Task totals
                    </Link>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-for="day in available_days"
                        :key="day.value"
                        :href="route('admin.students.progress', { student: student.id, day: day.value, mode: filters.mode })"
                        class="inline-flex rounded-full px-3 py-2 text-sm transition"
                        :class="filters.day === day.value ? 'bg-amber-200 text-stone-950' : 'bg-white text-stone-600 ring-1 ring-stone-200 hover:bg-stone-50'"
                    >
                        {{ day.label }}
                    </Link>
                </div>
            </div>

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

            <div v-if="runs.length === 0 && filters.mode === 'runs'" class="mt-6 rounded-[2rem] bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200">
                <p class="text-lg font-semibold text-stone-950">
                    No schedule runs for this day.
                </p>
                <p class="mt-2 text-sm text-stone-600">
                    Pick another day or start a schedule as this student and the timing history will appear here.
                </p>
            </div>

            <div v-else-if="filters.mode === 'summary'" class="mt-6 overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm">
                <div class="grid grid-cols-[minmax(0,1.5fr)_8rem_8rem_8rem_8rem_8rem] gap-3 bg-stone-100 px-5 py-4 text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">
                    <span>Task</span>
                    <span>Blocks</span>
                    <span>Done</span>
                    <span>Planned</span>
                    <span>Actual</span>
                    <span>Looked away</span>
                </div>

                <div v-if="task_summary.length === 0" class="px-5 py-6 text-sm text-stone-500">
                    No tracked task time for this day.
                </div>

                <div v-else class="divide-y divide-stone-200">
                    <div
                        v-for="task in task_summary"
                        :key="task.task_title"
                        class="grid grid-cols-[minmax(0,1.5fr)_8rem_8rem_8rem_8rem_8rem] gap-3 px-5 py-4 text-sm text-stone-700"
                    >
                        <span class="font-medium text-stone-950">{{ task.task_title }}</span>
                        <span>{{ task.blocks }}</span>
                        <span>{{ task.completed_blocks }}</span>
                        <span>{{ task.total_planned_duration_label }}</span>
                        <span class="font-medium text-stone-950">{{ task.total_actual_duration_label }}</span>
                        <span class="font-medium text-stone-950">{{ task.look_away_event_count }}</span>
                    </div>
                </div>
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
                                        ? 'bg-stone-950 text-white'
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
                        <div class="hidden bg-stone-100 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-stone-500 lg:grid lg:grid-cols-[5rem_minmax(0,1.6fr)_8rem_8rem_8rem_8rem_8rem_8rem] lg:gap-3">
                            <span>#</span>
                            <span>Task</span>
                            <span>Source</span>
                            <span>Started</span>
                            <span>Ended</span>
                            <span>Planned</span>
                            <span>Actual</span>
                            <span>Looked away</span>
                        </div>

                        <div class="divide-y divide-stone-200">
                            <div
                                v-for="(task, index) in run.task_sequence"
                                :key="task.id"
                                class="px-4 py-4 transition hover:bg-stone-50"
                                :class="task.kind === 'idle_gap'
                                    ? 'bg-stone-200/80 text-stone-900 hover:bg-stone-200'
                                    : task.status === 'paused' || task.status === 'unfinished'
                                        ? 'bg-stone-950 text-white hover:bg-stone-900'
                                        : ''"
                            >
                                <div class="grid gap-2 lg:grid-cols-[5rem_minmax(0,1.6fr)_8rem_8rem_8rem_8rem_8rem_8rem] lg:items-center lg:gap-3">
                                    <div class="text-sm font-semibold" :class="task.kind === 'idle_gap'
                                        ? 'text-stone-700'
                                        : task.status === 'paused' || task.status === 'unfinished'
                                            ? 'text-white'
                                            : 'text-stone-950'">
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium" :class="task.kind === 'idle_gap'
                                            ? 'text-stone-900'
                                            : task.status === 'paused' || task.status === 'unfinished'
                                                ? 'text-white'
                                                : 'text-stone-950'">
                                            {{ task.task_title }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap gap-3 text-xs" :class="task.kind === 'idle_gap'
                                            ? 'text-stone-600'
                                            : task.status === 'paused' || task.status === 'unfinished'
                                                ? 'text-stone-300'
                                                : 'text-stone-500'">
                                            <span>{{ task.kind === 'idle_gap' ? 'Gap' : (task.was_in_schedule ? `Block ${task.block_position}` : 'Outside schedule') }}</span>
                                            <span>{{ task.started_at_label || 'Not started' }}</span>
                                            <span>{{ task.actual_duration_label }}</span>
                                        </div>
                                    </div>
                                    <div class="hidden text-sm lg:block" :class="task.kind === 'idle_gap'
                                        ? 'text-stone-700'
                                        : task.status === 'paused' || task.status === 'unfinished'
                                            ? 'text-stone-200'
                                            : 'text-stone-700'">
                                        {{ task.kind === 'idle_gap' ? 'Gap' : (task.was_in_schedule ? `Block ${task.block_position}` : 'Outside') }}
                                    </div>
                                    <div class="hidden text-sm lg:block" :class="task.kind === 'idle_gap'
                                        ? 'text-stone-700'
                                        : task.status === 'paused' || task.status === 'unfinished'
                                            ? 'text-stone-200'
                                            : 'text-stone-700'">
                                        {{ task.started_at_label || '-' }}
                                    </div>
                                    <div class="hidden text-sm lg:block" :class="task.kind === 'idle_gap'
                                        ? 'text-stone-700'
                                        : task.status === 'paused' || task.status === 'unfinished'
                                            ? 'text-stone-200'
                                            : 'text-stone-700'">
                                        {{ task.ended_at_label || '-' }}
                                    </div>
                                    <div class="hidden text-sm lg:block" :class="task.kind === 'idle_gap'
                                        ? 'text-stone-700'
                                        : task.status === 'paused' || task.status === 'unfinished'
                                            ? 'text-stone-200'
                                            : 'text-stone-700'">
                                        {{ task.kind === 'idle_gap' ? '—' : task.planned_duration_label }}
                                    </div>
                                    <button
                                        v-if="task.unfinished_url"
                                        type="button"
                                        class="hidden text-left text-sm font-medium underline-offset-2 lg:block"
                                        :class="task.status === 'paused' || task.status === 'unfinished' ? 'text-white hover:underline' : 'text-stone-950 hover:underline'"
                                        @click="markTaskSessionUnfinished(task.unfinished_url)"
                                    >
                                        {{ task.actual_duration_label }}
                                    </button>
                                    <div
                                        v-else
                                        class="hidden text-sm font-medium lg:block"
                                        :class="task.status === 'paused' || task.status === 'unfinished' ? 'text-white' : 'text-stone-950'"
                                    >
                                        {{ task.actual_duration_label }}
                                    </div>
                                    <div
                                        class="hidden text-sm font-medium lg:block"
                                        :class="task.status === 'paused' || task.status === 'unfinished' ? 'text-white' : 'text-stone-950'"
                                    >
                                        {{ task.kind === 'idle_gap' ? '-' : task.look_away_event_count }}
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-4 text-sm" :class="task.kind === 'idle_gap'
                                    ? 'text-stone-700'
                                    : task.status === 'paused' || task.status === 'unfinished'
                                        ? 'text-stone-200'
                                        : 'text-stone-700'">
                                    <span class="capitalize">{{ task.status }}</span>
                                    <span v-if="task.kind === 'idle_gap'" class="text-stone-700">
                                        Did nothing for {{ task.actual_duration_label }}
                                    </span>
                                    <span v-else :class="task.delta_seconds > 0 ? 'text-rose-700' : 'text-emerald-700'">
                                        Delta {{ task.delta_label }}
                                    </span>
                                    <span v-if="task.kind !== 'idle_gap'">
                                        Looked away {{ task.look_away_event_count }}
                                    </span>
                                    <button
                                        v-if="task.unfinished_url"
                                        type="button"
                                        class="text-left font-semibold underline-offset-2 hover:underline lg:hidden"
                                        :class="task.status === 'paused' || task.status === 'unfinished' ? 'text-white' : 'text-stone-900'"
                                        @click="markTaskSessionUnfinished(task.unfinished_url)"
                                    >
                                        Mark unfinished
                                    </button>
                                </div>

                                <div
                                    v-if="task.kind !== 'idle_gap' && task.look_away_events.length > 0"
                                    class="mt-3 flex flex-wrap gap-2 text-xs"
                                    :class="task.status === 'paused' || task.status === 'unfinished' ? 'text-stone-100' : 'text-stone-600'"
                                >
                                    <span
                                        v-for="event in task.look_away_events"
                                        :key="event.id"
                                        class="rounded-full px-2.5 py-1"
                                        :class="task.status === 'paused' || task.status === 'unfinished' ? 'bg-white/15' : 'bg-stone-100'"
                                    >
                                        {{ lookAwayEventLabel(event) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
