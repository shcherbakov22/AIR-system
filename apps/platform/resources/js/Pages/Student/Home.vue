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
    scheduleFinishWindow: {
        can_finish_now: boolean;
        opens_at_label: string;
        closes_at_label: string;
    };
    communicationGate: {
        has_unread: boolean;
        unread_mentor_chat: {
            id: number;
            body?: string | null;
            created_at_label?: string | null;
            sender_name?: string | null;
            has_attachment: boolean;
        } | null;
        unread_announcement: {
            id: number;
            body?: string | null;
            created_at_label?: string | null;
            sender_name?: string | null;
            has_attachment: boolean;
        } | null;
    };
    assignmentGate: {
        has_unread: boolean;
        unread_count: number;
        latest_unread_assignment: {
            id: number;
            created_at_label?: string | null;
            creator_name?: string | null;
        } | null;
    };
    violationSummary: {
        open_violations: number;
    };
    openViolations: Array<{
        id: number;
        rule_title: string;
        push_up_count: number;
        occurred_at_label?: string | null;
        start_push_up_url?: string | null;
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
            status_label?: string;
            start_time: string;
            duration_minutes: number;
            notes?: string | null;
            is_next: boolean;
            actual_duration_seconds?: number;
            actual_duration_label?: string | null;
            unfinished_url?: string | null;
            resume_url?: string | null;
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
        can_end_early: boolean;
        started_at?: string | null;
        started_at_label?: string | null;
        source_type: string;
        schedule_run_id?: number | null;
        schedule_run_name?: string | null;
        schedule_run_block_id?: number | null;
        schedule_run_block_position?: number | null;
        unfinished_url?: string | null;
    } | null;
    pausedTaskSession: {
        id: number;
        status: string;
        task_assignment_id?: number | null;
        task_title: string;
        task_summary?: string | null;
        task_instructions?: string | null;
        assignment_notes?: string | null;
        planned_duration_minutes?: number | null;
        duration_seconds?: number | null;
        ended_at?: string | null;
        ended_at_label?: string | null;
        resume_url: string;
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
        can_end_early: boolean;
        can_interrupt_schedule: boolean;
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
const canFinishScheduleNow = computed(() =>
    props.activeScheduleRun !== null &&
    !activeScheduleTaskIsRunning.value &&
    props.scheduleFinishWindow.can_finish_now,
);
const pauseOwnTimerFormOpen = ref(false);
const pauseTaskTemplates = computed(() => {
    if (activeScheduleTaskIsRunning.value) {
        return props.taskTemplates.filter((taskTemplate) => taskTemplate.can_interrupt_schedule);
    }

    return props.taskTemplates;
});
const hasTaskTemplates = computed(() => pauseTaskTemplates.value.length > 0);
const hasBlockingViolations = computed(() => props.openViolations.length > 0);
const queueViolationPushUps = (url?: string | null) => {
    if (!url) {
        return;
    }

    router.post(url, {}, { preserveScroll: true, preserveState: true });
};
const hasBlockingCommunication = computed(() => props.communicationGate.has_unread);
const hasBlockingAssignments = computed(() => props.assignmentGate.has_unread);
const hasUnreadMentorChat = computed(() => props.communicationGate.unread_mentor_chat !== null);
const hasUnreadAnnouncement = computed(() => props.communicationGate.unread_announcement !== null);

const stopTaskSessionForm = useForm({});
const pauseOwnTimerForm = useForm({
    task_template_id: '',
    duration_minutes: '',
});
const selectedPauseTaskTemplate = computed(
    () => pauseTaskTemplates.value.find((taskTemplate) => String(taskTemplate.id) === pauseOwnTimerForm.task_template_id) ?? null,
);

watch(
    selectedPauseTaskTemplate,
    (taskTemplate) => {
        pauseOwnTimerForm.duration_minutes = taskTemplate ? String(taskTemplate.default_duration_minutes) : '';
    },
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

    stopAttentionTracking();
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
        return 'remaining';
    }

    if (activeTaskIsOvertime.value) {
        return 'overtime';
    }

    return 'elapsed';
});
const activeTaskEndsAtLabel = computed(() => {
    if (activeTaskStartedAtMs.value === null || activeTaskPlannedSeconds.value === null) {
        return null;
    }

    const remainingCurrentSegmentSeconds = Math.max(activeTaskPlannedSeconds.value - activeTaskElapsedBeforeCurrentSegment.value, 0);
    return formatClockTime(activeTaskStartedAtMs.value + remainingCurrentSegmentSeconds * 1000);
});

