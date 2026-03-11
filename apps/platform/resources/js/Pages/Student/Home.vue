<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

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
const studentCanManageOwnSchedule = computed(() => props.studentCapabilities.can_manage_own_schedule);
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
const canResumeScheduleRun = computed(
    () => props.activeScheduleRun !== null && props.activeScheduleRun.status === 'paused' && !props.activeTaskSession,
);
const pauseOwnTimerFormOpen = ref(false);
const hasTaskTemplates = computed(() => props.taskTemplates.length > 0);
const hasBlockingViolations = computed(() => props.openViolations.length > 0);

const stopTaskSessionForm = useForm({
    completion_notes: '',
});
const pauseOwnTimerForm = useForm({
    task_template_id: '',
    notes: '',
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
const activeTaskProgressPercent = computed(() => {
    if (activeTaskPlannedSeconds.value === null || activeTaskPlannedSeconds.value <= 0) {
        return null;
    }

    return Math.min(100, Math.max(0, Math.round((activeTaskElapsedSeconds.value / activeTaskPlannedSeconds.value) * 100)));
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
            stopTaskSessionForm.reset('completion_notes');
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
                class="mt-4 rounded-[1.5rem] bg-stone-950 p-4 text-white shadow-sm ring-1 ring-stone-800"
            >
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,1.2fr)]">
                    <div class="rounded-[1.25rem] bg-white/5 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-400">
                                Сейчас
                            </p>
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]"
                                :class="
                                    activeTaskSession
                                        ? 'bg-amber-200 text-stone-950'
                                        : activeScheduleRun?.status === 'paused'
                                          ? 'bg-stone-200 text-stone-900'
                                          : activeScheduleRun
                                            ? 'bg-emerald-200 text-emerald-950'
                                            : 'bg-stone-700 text-white'
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

                        <p class="mt-2 truncate text-lg font-semibold text-white">
                            {{
                                activeTaskSession?.task_title ??
                                activeScheduleRun?.schedule_name ??
                                'Выберите расписание'
                            }}
                        </p>

                        <p class="mt-1 text-sm text-stone-300">
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

                        <div class="mt-3 flex flex-wrap gap-3 text-xs text-stone-400">
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

                    <div class="rounded-[1.25rem] bg-white/5 p-4">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-stone-400">
                            Таймер
                        </p>

                        <template v-if="activeTaskSession">
                            <p
                                class="mt-2 font-mono text-3xl font-semibold"
                                :class="activeTaskIsOvertime ? 'text-rose-300' : 'text-white'"
                            >
                                {{ activeTaskTimerDisplay }}
                            </p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-400">
                                {{ activeTaskTimerDisplayLabel }}
                            </p>

                            <div class="mt-3 grid gap-2 text-sm text-stone-300 sm:grid-cols-3">
                                <span>Прошло {{ activeTaskElapsedLabel }}</span>
                                <span v-if="activeTaskPlannedLabel">План {{ activeTaskPlannedLabel }}</span>
                                <span v-if="activeTaskEndsAtLabel">До {{ activeTaskEndsAtLabel }}</span>
                            </div>
                        </template>

                        <template v-else-if="activeScheduleRun?.paused_block">
                            <p class="mt-2 text-lg font-semibold text-white">
                                Блок {{ activeScheduleRun.paused_block.position }}
                            </p>
                            <p class="mt-1 text-sm text-stone-300">
                                {{ activeScheduleRun.paused_block.task_title }}
                            </p>
                            <p class="mt-2 text-sm text-stone-400">
                                {{ activeScheduleRun.paused_block.duration_minutes }} минут
                            </p>
                        </template>

                        <template v-else-if="activeScheduleRun?.next_block">
                            <p class="mt-2 text-lg font-semibold text-white">
                                Блок {{ activeScheduleRun.next_block.position }}
                            </p>
                            <p class="mt-1 text-sm text-stone-300">
                                {{ activeScheduleRun.next_block.task_title }}
                            </p>
                            <p class="mt-2 text-sm text-stone-400">
                                {{ activeScheduleRun.next_block.duration_minutes }} минут
                            </p>
                        </template>

                        <template v-else>
                            <p class="mt-2 text-sm text-stone-300">
                                Выберите расписание и начните первый блок.
                            </p>
                        </template>
                    </div>

                    <div class="rounded-[1.25rem] bg-white/5 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="scheduleRunIsPaused"
                                type="button"
                                class="inline-flex rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-950 transition hover:bg-amber-300"
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
                                class="inline-flex rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-950 transition hover:bg-amber-300"
                                @click="startNextScheduleTask"
                            >
                                Следующий блок
                            </button>

                            <button
                                v-if="canPauseForOwnTimer"
                                type="button"
                                class="inline-flex rounded-full border border-stone-600 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-100 transition hover:border-amber-300 hover:text-white"
                                :disabled="!hasTaskTemplates"
                                @click="togglePauseOwnTimerForm"
                            >
                                {{ pauseOwnTimerFormOpen ? 'Скрыть свой таймер' : 'Свой таймер' }}
                            </button>
                        </div>

                        <form
                            v-if="activeTaskSession"
                            class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]"
                            @submit.prevent="stopTaskSession"
                        >
                            <input
                                id="quick_completion_notes"
                                v-model="stopTaskSessionForm.completion_notes"
                                type="text"
                                placeholder="Заметка о завершении"
                                class="block w-full rounded-full border-stone-700 bg-stone-900 px-4 py-2 text-sm text-stone-100 shadow-sm focus:border-amber-400 focus:ring-amber-400"
                            />
                            <button
                                type="submit"
                                :disabled="stopTaskSessionForm.processing"
                                class="inline-flex rounded-full bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-950 transition hover:bg-stone-200 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Остановить
                            </button>
                            <InputError class="md:col-span-2 text-rose-300" :message="stopTaskSessionForm.errors.completion_notes" />
                        </form>

                        <div
                            v-if="!hasTaskTemplates && canPauseForOwnTimer"
                            class="mt-3 rounded-[1rem] bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200"
                        >
                            В библиотеке пока нет заданий для своего таймера.
                        </div>

                        <form
                            v-if="pauseOwnTimerFormOpen"
                            class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1.2fr)_auto_minmax(0,1fr)_auto]"
                            @submit.prevent="pauseScheduleForOwnTimer"
                        >
                            <select
                                id="quick_own_timer_task_template_id"
                                v-model="pauseOwnTimerForm.task_template_id"
                                class="block w-full rounded-full border-stone-700 bg-stone-900 px-4 py-2 text-sm text-stone-100 shadow-sm focus:border-amber-400 focus:ring-amber-400"
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

                            <div class="flex items-center rounded-full border border-stone-700 bg-stone-900 px-4 py-2 text-sm text-stone-200">
                                {{
                                    selectedPauseTaskTemplate
                                        ? `${selectedPauseTaskTemplate.default_duration_minutes} мин`
                                        : '—'
                                }}
                            </div>

                            <input
                                id="quick_own_timer_notes"
                                v-model="pauseOwnTimerForm.notes"
                                type="text"
                                placeholder="Заметка"
                                class="block w-full rounded-full border-stone-700 bg-stone-900 px-4 py-2 text-sm text-stone-100 shadow-sm focus:border-amber-400 focus:ring-amber-400"
                            />

                            <button
                                type="submit"
                                :disabled="pauseOwnTimerForm.processing || !hasTaskTemplates"
                                class="inline-flex rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Пауза
                            </button>

                            <InputError class="md:col-span-4 text-rose-300" :message="pauseOwnTimerForm.errors.task_template_id" />
                            <InputError class="md:col-span-4 text-rose-300" :message="pauseOwnTimerForm.errors.notes" />
                        </form>

                        <div v-if="canStartScheduleRun" class="mt-3 grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="scheduleTemplate in weeklyScheduleTemplates"
                                :key="scheduleTemplate.id"
                                type="button"
                                class="flex items-center justify-between rounded-[1rem] bg-white/10 px-3 py-2 text-left text-sm ring-1 ring-white/10 transition hover:bg-white/15"
                                @click="startScheduleRun(scheduleTemplate.id)"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-white">{{ scheduleTemplate.name }}</span>
                                    <span class="block truncate text-[11px] uppercase tracking-[0.16em] text-stone-400">
                                        {{ scheduleTemplate.weekday.label }}
                                    </span>
                                </span>
                                <span class="ml-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-300">
                                    Старт
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-4 rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-stone-200 md:p-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                        Выполнение расписания
                    </p>
                    <h3 class="font-serif text-3xl text-stone-950">
                        Пульт ученика
                    </h3>
                </div>

                <div
                    v-if="hasBlockingViolations"
                    class="mt-4 rounded-[1.5rem] bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-200"
                >
                    <p class="text-xs uppercase tracking-[0.22em] text-rose-700">
                        Движение заблокировано
                    </p>
                    <div class="mt-2 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <p class="leading-6">
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
                    class="mt-5 grid gap-4 lg:grid-cols-[1fr_0.95fr]"
                >
                    <div class="rounded-[1.75rem] bg-stone-100 p-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Текущее состояние
                            </p>
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                :class="
                                    activeScheduleRun.status === 'paused'
                                        ? 'bg-stone-200 text-stone-800'
                                        : 'bg-amber-100 text-amber-800'
                                "
                            >
                                {{ activeScheduleRun.status === 'paused' ? 'пауза' : 'активно' }}
                            </span>
                        </div>

                        <h4 class="mt-3 text-2xl font-semibold text-stone-950">
                            {{ activeScheduleRun.schedule_name }}
                        </h4>
                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            {{ activeScheduleRun.notes || 'Заметка к расписанию не указана.' }}
                        </p>
                        <div class="mt-5 flex flex-wrap gap-4 text-sm text-stone-600">
                            <span>
                                Начато {{ activeScheduleRun.started_at_label || 'только что' }}
                            </span>
                            <span>
                                {{ activeScheduleRun.completed_blocks }} / {{ activeScheduleRun.total_blocks }}
                                блоков завершено
                            </span>
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] bg-stone-950 p-6 text-white">
                        <p class="text-xs uppercase tracking-[0.25em] text-amber-300/75">
                            Следующий шаг
                        </p>

                        <template v-if="activeTaskSession?.source_type === 'ad_hoc'">
                            <p class="mt-4 text-sm leading-7 text-stone-300">
                                Ваш собственный таймер идёт, пока расписание остаётся на паузе.
                                Возобновите расписание после завершения этого таймера.
                            </p>
                        </template>

                        <template v-else-if="activeScheduleTaskIsRunning">
                            <p class="mt-4 text-sm leading-7 text-stone-300">
                                Текущий блок расписания уже выполняется. Остановите его, прежде чем
                                запускать следующее задание по порядку.
                            </p>
                        </template>

                        <template v-else-if="scheduleRunIsPaused && activeScheduleRun.paused_block">
                            <h4 class="mt-4 text-2xl font-semibold text-white">
                                Возобновить блок {{ activeScheduleRun.paused_block.position }}
                            </h4>
                            <p class="mt-3 text-sm leading-7 text-stone-300">
                                {{
                                    `${activeScheduleRun.paused_block.task_title} на ${activeScheduleRun.paused_block.duration_minutes} минут`
                                }}
                            </p>
                            <button
                                type="button"
                                class="mt-6 inline-flex rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-950 transition hover:bg-amber-300"
                                @click="resumeScheduleRun"
                            >
                                Возобновить блок
                            </button>
                        </template>

                        <template v-else-if="scheduleRunIsPaused">
                            <p class="mt-4 text-sm leading-7 text-stone-300">
                                Возобновите приостановленное расписание, когда будете готовы
                                перейти к следующему запланированному блоку.
                            </p>
                            <button
                                type="button"
                                class="mt-6 inline-flex rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-950 transition hover:bg-amber-300"
                                @click="resumeScheduleRun"
                            >
                                Возобновить расписание
                            </button>
                        </template>

                        <template v-else-if="activeScheduleRun.next_block">
                            <h4 class="mt-4 text-2xl font-semibold text-white">
                                Блок {{ activeScheduleRun.next_block.position }}
                            </h4>
                            <p class="mt-3 text-sm leading-7 text-stone-300">
                                {{
                                    `${activeScheduleRun.next_block.task_title} на ${activeScheduleRun.next_block.duration_minutes} минут`
                                }}
                            </p>
                            <button
                                type="button"
                                class="mt-6 inline-flex rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-950 transition hover:bg-amber-300"
                                @click="startNextScheduleTask"
                            >
                                Начать следующее задание
                            </button>
                        </template>

                        <template v-else>
                            <p class="mt-4 text-sm leading-7 text-stone-300">
                                Все блоки завершены. Расписание закроется, как только обновится
                                текущее состояние выполнения.
                            </p>
                        </template>

                        <div v-if="canPauseForOwnTimer" class="mt-6 border-t border-stone-800 pt-6">
                            <button
                                type="button"
                                class="inline-flex rounded-full border border-stone-700 px-4 py-2 text-sm font-semibold uppercase tracking-[0.18em] text-stone-200 transition hover:border-amber-300 hover:text-white"
                                :disabled="!hasTaskTemplates"
                                @click="togglePauseOwnTimerForm"
                            >
                                {{ pauseOwnTimerFormOpen ? 'Скрыть свой таймер' : 'Свой таймер' }}
                            </button>

                            <p
                                v-if="!hasTaskTemplates"
                                class="mt-4 rounded-[1.25rem] bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950 ring-1 ring-amber-200"
                            >
                                В библиотеке заданий пока ничего нет. Добавьте задания, чтобы включить свой таймер.
                            </p>

                            <form
                                v-if="pauseOwnTimerFormOpen"
                                class="mt-5 space-y-4"
                                @submit.prevent="pauseScheduleForOwnTimer"
                            >
                                <div>
                                    <label
                                        for="own_timer_task_template_id"
                                        class="block text-xs uppercase tracking-[0.25em] text-stone-400"
                                    >
                                        Задание
                                    </label>
                                    <select
                                        id="own_timer_task_template_id"
                                        v-model="pauseOwnTimerForm.task_template_id"
                                        class="mt-2 block w-full rounded-[1.25rem] border-stone-700 bg-stone-900 text-stone-100 shadow-sm focus:border-amber-400 focus:ring-amber-400"
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
                                    <InputError
                                        class="mt-2 text-rose-300"
                                        :message="pauseOwnTimerForm.errors.task_template_id"
                                    />
                                </div>

                                <div>
                                    <label
                                        for="own_timer_duration_minutes"
                                        class="block text-xs uppercase tracking-[0.25em] text-stone-400"
                                    >
                                        Длительность
                                    </label>
                                    <div
                                        id="own_timer_duration_minutes"
                                        class="mt-2 flex min-h-11 items-center rounded-[1.25rem] border border-stone-700 bg-stone-900 px-4 text-sm font-medium text-stone-100"
                                    >
                                        {{
                                            selectedPauseTaskTemplate
                                                ? `${selectedPauseTaskTemplate.default_duration_minutes} минут`
                                                : '—'
                                        }}
                                    </div>
                                </div>

                                <div>
                                    <label
                                        for="own_timer_summary"
                                        class="block text-xs uppercase tracking-[0.25em] text-stone-400"
                                    >
                                        Описание
                                    </label>
                                    <div
                                        id="own_timer_summary"
                                        class="mt-2 rounded-[1.25rem] border border-stone-700 bg-stone-900 px-4 py-4 text-sm leading-6 text-stone-200"
                                    >
                                        {{ selectedPauseTaskTemplate?.summary || 'Описание для этого задания пока не добавлено.' }}
                                    </div>
                                </div>

                                <div>
                                    <label
                                        for="own_timer_instructions"
                                        class="block text-xs uppercase tracking-[0.25em] text-stone-400"
                                    >
                                        Инструкции
                                    </label>
                                    <div
                                        id="own_timer_instructions"
                                        class="mt-2 rounded-[1.25rem] border border-stone-700 bg-stone-900 px-4 py-4 text-sm leading-6 text-stone-200"
                                    >
                                        {{ selectedPauseTaskTemplate?.instructions || 'Инструкции для этого задания пока не добавлены.' }}
                                    </div>
                                </div>

                                <div>
                                    <label
                                        for="own_timer_notes"
                                        class="block text-xs uppercase tracking-[0.25em] text-stone-400"
                                    >
                                        Заметки
                                    </label>
                                    <textarea
                                        id="own_timer_notes"
                                        v-model="pauseOwnTimerForm.notes"
                                        rows="2"
                                        class="mt-2 block w-full rounded-[1.25rem] border-stone-700 bg-stone-900 text-stone-100 shadow-sm focus:border-amber-400 focus:ring-amber-400"
                                    />
                                    <InputError
                                        class="mt-2 text-rose-300"
                                        :message="pauseOwnTimerForm.errors.notes"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    :disabled="pauseOwnTimerForm.processing || !hasTaskTemplates"
                                    class="inline-flex rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Пауза и свой таймер
                                </button>
                            </form>
                        </div>
                    </div>

                    <details class="lg:col-span-2 rounded-[1.5rem] bg-stone-100 p-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Текущее расписание
                                </p>
                                <p class="mt-2 text-lg font-semibold text-stone-950">
                                    Блоки и подробности
                                </p>
                            </div>
                            <span class="text-sm font-medium text-stone-600">Показать</span>
                        </summary>

                        <div class="mt-4 space-y-2">
                            <article
                                v-for="block in activeScheduleRun.blocks"
                                :key="block.id"
                                class="flex flex-wrap items-center gap-3 rounded-[1rem] bg-white px-3 py-2 text-sm"
                            >
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Блок {{ block.position }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        block.status === 'completed'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : block.status === 'paused'
                                              ? 'bg-stone-200 text-stone-800'
                                            : block.status === 'in_progress'
                                              ? 'bg-amber-100 text-amber-800'
                                              : block.is_next
                                                ? 'bg-stone-900 text-white'
                                                : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{
                                        block.status === 'completed'
                                            ? 'завершён'
                                            : block.status === 'paused'
                                              ? 'пауза'
                                            : block.status === 'in_progress'
                                              ? 'выполняется'
                                              : block.is_next
                                                ? 'следующий'
                                                : 'ожидает'
                                    }}
                                </span>
                                <p class="min-w-0 flex-1 truncate font-semibold text-stone-950">
                                    {{ block.task.title }}
                                </p>
                                <p class="text-sm font-medium text-stone-600">
                                    {{ block.duration_minutes }} минут
                                </p>
                                <p
                                    v-if="block.notes"
                                    class="w-full truncate text-xs text-stone-500 md:w-auto md:max-w-[18rem]"
                                >
                                    {{ block.notes }}
                                </p>
                            </article>
                        </div>
                    </details>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-6 py-8">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Расписание не запущено
                    </p>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Запустите одно из активных расписаний ниже, чтобы начать выполнение по порядку.
                    </p>
                    <div v-if="canStartScheduleRun" class="mt-5 grid gap-3 lg:grid-cols-2">
                        <button
                            v-for="scheduleTemplate in weeklyScheduleTemplates"
                            :key="scheduleTemplate.id"
                            type="button"
                            class="flex items-center justify-between rounded-[1.25rem] bg-white px-4 py-3 text-left ring-1 ring-stone-200 transition hover:ring-stone-400"
                            @click="startScheduleRun(scheduleTemplate.id)"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-stone-950">{{ scheduleTemplate.name }}</span>
                                <span class="mt-1 block truncate text-xs uppercase tracking-[0.18em] text-stone-500">
                                    {{ scheduleTemplate.weekday.label }}
                                </span>
                            </span>
                            <span class="ml-4 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">
                                Старт
                            </span>
                        </button>
                    </div>
                </div>
            </section>

            <section class="mt-4 rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-stone-200 md:p-6">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                        Текущий таймер
                    </p>
                    <h3 class="font-serif text-3xl text-stone-950">
                        Выполнение
                    </h3>
                </div>

                <div v-if="activeTaskSession" class="mt-5 grid gap-4 lg:grid-cols-[1fr_0.9fr]">
                    <div class="rounded-[1.75rem] bg-stone-100 p-6">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Активное задание
                        </p>
                        <h4 class="mt-3 text-2xl font-semibold text-stone-950">
                            {{ activeTaskSession.task_title }}
                        </h4>
                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            {{ activeTaskSession.task_summary || 'Описание задания не указано.' }}
                        </p>
                        <div class="mt-5 flex flex-wrap gap-4 text-sm text-stone-600">
                            <span>
                                Начато {{ activeTaskSession.started_at_label || 'только что' }}
                            </span>
                            <span v-if="activeTaskSession.planned_duration_minutes">
                                Запланировано {{ activeTaskSession.planned_duration_minutes }} минут
                            </span>
                            <span v-if="activeTaskSession.schedule_run_name">
                                {{
                                    `Расписание ${activeTaskSession.schedule_run_name}, блок ${activeTaskSession.schedule_run_block_position}`
                                }}
                            </span>
                            <span
                                v-if="
                                    activeTaskSession.source_type === 'ad_hoc' &&
                                    activeScheduleRun &&
                                    activeScheduleRun.status === 'paused'
                                "
                            >
                                {{ `Расписание ${activeScheduleRun.schedule_name} на паузе` }}
                            </span>
                        </div>
                        <div class="mt-6 rounded-[1.5rem] bg-white px-5 py-5 ring-1 ring-stone-200">
                            <div class="flex flex-wrap items-end gap-4">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                        {{
                                            activeTaskSession.source_type === 'ad_hoc'
                                                ? 'Свой таймер'
                                                : 'Таймер'
                                        }}
                                    </p>
                                    <p
                                        class="mt-3 font-mono text-4xl font-semibold"
                                        :class="
                                            activeTaskIsOvertime
                                                ? 'text-rose-700'
                                                : 'text-stone-950'
                                        "
                                    >
                                        {{ activeTaskTimerDisplay }}
                                    </p>
                                </div>
                                <p class="pb-1 text-sm text-stone-600">
                                    {{ activeTaskTimerDisplayLabel }}
                                </p>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Прошло
                                    </p>
                                    <p class="mt-2 font-mono text-lg font-semibold text-stone-950">
                                        {{ activeTaskElapsedLabel }}
                                    </p>
                                </div>
                                <div v-if="activeTaskPlannedLabel !== null">
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        План
                                    </p>
                                    <p class="mt-2 font-mono text-lg font-semibold text-stone-950">
                                        {{ activeTaskPlannedLabel }}
                                    </p>
                                </div>
                                <div v-if="activeTaskEndsAtLabel !== null">
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Плановое завершение
                                    </p>
                                    <p class="mt-2 text-lg font-semibold text-stone-950">
                                        {{ activeTaskEndsAtLabel }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="activeTaskProgressPercent !== null"
                                class="mt-5 h-2 overflow-hidden rounded-full bg-stone-200"
                            >
                                <div
                                    class="h-full rounded-full transition-[width]"
                                    :class="
                                        activeTaskIsOvertime
                                            ? 'bg-rose-500'
                                            : 'bg-amber-500'
                                    "
                                    :style="{ width: `${activeTaskProgressPercent}%` }"
                                />
                            </div>
                        </div>
                        <p class="mt-5 text-sm leading-6 text-stone-600">
                            {{
                                activeTaskSession.task_instructions ||
                                'Инструкции к заданию для этой сессии не сохранены.'
                            }}
                        </p>
                        <p
                            v-if="activeTaskSession.assignment_notes"
                            class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950 ring-1 ring-amber-200"
                        >
                            {{ activeTaskSession.assignment_notes }}
                        </p>
                    </div>

                    <form
                        class="rounded-[1.75rem] bg-stone-950 p-6 text-white"
                        @submit.prevent="stopTaskSession"
                    >
                        <p class="text-xs uppercase tracking-[0.25em] text-amber-300/75">
                            Завершение сессии
                        </p>
                        <p class="mt-4 text-sm leading-7 text-stone-300">
                            {{
                                activeTaskSession.source_type === 'schedule'
                                    ? 'Остановите текущий блок расписания, когда закончите. Следующий блок останется заблокированным, пока этот не будет завершён.'
                                    : activeTaskSession.source_type === 'ad_hoc'
                                      ? 'Остановите свой таймер, когда закончите. Приостановленное расписание останется на паузе, пока вы его не возобновите.'
                                    : 'Используйте это действие, чтобы остановить текущее задание по таймеру.'
                            }}
                        </p>
                        <label
                            for="completion_notes"
                            class="mt-6 block text-xs uppercase tracking-[0.25em] text-stone-400"
                        >
                            Заметки о завершении (необязательно)
                        </label>
                        <textarea
                            id="completion_notes"
                            v-model="stopTaskSessionForm.completion_notes"
                            rows="2"
                            class="mt-3 block w-full rounded-[1.25rem] border-stone-700 bg-stone-900 text-stone-100 shadow-sm focus:border-amber-400 focus:ring-amber-400"
                        />
                        <InputError
                            class="mt-2 text-rose-300"
                            :message="stopTaskSessionForm.errors.completion_notes"
                        />
                        <button
                            type="submit"
                            :disabled="stopTaskSessionForm.processing"
                            class="mt-6 inline-flex rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Остановить задание
                        </button>
                    </form>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-6 py-8">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Активного таймера нет
                    </p>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        {{
                            activeScheduleRun && activeScheduleRun.status === 'paused'
                                ? 'Возобновите приостановленное расписание после завершения своего таймера.'
                                : activeScheduleRun
                                  ? 'Когда будете готовы, запустите следующий блок из расписания выше.'
                                : 'Сначала запустите расписание, затем каждый его блок будет выполняться из этого раздела.'
                        }}
                    </p>
                </div>
            </section>

            <section class="mt-4 rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-stone-200 md:p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-col gap-2">
                        <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                            Расписание
                        </p>
                        <h3 class="font-serif text-3xl text-stone-950">
                            Повторяющийся план
                        </h3>
                    </div>

                    <Link
                        v-if="studentCanManageOwnSchedule"
                        :href="route('student.schedules.index')"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Управлять расписаниями
                    </Link>
                </div>

                <details class="mt-6 rounded-[1.5rem] bg-stone-100 p-5" :open="!activeScheduleRun">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                        <p class="text-lg font-semibold text-stone-950">
                            Доступные расписания
                        </p>
                        <span class="text-sm font-medium text-stone-600">Показать</span>
                    </summary>

                <div v-if="weeklyScheduleTemplates.length > 0" class="mt-5 space-y-5">
                    <article
                        v-for="scheduleTemplate in weeklyScheduleTemplates"
                        :key="scheduleTemplate.id"
                        class="rounded-[1.75rem] bg-stone-100 p-6"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-stone-200 pb-5 md:flex-row md:items-start md:justify-between"
                        >
                            <div>
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    Расписание
                                </p>
                                <h4 class="mt-2 text-2xl font-semibold text-stone-950">
                                    {{ scheduleTemplate.name }}
                                </h4>
                                <p class="mt-3 text-sm leading-6 text-stone-600">
                                    {{ scheduleTemplate.notes || 'Заметка к расписанию не указана.' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    v-if="canStartScheduleRun"
                                    type="button"
                                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    @click="startScheduleRun(scheduleTemplate.id)"
                                >
                                    Запустить расписание
                                </button>
                            </div>
                        </div>

                        <div class="mt-5 space-y-2">
                            <article
                                v-for="entry in scheduleTemplate.entries"
                                :key="entry.id"
                                class="flex flex-wrap items-center gap-3 rounded-[1rem] bg-white px-3 py-2 text-sm"
                            >
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    {{ entry.start_time }}
                                </p>
                                <p class="min-w-0 flex-1 truncate font-semibold text-stone-950">
                                    {{ entry.task.title }}
                                </p>
                                <p class="text-sm font-medium text-stone-600">
                                    {{ entry.duration_minutes }} минут
                                </p>
                                <p
                                    v-if="entry.notes"
                                    class="w-full truncate text-xs text-stone-500 md:w-auto md:max-w-[18rem]"
                                >
                                    {{ entry.notes }}
                                </p>
                            </article>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-5 rounded-[1.5rem] bg-white px-6 py-8">
                    <template v-if="studentCanManageOwnSchedule">
                        <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                            Расписание ещё не задано
                        </p>
                        <p class="mt-3 text-sm leading-7 text-stone-600">
                            Сначала задайте недельное расписание, после чего оно появится здесь
                            для запуска и управления таймером.
                        </p>
                        <Link
                            :href="route('student.schedules.create')"
                            class="mt-5 inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                        >
                            Создать расписание
                        </Link>
                    </template>
                    <template v-else>
                        <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                            Управление расписанием отключено
                        </p>
                        <p class="mt-3 text-sm leading-7 text-stone-600">
                            Наставник отключил прямое редактирование расписания для этого ученика.
                        </p>
                    </template>
                </div>
                </details>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
