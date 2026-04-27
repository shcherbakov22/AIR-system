<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
    unfinished_url?: string | null;
    started_at?: string | null;
    started_at_label?: string | null;
    completed_at?: string | null;
    completed_at_label?: string | null;
};

type DashboardStudent = {
    id: number;
    display_name: string;
    status: string;
    extension_url: string;
    current_push_up_count: number;
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
        unfinished_url?: string | null;
    } | null;
    idle_for?: {
        started_at?: string | null;
        started_at_label?: string | null;
        seconds: number;
        label: string;
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
    latest_device_activity?: {
        device_label: string;
        focused_app?: {
            app_name?: string | null;
            window_title?: string | null;
            browser_domain?: string | null;
            observed_at?: string | null;
        } | null;
        open_apps: Array<{
            app_name?: string | null;
            window_title?: string | null;
        }>;
        installed_apps: Array<{
            display_name: string;
            display_version?: string | null;
            publisher?: string | null;
            install_location?: string | null;
        }>;
    } | null;
    app_control?: {
        pending_review: Array<{
            id: number;
            app_key: string;
            app_name: string;
            status: string;
            grace_deadline_at?: string | null;
        }>;
        permitted: Array<{
            id: number;
            app_key: string;
            app_name: string;
            status: string;
        }>;
        blocked: Array<{
            id: number;
            app_key: string;
            app_name: string;
            status: string;
        }>;
        permit_url_template: string;
        block_url_template: string;
    } | null;
    communication_gate?: {
        has_unread: boolean;
        has_unread_student_chat: boolean;
        unread_student_chat?: {
            id: number;
            body?: string | null;
            created_at_label?: string | null;
            sender_name?: string | null;
            has_attachment: boolean;
        } | null;
        unread_student_chats: Array<{
            id: number;
            body?: string | null;
            created_at_label?: string | null;
            sender_name?: string | null;
            has_attachment: boolean;
            read_url: string;
        }>;
        admin_blocking_message?: string | null;
        chat_url: string;
        read_url: string;
    } | null;
    open_violations: Array<{
        id: number;
        rule_title: string;
        push_up_count: number;
        occurred_at_label?: string | null;
        start_push_up_url?: string | null;
    }>;
    violation_rule_options: Array<{
        id: number;
        title: string;
    }>;
};

const props = defineProps<{
    serverNow: string;
    serverSpeech: {
        enabled: boolean;
        pending_count: number;
    };
    pushUpStation?: {
        name: string;
        is_active: boolean;
        last_seen_at?: string | null;
        last_seen_at_label?: string | null;
        pending_count: number;
        session?: {
            id: number;
            status: string;
            student_name: string;
            required_push_ups: number;
        } | null;
    } | null;
    monitorStudents: DashboardStudent[];
}>();

type DashboardCapture = NonNullable<DashboardStudent['latest_screen_capture']>;

const selectedCapture = ref<(DashboardCapture & { studentName: string }) | null>(null);
const selectedCaptureHistory = ref<Array<DashboardCapture & { studentName: string }>>([]);
const selectedCaptureHistoryIndex = ref(0);
const captureHistoryLoading = ref(false);
const capturePreloadCache = new Map<string, Promise<void>>();
const captureReadyUrls = ref<Record<string, true>>({});
const captureHistoryCache = new Map<number, DashboardCapture[]>();
const captureHistoryRequestCache = new Map<number, Promise<DashboardCapture[]>>();
const selectedAppsStudent = ref<DashboardStudent | null>(null);
const appsPanelRefreshing = ref(false);
const selectedViolationRuleIds = ref<Record<number, string>>({});
const violationApplyPendingByStudentId = ref<Record<number, boolean>>({});
const assignmentComposerOpenByStudentId = ref<Record<number, boolean>>({});
const assignmentBodyByStudentId = ref<Record<number, string>>({});
const assignmentCreatePendingByStudentId = ref<Record<number, boolean>>({});
const pushUpStationMenuOpen = ref(false);
const violationSelectByStudentId = new Map<number, HTMLSelectElement>();
const browserSpeechStorageKey = 'air-dashboard-browser-speech-enabled';
const browserSpeechWatermarkStorageKey = 'air-dashboard-browser-speech-watermark';
const browserSpeechEnabled = ref(localStorage.getItem(browserSpeechStorageKey) !== '0');
const browserSpeechWatermark = ref(Number(localStorage.getItem(browserSpeechWatermarkStorageKey) ?? '0') || 0);
const serverSpeechPendingCount = ref(props.serverSpeech.pending_count);
const monitorGridRef = ref<HTMLElement | null>(null);
let captureObserver: IntersectionObserver | null = null;
const speechLogsOpen = ref(false);
const speechLogsLoading = ref(false);
const speechLogs = ref<Array<{
    id: number;
    kind: string;
    message: string;
    spoken_at?: string | null;
    spoken_at_label?: string | null;
    student_name?: string | null;
}>>([]);
const speechPlaybackActive = ref(false);
const activeSpeechAnnouncementId = ref<number | null>(null);

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

watch(
    () => props.serverSpeech,
    (serverSpeech) => {
        serverSpeechPendingCount.value = serverSpeech.pending_count;
    },
    { immediate: true, deep: true },
);

watch(browserSpeechEnabled, (enabled) => {
    localStorage.setItem(browserSpeechStorageKey, enabled ? '1' : '0');

    if (!enabled && 'speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        speechPlaybackActive.value = false;
        activeSpeechAnnouncementId.value = null;
        return;
    }

    if ('speechSynthesis' in window) {
        refreshSpeechVoice();
    }
});

watch(browserSpeechWatermark, (value) => {
    localStorage.setItem(browserSpeechWatermarkStorageKey, String(value));
});

let clockInterval: number | null = null;
let reloadInterval: number | null = null;
let speechPollInterval: number | null = null;
let isReloading = false;
const scheduleBoardRefs = new Map<number, HTMLElement>();
let englishSpeechVoice: SpeechSynthesisVoice | null = null;

const reloadMonitorBoard = () => {
    if (isReloading || document.hidden) {
        return;
    }

    isReloading = true;

    router.reload({
        only: ['serverNow', 'monitorStudents', 'serverSpeech', 'pushUpStation'],
        onFinish: () => {
            isReloading = false;
        },
    });
};

const handleVisibilityChange = () => {
    if (!document.hidden) {
        reloadMonitorBoard();
    }
};

