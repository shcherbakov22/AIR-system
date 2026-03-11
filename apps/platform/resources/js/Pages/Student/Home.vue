<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    serverNow: string;
    student: {
        display_name: string;
        status: string;
        notes?: string | null;
    };
    studentCapabilities: {
        can_manage_own_schedule: boolean;
        can_use_ad_hoc_timer: boolean;
    };
    violationSummary: {
        open_violations: number;
    };
    openViolations: Array<{
        id: number;
        rule_title: string;
        occurred_at_label?: string | null;
    }>;
    activeScheduleRun: {
        id: number;
        status: string;
        schedule_name: string;
        weekday_label: string;
        notes?: string | null;
        started_at_label?: string | null;
        completed_blocks: number;
        total_blocks: number;
        next_block: {
            id: number;
            position: number;
            task_title: string;
            duration_minutes: number;
        } | null;
        paused_block: {
            id: number;
            position: number;
            task_title: string;
            duration_minutes: number;
        } | null;
        blocks: Array<{
            id: number;
            position: number;
            status: string;
            start_time: string;
            duration_minutes: number;
            notes?: string | null;
            is_next: boolean;
            task: {
                title: string;
                summary?: string | null;
                instructions?: string | null;
            };
        }>;
    } | null;
    activeTaskSession: {
        id: number;
        status: string;
        task_assignment_id?: number | null;
        task_title: string;
        task_summary?: string | null;
        task_instructions?: string | null;
        assignment_notes?: string | null;
        planned_duration_minutes?: number | null;
        duration_seconds?: number | null;
        started_at?: string | null;
        started_at_label?: string | null;
        source_type: string;
        schedule_run_id?: number | null;
        schedule_run_name?: string | null;
        schedule_run_block_id?: number | null;
        schedule_run_block_position?: number | null;
    } | null;
    weeklyScheduleTemplates: Array<{
        id: number;
        name: string;
        weekday: {
            value: string;
            label: string;
        };
        notes?: string | null;
        entries: Array<{
            id: number;
            start_time: string;
            duration_minutes: number;
            notes?: string | null;
            task: {
                title: string;
                summary?: string | null;
                instructions?: string | null;
            };
        }>;
    }>;
    taskTemplates: Array<{
        id: number;
        title: string;
        summary?: string | null;
        instructions?: string | null;
        default_duration_minutes: number;
    }>;
}>();

const page = usePage<PageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const studentCanUseAdHocTimer = computed(() => props.studentCapabilities.can_use_ad_hoc_timer);
const canStartScheduleRun = computed(() => !props.activeScheduleRun && !props.activeTaskSession);
const scheduleRunIsPaused = computed(() => props.activeScheduleRun?.status === 'paused');
const activeScheduleTaskIsRunning = computed(
    () =>
        props.activeTaskSession !== null &&
        props.activeScheduleRun !== null &&
        props.activeTaskSession.schedule_run_id === props.activeScheduleRun.id,
);
const canPauseForOwnTimer = computed(
    () =>
        studentCanUseAdHocTimer.value &&
        props.activeScheduleRun !== null &&
        props.activeScheduleRun.status === 'active' &&
        (props.activeTaskSession === null || props.activeTaskSession.source_type === 'schedule'),
);
const pauseOwnTimerFormOpen = ref(false);
const hasTaskTemplates = computed(() => props.taskTemplates.length > 0);
const hasBlockingViolations = computed(() => props.openViolations.length > 0);

const stopTaskSessionForm = useForm({});
const pauseOwnTimerForm = useForm({
    task_template_id: '',
});
const selectedPauseTaskTemplate = computed(
    () => props.taskTemplates.find((taskTemplate) => String(taskTemplate.id) === pauseOwnTimerForm.task_template_id) ?? null,
);

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