const BODY_MISSING_VIOLATION_SECONDS = 10;
const BODY_DETECTION_INTERVAL_MS = 250;
const BODY_MISSING_EVENT_REPEAT_MS = 30_000;
const BODY_LANDMARK_CONFIDENCE = 0.35;
const activeTaskSessionId = computed(() => props.activeTaskSession?.id ?? null);

let attentionVideo: HTMLVideoElement | null = null;
let attentionStream: MediaStream | null = null;
let poseLandmarker: any = null;
let attentionAnimationFrame: number | null = null;
let attentionLastDetectionAt = 0;
let bodyMissingStartedAt: number | null = null;
let bodyMissingLastReportedAt: number | null = null;
let bodyMissingEventToken: string | null = null;
let attentionStartToken = 0;

const stopAttentionTracking = () => {
    attentionStartToken += 1;

    if (attentionAnimationFrame !== null) {
        window.cancelAnimationFrame(attentionAnimationFrame);
        attentionAnimationFrame = null;
    }

    if (attentionStream) {
        attentionStream.getTracks().forEach((track) => track.stop());
        attentionStream = null;
    }

    if (attentionVideo) {
        attentionVideo.srcObject = null;
        attentionVideo = null;
    }

    bodyMissingStartedAt = null;
    bodyMissingLastReportedAt = null;
    bodyMissingEventToken = null;
    attentionLastDetectionAt = 0;
};

const landmarkConfidence = (landmark: { visibility?: number; presence?: number } | undefined): number => {
    if (!landmark) {
        return 0;
    }

    return Math.min(landmark.visibility ?? 1, landmark.presence ?? 1);
};

const bodyPresenceScore = (landmarks: Array<{ visibility?: number; presence?: number }> | undefined): number => {
    if (!landmarks || landmarks.length === 0) {
        return 0;
    }

    const coreIndexes = [0, 11, 12, 23, 24];
    const coreScores = coreIndexes.map((index) => landmarkConfidence(landmarks[index]));
    const visibleCoreCount = coreScores.filter((score) => score >= BODY_LANDMARK_CONFIDENCE).length;
    const visibleLandmarkCount = landmarks.filter((landmark) => landmarkConfidence(landmark) >= BODY_LANDMARK_CONFIDENCE).length;

    if (visibleCoreCount >= 2 || visibleLandmarkCount >= 5) {
        return Math.max(...coreScores, visibleLandmarkCount / Math.max(landmarks.length, 1));
    }

    return 0;
};

const postBodyMissingEvent = async (awaySeconds: number, bodyConfidence: number) => {
    const taskSessionId = activeTaskSessionId.value;

    if (!taskSessionId || !bodyMissingEventToken) {
        return;
    }

    await fetch(route('student.attention.events.store'), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            event_type: 'body_missing',
            occurred_at: new Date().toISOString(),
            payload: {
                reason: 'body_missing',
                score: bodyConfidence,
                body_confidence: bodyConfidence,
                away_seconds: awaySeconds,
                client_event_id: `body-missing-${taskSessionId}-${bodyMissingEventToken}`,
            },
        }),
    });
};

