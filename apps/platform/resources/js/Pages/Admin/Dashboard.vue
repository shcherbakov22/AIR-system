<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    serverNow: string;
    activeTaskSessions: Array<{
        id: number;
        task_title: string;
        started_at?: string | null;
        started_at_label?: string | null;
        duration_seconds?: number | null;
        planned_duration_minutes?: number | null;
        source_type: string;
        student: {
            id: number;
            display_name: string;
            username: string;
            status: string;
        };
        schedule_run?: {
            id: number;
            name: string;
        } | null;
        schedule_run_block?: {
            position: number;
        } | null;
    }>;
}>();

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

let liveTimerInterval: number | null = null;

onMounted(() => {
    syncLiveNow();
    liveTimerInterval = window.setInterval(syncLiveNow, 1000);
});

onBeforeUnmount(() => {
    if (liveTimerInterval !== null) {
        window.clearInterval(liveTimerInterval);
    }
});

const activeSessions = computed(() =>
    props.activeTaskSessions.map((taskSession) => {
        const startedAtMs = parseTimestamp(taskSession.started_at ?? null);
        const elapsedSeconds = startedAtMs === null
            ? taskSession.duration_seconds ?? 0
            : Math.max(
                taskSession.duration_seconds ?? 0,
                (taskSession.duration_seconds ?? 0) + Math.floor((liveNowMs.value - startedAtMs) / 1000),
            );
        const plannedSeconds = taskSession.planned_duration_minutes
            ? taskSession.planned_duration_minutes * 60
            : null;
        const remainingSeconds = plannedSeconds === null
            ? null
            : Math.max(plannedSeconds - elapsedSeconds, 0);

        return {
            ...taskSession,
            elapsedLabel: formatDuration(elapsedSeconds),
            remainingLabel: remainingSeconds === null ? null : formatDuration(remainingSeconds),
        };
    }),
);
</script>

<template>
    <Head title="Mentor dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Mentor dashboard
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Student activity
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                v-if="activeSessions.length === 0"
                class="rounded-[2rem] bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200"
            >
                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                    Active work
                </p>
                <p class="mt-3 text-lg font-semibold text-stone-950">
                    No students are currently in a task.
                </p>
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="taskSession in activeSessions"
                    :key="taskSession.id"
                    class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Student
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ taskSession.student.display_name }}
                            </h3>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ taskSession.student.username }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                Elapsed
                            </p>
                            <p class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ taskSession.elapsedLabel }}
                            </p>
                            <template v-if="taskSession.remainingLabel">
                                <p class="mt-3 text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Remaining
                                </p>
                                <p class="mt-2 text-lg font-semibold text-stone-700">
                                    {{ taskSession.remainingLabel }}
                                </p>
                            </template>
                        </div>
                    </div>

                    <div class="mt-5 rounded-[1.25rem] bg-stone-100 px-4 py-4">
                        <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                            Task
                        </p>
                        <p class="mt-2 text-lg font-semibold text-stone-950">
                            {{ taskSession.task_title }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-3 text-sm text-stone-600">
                            <span v-if="taskSession.schedule_run?.name">
                                {{ taskSession.schedule_run.name }}
                            </span>
                            <span v-if="taskSession.schedule_run_block?.position">
                                Block {{ taskSession.schedule_run_block.position }}
                            </span>
                            <span v-if="taskSession.started_at_label">
                                Started {{ taskSession.started_at_label }}
                            </span>
                            <span v-if="taskSession.planned_duration_minutes">
                                Plan {{ taskSession.planned_duration_minutes }} min
                            </span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