const setScheduleBoardRef = (studentId: number, element: unknown) => {
    if (element instanceof HTMLElement) {
        scheduleBoardRefs.set(studentId, element);
        return;
    }

    scheduleBoardRefs.delete(studentId);
};

const loadSpeechLogs = async () => {
    speechLogsLoading.value = true;

    try {
        const response = await window.fetch(route('admin.speech-announcements.history'), {
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
            announcements: Array<{
                id: number;
                kind: string;
                message: string;
                spoken_at?: string | null;
                spoken_at_label?: string | null;
                student_name?: string | null;
            }>;
        };

        speechLogs.value = payload.announcements ?? [];
    } finally {
        speechLogsLoading.value = false;
    }
};

const toggleBrowserSpeech = () => {
    browserSpeechEnabled.value = !browserSpeechEnabled.value;
};

const resolveEnglishSpeechVoice = (): SpeechSynthesisVoice | null => {
    if (!('speechSynthesis' in window)) {
        return null;
    }

    const voices = window.speechSynthesis.getVoices();
    if (voices.length === 0) {
        return null;
    }

    return voices.find((voice) => voice.lang.toLowerCase() === 'en-us')
        ?? voices.find((voice) => voice.lang.toLowerCase().startsWith('en-'))
        ?? voices.find((voice) => voice.default && voice.lang.toLowerCase().startsWith('en'))
        ?? voices.find((voice) => voice.lang.toLowerCase().includes('en'))
        ?? null;
};

const refreshSpeechVoice = () => {
    englishSpeechVoice = resolveEnglishSpeechVoice();
};

const fetchNextSpeechAnnouncement = async () => {
    if (!browserSpeechEnabled.value || speechPlaybackActive.value || !('speechSynthesis' in window)) {
        return;
    }

    const response = await window.fetch(`${route('admin.speech-announcements.next')}?after_id=${encodeURIComponent(String(browserSpeechWatermark.value))}`, {
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
        announcement: {
            id: number;
            message: string;
        } | null;
    };

    if (!payload.announcement) {
        return;
    }

    speechPlaybackActive.value = true;
    activeSpeechAnnouncementId.value = payload.announcement.id;
    browserSpeechWatermark.value = Math.max(browserSpeechWatermark.value, payload.announcement.id);

    const utterance = new SpeechSynthesisUtterance(payload.announcement.message);
    utterance.lang = 'en-US';
    utterance.rate = 1;
    utterance.pitch = 1;
    utterance.voice = englishSpeechVoice;

    const finish = async (markSpoken: boolean) => {
        if (markSpoken && activeSpeechAnnouncementId.value !== null) {
            const spokenResponse = await window.fetch(route('admin.speech-announcements.mark-spoken', activeSpeechAnnouncementId.value), {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name=\"csrf-token\"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (spokenResponse.ok) {
                const spokenPayload = await spokenResponse.json() as { pending_count: number };
                serverSpeechPendingCount.value = spokenPayload.pending_count;

                if (speechLogsOpen.value) {
                    await loadSpeechLogs();
                }
            }
        }

        speechPlaybackActive.value = false;
        activeSpeechAnnouncementId.value = null;
    };

    utterance.onend = () => {
        finish(true).catch(() => {
            speechPlaybackActive.value = false;
            activeSpeechAnnouncementId.value = null;
        });
    };

    utterance.onerror = () => {
        finish(false).catch(() => {
            speechPlaybackActive.value = false;
            activeSpeechAnnouncementId.value = null;
        });
    };

    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(utterance);
};

const openSpeechLogs = async () => {
    speechLogsOpen.value = true;
    await loadSpeechLogs();
};

const closeSpeechLogs = () => {
    speechLogsOpen.value = false;
};

onMounted(() => {
    syncLiveNow();
    clockInterval = window.setInterval(syncLiveNow, 1000);
    reloadInterval = window.setInterval(reloadMonitorBoard, 5000);
    speechPollInterval = window.setInterval(() => {
        fetchNextSpeechAnnouncement().catch(() => {});
    }, 3000);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', reloadMonitorBoard);

    if ('speechSynthesis' in window) {
        refreshSpeechVoice();
        window.speechSynthesis.onvoiceschanged = () => {
            refreshSpeechVoice();
        };
    }

    // Preload visible student captures on mount and scroll
    nextTick(() => {
        setupCaptureObserver();
        preloadVisibleCaptures();
    });
});

onBeforeUnmount(() => {
    if (clockInterval !== null) {
        window.clearInterval(clockInterval);
    }

    if (reloadInterval !== null) {
        window.clearInterval(reloadInterval);
    }

    if (speechPollInterval !== null) {
        window.clearInterval(speechPollInterval);
    }

    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = null;
        window.speechSynthesis.cancel();
    }

    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('focus', reloadMonitorBoard);
    captureObserver?.disconnect();
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
        const idleFor = !student.idle_for
            ? null
            : {
                ...student.idle_for,
                label: formatDuration(student.idle_for.seconds + liveDeltaSeconds),
            };
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
            idle_for: idleFor,
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

const pushUpStationIndicatorClass = computed(() => {
    if (!props.pushUpStation) {
        return 'bg-stone-200 text-stone-700';
    }

    return props.pushUpStation.is_active
        ? 'bg-emerald-50 text-emerald-900'
        : 'bg-amber-50 text-amber-900';
});

const togglePushUpStationMenu = () => {
    if (!props.pushUpStation) {
        return;
    }

    pushUpStationMenuOpen.value = !pushUpStationMenuOpen.value;
};

const preloadCaptureImage = (imageUrl?: string | null): Promise<void> => {
    if (!imageUrl) {
        return Promise.resolve();
    }

    if (captureReadyUrls.value[imageUrl]) {
        return Promise.resolve();
    }

    const existing = capturePreloadCache.get(imageUrl);
    if (existing) {
        return existing;
    }

    const preloadPromise = new Promise<void>((resolve) => {
        const image = new Image();
        let settled = false;
        const markReady = () => {
            if (settled) {
                return;
            }

            settled = true;
            captureReadyUrls.value = {
                ...captureReadyUrls.value,
                [imageUrl]: true,
            };
            resolve();
        };

        image.decoding = 'sync';
        image.loading = 'eager';
        image.onload = () => {
            void image.decode()
                .catch(() => undefined)
                .finally(markReady);
        };
        image.onerror = () => {
            if (settled) {
                return;
            }

            settled = true;
            resolve();
        };
        image.src = imageUrl;

        if (image.complete) {
            void image.decode()
                .catch(() => undefined)
                .finally(markReady);
        }
    });

    capturePreloadCache.set(imageUrl, preloadPromise);

    return preloadPromise;
};

const preloadCaptureNeighbors = (index: number) => {
    [
        selectedCaptureHistory.value[index - 2],
        selectedCaptureHistory.value[index - 1],
        selectedCaptureHistory.value[index + 1],
        selectedCaptureHistory.value[index + 2],
    ].forEach((capture) => {
        if (capture?.image_url) {
            void preloadCaptureImage(capture.image_url);
        }
    });
};

const preloadCaptureHistory = (captures: Array<DashboardCapture & { studentName: string }>) => {
    captures.forEach((capture) => {
        if (capture.image_url) {
            void preloadCaptureImage(capture.image_url);
        }
    });
};

const fetchCaptureHistory = async (captureId: number): Promise<DashboardCapture[]> => {
    if (!captureId) {
        return [];
    }

    const cached = captureHistoryCache.get(captureId);
    if (cached) {
        return cached;
    }

    const existingRequest = captureHistoryRequestCache.get(captureId);
    if (existingRequest) {
        return existingRequest;
    }

    const request = window.fetch(route('admin.student-monitor-captures.day-history', captureId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then(async (response) => {
            if (!response.ok) {
                return [];
            }

            const payload = await response.json() as {
                captures: DashboardCapture[];
            };

            const captures = payload.captures ?? [];
            captureHistoryCache.set(captureId, captures);

            return captures;
        })
        .finally(() => {
            captureHistoryRequestCache.delete(captureId);
        });

    captureHistoryRequestCache.set(captureId, request);

    return request;
};

const warmCaptureHistory = (captureId?: number | null) => {
    if (!captureId || captureHistoryCache.has(captureId) || captureHistoryRequestCache.has(captureId)) {
        return;
    }

    void fetchCaptureHistory(captureId);
};

const setupCaptureObserver = () => {
    if (!monitorGridRef.value) return;

    captureObserver?.disconnect();

    captureObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                const studentCard = entry.target as HTMLElement;
                const studentId = studentCard.dataset.studentId;
                const student = monitorStudents.value.find((s) => s.id === Number(studentId));
                if (!student) return;

                if (entry.isIntersecting) {
                    if (student.latest_screen_capture?.image_url) {
                        void preloadCaptureImage(student.latest_screen_capture.image_url);
                    }
                    warmCaptureHistory(student.latest_screen_capture?.id);
                    if (student.latest_camera_capture?.image_url) {
                        void preloadCaptureImage(student.latest_camera_capture.image_url);
                    }
                    warmCaptureHistory(student.latest_camera_capture?.id);
                }
            });
        },
        {
            root: monitorGridRef.value,
            rootMargin: '200px 0px',
            threshold: 0,
        },
    );

    // Observe all existing student cards
    observeStudentCards();
};

const observeStudentCards = () => {
    if (!monitorGridRef.value || !captureObserver) return;
    const cards = monitorGridRef.value.querySelectorAll('[data-student-id]');
    cards.forEach((card) => captureObserver!.observe(card));
};

const preloadVisibleCaptures = () => {
    if (!monitorGridRef.value) return;
    const cards = monitorGridRef.value.querySelectorAll('[data-student-id]');
    cards.forEach((card) => {
        const studentId = (card as HTMLElement).dataset.studentId;
        const student = monitorStudents.value.find((s) => s.id === Number(studentId));
        if (!student) return;
        if (student.latest_screen_capture?.image_url) {
            void preloadCaptureImage(student.latest_screen_capture.image_url);
        }
        warmCaptureHistory(student.latest_screen_capture?.id);
        if (student.latest_camera_capture?.image_url) {
            void preloadCaptureImage(student.latest_camera_capture.image_url);
        }
        warmCaptureHistory(student.latest_camera_capture?.id);
    });
};

// Re-observe when monitorStudents changes
watch(
    () => props.monitorStudents,
    () => {
        syncSelectedAppsStudent();
        nextTick(() => observeStudentCards());
    },
);

const deleteViolation = (violationId: number) => {
    router.delete(route('admin.violations.destroy', violationId), {
        data: {
            return_to_dashboard: true,
        },
        preserveScroll: true,
        preserveState: true,
    });
};

const applyViolation = (studentId: number) => {
    if (violationApplyPendingByStudentId.value[studentId]) {
        return;
    }

    const selectedRuleDefinitionId = selectedViolationRuleIds.value[studentId];
    if (!selectedRuleDefinitionId) {
        return;
    }

    violationApplyPendingByStudentId.value[studentId] = true;

    router.post(route('admin.violations.store'), {
        student_id: studentId,
        rule_definition_id: Number(selectedRuleDefinitionId),
        occurred_at: new Date(liveNowMs.value).toISOString(),
        return_to_dashboard: true,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            selectedViolationRuleIds.value[studentId] = '';
        },
        onFinish: () => {
            violationApplyPendingByStudentId.value[studentId] = false;
        },
    });
};

const setViolationSelectRef = (studentId: number, element: unknown) => {
    if (element instanceof HTMLSelectElement) {
        violationSelectByStudentId.set(studentId, element);
        return;
    }

    violationSelectByStudentId.delete(studentId);
};

const openViolationDropdown = (studentId: number) => {
    const select = violationSelectByStudentId.get(studentId);
    if (!select) {
        return;
    }

    const pickerCapableSelect = select as HTMLSelectElement & { showPicker?: () => void };
    if (typeof pickerCapableSelect.showPicker === 'function') {
        pickerCapableSelect.showPicker();
        return;
    }

    select.focus();
    select.click();
};

const toggleAssignmentComposer = (studentId: number) => {
    assignmentComposerOpenByStudentId.value = {
        ...assignmentComposerOpenByStudentId.value,
        [studentId]: !assignmentComposerOpenByStudentId.value[studentId],
    };
};

const createAssignment = (studentId: number) => {
    if (assignmentCreatePendingByStudentId.value[studentId]) {
        return;
    }

    const body = (assignmentBodyByStudentId.value[studentId] ?? '').trim();
    if (!body) {
        return;
    }

    assignmentCreatePendingByStudentId.value[studentId] = true;

    router.post(route('admin.assignments.store'), {
        student_id: studentId,
        body,
        return_to_dashboard: true,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            assignmentBodyByStudentId.value[studentId] = '';
            assignmentComposerOpenByStudentId.value[studentId] = false;
        },
        onFinish: () => {
            assignmentCreatePendingByStudentId.value[studentId] = false;
        },
    });
};