const processBodyDetectionResult = (bodyPresent: boolean, bodyConfidence: number) => {
    const now = Date.now();

    if (bodyPresent) {
        bodyMissingStartedAt = null;
        bodyMissingLastReportedAt = null;
        bodyMissingEventToken = null;
        return;
    }

    bodyMissingStartedAt ??= now;
    bodyMissingEventToken ??= String(Math.floor(bodyMissingStartedAt / 1000));

    const awaySeconds = (now - bodyMissingStartedAt) / 1000;

    if (
        awaySeconds >= BODY_MISSING_VIOLATION_SECONDS &&
        (bodyMissingLastReportedAt === null || now - bodyMissingLastReportedAt >= BODY_MISSING_EVENT_REPEAT_MS)
    ) {
        bodyMissingLastReportedAt = now;
        void postBodyMissingEvent(awaySeconds, bodyConfidence);
    }
};

const runBodyDetectionLoop = () => {
    if (!attentionVideo || !poseLandmarker || activeTaskSessionId.value === null) {
        return;
    }

    const now = performance.now();

    if (now - attentionLastDetectionAt >= BODY_DETECTION_INTERVAL_MS && attentionVideo.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
        attentionLastDetectionAt = now;

        try {
            const result = poseLandmarker.detectForVideo(attentionVideo, now);
            const score = bodyPresenceScore(result?.landmarks?.[0]);
            processBodyDetectionResult(score > 0, score);
        } catch {
            processBodyDetectionResult(false, 0);
        }
    }

    attentionAnimationFrame = window.requestAnimationFrame(runBodyDetectionLoop);
};

const startAttentionTracking = async () => {
    const taskSessionId = activeTaskSessionId.value;

    if (!taskSessionId || attentionStream) {
        return;
    }

    const startToken = attentionStartToken;

    try {
        const [{ FilesetResolver, PoseLandmarker }, stream] = await Promise.all([
            import('@mediapipe/tasks-vision'),
            navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                },
                audio: false,
            }),
        ]);

        if (startToken !== attentionStartToken || activeTaskSessionId.value !== taskSessionId) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }

        attentionStream = stream;
        attentionVideo = document.createElement('video');
        attentionVideo.muted = true;
        attentionVideo.playsInline = true;
        attentionVideo.srcObject = stream;
        await attentionVideo.play();

        if (!poseLandmarker) {
            const vision = await FilesetResolver.forVisionTasks('/mediapipe/tasks-vision/wasm');
            const options = {
                baseOptions: {
                    modelAssetPath: '/mediapipe/models/pose_landmarker_lite.task',
                    delegate: 'GPU' as const,
                },
                runningMode: 'VIDEO' as const,
                numPoses: 1,
                minPoseDetectionConfidence: 0.5,
                minPosePresenceConfidence: 0.5,
                minTrackingConfidence: 0.5,
            };

            try {
                poseLandmarker = await PoseLandmarker.createFromOptions(vision, options);
            } catch {
                poseLandmarker = await PoseLandmarker.createFromOptions(vision, {
                    ...options,
                    baseOptions: {
                        ...options.baseOptions,
                        delegate: 'CPU',
                    },
                });
            }
        }

        runBodyDetectionLoop();
    } catch {
        stopAttentionTracking();
    }
};

watch(
    activeTaskSessionId,
    (taskSessionId) => {
        stopAttentionTracking();

        if (taskSessionId !== null && typeof navigator.mediaDevices?.getUserMedia === 'function') {
            void startAttentionTracking();
        }
    },
    { immediate: true },
);

