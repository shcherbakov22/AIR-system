<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

type DashboardBlock = {
    id: number;
    position: number;
    status: string;
    status_label: string;
    task_title: string;
    planned_duration_minutes: number;
    planned_duration_label: string;
    actual_duration_seconds: number;
    actual_duration_label: string;
    display_duration_label: string;
    display_duration_caption: string;
    started_at?: string | null;
    started_at_label?: string | null;
    completed_at?: string | null;
    completed_at_label?: string | null;
};

type DashboardStudent = {
    id: number;
    display_name: string;
    status: string;
    user: {
        id: number;
        username: string;
        last_login_at?: string | null;
    };
    schedule_board?: {
        id: number;
        source_type: string;
        source_label: string;
        name: string;
        status: string;
        status_label: string;
        started_at?: string | null;
        started_at_label?: string | null;
        completed_at?: string | null;
        completed_at_label?: string | null;
        completed_blocks: number;
        total_blocks: number;
        blocks: DashboardBlock[];
    } | null;
    active_task_session?: {
        id: number;
        task_title: string;
        started_at?: string | null;
        started_at_label?: string | null;
        duration_seconds?: number | null;
        planned_duration_minutes?: number | null;
        source_type: string;
        schedule_run?: {
            id: number;
            name: string;
        } | null;
        schedule_run_block?: {
            position: number;
        } | null;
    } | null;
    latest_screen_capture?: {
        id: number;
        captured_at_label?: string | null;
        image_url: string;
    } | null;
    latest_camera_capture?: {
        id: number;
        captured_at_label?: string | null;
        image_url: string;
    } | null;
    open_violations: Array<{
        id: number;
        rule_title: string;
        occurred_at_label?: string | null;
    }>;
};

const props = defineProps<{
    serverNow: string;
    ruleDefinitions: Array<{
        id: number;
        title: string;
    }>;
    monitorStudents: DashboardStudent[];
}>();

const selectedRules = reactive<Record<number, string>>({});

const parseTimestamp = (value?: string | null): number | null => {
    if (!value) {
        return null;
    }

    const parsed = Date.parse(value);

    return Number.isNaN(parsed) ? null : parsed;
};

const formatDuration = (totalSeconds: number): string => {
    const safeSeconds = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

const serverNowMs = ref(parseTimestamp(props.serverNow) ?? Date.now());
const clientBaselineMs = ref(Date.now());
const liveNowMs = ref(serverNowMs.value);

const syncLiveNow = () => {
    liveNowMs.value = serverNowMs.value + (Date.now() - clientBaselineMs.value);
};

watch(
    () => props.serverNow,
    (serverNow) => {
        serverNowMs.value = parseTimestamp(serverNow) ?? Date.now();
        clientBaselineMs.value = Date.now();
        syncLiveNow();
    },
    { immediate: true },
);

let clockInterval: number | null = null;
let reloadInterval: number | null = null;

onMounted(() => {
    syncLiveNow();
    clockInterval = window.setInterval(syncLiveNow, 1000);
    reloadInterval = window.setInterval(() => {
        router.reload({
            only: ['serverNow', 'monitorStudents'],
        });
    }, 30000);
});

onBeforeUnmount(() => {
    if (clockInterval !== null) {
        window.clearInterval(clockInterval);
    }

    if (reloadInterval !== null) {
        window.clearInterval(reloadInterval);
    }
});

const monitorStudents = computed(() => {
    const liveDeltaSeconds = Math.max(0, Math.floor((liveNowMs.value - serverNowMs.value) / 1000));

    return props.monitorStudents.map((student) => {
        const taskSession = student.active_task_session;
        const startedAtMs = parseTimestamp(taskSession?.started_at ?? null);
        const elapsedSeconds = !taskSession
            ? null
            : startedAtMs === null
                ? taskSession.duration_seconds ?? 0
                : Math.max(
                    taskSession.duration_seconds ?? 0,
                    (taskSession.duration_seconds ?? 0) + Math.floor((liveNowMs.value - startedAtMs) / 1000),
                );
        const plannedSeconds = taskSession?.planned_duration_minutes
            ? taskSession.planned_duration_minutes * 60
            : null;
        const remainingSeconds = elapsedSeconds === null || plannedSeconds === null
            ? null
            : Math.max(plannedSeconds - elapsedSeconds, 0);
        const scheduleBoard = student.schedule_board
            ? {
                ...student.schedule_board,
                blocks: student.schedule_board.blocks.map((block) => {
                    const liveActualSeconds = block.status === 'in_progress'
                        ? block.actual_duration_seconds + liveDeltaSeconds
                        : block.actual_duration_seconds;
                    const usesPlannedTime = block.status === 'pending' || block.status === 'planned';

                    return {
                        ...block,
                        displayDurationLabel: usesPlannedTime
                            ? block.planned_duration_label
                            : formatDuration(liveActualSeconds),
                        displayDurationCaption: usesPlannedTime ? 'Planned' : 'Spent',
                    };
                }),
            }
            : null;

        return {
            ...student,
            schedule_board: scheduleBoard,
            active_task_session: taskSession
                ? {
                    ...taskSession,
                    elapsedLabel: elapsedSeconds === null ? null : formatDuration(elapsedSeconds),
                    remainingLabel: remainingSeconds === null ? null : formatDuration(remainingSeconds),
                }
                : null,
        };
    });
});

const createViolation = (studentId: number) => {
    const selectedRuleId = Number(selectedRules[studentId] ?? '');

    if (!selectedRuleId) {
        return;
    }

    const now = new Date();
    now.setSeconds(0, 0);

    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60_000);

    router.post(route('admin.violations.store'), {
        student_id: studentId,
        rule_definition_id: selectedRuleId,
        occurred_at: local.toISOString().slice(0, 16),
        notes: '',
        return_to_dashboard: true,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            selectedRules[studentId] = '';
        },
    });
};