const queueStudentPushUps = (url?: string | null) => {
    if (!url) {
        return;
    }

    router.post(url, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const openCapture = (studentName: string, capture: DashboardCapture) => {
    selectedCapture.value = {
        ...capture,
        studentName,
    };
    void preloadCaptureImage(capture.image_url);

    const cachedHistory = captureHistoryCache.get(capture.id);

    if (cachedHistory && cachedHistory.length > 0) {
        selectedCaptureHistory.value = cachedHistory.map((historyCapture) => ({
            ...historyCapture,
            studentName,
        }));
        const currentIndex = selectedCaptureHistory.value.findIndex((historyCapture) => historyCapture.id === capture.id);
        selectedCaptureHistoryIndex.value = currentIndex >= 0 ? currentIndex : 0;
        preloadCaptureHistory(selectedCaptureHistory.value);
        preloadCaptureNeighbors(selectedCaptureHistoryIndex.value);
    } else {
        selectedCaptureHistory.value = [];
        selectedCaptureHistoryIndex.value = 0;
    }

    loadCaptureHistory(studentName, capture.id);
};

const closeCapture = () => {
    selectedCapture.value = null;
    selectedCaptureHistory.value = [];
    selectedCaptureHistoryIndex.value = 0;
    captureHistoryLoading.value = false;
};

const openAppsPanel = (student: DashboardStudent) => {
    selectedAppsStudent.value = student;
};

const closeAppsPanel = () => {
    selectedAppsStudent.value = null;
};

const syncSelectedAppsStudent = () => {
    if (!selectedAppsStudent.value) {
        return;
    }

    selectedAppsStudent.value = monitorStudents.value.find((student) => student.id === selectedAppsStudent.value?.id) ?? null;
};

const refreshAppsPanel = () => {
    if (!selectedAppsStudent.value || appsPanelRefreshing.value) {
        return;
    }

    appsPanelRefreshing.value = true;

    router.reload({
        only: ['serverNow', 'monitorStudents'],
        onSuccess: () => {
            syncSelectedAppsStudent();
        },
        onFinish: () => {
            appsPanelRefreshing.value = false;
        },
    });
};

const hasAnyAdminChatGate = computed(() =>
    monitorStudents.value.some((student) => Boolean(student.communication_gate?.has_unread_student_chat)),
);

const markStudentChatNotificationRead = (student: DashboardStudent, readUrl?: string | null) => {
    if (!readUrl) {
        return;
    }

    router.patch(readUrl, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const markTaskSessionUnfinished = (student: DashboardStudent) => {
    const unfinishedUrl = student.active_task_session?.unfinished_url;

    if (!unfinishedUrl || hasAnyAdminChatGate.value) {
        return;
    }

    router.patch(unfinishedUrl, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const markTaskSessionUnfinishedByUrl = (unfinishedUrl?: string | null) => {
    if (!unfinishedUrl || hasAnyAdminChatGate.value) {
        return;
    }

    router.patch(unfinishedUrl, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const appPolicyUrl = (template: string, policyId: number): string =>
    template.replace('__APP_POLICY__', String(policyId));

const moveStudentAppPolicy = (
    student: DashboardStudent,
    policyId: number,
    targetStatus: 'pending_review' | 'permitted' | 'blocked',
) => {
    if (!student.app_control) {
        return;
    }

    const buckets = student.app_control;
    const sourceKeys: Array<'pending_review' | 'permitted' | 'blocked'> = ['pending_review', 'permitted', 'blocked'];
    let movedPolicy: { id: number; app_key: string; app_name: string; status: string; grace_deadline_at?: string | null } | null = null;

    for (const key of sourceKeys) {
        const index = buckets[key].findIndex((policy) => policy.id === policyId);
        if (index === -1) {
            continue;
        }

        movedPolicy = { ...buckets[key][index], status: targetStatus };
        buckets[key].splice(index, 1);
        break;
    }

    if (!movedPolicy) {
        return;
    }

    if (targetStatus !== 'pending_review') {
        delete movedPolicy.grace_deadline_at;
    }

    const targetBucket = buckets[targetStatus];
    if (targetBucket.some((policy) => policy.id === movedPolicy.id)) {
        return;
    }

    targetBucket.unshift(movedPolicy);
};

const permitStudentApp = (student: DashboardStudent, policyId: number) => {
    if (!student.app_control) {
        return;
    }

    router.patch(appPolicyUrl(student.app_control.permit_url_template, policyId), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            moveStudentAppPolicy(student, policyId, 'permitted');
        },
    });
};

const blockStudentApp = (student: DashboardStudent, policyId: number) => {
    if (!student.app_control) {
        return;
    }

    router.patch(appPolicyUrl(student.app_control.block_url_template, policyId), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            moveStudentAppPolicy(student, policyId, 'blocked');
        },
    });
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
        const captures = await fetchCaptureHistory(captureId);

        if (selectedCapture.value?.id !== captureId) {
            return;
        }

        selectedCaptureHistory.value = captures.map((historyCapture) => ({
            ...historyCapture,
            studentName,
        }));
        preloadCaptureHistory(selectedCaptureHistory.value);

        const currentIndex = selectedCaptureHistory.value.findIndex((historyCapture) => historyCapture.id === captureId);

        if (currentIndex >= 0) {
            selectedCaptureHistoryIndex.value = currentIndex;
            selectedCapture.value = selectedCaptureHistory.value[currentIndex];
            preloadCaptureNeighbors(currentIndex);
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
    preloadCaptureNeighbors(index);
    void preloadCaptureImage(nextCapture.image_url);
};

const jumpToCaptureHistory = (event: Event) => {
    const target = event.target as HTMLInputElement | null;
    const sliderValue = Number(target?.value ?? NaN);

    if (Number.isNaN(sliderValue)) {
        return;
    }

    const nextIndex = (selectedCaptureHistory.value.length - 1) - sliderValue;

    showCaptureHistoryItem(nextIndex);
};

const showPreviousCapture = () => {
    showCaptureHistoryItem(selectedCaptureHistoryIndex.value + 1);
};

const showNextCapture = () => {
    showCaptureHistoryItem(selectedCaptureHistoryIndex.value - 1);
};

const handleCaptureWheel = (event: WheelEvent) => {
    if (selectedCaptureHistory.value.length <= 1 || captureHistoryLoading.value) {
        return;
    }

    event.preventDefault();

    if (event.deltaY > 0) {
        showPreviousCapture();
        return;
    }

    if (event.deltaY < 0) {
        showNextCapture();
    }
};

const isUnfinishedBlock = (block: DashboardBlock): boolean =>
    block.status_label.toLowerCase() === 'unfinished';

const blockRowClass = (block: DashboardBlock): string => {
    if (isUnfinishedBlock(block)) {
        return 'border-stone-900 bg-stone-950';
    }

    if (block.status === 'completed') {
        return 'border-emerald-200 bg-emerald-50';
    }

    if (block.status === 'in_progress') {
        return 'border-amber-300 bg-amber-50';
    }

    if (block.status === 'paused') {
        return 'border-stone-900 bg-stone-950';
    }

    return 'border-stone-200 bg-white';
};

const blockTitleClass = (block: DashboardBlock): string =>
    isUnfinishedBlock(block) || block.status === 'paused'
        ? 'text-white'
        : 'text-stone-900';

const blockDurationClass = (block: DashboardBlock): string => {
    const baseClass = 'bg-stone-950 text-white ring-1 ring-white/20';

    if (block.unfinished_url && !hasAnyAdminChatGate.value) {
        return `${baseClass} transition hover:bg-stone-800`;
    }

    return `${baseClass} opacity-100`;
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

    <AuthenticatedLayout :sidebar-drawer="true" :full-width="true" :disable-sidebar-toggle="hasAnyAdminChatGate">
        <div class="fixed right-2 top-1.5 z-30 flex items-center gap-2">
            <div v-if="props.pushUpStation" class="relative">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="pushUpStationIndicatorClass"
                    @click="togglePushUpStationMenu"
                >
                    <span class="inline-block h-2 w-2 rounded-full" :class="props.pushUpStation.is_active ? 'bg-emerald-500' : 'bg-amber-500'" />
                    <span>
                        {{ props.pushUpStation.is_active ? 'Counter active' : 'Counter stale' }}
                    </span>
                    <span class="text-[10px] opacity-70">
                        {{ props.pushUpStation.pending_count }}
                    </span>
                </button>

                <div
                    v-if="pushUpStationMenuOpen"
                    class="absolute right-0 mt-2 w-72 rounded-2xl border border-stone-200 bg-white p-3 shadow-xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-stone-900">{{ props.pushUpStation.name }}</p>
                            <p class="text-[11px] uppercase tracking-[0.14em]" :class="props.pushUpStation.is_active ? 'text-emerald-700' : 'text-amber-700'">
                                {{ props.pushUpStation.is_active ? 'Active' : 'Stale' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-stone-300 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-700"
                            @click="pushUpStationMenuOpen = false"
                        >
                            Close
                        </button>
                    </div>

                    <div class="mt-3 grid gap-2 text-xs text-stone-600">
                        <div class="flex items-center justify-between gap-2">
                            <span>Pending queue</span>
                            <span class="font-semibold text-stone-900">{{ props.pushUpStation.pending_count }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span>Last seen</span>
                            <span class="font-semibold text-stone-900">{{ props.pushUpStation.last_seen_at_label ?? 'Never' }}</span>
                        </div>
                    </div>

                    <div class="mt-3 rounded-xl border border-stone-200 bg-stone-50 p-3">
                        <template v-if="props.pushUpStation.session">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-500">Current session</p>
                            <p class="mt-1 text-sm font-semibold text-stone-900">{{ props.pushUpStation.session.student_name }}</p>
                            <div class="mt-2 grid gap-1 text-xs text-stone-600">
                                <div class="flex items-center justify-between gap-2">
                                    <span>Status</span>
                                    <span class="font-semibold text-stone-900">{{ props.pushUpStation.session.status }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span>Push-ups</span>
                                    <span class="font-semibold text-stone-900">{{ props.pushUpStation.session.required_push_ups }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span>Session</span>
                                    <span class="font-semibold text-stone-900">#{{ props.pushUpStation.session.id }}</span>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-xs font-medium text-stone-500">No active push-up session.</p>
                    </div>
                </div>
            </div>
            <span
                v-if="hasAnyAdminChatGate"
                class="inline-flex items-center gap-1 rounded-full border border-stone-200 bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-400 opacity-50"
                aria-disabled="true"
            >
                <span>Violations</span>
            </span>
            <Link
                v-else
                :href="route('admin.violations.index')"
                class="inline-flex items-center gap-1 rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-semibold text-stone-700 transition hover:text-stone-950"
            >
                <span>Violations</span>
            </Link>

            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold text-stone-700 transition hover:text-stone-950"
                :class="speechLogsOpen ? 'bg-stone-900 text-white' : 'bg-white/80 text-stone-700'"
                @click="openSpeechLogs"
            >
                <span>Logs</span>
            </button>

            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold transition"
                :class="browserSpeechEnabled ? 'bg-emerald-50 text-emerald-900 hover:bg-emerald-100' : 'bg-stone-200 text-stone-700 hover:bg-stone-300'"
                @click="toggleBrowserSpeech"
            >
                <span class="sr-only">Toggle browser voice</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 8H7L11 5V15L7 12H4V8Z" stroke-linejoin="round" />
                    <path v-if="browserSpeechEnabled" d="M14 7C15.3333 8.16667 16 9.16667 16 10C16 10.8333 15.3333 11.8333 14 13" stroke-linecap="round" />
                </svg>
                <span>{{ browserSpeechEnabled ? 'Voice on' : 'Voice off' }}</span>
                <span class="text-[10px] opacity-70">
                    {{ serverSpeechPendingCount }}
                </span>
            </button>
        </div>

        <div class="h-[calc(100vh-1.75rem)] overflow-x-auto overflow-y-hidden px-2 pt-8 pb-2 sm:px-3 sm:pt-8 sm:pb-3 lg:px-4 lg:pt-8 lg:pb-4">
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
                ref="monitorGridRef"
                class="grid h-full auto-cols-[minmax(8.75rem,1fr)] grid-flow-col gap-1.5 overflow-x-auto"
            >
                <article
                    v-for="student in monitorStudents"
                    :key="student.id"
                    :data-student-id="student.id"
                    class="relative flex h-[calc(100vh-3.5rem)] max-h-[calc(100vh-3.5rem)] min-h-[22rem] w-full min-w-0 flex-col overflow-hidden rounded-[1rem] bg-white p-1.5 shadow-sm ring-1 ring-stone-200"
                >
                    <div>
                        <p class="truncate text-sm font-semibold text-stone-950">
                            {{ student.display_name }}
                        </p>

                        <div class="mt-1 flex min-w-0 flex-wrap items-center gap-0.5">
                            <span
                                v-if="hasAnyAdminChatGate"
                                class="inline-flex rounded-full border border-stone-200 bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-stone-400 opacity-50"
                                aria-disabled="true"
                            >
                                Pr
                            </span>
                            <Link
                                v-else
                                :href="route('admin.students.progress', student.id)"
                                class="rounded-full border border-stone-300 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                            >
                                Pr
                            </Link>
                            <Link
                                :href="student.extension_url"
                                class="rounded-full border border-stone-300 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                            >
                                EX
                            </Link>
                            <Link
                                v-if="student.communication_gate?.has_unread_student_chat"
                                :href="student.communication_gate?.chat_url ?? route('admin.chats.show', student.id)"
                                class="rounded-full border border-rose-300 bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-rose-700 transition hover:border-rose-500 hover:bg-rose-100 hover:text-rose-800"
                            >
                                Ch
                            </Link>
                            <span
                                v-else-if="hasAnyAdminChatGate"
                                class="inline-flex rounded-full border border-stone-200 bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-stone-400 opacity-50"
                                aria-disabled="true"
                            >
                                Ch
                            </span>
                            <Link
                                v-else
                                :href="student.communication_gate?.chat_url ?? route('admin.chats.show', student.id)"
                                class="rounded-full border border-stone-300 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                            >
                                Ch
                            </Link>
                            <button
                                type="button"
                                class="rounded-full border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] transition disabled:cursor-not-allowed disabled:opacity-50"
                                :class="hasAnyAdminChatGate
                                    ? 'border-stone-200 bg-stone-100 text-stone-400'
                                    : 'border-stone-300 text-stone-700 hover:border-stone-900 hover:text-stone-950'"
                                :disabled="hasAnyAdminChatGate"
                                @click="openAppsPanel(student)"
                            >
                                Ap
                            </button>
                            <button
                                type="button"
                                class="rounded-full border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] transition disabled:cursor-not-allowed disabled:opacity-50"
                                :class="hasAnyAdminChatGate
                                    ? 'border-sky-100 bg-sky-50 text-sky-300'
                                    : assignmentComposerOpenByStudentId[student.id]
                                        ? 'border-sky-500 bg-sky-100 text-sky-900'
                                        : 'border-sky-300 bg-sky-50 text-sky-800 hover:border-sky-500 hover:bg-sky-100'"
                                :disabled="hasAnyAdminChatGate"
                                @click="toggleAssignmentComposer(student.id)"
                            >
                                AS
                            </button>
                            <button
                                type="button"
                                class="rounded-full border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] transition disabled:cursor-not-allowed disabled:opacity-50"
                                :class="hasAnyAdminChatGate
                                    ? 'border-orange-100 bg-orange-50 text-orange-300'
                                    : 'border-orange-300 bg-orange-50 text-orange-800 hover:border-orange-500 hover:bg-orange-100'"
                                :disabled="hasAnyAdminChatGate || !!violationApplyPendingByStudentId[student.id]"
                                @click="openViolationDropdown(student.id)"
                            >
                                VL
                            </button>
                        </div>
                    </div>

                    <form
                        v-if="assignmentComposerOpenByStudentId[student.id]"
                        class="mt-1 rounded-[0.75rem] border border-sky-200 bg-sky-50 px-1.5 py-1.5"
                        @submit.prevent="createAssignment(student.id)"
                    >
                        <textarea
                            v-model="assignmentBodyByStudentId[student.id]"
                            rows="2"
                            class="w-full resize-none rounded-[0.55rem] border-sky-200 bg-white px-2 py-1 text-[11px] leading-4 text-stone-900 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                            placeholder="Assignment"
                            :disabled="!!assignmentCreatePendingByStudentId[student.id]"
                        />
                        <div class="mt-1 flex justify-end">
                            <button
                                type="submit"
                                class="rounded-full bg-sky-700 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-white transition hover:bg-sky-600 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!!assignmentCreatePendingByStudentId[student.id] || !(assignmentBodyByStudentId[student.id] ?? '').trim()"
                            >
                                Add
                            </button>
                        </div>
                    </form>

                    <select
                        :ref="(el) => setViolationSelectRef(student.id, el)"
                        v-model="selectedViolationRuleIds[student.id]"
                        class="absolute left-0 top-0 h-0 w-0 opacity-0 pointer-events-none"
                        :disabled="hasAnyAdminChatGate || !!violationApplyPendingByStudentId[student.id]"
                        @change="applyViolation(student.id)"
                    >
                        <option value="">Add violation</option>
                        <option
                            v-for="ruleDefinition in student.violation_rule_options"
                            :key="ruleDefinition.id"
                            :value="String(ruleDefinition.id)"
                        >
                            {{ ruleDefinition.title }}
                        </option>
                    </select>

                    <div
                        v-if="student.communication_gate?.has_unread_student_chat"
                        class="mt-1 rounded-[0.75rem] border border-rose-300 bg-rose-50 px-2 py-2"
                    >
                        <div class="space-y-2">
                            <button
                                v-for="message in student.communication_gate?.unread_student_chats ?? []"
                                :key="message.id"
                                type="button"
                                class="block w-full rounded-[0.65rem] border border-rose-200 bg-white/70 px-2 py-2 text-left transition hover:border-rose-400 hover:bg-rose-100"
                                @click="markStudentChatNotificationRead(student, message.read_url)"
                            >
                                <p class="line-clamp-3 text-sm font-medium leading-5 text-rose-950">
                                    {{ message.body || 'Attachment only message.' }}
                                </p>
                                <p class="mt-1 text-[10px] text-rose-700">
                                    {{ message.sender_name || student.display_name }}
                                    <span v-if="message.created_at_label">
                                        · {{ message.created_at_label }}
                                    </span>
                                </p>
                            </button>
                        </div>
                    </div>

                    <div class="mt-1 flex flex-col gap-1">
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
                                <p class="truncate text-[11px] font-medium text-stone-600">
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
                                <p class="truncate text-[11px] font-medium text-stone-600">
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

                    <div v-if="student.open_violations.length > 0" class="mt-1 rounded-[0.75rem] bg-rose-50 px-1.5 py-1 ring-1 ring-rose-200">
                        <div class="space-y-1">
                            <div
                                v-for="violation in student.open_violations"
                                :key="violation.id"
                                class="flex items-center justify-between gap-2 rounded-[0.75rem] bg-white px-2 py-1.5 ring-1 ring-rose-200"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] font-medium text-rose-950">
                                        {{ violation.rule_title }}
                                    </p>
                                    <p class="text-[10px] text-rose-700">
                                        {{ violation.push_up_count }} push-ups<span v-if="violation.occurred_at_label">, {{ violation.occurred_at_label }}</span>
                                    </p>
                                </div>

                                <button
                                    v-if="violation.start_push_up_url"
                                    type="button"
                                    class="inline-flex rounded-full border border-amber-300 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-amber-700 transition hover:border-amber-500 hover:text-amber-900"
                                    @click="queueStudentPushUps(violation.start_push_up_url)"
                                >
                                    Do pushups
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex rounded-full border border-rose-300 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-rose-700 transition hover:border-rose-500 hover:text-rose-900"
                                    @click="deleteViolation(violation.id)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-1 rounded-[0.75rem] bg-amber-100 px-2 py-2">
                        <div class="flex items-center justify-between gap-2">
                            <p class="min-w-0 truncate text-[16px] font-semibold text-stone-900">
                                {{ student.active_task_session?.task_title ?? 'No active task' }}
                            </p>
                            <button
                                type="button"
                                class="shrink-0 text-[15px] font-semibold leading-tight"
                                :class="student.active_task_session?.unfinished_url && !hasAnyAdminChatGate
                                    ? 'text-stone-700 transition hover:text-stone-950'
                                    : 'cursor-default text-stone-700'"
                                :disabled="!student.active_task_session?.unfinished_url || hasAnyAdminChatGate"
                                @click="markTaskSessionUnfinished(student)"
                            >
                                <template v-if="student.active_task_session">
                                    {{ student.active_task_session.elapsedLabel }}
                                </template>
                                <template v-else>
                                    {{ student.idle_for?.label ?? 'Idle' }}
                                </template>
                            </button>
                        </div>
                    </div>

                    <div v-if="student.schedule_board" class="mt-1 min-h-0 flex flex-1 flex-col overflow-hidden rounded-[0.55rem] bg-stone-50/60 p-px">
                        <div
                            :ref="(element) => setScheduleBoardRef(student.id, element)"
                            class="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-px"
                        >
                            <div class="grid min-h-full grid-cols-1 content-start gap-px">
                                <div
                                    v-for="block in student.schedule_board.blocks"
                                    :key="`${student.id}-${student.schedule_board.source_type}-${block.id}`"
                                    class="rounded-[0.35rem] border px-1 py-[3px]"
                                    :class="blockRowClass(block)"
                                    :title="blockTooltip(block)"
                                    :data-active-block="block.status === 'in_progress' || block.status === 'paused' ? 'true' : 'false'"
                                >
                                    <div class="flex items-center justify-between gap-1.5 text-[9px] leading-none">
                                        <p
                                            class="min-w-0 truncate font-medium"
                                            :class="blockTitleClass(block)"
                                        >
                                            {{ block.position }}. {{ block.task_title }}
                                        </p>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded-full px-1.5 py-0.5 text-[8px] font-semibold"
                                            :class="blockDurationClass(block)"
                                            :disabled="!block.unfinished_url || hasAnyAdminChatGate"
                                            @click="markTaskSessionUnfinishedByUrl(block.unfinished_url)"
                                        >
                                            {{ block.displayDurationLabel }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-1 flex min-h-0 flex-1 items-center justify-center rounded-[0.85rem] border border-dashed border-stone-300 bg-stone-50 px-3 py-5 text-center text-sm text-stone-500"
                    >
                        No schedule available.
                    </div>
                </article>
            </div>

            <div
                v-if="selectedAppsStudent"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/70 p-4"
                @click.self="closeAppsPanel"
            >
                <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-[1.25rem] bg-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-stone-200 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-stone-950">
                                {{ selectedAppsStudent.display_name }}
                            </p>
                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-stone-500">
                                {{ selectedAppsStudent.latest_device_activity?.device_label ?? 'No active companion device' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700 transition hover:border-stone-400 hover:text-stone-950 disabled:cursor-wait disabled:opacity-60"
                                :disabled="appsPanelRefreshing"
                                @click="refreshAppsPanel"
                            >
                                {{ appsPanelRefreshing ? 'Refreshing' : 'Refresh' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700"
                                @click="closeAppsPanel"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 space-y-4 overflow-y-auto px-5 py-5">
                        <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                Focused app
                            </p>
                            <p class="mt-2 text-sm font-semibold text-stone-950">
                                {{ selectedAppsStudent.latest_device_activity?.focused_app?.app_name || 'No focused app reported yet' }}
                            </p>
                            <p v-if="selectedAppsStudent.latest_device_activity?.focused_app?.window_title" class="mt-1 text-sm text-stone-600">
                                {{ selectedAppsStudent.latest_device_activity.focused_app.window_title }}
                            </p>
                            <p v-if="selectedAppsStudent.latest_device_activity?.focused_app?.browser_domain" class="mt-2 text-xs uppercase tracking-[0.16em] text-stone-500">
                                {{ selectedAppsStudent.latest_device_activity.focused_app.browser_domain }}
                            </p>
                        </div>

                        <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                Open apps
                            </p>
                            <div
                                v-if="(selectedAppsStudent.latest_device_activity?.open_apps.length ?? 0) > 0"
                                class="mt-3 space-y-2"
                            >
                                <div
                                    v-for="(app, index) in selectedAppsStudent.latest_device_activity?.open_apps ?? []"
                                    :key="`${selectedAppsStudent.id}-${index}`"
                                    class="rounded-[0.9rem] bg-white px-3 py-2 ring-1 ring-stone-200"
                                >
                                    <p class="text-sm font-medium text-stone-950">
                                        {{ app.app_name || 'Unknown app' }}
                                    </p>
                                    <p v-if="app.window_title" class="mt-1 text-sm text-stone-600">
                                        {{ app.window_title }}
                                    </p>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-sm text-stone-500">
                                No open app snapshot yet.
                            </p>
                        </div>

                        <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                Pending review
                            </p>
                            <div v-if="(selectedAppsStudent.app_control?.pending_review.length ?? 0) > 0" class="mt-3 space-y-2">
                                <div
                                    v-for="app in selectedAppsStudent.app_control?.pending_review ?? []"
                                    :key="`pending-${app.id}`"
                                    class="rounded-[0.9rem] bg-white px-3 py-2 ring-1 ring-stone-200"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-stone-950">
                                                {{ app.app_name }}
                                            </p>
                                            <p class="mt-1 text-xs uppercase tracking-[0.14em] text-amber-700">
                                                Shuts down at {{ app.grace_deadline_at ? new Date(app.grace_deadline_at).toLocaleTimeString() : 'soon' }}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-full border border-emerald-300 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-emerald-700"
                                                @click="permitStudentApp(selectedAppsStudent, app.id)"
                                            >
                                                Permit
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full border border-rose-300 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-rose-700"
                                                @click="blockStudentApp(selectedAppsStudent, app.id)"
                                            >
                                                Block
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-sm text-stone-500">
                                No apps pending review.
                            </p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                    Permitted
                                </p>
                                <div v-if="(selectedAppsStudent.app_control?.permitted.length ?? 0) > 0" class="mt-3 space-y-2">
                                    <div
                                        v-for="app in selectedAppsStudent.app_control?.permitted ?? []"
                                        :key="`permitted-${app.id}`"
                                        class="rounded-[0.9rem] bg-white px-3 py-2 ring-1 ring-stone-200"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-sm font-medium text-stone-950">
                                                {{ app.app_name }}
                                            </p>
                                            <button
                                                type="button"
                                                class="rounded-full border border-rose-300 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-rose-700"
                                                @click="blockStudentApp(selectedAppsStudent, app.id)"
                                            >
                                                Block
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-stone-500">
                                    No permitted apps recorded yet.
                                </p>
                            </div>

                            <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                    Blocked
                                </p>
                                <div v-if="(selectedAppsStudent.app_control?.blocked.length ?? 0) > 0" class="mt-3 space-y-2">
                                    <div
                                        v-for="app in selectedAppsStudent.app_control?.blocked ?? []"
                                        :key="`blocked-${app.id}`"
                                        class="rounded-[0.9rem] bg-white px-3 py-2 ring-1 ring-stone-200"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-sm font-medium text-stone-950">
                                                {{ app.app_name }}
                                            </p>
                                            <button
                                                type="button"
                                                class="rounded-full border border-emerald-300 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-emerald-700"
                                                @click="permitStudentApp(selectedAppsStudent, app.id)"
                                            >
                                                Permit
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-stone-500">
                                    No blocked apps.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                Installed apps
                            </p>
                            <div v-if="(selectedAppsStudent.latest_device_activity?.installed_apps.length ?? 0) > 0" class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1">
                                <div
                                    v-for="(app, index) in selectedAppsStudent.latest_device_activity?.installed_apps ?? []"
                                    :key="`installed-${index}`"
                                    class="rounded-[0.9rem] bg-white px-3 py-2 ring-1 ring-stone-200"
                                >
                                    <p class="text-sm font-medium text-stone-950">
                                        {{ app.display_name }}
                                    </p>
                                    <p v-if="app.publisher || app.display_version" class="mt-1 text-xs text-stone-600">
                                        {{ [app.publisher, app.display_version].filter(Boolean).join(' · ') }}
                                    </p>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-sm text-stone-500">
                                No installed app inventory yet.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="selectedCapture"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/85 p-4"
                @click.self="closeCapture"
                @wheel.prevent="handleCaptureWheel"
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

                    <div
                        v-if="selectedCaptureHistory.length > 1"
                        class="border-b border-stone-200 px-4 py-3"
                    >
                        <div class="flex items-center gap-3">
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">
                                Scrub
                            </span>
                            <input
                                type="range"
                                min="0"
                                :max="selectedCaptureHistory.length - 1"
                                :value="(selectedCaptureHistory.length - 1) - selectedCaptureHistoryIndex"
                                class="h-2 w-full cursor-pointer accent-stone-900"
                                :disabled="captureHistoryLoading"
                                @input="jumpToCaptureHistory"
                            >
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-500">
                                {{ selectedCaptureHistoryIndex + 1 }} / {{ selectedCaptureHistory.length }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div
                            class="flex min-h-[24rem] items-center justify-center bg-stone-950"
                        >
                            <div class="hidden" aria-hidden="true">
                                <img
                                    v-for="capture in selectedCaptureHistory"
                                    :key="`preload-${capture.id}`"
                                    :src="capture.image_url"
                                    alt=""
                                    loading="eager"
                                    decoding="sync"
                                >
                            </div>
                            <img
                                v-if="selectedCapture.image_url"
                                :key="selectedCapture.id"
                                :src="selectedCapture.image_url"
                                :alt="selectedCapture.capture_kind === 'camera' ? 'Camera capture' : 'Screen capture'"
                                loading="eager"
                                decoding="sync"
                                fetchpriority="high"
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

            <div
                v-if="speechLogsOpen"
                class="fixed inset-0 z-50 flex items-start justify-end bg-stone-950/20 p-4"
                @click.self="closeSpeechLogs"
            >
                <div class="mt-8 w-full max-w-md overflow-hidden rounded-[1.25rem] bg-white shadow-2xl ring-1 ring-stone-200">
                    <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-stone-950">
                                Speech logs
                            </p>
                            <p class="text-xs text-stone-500">
                                Everything the monitor has spoken
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-2.5 py-1 text-[11px] font-semibold text-stone-700"
                                :disabled="speechLogsLoading"
                                @click="loadSpeechLogs"
                            >
                                Refresh
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-2.5 py-1 text-[11px] font-semibold text-stone-700"
                                @click="closeSpeechLogs"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto px-3 py-3">
                        <p v-if="speechLogsLoading" class="text-sm text-stone-500">
                            Loading logs...
                        </p>

                        <div v-else-if="speechLogs.length > 0" class="space-y-2">
                            <div
                                v-for="log in speechLogs"
                                :key="log.id"
                                class="rounded-[0.9rem] bg-stone-50 px-3 py-2 ring-1 ring-stone-200"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-500">
                                        {{ log.kind }}
                                    </p>
                                    <p class="shrink-0 text-[10px] text-stone-500">
                                        {{ log.spoken_at_label ?? 'Just now' }}
                                    </p>
                                </div>

                                <p class="mt-1 text-sm font-medium text-stone-950">
                                    {{ log.message }}
                                </p>

                                <p v-if="log.student_name" class="mt-1 text-[11px] text-stone-500">
                                    {{ log.student_name }}
                                </p>
                            </div>
                        </div>

                        <p v-else class="text-sm text-stone-500">
                            No spoken announcements yet.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
