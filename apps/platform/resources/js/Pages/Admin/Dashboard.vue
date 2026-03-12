<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    serverNow: string;
    ruleDefinitions: Array<{
        id: number;
        title: string;
    }>;
    monitorStudents: Array<{
        id: number;
        display_name: string;
        status: string;
        user: {
            id: number;
            username: string;
            last_login_at?: string | null;
        };
        active_schedule_run?: {
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
            blocks: Array<{
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
            }>;
        } | null;
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
            blocks: Array<{
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
            }>;
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
            capture_kind: string;
            captured_at?: string | null;
            captured_at_label?: string | null;
            task_title?: string | null;
            source_label?: string | null;
            image_url: string;
        } | null;
        latest_camera_capture?: {
            id: number;
            capture_kind: string;
            captured_at?: string | null;
            captured_at_label?: string | null;
            task_title?: string | null;
            source_label?: string | null;
            image_url: string;
        } | null;
        open_violations: Array<{
            id: number;
            rule_title: string;
            occurred_at_label?: string | null;
        }>;
    }>;
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
                        liveActualDurationLabel: formatDuration(liveActualSeconds),
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

const studentStatusClass = (student: (typeof props.monitorStudents)[number]): string => {
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

const scheduleBlockStatusClass = (status: string): string => {
    if (status === 'completed') {
        return 'border-emerald-200 bg-emerald-50';
    }

    if (status === 'in_progress') {
        return 'border-amber-300 bg-amber-50';
    }

    if (status === 'paused') {
        return 'border-sky-300 bg-sky-50';
    }

    return 'border-stone-200 bg-stone-50';
};

const blockBadgeClass = (status: string): string => {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (status === 'in_progress') {
        return 'bg-amber-100 text-amber-800';
    }

    if (status === 'paused') {
        return 'bg-sky-100 text-sky-800';
    }

    return 'bg-stone-200 text-stone-700';
};
</script>

<template>
    <Head title="Mentor monitor" />

    <AuthenticatedLayout :hide-sidebar="true" :full-width="true">
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Mentor dashboard
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Full schedule board
                    </h2>
                </div>

                <div class="flex flex-col items-start gap-2 lg:items-end">
                    <p class="text-sm text-stone-500">
                        Every student, their live task, and the full schedule board in one view. Refreshes every 30 seconds.
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

        <div class="px-4 py-4 sm:px-5 lg:px-6">
            <div
                v-if="monitorStudents.length === 0"
                class="rounded-[2rem] bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200"
            >
                <p class="text-lg font-semibold text-stone-950">
                    No students are available yet.
                </p>
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="student in monitorStudents"
                    :key="student.id"
                    class="overflow-hidden rounded-[1.75rem] bg-white shadow-sm ring-1 ring-stone-200"
                >
                    <div class="grid gap-0 xl:grid-cols-[19rem_minmax(0,1fr)]">
                        <div class="border-b border-stone-200 bg-stone-50/90 p-4 xl:border-b-0 xl:border-r">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-2xl font-semibold text-stone-950">
                                        {{ student.display_name }}
                                    </p>
                                    <p class="text-sm text-stone-500">
                                        {{ student.user.username }}
                                    </p>
                                </div>

                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="studentStatusClass(student)"
                                >
                                    {{ student.active_task_session ? 'Task active' : student.schedule_board ? 'Schedule loaded' : 'No schedule' }}
                                </span>
                            </div>

                            <div class="mt-3 rounded-[1.1rem] bg-white px-3 py-3 ring-1 ring-stone-200">
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Current task
                                </p>

                                <div v-if="student.active_task_session" class="mt-2">
                                    <p class="text-lg font-semibold text-stone-950">
                                        {{ student.active_task_session.task_title }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-sm text-stone-600">
                                        <span v-if="student.active_task_session.schedule_run?.name">
                                            {{ student.active_task_session.schedule_run.name }}
                                        </span>
                                        <span v-if="student.active_task_session.schedule_run_block?.position">
                                            Block {{ student.active_task_session.schedule_run_block.position }}
                                        </span>
                                        <span v-if="student.active_task_session.started_at_label">
                                            Started {{ student.active_task_session.started_at_label }}
                                        </span>
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div class="rounded-xl bg-stone-100 px-3 py-2">
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-stone-500">
                                                Elapsed
                                            </p>
                                            <p class="mt-1 text-lg font-semibold text-stone-950">
                                                {{ student.active_task_session.elapsedLabel }}
                                            </p>
                                        </div>
                                        <div class="rounded-xl bg-stone-100 px-3 py-2">
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-stone-500">
                                                Remaining
                                            </p>
                                            <p class="mt-1 text-lg font-semibold text-stone-950">
                                                {{ student.active_task_session.remainingLabel ?? 'Open' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <p v-else class="mt-2 text-base font-medium text-stone-700">
                                    No active task session
                                </p>
                            </div>

                            <div class="mt-3 grid gap-2">
                                <div class="grid gap-2 sm:grid-cols-3 xl:grid-cols-1">
                                    <a
                                        v-if="student.latest_screen_capture"
                                        :href="student.latest_screen_capture.image_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-full border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                                    >
                                        Screen · {{ student.latest_screen_capture.captured_at_label }}
                                    </a>
                                    <div
                                        v-else
                                        class="rounded-full border border-dashed border-stone-300 px-3 py-2 text-sm text-stone-500"
                                    >
                                        No screen
                                    </div>

                                    <a
                                        v-if="student.latest_camera_capture"
                                        :href="student.latest_camera_capture.image_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-full border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                                    >
                                        Camera · {{ student.latest_camera_capture.captured_at_label }}
                                    </a>
                                    <div
                                        v-else
                                        class="rounded-full border border-dashed border-stone-300 px-3 py-2 text-sm text-stone-500"
                                    >
                                        No camera
                                    </div>

                                    <Link
                                        :href="route('admin.students.progress', student.id)"
                                        class="rounded-full border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                                    >
                                        Progress
                                    </Link>
                                </div>

                                <div class="rounded-[1.1rem] bg-white px-3 py-3 ring-1 ring-stone-200">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                                Open violations
                                            </p>
                                            <p class="mt-1 text-sm text-stone-600">
                                                {{ student.open_violations.length === 0 ? 'Clear' : `${student.open_violations.length} open` }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-2">
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

                                    <div v-if="student.open_violations.length > 0" class="mt-3 space-y-2">
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
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            <div v-if="student.schedule_board">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                                :class="scheduleSourceClass(student.schedule_board.source_type)"
                                            >
                                                {{ student.schedule_board.source_label }}
                                            </span>
                                            <span class="inline-flex rounded-full bg-stone-200 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700">
                                                {{ student.schedule_board.status_label }}
                                            </span>
                                        </div>

                                        <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                            {{ student.schedule_board.name }}
                                        </h3>

                                        <div class="mt-2 flex flex-wrap gap-3 text-sm text-stone-600">
                                            <span>
                                                {{ student.schedule_board.completed_blocks }} / {{ student.schedule_board.total_blocks }} blocks
                                            </span>
                                            <span v-if="student.schedule_board.started_at_label">
                                                Started {{ student.schedule_board.started_at_label }}
                                            </span>
                                            <span v-if="student.schedule_board.completed_at_label">
                                                Finished {{ student.schedule_board.completed_at_label }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="rounded-[1.1rem] bg-stone-100 px-4 py-3 text-sm text-stone-600">
                                        <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                            Board view
                                        </p>
                                        <p class="mt-1">
                                            Full schedule stays visible here even when the student is idle.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-2 [grid-template-columns:repeat(auto-fit,minmax(8.75rem,1fr))]">
                                    <div
                                        v-for="block in student.schedule_board.blocks"
                                        :key="`${student.id}-${student.schedule_board.source_type}-${block.id}`"
                                        class="rounded-[1rem] border px-3 py-3"
                                        :class="scheduleBlockStatusClass(block.status)"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">
                                                Block {{ block.position }}
                                            </p>
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]"
                                                :class="blockBadgeClass(block.status)"
                                            >
                                                {{ block.status_label }}
                                            </span>
                                        </div>

                                        <p class="mt-2 line-clamp-2 text-sm font-semibold text-stone-950">
                                            {{ block.task_title }}
                                        </p>

                                        <div class="mt-3">
                                            <p class="text-lg font-semibold text-stone-950">
                                                {{ block.displayDurationLabel }}
                                            </p>
                                            <p class="text-[11px] uppercase tracking-[0.16em] text-stone-500">
                                                {{ block.displayDurationCaption }}
                                            </p>
                                        </div>

                                        <p v-if="block.completed_at_label" class="mt-2 text-[11px] text-stone-500">
                                            Finished {{ block.completed_at_label }}
                                        </p>
                                        <p v-else-if="block.started_at_label" class="mt-2 text-[11px] text-stone-500">
                                            Started {{ block.started_at_label }}
                                        </p>
                                        <p v-else class="mt-2 text-[11px] text-stone-500">
                                            Ready to start
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex min-h-40 items-center justify-center rounded-[1.5rem] border border-dashed border-stone-300 bg-stone-50 px-6 py-8 text-center"
                            >
                                <div>
                                    <p class="text-lg font-semibold text-stone-950">
                                        No schedule available
                                    </p>
                                    <p class="mt-2 text-sm text-stone-600">
                                        This student does not have a saved schedule or a recent schedule run yet.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
