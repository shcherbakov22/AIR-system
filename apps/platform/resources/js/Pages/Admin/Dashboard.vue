<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

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
        capture_kind: string;
        captured_at?: string | null;
        captured_at_label?: string | null;
        uploaded_at?: string | null;
        uploaded_at_label?: string | null;
        task_title?: string | null;
        source_label?: string | null;
        image_url: string;
    } | null;
    latest_camera_capture?: {
        id: number;
        capture_kind: string;
        captured_at?: string | null;
        captured_at_label?: string | null;
        uploaded_at?: string | null;
        uploaded_at_label?: string | null;
        task_title?: string | null;
        source_label?: string | null;
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

type DashboardCapture = NonNullable<DashboardStudent['latest_screen_capture']>;

const selectedCapture = ref<(DashboardCapture & { studentName: string }) | null>(null);
const selectedCaptureHistory = ref<Array<DashboardCapture & { studentName: string }>>([]);
const selectedCaptureHistoryIndex = ref(0);
const captureHistoryLoading = ref(false);
const speechEnabled = ref(false);
const isSpeaking = ref(false);

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
let speechInterval: number | null = null;
let isReloading = false;
const scheduleBoardRefs = new Map<number, HTMLElement>();

const reloadMonitorBoard = () => {
    if (isReloading || document.hidden) {
        return;
    }

    isReloading = true;

    router.reload({
        only: ['serverNow', 'monitorStudents'],
        onFinish: () => {
            isReloading = false;
        },
    });
};

const handleVisibilityChange = () => {
    if (!document.hidden) {
        reloadMonitorBoard();
        pollSpeechAnnouncements();
    }
};

const setScheduleBoardRef = (studentId: number, element: unknown) => {
    if (element instanceof HTMLElement) {
        scheduleBoardRefs.set(studentId, element);
        return;
    }

    scheduleBoardRefs.delete(studentId);
};

const scrollScheduleBoardsToActiveBlock = () => {
    scheduleBoardRefs.forEach((board) => {
        const activeBlock = board.querySelector<HTMLElement>('[data-active-block="true"]');

        if (!activeBlock) {
            board.scrollTop = 0;
            return;
        }

        const boardHeight = board.clientHeight;
        const targetTop = Math.max(activeBlock.offsetTop - Math.max((boardHeight - activeBlock.offsetHeight) / 2, 24), 0);

        board.scrollTo({
            top: targetTop,
            behavior: 'smooth',
        });
    });
};

const getSpeechVoice = (): SpeechSynthesisVoice | null => {
    const voices = window.speechSynthesis.getVoices();

    return voices.find((voice) => voice.lang?.toLowerCase() === 'en-us')
        ?? voices.find((voice) => voice.lang?.toLowerCase() === 'en-gb')
        ?? voices.find((voice) => voice.lang?.toLowerCase().startsWith('en'))
        ?? null;
};

const speakText = (text: string): Promise<void> => new Promise((resolve) => {
    const utterance = new SpeechSynthesisUtterance(text);
    const voice = getSpeechVoice();

    utterance.lang = 'en-US';

    if (!voice) {
        resolve();
        return;
    }

    utterance.voice = voice;
    utterance.rate = 1;
    utterance.pitch = 1;

    utterance.onend = () => {
        isSpeaking.value = false;
        resolve();
    };

    utterance.onerror = () => {
        isSpeaking.value = false;
        resolve();
    };

    isSpeaking.value = true;
    window.speechSynthesis.speak(utterance);
});

const pollSpeechAnnouncements = async () => {
    if (!speechEnabled.value || isSpeaking.value || document.hidden) {
        return;
    }

    try {
        const response = await window.fetch(route('admin.speech-announcements.next'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json() as {
            announcement: null | {
                id: number;
                kind: string;
                message: string;
            };
        };

        if (!payload.announcement?.message) {
            return;
        }

        await speakText(payload.announcement.message);
    } catch {
        isSpeaking.value = false;
    }
};

const applySpeechState = (enabled: boolean) => {
    speechEnabled.value = enabled;
    window.localStorage.setItem('mentor-monitor-speech-enabled', enabled ? '1' : '0');

    if (!enabled) {
        if (speechInterval !== null) {
            window.clearInterval(speechInterval);
            speechInterval = null;
        }

        window.speechSynthesis.cancel();
        isSpeaking.value = false;
        return;
    }

    pollSpeechAnnouncements();

    if (speechInterval === null) {
        speechInterval = window.setInterval(pollSpeechAnnouncements, 4000);
    }
};

const toggleSpeech = () => {
    applySpeechState(!speechEnabled.value);
};

onMounted(() => {
    syncLiveNow();
    clockInterval = window.setInterval(syncLiveNow, 1000);
    reloadInterval = window.setInterval(reloadMonitorBoard, 5000);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', reloadMonitorBoard);
    nextTick(scrollScheduleBoardsToActiveBlock);

    const savedSpeechState = window.localStorage.getItem('mentor-monitor-speech-enabled');
    applySpeechState(savedSpeechState === '1');
});

onBeforeUnmount(() => {
    if (clockInterval !== null) {
        window.clearInterval(clockInterval);
    }

    if (reloadInterval !== null) {
        window.clearInterval(reloadInterval);
    }

    if (speechInterval !== null) {
        window.clearInterval(speechInterval);
    }

    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('focus', reloadMonitorBoard);
    window.speechSynthesis.cancel();
});

watch(
    () => props.monitorStudents,
    async () => {
        await nextTick();
        scrollScheduleBoardsToActiveBlock();
    },
    { deep: true },
);

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

const openCapture = (studentName: string, capture: DashboardCapture) => {
    selectedCapture.value = {
        ...capture,
        studentName,
    };
    selectedCaptureHistory.value = [{
        ...capture,
        studentName,
    }];
    selectedCaptureHistoryIndex.value = 0;
    loadCaptureHistory(studentName, capture.id);
};

const closeCapture = () => {
    selectedCapture.value = null;
    selectedCaptureHistory.value = [];
    selectedCaptureHistoryIndex.value = 0;
    captureHistoryLoading.value = false;
};

const openCapturePlaceholder = (
    studentName: string,
    captureKind: 'screen' | 'camera',
) => {
    selectedCapture.value = {
        id: 0,
        capture_kind: captureKind,
        captured_at: null,
        captured_at_label: null,
        uploaded_at: null,
        uploaded_at_label: null,
        task_title: null,
        source_label: null,
        image_url: '',
        studentName,
    };
    selectedCaptureHistory.value = [];
    selectedCaptureHistoryIndex.value = 0;
};

const loadCaptureHistory = async (studentName: string, captureId: number) => {
    if (!captureId) {
        return;
    }

    captureHistoryLoading.value = true;

    try {
        const response = await window.fetch(route('admin.student-monitor-captures.day-history', captureId), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json() as {
            captures: DashboardCapture[];
        };

        selectedCaptureHistory.value = payload.captures.map((historyCapture) => ({
            ...historyCapture,
            studentName,
        }));

        const currentIndex = selectedCaptureHistory.value.findIndex((historyCapture) => historyCapture.id === captureId);

        if (currentIndex >= 0) {
            selectedCaptureHistoryIndex.value = currentIndex;
            selectedCapture.value = selectedCaptureHistory.value[currentIndex];
        }
    } finally {
        captureHistoryLoading.value = false;
    }
};

const showCaptureHistoryItem = (index: number) => {
    const nextCapture = selectedCaptureHistory.value[index];

    if (!nextCapture) {
        return;
    }

    selectedCaptureHistoryIndex.value = index;
    selectedCapture.value = nextCapture;
};

const showPreviousCapture = () => {
    showCaptureHistoryItem(selectedCaptureHistoryIndex.value + 1);
};

const showNextCapture = () => {
    showCaptureHistoryItem(selectedCaptureHistoryIndex.value - 1);
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

    <AuthenticatedLayout :sidebar-drawer="true" :full-width="true">
        <button
            type="button"
            class="fixed right-2 top-1.5 z-30 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold text-stone-700 transition hover:text-stone-950"
            :class="speechEnabled ? 'bg-amber-100 text-amber-900' : 'bg-white/80 text-stone-700'"
            @click="toggleSpeech"
        >
            <span class="sr-only">Toggle voice announcements</span>
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M4 8H7L11 5V15L7 12H4V8Z" stroke-linejoin="round" />
                <path v-if="speechEnabled" d="M14 7C15.3333 8.16667 16 9.16667 16 10C16 10.8333 15.3333 11.8333 14 13" stroke-linecap="round" />
            </svg>
            <span>{{ speechEnabled ? (isSpeaking ? 'Speaking' : 'Voice on') : 'Voice off' }}</span>
        </button>

        <div class="h-[calc(100vh-1.75rem)] overflow-hidden px-2 pt-8 pb-2 sm:px-3 sm:pt-8 sm:pb-3 lg:px-4 lg:pt-8 lg:pb-4">
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
                class="grid h-full items-stretch gap-2 overflow-hidden md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-7"
            >
                <article
                    v-for="student in monitorStudents"
                    :key="student.id"
                    class="relative flex h-full min-h-0 flex-col overflow-hidden rounded-[1.25rem] bg-white p-2 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="flex items-start justify-between gap-1.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-stone-950">
                                {{ student.display_name }}
                            </p>
                        </div>

                        <Link
                            :href="route('admin.students.progress', student.id)"
                            class="shrink-0 rounded-full border border-stone-300 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                        >
                            Progress
                        </Link>
                    </div>

                    <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                        <button
                            v-if="student.latest_screen_capture"
                            type="button"
                            class="group overflow-hidden rounded-[0.75rem] border border-stone-200 bg-stone-50"
                            @click="openCapture(student.display_name, student.latest_screen_capture)"
                        >
                            <img
                                :src="student.latest_screen_capture.image_url"
                                alt="Latest screen capture"
                                class="aspect-[4/3] h-auto w-full object-cover transition group-hover:scale-[1.02]"
                            >
                            <div class="px-1.5 py-1">
                                <p class="text-[8px] font-semibold uppercase tracking-[0.14em] text-stone-600">
                                    Screen
                                </p>
                                <p class="truncate text-[9px] text-stone-500">
                                    {{ student.latest_screen_capture.uploaded_at_label ?? student.latest_screen_capture.captured_at_label ?? 'Just now' }}
                                </p>
                            </div>
                        </button>
                        <button
                            v-else
                            type="button"
                            class="flex aspect-[4/3] flex-col items-center justify-center rounded-[0.75rem] border border-dashed border-stone-300 bg-stone-50 px-2 text-center"
                            @click="openCapturePlaceholder(student.display_name, 'screen')"
                        >
                            <p class="text-[8px] font-semibold uppercase tracking-[0.14em] text-stone-500">
                                Screen
                            </p>
                            <p class="mt-1 text-[9px] text-stone-400">
                                No capture
                            </p>
                        </button>

                        <button
                            v-if="student.latest_camera_capture"
                            type="button"
                            class="group overflow-hidden rounded-[0.75rem] border border-stone-200 bg-stone-50"
                            @click="openCapture(student.display_name, student.latest_camera_capture)"
                        >
                            <img
                                :src="student.latest_camera_capture.image_url"
                                alt="Latest camera capture"
                                class="aspect-[4/3] h-auto w-full object-cover transition group-hover:scale-[1.02]"
                            >
                            <div class="px-1.5 py-1">
                                <p class="text-[8px] font-semibold uppercase tracking-[0.14em] text-stone-600">
                                    Camera
                                </p>
                                <p class="truncate text-[9px] text-stone-500">
                                    {{ student.latest_camera_capture.uploaded_at_label ?? student.latest_camera_capture.captured_at_label ?? 'Just now' }}
                                </p>
                            </div>
                        </button>
                        <button
                            v-else
                            type="button"
                            class="flex aspect-[4/3] flex-col items-center justify-center rounded-[0.75rem] border border-dashed border-stone-300 bg-stone-50 px-2 text-center"
                            @click="openCapturePlaceholder(student.display_name, 'camera')"
                        >
                            <p class="text-[8px] font-semibold uppercase tracking-[0.14em] text-stone-500">
                                Camera
                            </p>
                            <p class="mt-1 text-[9px] text-stone-400">
                                No capture
                            </p>
                        </button>
                    </div>

                    <div class="mt-1.5 rounded-[0.85rem] bg-stone-50 px-2 py-1.5">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                            Violations
                        </p>

                        <div class="mt-1.5 flex gap-1.5">
                            <select
                                v-model="selectedRules[student.id]"
                                class="min-w-0 flex-1 rounded-full border-stone-300 px-3 py-1.5 text-xs shadow-sm focus:border-amber-700 focus:ring-amber-700"
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
                                class="inline-flex rounded-full bg-amber-500 px-3 py-1.5 text-xs font-semibold text-stone-950 transition hover:bg-amber-400"
                                :disabled="!selectedRules[student.id]"
                                @click="createViolation(student.id)"
                            >
                                Add
                            </button>
                        </div>

                        <div v-if="student.open_violations.length > 0" class="mt-1.5 space-y-1">
                            <div
                                v-for="violation in student.open_violations"
                                :key="violation.id"
                                class="flex items-center justify-between gap-2 rounded-[0.75rem] bg-white px-2 py-1.5 ring-1 ring-stone-200"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] font-medium text-stone-900">
                                        {{ violation.rule_title }}
                                    </p>
                                    <p class="text-[10px] text-stone-500">
                                        {{ violation.occurred_at_label }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex rounded-full border border-stone-300 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    @click="deleteViolation(violation.id)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <p v-else class="mt-1.5 text-[11px] text-stone-600">
                            No open violations.
                        </p>
                    </div>

                    <div class="mt-1.5 min-w-0">
                        <p class="truncate text-[13px] font-medium text-stone-900">
                            {{ student.schedule_board?.name ?? 'No schedule' }}
                        </p>
                    </div>

                    <div class="mt-1.5 rounded-[0.85rem] bg-stone-100 px-2 py-1.5">
                        <p class="truncate text-[13px] font-medium text-stone-900">
                            {{ student.active_task_session?.task_title ?? 'No active task' }}
                        </p>
                        <p class="truncate text-[10px] leading-tight text-stone-500">
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

                    <div v-if="student.schedule_board" class="mt-1.5 min-h-0 flex-1 overflow-hidden rounded-[0.65rem] bg-stone-50/60 p-px">
                        <div
                            :ref="(element) => setScheduleBoardRef(student.id, element)"
                            class="grid h-full grid-cols-1 content-start gap-px overflow-y-auto pr-px"
                        >
                            <div
                                v-for="block in student.schedule_board.blocks"
                                :key="`${student.id}-${student.schedule_board.source_type}-${block.id}`"
                                class="rounded-[0.35rem] border px-1 py-[3px]"
                                :class="blockRowClass(block.status)"
                                :title="blockTooltip(block)"
                                :data-active-block="block.status === 'in_progress' || block.status === 'paused' ? 'true' : 'false'"
                            >
                                <div class="flex items-center justify-between gap-1.5 text-[9px] leading-none">
                                    <p class="min-w-0 truncate font-medium text-stone-900">
                                        {{ block.position }}. {{ block.task_title }}
                                    </p>
                                    <span class="shrink-0 text-[8px] font-semibold text-stone-700">
                                        {{ block.displayDurationLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-1.5 flex min-h-0 flex-1 items-center justify-center rounded-[1rem] border border-dashed border-stone-300 bg-stone-50 px-4 py-6 text-center text-sm text-stone-500"
                    >
                        No schedule available.
                    </div>
                </article>
            </div>

            <div
                v-if="selectedCapture"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/85 p-4"
                @click.self="closeCapture"
            >
                <div class="w-full max-w-6xl overflow-hidden rounded-[1.25rem] bg-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-stone-200 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-stone-950">
                                {{ selectedCapture.studentName }} - {{ selectedCapture.capture_kind === 'camera' ? 'Camera' : 'Screen' }}
                            </p>
                            <p class="truncate text-xs text-stone-500">
                                {{ selectedCapture.task_title ?? 'No activity snapshot' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <p
                                v-if="selectedCaptureHistory.length > 0"
                                class="text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-500"
                            >
                                {{ selectedCaptureHistoryIndex + 1 }} / {{ selectedCaptureHistory.length }}
                            </p>

                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="captureHistoryLoading || selectedCaptureHistoryIndex >= selectedCaptureHistory.length - 1"
                                @click="showPreviousCapture"
                            >
                                Older
                            </button>

                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="captureHistoryLoading || selectedCaptureHistoryIndex <= 0"
                                @click="showNextCapture"
                            >
                                Newer
                            </button>

                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700"
                                @click="closeCapture"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="flex min-h-[24rem] items-center justify-center bg-stone-950">
                            <img
                                v-if="selectedCapture.image_url"
                                :src="selectedCapture.image_url"
                                :alt="selectedCapture.capture_kind === 'camera' ? 'Camera capture' : 'Screen capture'"
                                class="max-h-[80vh] w-full object-contain"
                            >
                            <div
                                v-else
                                class="flex h-full w-full flex-col items-center justify-center gap-2 px-6 text-center text-stone-300"
                            >
                                <p class="text-sm font-semibold uppercase tracking-[0.16em]">
                                    {{ selectedCapture.capture_kind === 'camera' ? 'Camera' : 'Screen' }}
                                </p>
                                <p class="text-base font-medium text-white">
                                    No image uploaded
                                </p>
                                <p class="text-sm text-stone-400">
                                    This is the placeholder fullscreen state.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 px-4 py-4 text-sm text-stone-700">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                    Uploaded
                                </p>
                                <p class="mt-1 text-sm font-medium text-stone-950">
                                    {{ selectedCapture.uploaded_at_label ?? selectedCapture.captured_at_label ?? 'Unknown' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                    Captured
                                </p>
                                <p class="mt-1 text-sm font-medium text-stone-950">
                                    {{ selectedCapture.captured_at_label ?? selectedCapture.uploaded_at_label ?? 'Unknown' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                    Activity
                                </p>
                                <p class="mt-1 text-sm font-medium text-stone-950">
                                    {{ selectedCapture.task_title ?? 'No activity snapshot' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                    Source
                                </p>
                                <p class="mt-1 text-sm font-medium text-stone-950">
                                    {{ selectedCapture.source_label ?? 'Unknown source' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