const deleteViolation = (violationId: number) => {
    if (!window.confirm('Delete this violation?')) {
        return;
    }

    router.delete(route('admin.violations.destroy', violationId), {
        data: {
            return_to_dashboard: true,
        },
        preserveScroll: true,
        preserveState: true,
    });
};

const studentStatusClass = (student: DashboardStudent): string => {
    if (student.active_task_session) {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (student.schedule_board) {
        return 'bg-sky-100 text-sky-800';
    }

    return 'bg-stone-200 text-stone-700';
};

const scheduleSourceClass = (sourceType: string): string => {
    if (sourceType === 'run') {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-stone-200 text-stone-700';
};

const blockRowClass = (status: string): string => {
    if (status === 'completed') {
        return 'border-emerald-200 bg-emerald-50';
    }

    if (status === 'in_progress') {
        return 'border-amber-300 bg-amber-50';
    }

    if (status === 'paused') {
        return 'border-sky-300 bg-sky-50';
    }

    return 'border-stone-200 bg-white';
};

const blockTooltip = (block: DashboardBlock): string => {
    const parts = [
        `Block ${block.position}: ${block.task_title}`,
        block.status_label,
        `${block.display_duration_caption}: ${block.display_duration_label}`,
    ];

    if (block.completed_at_label) {
        parts.push(`Finished ${block.completed_at_label}`);
    } else if (block.started_at_label) {
        parts.push(`Started ${block.started_at_label}`);
    }

    return parts.join(' - ');
};
</script>

<template>
    <Head title="Mentor monitor" />

    <AuthenticatedLayout :hide-sidebar="true" :full-width="true">
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Mentor dashboard
                    </p>
                    <h2 class="font-serif text-3xl leading-none text-stone-950">
                        Schedule columns
                    </h2>
                </div>

                <div class="flex flex-col items-start gap-2 lg:items-end">
                    <p class="text-sm text-stone-500">
                        Column view for all students. Each card keeps the schedule readable without the page turning into a wall of rows.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('admin.students.index')"
                            class="rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                        >
                            Students
                        </Link>
                        <Link
                            :href="route('admin.rule-definitions.index')"
                            class="rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                        >
                            Rules
                        </Link>
                        <Link
                            :href="route('profile.edit')"
                            class="rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                        >
                            Profile
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                        >
                            Log out
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="px-4 py-3 sm:px-5 lg:px-6">
            <div
                v-if="monitorStudents.length === 0"
                class="rounded-[2rem] bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200"
            >
                <p class="text-lg font-semibold text-stone-950">
                    No students are available yet.
                </p>
            </div>

            <div
                v-else
                class="grid gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-7"
            >
                <article
                    v-for="student in monitorStudents"
                    :key="student.id"
                    class="relative flex h-[24rem] min-h-0 flex-col rounded-[1.5rem] bg-white p-3 shadow-sm ring-1 ring-stone-200 lg:h-[calc(100vh-9.5rem)]"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-base font-semibold text-stone-950">
                                    {{ student.display_name }}
                                </p>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em]"
                                    :class="studentStatusClass(student)"
                                >
                                    {{ student.active_task_session ? 'Live' : student.schedule_board ? 'Loaded' : 'None' }}
                                </span>
                            </div>

                            <p class="truncate text-xs text-stone-500">
                                {{ student.user.username }}
                            </p>
                        </div>

                        <details class="group relative shrink-0">
                            <summary class="cursor-pointer list-none rounded-full border border-stone-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-stone-700 transition hover:border-stone-900 hover:text-stone-950">
                                Details
                            </summary>

                            <div class="absolute right-0 top-full z-20 mt-2 w-[19rem] rounded-[1.25rem] bg-white p-3 shadow-xl ring-1 ring-stone-200">
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        :href="route('admin.students.progress', student.id)"
                                        class="rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                                    >
                                        Progress
                                    </Link>
                                    <a
                                        v-if="student.latest_screen_capture"
                                        :href="student.latest_screen_capture.image_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                                    >
                                        Screen
                                    </a>
                                    <a
                                        v-if="student.latest_camera_capture"
                                        :href="student.latest_camera_capture.image_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                                    >
                                        Camera
                                    </a>
                                </div>

                                <div class="mt-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-stone-500">
                                        Violations
                                    </p>
                                    <div class="mt-2 flex gap-2">
                                        <select
                                            v-model="selectedRules[student.id]"
                                            class="min-w-0 flex-1 rounded-full border-stone-300 px-4 py-2 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                        >
                                            <option value="">
                                                Add rule...
                                            </option>
                                            <option
                                                v-for="ruleDefinition in props.ruleDefinitions"
                                                :key="ruleDefinition.id"
                                                :value="String(ruleDefinition.id)"
                                            >
                                                {{ ruleDefinition.title }}
                                            </option>
                                        </select>

                                        <button
                                            type="button"
                                            class="inline-flex rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
                                            :disabled="!selectedRules[student.id]"
                                            @click="createViolation(student.id)"
                                        >
                                            Add
                                        </button>
                                    </div>

                                    <div v-if="student.open_violations.length > 0" class="mt-2 space-y-2">
                                        <div
                                            v-for="violation in student.open_violations"
                                            :key="violation.id"
                                            class="flex items-center justify-between gap-3 rounded-[1rem] bg-stone-100 px-3 py-2"
                                        >
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-stone-900">
                                                    {{ violation.rule_title }}
                                                </p>
                                                <p class="text-xs text-stone-500">
                                                    {{ violation.occurred_at_label }}
                                                </p>
                                            </div>

                                            <button
                                                type="button"
                                                class="inline-flex rounded-full border border-stone-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                                @click="deleteViolation(violation.id)"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>

                                    <p v-else class="mt-2 text-sm text-stone-600">
                                        No open violations.
                                    </p>
                                </div>
                            </div>
                        </details>
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-if="student.schedule_board"
                            class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em]"
                            :class="scheduleSourceClass(student.schedule_board.source_type)"
                        >
                            {{ student.schedule_board.source_label }}
                        </span>
                        <span v-if="student.open_violations.length > 0" class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-red-700">
                            {{ student.open_violations.length }} open
                        </span>
                    </div>

                    <div class="mt-2 min-w-0">
                        <p class="truncate text-sm font-medium text-stone-900">
                            {{ student.schedule_board?.name ?? 'No schedule' }}
                        </p>
                        <p v-if="student.schedule_board" class="truncate text-xs text-stone-500">
                            {{ student.schedule_board.completed_blocks }}/{{ student.schedule_board.total_blocks }} blocks - {{ student.schedule_board.status_label }}
                        </p>
                    </div>

                    <div class="mt-2 rounded-[1rem] bg-stone-100 px-3 py-2">
                        <p class="truncate text-sm font-medium text-stone-900">
                            {{ student.active_task_session?.task_title ?? 'No active task' }}
                        </p>
                        <p class="truncate text-xs text-stone-500">
                            <template v-if="student.active_task_session">
                                {{ student.active_task_session.elapsedLabel }}
                                <span v-if="student.active_task_session.remainingLabel">
                                    - {{ student.active_task_session.remainingLabel }} left
                                </span>
                            </template>
                            <template v-else>
                                Idle
                            </template>
                        </p>
                    </div>

                    <div v-if="student.schedule_board" class="mt-2 min-h-0 flex-1 overflow-hidden rounded-[0.75rem] bg-stone-50 p-0.5">
                        <div class="grid h-full grid-cols-1 gap-px overflow-y-auto pr-0 content-start">
                            <div
                                v-for="block in student.schedule_board.blocks"
                                :key="`${student.id}-${student.schedule_board.source_type}-${block.id}`"
                                class="rounded-[0.45rem] border px-1.5 py-0.5"
                                :class="blockRowClass(block.status)"
                                :title="blockTooltip(block)"
                            >
                                <div class="flex items-center justify-between gap-2 text-[10px] leading-none">
                                    <p class="min-w-0 truncate font-medium text-stone-900">
                                        {{ block.position }}. {{ block.task_title }}
                                    </p>
                                    <span class="shrink-0 text-[9px] font-semibold text-stone-700">
                                        {{ block.displayDurationLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-3 flex min-h-0 flex-1 items-center justify-center rounded-[1rem] border border-dashed border-stone-300 bg-stone-50 px-4 text-center text-sm text-stone-500"
                    >
                        No schedule available.
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