const formatClockTime = (timestampMs: number | null): string | null => {
    if (timestampMs === null) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(timestampMs);
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

const activeTaskStartedAtMs = computed(() => parseTimestamp(props.activeTaskSession?.started_at ?? null));
const activeTaskElapsedBeforeCurrentSegment = computed(() => props.activeTaskSession?.duration_seconds ?? 0);
const activeTaskElapsedSeconds = computed(() => {
    if (activeTaskStartedAtMs.value === null) {
        return activeTaskElapsedBeforeCurrentSegment.value;
    }

    return Math.max(
        activeTaskElapsedBeforeCurrentSegment.value,
        activeTaskElapsedBeforeCurrentSegment.value + Math.floor((liveNowMs.value - activeTaskStartedAtMs.value) / 1000),
    );
});
const activeTaskPlannedSeconds = computed(() =>
    props.activeTaskSession?.planned_duration_minutes
        ? props.activeTaskSession.planned_duration_minutes * 60
        : null,
);
const activeTaskRemainingSeconds = computed(() => {
    if (activeTaskPlannedSeconds.value === null) {
        return null;
    }

    return Math.max(activeTaskPlannedSeconds.value - activeTaskElapsedSeconds.value, 0);
});
const activeTaskOverrunSeconds = computed(() => {
    if (activeTaskPlannedSeconds.value === null) {
        return null;
    }

    return Math.max(activeTaskElapsedSeconds.value - activeTaskPlannedSeconds.value, 0);
});
const activeTaskElapsedLabel = computed(() => formatDuration(activeTaskElapsedSeconds.value));
const activeTaskPlannedLabel = computed(() =>
    activeTaskPlannedSeconds.value === null
        ? null
        : formatDuration(activeTaskPlannedSeconds.value),
);
const activeTaskRemainingLabel = computed(() =>
    activeTaskRemainingSeconds.value === null
        ? null
        : formatDuration(activeTaskRemainingSeconds.value),
);
const activeTaskOverrunLabel = computed(() =>
    activeTaskOverrunSeconds.value === null
        ? null
        : formatDuration(activeTaskOverrunSeconds.value),
);
const activeTaskIsOvertime = computed(() =>
    activeTaskOverrunSeconds.value !== null && activeTaskOverrunSeconds.value > 0,
);
const activeTaskHasRemainingTime = computed(() =>
    activeTaskRemainingSeconds.value !== null && !activeTaskIsOvertime.value,
);
const activeTaskTimerDisplay = computed(() => {
    if (activeTaskHasRemainingTime.value && activeTaskRemainingLabel.value !== null) {
        return activeTaskRemainingLabel.value;
    }

    if (activeTaskIsOvertime.value && activeTaskOverrunLabel.value !== null) {
        return activeTaskOverrunLabel.value;
    }

    return activeTaskElapsedLabel.value;
});
const activeTaskTimerDisplayLabel = computed(() => {
    if (activeTaskHasRemainingTime.value) {
        return 'осталось';
    }

    if (activeTaskIsOvertime.value) {
        return 'сверх плана';
    }

    return 'прошло';
});
const activeTaskEndsAtLabel = computed(() => {
    if (activeTaskStartedAtMs.value === null || activeTaskPlannedSeconds.value === null) {
        return null;
    }

    const remainingCurrentSegmentSeconds = Math.max(activeTaskPlannedSeconds.value - activeTaskElapsedBeforeCurrentSegment.value, 0);
    return formatClockTime(activeTaskStartedAtMs.value + remainingCurrentSegmentSeconds * 1000);
});

const stopTaskSession = () => {
    if (!props.activeTaskSession) {
        return;
    }

    stopTaskSessionForm.patch(route('student.task-sessions.stop', props.activeTaskSession.id), {
        preserveScroll: true,
        onSuccess: () => {
            stopTaskSessionForm.reset();
        },
    });
};

const showBlockingViolationDialog = () => {
    if (!hasBlockingViolations.value) {
        return false;
    }

    const lines = [
        'Есть открытые нарушения:',
        ...props.openViolations.map((violation) =>
            `- ${violation.rule_title}${violation.occurred_at_label ? ` (${violation.occurred_at_label})` : ''}`,
        ),
        '',
        'Пока наставник не закроет их, продолжать расписание и запускать свой таймер нельзя.',
    ];

    window.alert(lines.join('\n'));

    return true;
};

const startScheduleRun = (scheduleTemplateId: number) => {
    if (showBlockingViolationDialog()) {
        return;
    }

    router.post(route('student.schedule-runs.store', scheduleTemplateId), {}, { preserveScroll: true });
};

const startNextScheduleTask = () => {
    if (!props.activeScheduleRun?.next_block) {
        return;
    }

    if (showBlockingViolationDialog()) {
        return;
    }

    router.post(
        route('student.schedule-run-blocks.start', {
            scheduleRun: props.activeScheduleRun.id,
            scheduleRunBlock: props.activeScheduleRun.next_block.id,
        }),
        {},
        { preserveScroll: true },
    );
};

const togglePauseOwnTimerForm = () => {
    if (!pauseOwnTimerFormOpen.value && showBlockingViolationDialog()) {
        return;
    }

    pauseOwnTimerFormOpen.value = !pauseOwnTimerFormOpen.value;

    if (!pauseOwnTimerFormOpen.value) {
        pauseOwnTimerForm.reset();
        pauseOwnTimerForm.clearErrors();
    }
};

const pauseScheduleForOwnTimer = () => {
    if (!props.activeScheduleRun) {
        return;
    }

    if (showBlockingViolationDialog()) {
        return;
    }

    pauseOwnTimerForm.post(route('student.schedule-runs.pause', props.activeScheduleRun.id), {
        preserveScroll: true,
        onSuccess: () => {
            pauseOwnTimerFormOpen.value = false;
            pauseOwnTimerForm.reset();
        },
    });
};

const resumeScheduleRun = () => {
    if (!props.activeScheduleRun) {
        return;
    }

    if (showBlockingViolationDialog()) {
        return;
    }

    router.post(route('student.schedule-runs.resume', props.activeScheduleRun.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Портал ученика" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-5 py-6">
            <div
                v-if="flashSuccess"
                class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="mb-5 rounded-[1.5rem] bg-rose-50 px-6 py-4 text-sm text-rose-800 ring-1 ring-rose-200"
            >
                {{ flashError }}
            </div>

            <section
                v-if="activeScheduleRun || activeTaskSession || canStartScheduleRun"
                class="mt-4 rounded-[1.5rem] bg-white p-4 shadow-sm ring-1 ring-stone-200"
            >
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,1.2fr)]">
                    <div class="rounded-[1.25rem] bg-stone-100 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">
                                Сейчас
                            </p>
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]"
                                :class="
                                    activeTaskSession
                                        ? 'bg-amber-200 text-stone-950'
                                        : activeScheduleRun?.status === 'paused'
                                          ? 'bg-stone-300 text-stone-900'
                                          : activeScheduleRun
                                            ? 'bg-emerald-200 text-emerald-950'
                                            : 'bg-stone-900 text-white'
                                "
                            >
                                {{
                                    activeTaskSession
                                        ? 'таймер идет'
                                        : activeScheduleRun?.status === 'paused'
                                          ? 'пауза'
                                          : activeScheduleRun
                                            ? 'расписание активно'
                                            : 'готово к старту'
                                }}
                            </span>
                        </div>

                        <p class="mt-2 truncate text-lg font-semibold text-stone-950">
                            {{
                                activeTaskSession?.task_title ??
                                activeScheduleRun?.schedule_name ??
                                'Выберите расписание'
                            }}
                        </p>

                        <p class="mt-1 text-sm text-stone-600">
                            {{
                                activeTaskSession?.source_type === 'ad_hoc'
                                    ? 'Свой таймер удерживает расписание на паузе.'
                                    : activeTaskSession?.schedule_run_block_position
                                      ? `Блок ${activeTaskSession.schedule_run_block_position} выполняется сейчас.`
                                      : activeScheduleRun?.paused_block
                                        ? `На паузе блок ${activeScheduleRun.paused_block.position}.`
                                        : activeScheduleRun?.next_block
                                          ? `Следующий блок ${activeScheduleRun.next_block.position}.`
                                          : `Доступно расписаний: ${weeklyScheduleTemplates.length}.`
                            }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-3 text-xs text-stone-500">
                            <span v-if="activeScheduleRun">
                                {{ activeScheduleRun.completed_blocks }} / {{ activeScheduleRun.total_blocks }} блоков
                            </span>
                            <span v-if="activeTaskSession?.planned_duration_minutes">
                                {{ activeTaskSession.planned_duration_minutes }} минут по плану
                            </span>
                            <span v-if="activeScheduleRun?.started_at_label">
                                Старт {{ activeScheduleRun.started_at_label }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-[1.25rem] bg-stone-100 p-4">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">
                            Таймер
                        </p>

                        <template v-if="activeTaskSession">
                            <p
                                class="mt-2 font-mono text-3xl font-semibold"
                                :class="activeTaskIsOvertime ? 'text-rose-700' : 'text-stone-950'"
                            >
                                {{ activeTaskTimerDisplay }}
                            </p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-500">
                                {{ activeTaskTimerDisplayLabel }}
                            </p>

                            <div class="mt-3 grid gap-2 text-sm text-stone-600 sm:grid-cols-3">
                                <span>Прошло {{ activeTaskElapsedLabel }}</span>
                                <span v-if="activeTaskPlannedLabel">План {{ activeTaskPlannedLabel }}</span>
                                <span v-if="activeTaskEndsAtLabel">До {{ activeTaskEndsAtLabel }}</span>
                            </div>
                        </template>

                        <template v-else-if="activeScheduleRun?.paused_block">
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                Блок {{ activeScheduleRun.paused_block.position }}
                            </p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ activeScheduleRun.paused_block.task_title }}
                            </p>
                            <p class="mt-2 text-sm text-stone-500">
                                {{ activeScheduleRun.paused_block.duration_minutes }} минут
                            </p>
                        </template>

                        <template v-else-if="activeScheduleRun?.next_block">
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                Блок {{ activeScheduleRun.next_block.position }}
                            </p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ activeScheduleRun.next_block.task_title }}
                            </p>
                            <p class="mt-2 text-sm text-stone-500">
                                {{ activeScheduleRun.next_block.duration_minutes }} минут
                            </p>
                        </template>

                        <template v-else>
                            <p class="mt-2 text-sm text-stone-600">
                                Выберите расписание и начните первый блок.
                            </p>
                        </template>
                    </div>

                    <div class="rounded-[1.25rem] bg-stone-100 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="scheduleRunIsPaused"
                                type="button"
                                class="inline-flex rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800"
                                @click="resumeScheduleRun"
                            >
                                {{
                                    activeScheduleRun?.paused_block
                                        ? 'Продолжить блок'
                                        : 'Продолжить расписание'
                                }}
                            </button>

                            <button
                                v-else-if="activeScheduleRun?.next_block && !activeScheduleTaskIsRunning"
                                type="button"
                                class="inline-flex rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800"
                                @click="startNextScheduleTask"
                            >
                                Следующий блок
                            </button>

                            <div
                                v-if="canPauseForOwnTimer || activeTaskSession"
                                class="flex flex-row items-center gap-2"
                                style="display: flex; flex-direction: row; flex-wrap: nowrap; align-items: center; gap: 0.5rem;"
                            >
                                <button
                                    v-if="canPauseForOwnTimer"
                                    type="button"
                                    class="inline-flex shrink-0 justify-center whitespace-nowrap rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    :disabled="!hasTaskTemplates"
                                    @click="togglePauseOwnTimerForm"
                                >
                                    {{ pauseOwnTimerFormOpen ? 'Скрыть свой таймер' : 'Свой таймер' }}
                                </button>
                                <form
                                    v-if="activeTaskSession"
                                    @submit.prevent="stopTaskSession"
                                    class="shrink-0"
                                    style="display: flex;"
                                >
                                    <button
                                        type="submit"
                                        :disabled="stopTaskSessionForm.processing"
                                        class="inline-flex shrink-0 justify-center whitespace-nowrap rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Завершить
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div
                            v-if="!hasTaskTemplates && canPauseForOwnTimer"
                            class="mt-3 rounded-[1rem] bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200"
                        >
                            В библиотеке пока нет заданий для своего таймера.
                        </div>

                        <form
                            v-if="pauseOwnTimerFormOpen"
                            class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1.2fr)_auto_auto]"
                            @submit.prevent="pauseScheduleForOwnTimer"
                        >
                            <select
                                id="quick_own_timer_task_template_id"
                                v-model="pauseOwnTimerForm.task_template_id"
                                class="block w-full rounded-full border-stone-300 bg-white px-4 py-2 text-sm text-stone-950 shadow-sm focus:border-stone-950 focus:ring-stone-950"
                            >
                                <option value="">
                                    Выберите задание
                                </option>
                                <option
                                    v-for="taskTemplate in props.taskTemplates"
                                    :key="taskTemplate.id"
                                    :value="String(taskTemplate.id)"
                                >
                                    {{ taskTemplate.title }}
                                </option>
                            </select>

                            <div class="flex items-center rounded-full border border-stone-300 bg-white px-4 py-2 text-sm text-stone-700">
                                {{
                                    selectedPauseTaskTemplate
                                        ? `${selectedPauseTaskTemplate.default_duration_minutes} мин`
                                        : '—'
                                }}
                            </div>

                            <button
                                type="submit"
                                :disabled="pauseOwnTimerForm.processing || !hasTaskTemplates"
                                class="inline-flex shrink-0 justify-center whitespace-nowrap rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Пауза
                            </button>

                            <InputError class="md:col-span-3" :message="pauseOwnTimerForm.errors.task_template_id" />
                        </form>

                        <div v-if="canStartScheduleRun" class="mt-3 grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="scheduleTemplate in weeklyScheduleTemplates"
                                :key="scheduleTemplate.id"
                                type="button"
                                class="flex items-center justify-between rounded-[1rem] bg-white px-3 py-2 text-left text-sm ring-1 ring-stone-200 transition hover:ring-stone-400"
                                @click="startScheduleRun(scheduleTemplate.id)"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-stone-950">{{ scheduleTemplate.name }}</span>
                                </span>
                                <span class="ml-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-stone-700">
                                    Старт
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasBlockingViolations"
                    class="mt-3 rounded-[1.25rem] bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-200"
                >
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <p class="font-medium">
                            Пока наставник не закроет нарушения, продолжать расписание и запускать свой таймер нельзя.
                        </p>
                        <div class="min-w-0 space-y-1 text-sm">
                            <p
                                v-for="violation in openViolations"
                                :key="violation.id"
                                class="truncate"
                            >
                                {{ violation.rule_title }}<span v-if="violation.occurred_at_label">, {{ violation.occurred_at_label }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="activeScheduleRun"
                    class="mt-3 rounded-[1.25rem] bg-stone-100 p-4"
                >
                    <div class="flex flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.18em] text-stone-500">
                        <span>{{ activeScheduleRun.schedule_name }}</span>
                        <span>{{ activeScheduleRun.completed_blocks }} / {{ activeScheduleRun.total_blocks }}</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        <article
                            v-for="block in activeScheduleRun.blocks"
                            :key="block.id"
                            class="flex flex-wrap items-center gap-3 rounded-[0.9rem] bg-white px-3 py-2 text-sm ring-1 ring-stone-200"
                        >
                            <span class="text-[11px] uppercase tracking-[0.16em] text-stone-500">
                                {{ block.position }}
                            </span>
                            <span
                                class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]"
                                :class="
                                    block.status === 'completed'
                                        ? 'bg-emerald-200 text-emerald-950'
                                        : block.status === 'paused'
                                          ? 'bg-stone-300 text-stone-900'
                                        : block.status === 'in_progress'
                                          ? 'bg-amber-200 text-stone-950'
                                          : block.is_next
                                            ? 'bg-stone-950 text-white'
                                            : 'bg-stone-200 text-stone-700'
                                "
                            >
                                {{
                                    block.status === 'completed'
                                        ? 'готово'
                                        : block.status === 'paused'
                                          ? 'пауза'
                                        : block.status === 'in_progress'
                                          ? 'идет'
                                          : block.is_next
                                            ? 'следующий'
                                            : 'ждет'
                                }}
                            </span>
                            <p class="min-w-0 flex-1 truncate font-semibold text-stone-950">
                                {{ block.task.title }}
                            </p>
                            <p class="text-xs text-stone-600">
                                {{ block.duration_minutes }} мин
                            </p>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