const currentSummaryDetail = computed(() => {
    if (props.pausedTaskSession) {
        return 'This unfinished task can be resumed from where it stopped.';
    }

    if (props.activeTaskSession?.source_type === 'ad_hoc') {
        return 'Your custom timer is keeping the schedule paused.';
    }

    if (props.activeTaskSession?.schedule_run_block_position) {
        return `Block ${props.activeTaskSession.schedule_run_block_position} is running now.`;
    }

    if (props.activeScheduleRun?.paused_block) {
        return `Block ${props.activeScheduleRun.paused_block.position} is paused.`;
    }

    return null;
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

const markTaskSessionUnfinished = () => {
    if (!props.activeTaskSession?.unfinished_url) {
        return;
    }

    stopTaskSessionForm.patch(props.activeTaskSession.unfinished_url, {
        preserveScroll: true,
        onSuccess: () => {
            stopTaskSessionForm.reset();
        },
    });
};

const resumePausedTaskSession = () => {
    if (!props.pausedTaskSession?.resume_url) {
        return;
    }

    router.post(props.pausedTaskSession.resume_url, {}, { preserveScroll: true });
};

const resumeScheduleBlockTask = (resumeUrl?: string | null) => {
    if (!resumeUrl) {
        return;
    }

    router.post(resumeUrl, {}, { preserveScroll: true });
};

const openAiChatForViolation = (violationId: number, ruleTitle: string) => {
    router.get(
        route('student.ai-overseer-decisions.index'),
        { violation_id: violationId, label: ruleTitle },
        { preserveScroll: false },
    );
};

const showBlockingViolationDialog = () => {
    if (!hasBlockingViolations.value) {
        return false;
    }

    const lines = [
        'There are open violations:',
        ...props.openViolations.map((violation) =>
            `- ${violation.rule_title} - ${violation.push_up_count} push-ups${violation.occurred_at_label ? ` (${violation.occurred_at_label})` : ''}`,
        ),
        '',
        'Until a mentor closes them, you cannot continue the schedule or start your custom timer.',
    ];

    window.alert(lines.join('\n'));

    return true;
};

const showBlockingCommunicationDialog = () => {
    if (!hasBlockingCommunication.value) {
        return false;
    }

    const lines = ['Read all new mentor communication before continuing the schedule.'];

    if (props.communicationGate.unread_mentor_chat) {
        lines.push(
            `- Unread mentor message${props.communicationGate.unread_mentor_chat.created_at_label ? ` (${props.communicationGate.unread_mentor_chat.created_at_label})` : ''}`,
        );
    }

    if (props.communicationGate.unread_announcement) {
        lines.push(
            `- Unread announcement${props.communicationGate.unread_announcement.created_at_label ? ` (${props.communicationGate.unread_announcement.created_at_label})` : ''}`,
        );
    }

    lines.push('');

    if (hasUnreadMentorChat.value && hasUnreadAnnouncement.value) {
        lines.push('Open Chat and Announcements from the sidebar, then come back.');
    } else if (hasUnreadMentorChat.value) {
        lines.push('Open Chat from the sidebar, then come back.');
    } else {
        lines.push('Open Announcements from the sidebar, then come back.');
    }

    window.alert(lines.join('\n'));

    return true;
};

const showBlockingAssignmentDialog = () => {
    if (!hasBlockingAssignments.value) {
        return false;
    }

    const count = props.assignmentGate.unread_count;
    const latest = props.assignmentGate.latest_unread_assignment;
    const lines = [
        count === 1
            ? 'There is an unread assignment.'
            : `There are ${count} unread assignments.`,
    ];

    if (latest) {
        lines.push(
            latest.created_at_label ? `- ${latest.created_at_label}` : '- Assignment',
        );
    }

    lines.push('', 'Open Assignments from the sidebar, review them, then come back.');

    window.alert(lines.join('\n'));

    return true;
};

const startScheduleRun = (scheduleTemplateId: number) => {
    if (showBlockingViolationDialog()) {
        return;
    }

    if (showBlockingAssignmentDialog()) {
        return;
    }

    if (showBlockingCommunicationDialog()) {
        return;
    }

    router.post(route('student.schedule-runs.store', scheduleTemplateId), {}, { preserveScroll: true });
};

const startNextScheduleTask = () => {
    if (!props.activeScheduleRun?.next_block) {
        return;
    }

    startScheduleBlock(props.activeScheduleRun.next_block.id);
};

const startScheduleBlock = (scheduleRunBlockId: number) => {
    if (!props.activeScheduleRun) {
        return;
    }

    if (showBlockingViolationDialog()) {
        return;
    }

    if (showBlockingAssignmentDialog()) {
        return;
    }

    if (showBlockingCommunicationDialog()) {
        return;
    }

    router.post(
        route('student.schedule-run-blocks.start', {
            scheduleRun: props.activeScheduleRun.id,
            scheduleRunBlock: scheduleRunBlockId,
        }),
        {},
        { preserveScroll: true },
    );
};

const togglePauseOwnTimerForm = () => {
    if (!pauseOwnTimerFormOpen.value && showBlockingViolationDialog()) {
        return;
    }

    if (!pauseOwnTimerFormOpen.value && showBlockingAssignmentDialog()) {
        return;
    }

    if (!pauseOwnTimerFormOpen.value && showBlockingCommunicationDialog()) {
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

    if (showBlockingAssignmentDialog()) {
        return;
    }

    if (showBlockingCommunicationDialog()) {
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

    if (showBlockingAssignmentDialog()) {
        return;
    }

    if (showBlockingCommunicationDialog()) {
        return;
    }

    router.post(route('student.schedule-runs.resume', props.activeScheduleRun.id), {}, { preserveScroll: true });
};

const completeScheduleRun = () => {
    if (!props.activeScheduleRun) {
        return;
    }

    if (!props.scheduleFinishWindow.can_finish_now) {
        window.alert(`Schedules can only be finished manually between ${props.scheduleFinishWindow.opens_at_label} and ${props.scheduleFinishWindow.closes_at_label}.`);
        return;
    }

    if (showBlockingViolationDialog()) {
        return;
    }

    if (showBlockingAssignmentDialog()) {
        return;
    }

    if (showBlockingCommunicationDialog()) {
        return;
    }

    router.post(route('student.schedule-runs.complete', props.activeScheduleRun.id), {}, { preserveScroll: true });
};

const canStartBlock = (block: NonNullable<typeof props.activeScheduleRun>['blocks'][number]): boolean => {
    return (
        props.activeScheduleRun !== null &&
        props.activeScheduleRun.status === 'active' &&
        props.activeTaskSession === null &&
        block.status === 'pending'
    );
};
</script>

<template>
    <Head title="Student portal" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl p-5">
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
                v-if="activeScheduleRun || activeTaskSession || pausedTaskSession || canStartScheduleRun"
                class="rounded-[1.5rem] bg-white p-4 shadow-sm ring-1 ring-stone-200"
            >
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,1.2fr)]">
                    <div
                        class="rounded-[1.25rem] p-4"
                        :class="pausedTaskSession && !activeScheduleRun && !activeTaskSession ? 'bg-stone-950 text-white' : 'bg-stone-100'"
                    >
                        <p
                            class="truncate text-lg font-semibold"
                            :class="pausedTaskSession && !activeScheduleRun && !activeTaskSession ? 'text-white' : 'text-stone-950'"
                        >
                            {{
                                activeTaskSession?.task_title ??
                                (activeScheduleRun ? 'Schedule' : null) ??
                                pausedTaskSession?.task_title ??
                                'Choose a schedule'
                            }}
                        </p>

                        <p
                            v-if="currentSummaryDetail"
                            class="mt-1 text-sm"
                            :class="pausedTaskSession && !activeScheduleRun && !activeTaskSession ? 'text-stone-300' : 'text-stone-600'"
                        >
                            {{ currentSummaryDetail }}
                        </p>

                        <div
                            class="mt-3 flex flex-wrap gap-3 text-xs"
                            :class="pausedTaskSession && !activeScheduleRun && !activeTaskSession ? 'text-stone-400' : 'text-stone-500'"
                        >
                            <span v-if="activeScheduleRun">
                                {{ activeScheduleRun.completed_blocks }} / {{ activeScheduleRun.total_blocks }} blocks
                            </span>
                            <span v-if="activeTaskSession?.planned_duration_minutes">
                                {{ activeTaskSession.planned_duration_minutes }} min planned
                            </span>
                            <span v-if="pausedTaskSession?.planned_duration_minutes">
                                {{ pausedTaskSession.planned_duration_minutes }} min planned
                            </span>
                            <span v-if="activeScheduleRun?.started_at_label">
                                Start {{ activeScheduleRun.started_at_label }}
                            </span>
                            <span v-if="pausedTaskSession?.ended_at_label">
                                Saved {{ pausedTaskSession.ended_at_label }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-[1.25rem] bg-stone-100 p-4">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">
                            Timer
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
                                <span>Elapsed {{ activeTaskElapsedLabel }}</span>
                                <span v-if="activeTaskPlannedLabel">Plan {{ activeTaskPlannedLabel }}</span>
                                <span v-if="activeTaskEndsAtLabel">Until {{ activeTaskEndsAtLabel }}</span>
                            </div>
                        </template>

                        <template v-else-if="pausedTaskSession">
                            <p class="mt-2 text-lg font-semibold text-white">
                                Unfinished
                            </p>
                            <p class="mt-1 text-sm text-stone-300">
                                {{ pausedTaskSession.task_title }}
                            </p>
                            <p class="mt-2 text-sm text-stone-400">
                                {{ formatDuration(pausedTaskSession.duration_seconds ?? 0) }} spent
                            </p>
                        </template>

                        <template v-else-if="activeScheduleRun?.paused_block">
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                Block {{ activeScheduleRun.paused_block.position }}
                            </p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ activeScheduleRun.paused_block.task_title }}
                            </p>
                            <p class="mt-2 text-sm text-stone-500">
                                {{ activeScheduleRun.paused_block.duration_minutes }} min
                            </p>
                        </template>

                        <template v-else-if="activeScheduleRun?.next_block">
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                Block {{ activeScheduleRun.next_block.position }}
                            </p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ activeScheduleRun.next_block.task_title }}
                            </p>
                            <p class="mt-2 text-sm text-stone-500">
                                {{ activeScheduleRun.next_block.duration_minutes }} min
                            </p>
                        </template>

                        <template v-else>
                            <p class="mt-2 text-sm text-stone-600">
                                Choose a schedule and start the first block.
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
                                        ? 'Continue block'
                                        : 'Continue schedule'
                                }}
                            </button>

                            <button
                                v-else-if="activeScheduleRun?.next_block && !activeScheduleTaskIsRunning"
                                type="button"
                                class="inline-flex rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800"
                                @click="startNextScheduleTask"
                            >
                                Next block
                            </button>

                            <button
                                v-if="activeScheduleRun && !activeScheduleTaskIsRunning"
                                type="button"
                                class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                :disabled="!canFinishScheduleNow"
                                :title="!canFinishScheduleNow ? `Available between ${scheduleFinishWindow.opens_at_label} and ${scheduleFinishWindow.closes_at_label}` : undefined"
                                :class="{ 'cursor-not-allowed opacity-50': !canFinishScheduleNow }"
                                @click="completeScheduleRun"
                            >
                                Finish schedule
                            </button>

                            <div
                                v-if="canPauseForOwnTimer || activeTaskSession || pausedTaskSession"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <button
                                    v-if="canPauseForOwnTimer"
                                    type="button"
                                    class="inline-block rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    :disabled="!hasTaskTemplates"
                                    @click="togglePauseOwnTimerForm"
                                >
                                    {{ pauseOwnTimerFormOpen ? 'Hide custom timer' : 'Custom timer' }}
                                </button>
                                <button
                                    v-if="activeTaskSession"
                                    type="button"
                                    :disabled="stopTaskSessionForm.processing"
                                    class="inline-block rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="markTaskSessionUnfinished"
                                >
                                    Unfinished
                                </button>
                                <button
                                    v-if="activeTaskSession"
                                    type="button"
                                    class="inline-block rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800"
                                    @click="stopTaskSession"
                                >
                                    Finish
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="!hasTaskTemplates && canPauseForOwnTimer"
                            class="mt-3 rounded-[1rem] bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200"
                        >
                            {{
                                activeScheduleTaskIsRunning
                                    ? 'There are no tasks allowed while switching away from a running schedule task.'
                                    : 'There are no tasks in the catalog for a custom timer yet.'
                            }}
                        </div>

                        <div
                            v-if="activeScheduleRun && !activeScheduleTaskIsRunning && !scheduleFinishWindow.can_finish_now"
                            class="mt-3 rounded-[1rem] bg-stone-200 px-3 py-2 text-sm text-stone-700"
                        >
                            Finish schedule unlocks at {{ scheduleFinishWindow.opens_at_label }} and closes again at {{ scheduleFinishWindow.closes_at_label }}.
                        </div>

                        <form
                            v-if="pauseOwnTimerFormOpen"
                            class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1.2fr)_8rem_auto]"
                            @submit.prevent="pauseScheduleForOwnTimer"
                        >
                            <select
                                id="quick_own_timer_task_template_id"
                                v-model="pauseOwnTimerForm.task_template_id"
                                class="block w-full rounded-full border-stone-300 bg-white px-4 py-2 text-sm text-stone-950 shadow-sm focus:border-stone-950 focus:ring-stone-950"
                            >
                                <option value="">
                                    Choose a task
                                </option>
                                <option
                                    v-for="taskTemplate in pauseTaskTemplates"
                                    :key="taskTemplate.id"
                                    :value="String(taskTemplate.id)"
                                >
                                    {{ taskTemplate.title }}
                                </option>
                            </select>

                            <input
                                v-model="pauseOwnTimerForm.duration_minutes"
                                type="number"
                                min="1"
                                max="10000"
                                step="1"
                                inputmode="numeric"
                                class="block w-full rounded-full border-stone-300 bg-white px-4 py-2 text-sm text-stone-950 shadow-sm focus:border-stone-950 focus:ring-stone-950"
                                aria-label="Timer duration in minutes"
                            >

                            <button
                                type="submit"
                                :disabled="pauseOwnTimerForm.processing || !hasTaskTemplates"
                                class="inline-flex shrink-0 justify-center whitespace-nowrap rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Pause
                            </button>

                            <InputError class="md:col-span-3" :message="pauseOwnTimerForm.errors.task_template_id" />
                            <InputError class="md:col-span-3" :message="pauseOwnTimerForm.errors.duration_minutes" />
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
                                    <span class="block truncate font-semibold text-stone-950">Schedule</span>
                                    <span class="block truncate text-[11px] uppercase tracking-[0.14em] text-stone-500">
                                        {{ scheduleTemplate.weekday.label }} · {{ scheduleTemplate.entries.length }} blocks
                                    </span>
                                </span>
                                <span class="ml-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-stone-700">
                                    Start
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasBlockingAssignments"
                    class="mt-3 rounded-[1.25rem] bg-sky-50 px-4 py-3 text-sm text-sky-950 ring-1 ring-sky-200"
                >
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <p class="font-medium">
                            {{
                                assignmentGate.unread_count === 1
                                    ? 'An unread assignment is waiting. Open Assignments before continuing the schedule.'
                                    : `${assignmentGate.unread_count} unread assignments are waiting. Open Assignments before continuing the schedule.`
                            }}
                        </p>
                        <div class="min-w-0 space-y-1 text-sm">
                            <p v-if="assignmentGate.latest_unread_assignment" class="truncate">
                                <span v-if="assignmentGate.latest_unread_assignment.created_at_label">
                                    {{ assignmentGate.latest_unread_assignment.created_at_label }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasBlockingCommunication"
                    class="mt-3 rounded-[1.25rem] bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-200"
                >
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <p class="font-medium">
                            {{
                                hasUnreadMentorChat && hasUnreadAnnouncement
                                    ? 'New mentor chat and announcements are waiting. Open both before continuing the schedule.'
                                    : hasUnreadMentorChat
                                      ? 'A new mentor chat message is waiting. Open Chat before continuing the schedule.'
                                      : 'A new announcement is waiting. Open Announcements before continuing the schedule.'
                            }}
                        </p>
                        <div class="min-w-0 space-y-1 text-sm">
                            <p v-if="communicationGate.unread_mentor_chat" class="truncate">
                                Unread mentor message<span v-if="communicationGate.unread_mentor_chat.created_at_label">, {{ communicationGate.unread_mentor_chat.created_at_label }}</span>
                            </p>
                            <p v-if="communicationGate.unread_announcement" class="truncate">
                                Unread announcement<span v-if="communicationGate.unread_announcement.created_at_label">, {{ communicationGate.unread_announcement.created_at_label }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasBlockingViolations"
                    class="mt-3 rounded-[1.25rem] bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-200"
                >
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <p class="font-medium">
                            Until a mentor closes these violations, you cannot continue the schedule or start your custom timer.
                        </p>
                        <div class="min-w-0 space-y-1 text-sm">
                            <p
                                v-for="violation in openViolations"
                                :key="violation.id"
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="truncate">
                                    {{ violation.rule_title }} - {{ violation.push_up_count }} push-ups<span v-if="violation.occurred_at_label">, {{ violation.occurred_at_label }}</span>
                                </span>
                                <button
                                    v-if="violation.start_push_up_url"
                                    type="button"
                                    class="shrink-0 rounded-full border border-amber-300 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-amber-700 transition hover:border-amber-500 hover:text-amber-900"
                                    @click="queueViolationPushUps(violation.start_push_up_url)"
                                >
                                    Do pushups
                                </button>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-full border border-rose-300 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-rose-700 transition hover:border-rose-500 hover:text-rose-900 disabled:opacity-50"
                                    @click="openAiChatForViolation(violation.id, violation.rule_title)"
                                >
                                    Ask AI
                                </button>
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="activeScheduleRun"
                    class="mt-3 rounded-[1.25rem] bg-stone-100 p-4"
                >
                    <div class="flex flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.18em] text-stone-500">
                        <span>Schedule</span>
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
                                    (block.status_label ?? block.status) === 'completed'
                                        ? 'bg-emerald-200 text-emerald-950'
                                        : (block.status_label ?? block.status) === 'skipped'
                                          ? 'bg-sky-100 text-sky-900'
                                          : (block.status_label ?? block.status) === 'unfinished'
                                          ? 'bg-stone-950 text-white'
                                          : (block.status_label ?? block.status) === 'paused'
                                          ? 'bg-stone-950 text-white'
                                          : (block.status_label ?? block.status) === 'in_progress'
                                            ? 'bg-amber-200 text-stone-950'
                                            : block.is_next
                                              ? 'bg-stone-950 text-white'
                                              : 'bg-stone-200 text-stone-700'
                                "
                            >
                                {{
                                    (block.status_label ?? block.status) === 'completed'
                                        ? 'done'
                                        : (block.status_label ?? block.status) === 'skipped'
                                          ? 'skipped'
                                          : (block.status_label ?? block.status) === 'unfinished'
                                          ? 'unfinished'
                                          : (block.status_label ?? block.status) === 'paused'
                                          ? 'paused'
                                          : (block.status_label ?? block.status) === 'in_progress'
                                            ? 'running'
                                            : block.is_next
                                              ? 'next'
                                              : 'waiting'
                                }}
                            </span>
                            <p class="min-w-0 flex-1 truncate font-semibold text-stone-950">
                                {{ block.task.title }}
                            </p>
                            <button
                                v-if="block.unfinished_url"
                                type="button"
                                class="text-xs font-semibold text-stone-700 underline-offset-2 transition hover:text-stone-950 hover:underline"
                                @click="stopTaskSessionForm.patch(block.unfinished_url, { preserveScroll: true })"
                            >
                                {{ block.actual_duration_label }}
                            </button>
                            <p v-else class="text-xs text-stone-600">
                                {{ block.duration_minutes }} min
                            </p>
                            <button
                                v-if="block.resume_url"
                                type="button"
                                class="inline-flex shrink-0 rounded-full bg-stone-950 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-stone-800"
                                @click="resumeScheduleBlockTask(block.resume_url)"
                            >
                                Start
                            </button>
                            <button
                                v-else-if="canStartBlock(block)"
                                type="button"
                                class="inline-flex shrink-0 rounded-full bg-stone-950 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-stone-800"
                                @click="startScheduleBlock(block.id)"
                            >
                                Start
                            </button>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
