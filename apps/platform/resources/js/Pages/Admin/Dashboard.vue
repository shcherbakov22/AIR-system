<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
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
            name: string;
            status: string;
            started_at?: string | null;
            started_at_label?: string | null;
            completed_blocks: number;
            total_blocks: number;
            blocks: Array<{
                id: number;
                position: number;
                status: string;
                task_title: string;
                planned_duration_minutes: number;
                planned_duration_label: string;
                actual_duration_seconds: number;
                actual_duration_label: string;
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

const monitorStudents = computed(() =>
    props.monitorStudents.map((student) => {
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

        return {
            ...student,
            active_task_session: taskSession
                ? {
                    ...taskSession,
                    elapsedLabel: elapsedSeconds === null ? null : formatDuration(elapsedSeconds),
                    remainingLabel: remainingSeconds === null ? null : formatDuration(remainingSeconds),
                }
                : null,
        };
    }),
);

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

const scheduleBlockStatusClass = (status: string): string => {
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

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Mentor dashboard
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Live monitor
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-5">
            <div
                v-if="monitorStudents.length === 0"
                class="rounded-[2rem] bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200"
            >
                <p class="text-lg font-semibold text-stone-950">
                    No students are available yet.
                </p>
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="student in monitorStudents"
                    :key="student.id"
                    class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
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
                            :class="student.active_task_session ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600'"
                        >
                            {{ student.active_task_session ? 'Task active' : 'Idle' }}
                        </span>
                    </div>

                    <div class="mt-4 rounded-[1.25rem] bg-stone-100 px-4 py-4">
                        <div v-if="student.active_task_session" class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Current task
                                </p>
                                <p class="mt-2 text-lg font-semibold text-stone-950">
                                    {{ student.active_task_session.task_title }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-3 text-sm text-stone-600">
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
                            </div>

                            <div class="flex gap-6 text-right">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Elapsed
                                    </p>
                                    <p class="mt-2 text-2xl font-semibold text-stone-950">
                                        {{ student.active_task_session.elapsedLabel }}
                                    </p>
                                </div>
                                <div v-if="student.active_task_session.remainingLabel">
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Remaining
                                    </p>
                                    <p class="mt-2 text-2xl font-semibold text-stone-950">
                                        {{ student.active_task_session.remainingLabel }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-else>
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Current task
                            </p>
                            <p class="mt-2 text-base font-medium text-stone-700">
                                No active task session
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-[1.25rem] border border-stone-200 p-4">
                        <div v-if="student.active_schedule_run">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Active schedule
                                    </p>
                                    <p class="mt-2 text-lg font-semibold text-stone-950">
                                        {{ student.active_schedule_run.name }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-3 text-sm text-stone-600">
                                        <span class="capitalize">
                                            {{ student.active_schedule_run.status }}
                                        </span>
                                        <span v-if="student.active_schedule_run.started_at_label">
                                            Started {{ student.active_schedule_run.started_at_label }}
                                        </span>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Blocks
                                    </p>
                                    <p class="mt-2 text-2xl font-semibold text-stone-950">
                                        {{ student.active_schedule_run.completed_blocks }} / {{ student.active_schedule_run.total_blocks }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 max-h-56 overflow-y-auto rounded-[1rem] bg-stone-100">
                                <div
                                    v-for="block in student.active_schedule_run.blocks"
                                    :key="block.id"
                                    class="flex items-center justify-between gap-3 border-b border-stone-200 px-3 py-2 last:border-b-0"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-stone-700">
                                                {{ block.position }}.
                                            </span>
                                            <p class="truncate text-sm font-medium text-stone-950">
                                                {{ block.task_title }}
                                            </p>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-stone-500">
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 font-semibold uppercase tracking-[0.12em]"
                                                :class="scheduleBlockStatusClass(block.status)"
                                            >
                                                {{ block.status }}
                                            </span>
                                            <span v-if="block.completed_at_label">
                                                Finished {{ block.completed_at_label }}
                                            </span>
                                            <span v-else-if="block.started_at_label">
                                                Started {{ block.started_at_label }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <p class="text-sm font-semibold text-stone-950">
                                            {{ block.status === 'pending' ? block.planned_duration_label : block.actual_duration_label }}
                                        </p>
                                        <p class="text-xs text-stone-500">
                                            {{ block.status === 'pending' ? 'Planned' : 'Spent' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else>
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Active schedule
                            </p>
                            <p class="mt-2 text-base font-medium text-stone-700">
                                No schedule run in progress
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        <div class="rounded-[1.25rem] border border-stone-200 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Screen
                                </p>
                                <p class="text-xs text-stone-500">
                                    {{ student.latest_screen_capture?.captured_at_label ?? 'No capture' }}
                                </p>
                            </div>

                            <a
                                v-if="student.latest_screen_capture"
                                :href="student.latest_screen_capture.image_url"
                                target="_blank"
                                rel="noreferrer"
                                class="mt-3 block overflow-hidden rounded-[1rem] bg-stone-100"
                            >
                                <img
                                    :src="student.latest_screen_capture.image_url"
                                    :alt="`${student.display_name} screen capture`"
                                    class="h-44 w-full object-cover"
                                >
                            </a>

                            <div
                                v-else
                                class="mt-3 flex h-44 items-center justify-center rounded-[1rem] bg-stone-100 text-sm text-stone-500"
                            >
                                No screen capture yet
                            </div>

                            <p v-if="student.latest_screen_capture?.task_title" class="mt-3 text-sm text-stone-600">
                                {{ student.latest_screen_capture.task_title }}
                            </p>
                        </div>

                        <div class="rounded-[1.25rem] border border-stone-200 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Camera
                                </p>
                                <p class="text-xs text-stone-500">
                                    {{ student.latest_camera_capture?.captured_at_label ?? 'No capture' }}
                                </p>
                            </div>

                            <a
                                v-if="student.latest_camera_capture"
                                :href="student.latest_camera_capture.image_url"
                                target="_blank"
                                rel="noreferrer"
                                class="mt-3 block overflow-hidden rounded-[1rem] bg-stone-100"
                            >
                                <img
                                    :src="student.latest_camera_capture.image_url"
                                    :alt="`${student.display_name} camera capture`"
                                    class="h-44 w-full object-cover"
                                >
                            </a>

                            <div
                                v-else
                                class="mt-3 flex h-44 items-center justify-center rounded-[1rem] bg-stone-100 text-sm text-stone-500"
                            >
                                No camera capture yet
                            </div>

                            <p v-if="student.latest_camera_capture?.task_title" class="mt-3 text-sm text-stone-600">
                                {{ student.latest_camera_capture.task_title }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-[1.25rem] border border-stone-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Open violations
                                </p>
                                <p class="mt-2 text-sm text-stone-600">
                                    {{ student.open_violations.length === 0 ? 'No open violations.' : `${student.open_violations.length} open violation${student.open_violations.length === 1 ? '' : 's'}.` }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <select
                                    v-model="selectedRules[student.id]"
                                    class="rounded-full border-stone-300 px-4 py-2 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                >
                                    <option value="">
                                        Add rule...
                                    </option>
                                    <option v-for="ruleDefinition in props.ruleDefinitions" :key="ruleDefinition.id" :value="String(ruleDefinition.id)">
                                        {{ ruleDefinition.title }}
                                    </option>
                                </select>

                                <button
                                    type="button"
                                    class="inline-flex rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
                                    :disabled="!selectedRules[student.id]"
                                    @click="createViolation(student.id)"
                                >
                                    Add violation
                                </button>
                            </div>
                        </div>

                        <div v-if="student.open_violations.length > 0" class="mt-4 space-y-2">
                            <div
                                v-for="violation in student.open_violations"
                                :key="violation.id"
                                class="flex items-center justify-between gap-3 rounded-[1rem] bg-stone-100 px-3 py-3"
                            >
                                <div>
                                    <p class="text-sm font-medium text-stone-900">
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
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
