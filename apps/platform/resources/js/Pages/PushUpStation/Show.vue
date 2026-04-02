<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type PushUpSessionPayload = {
    id: number;
    status: string;
    required_push_ups: number;
    current_rep: number;
    current_set: number;
    configuration: {
        sets: number;
        reps: number;
        rest_seconds: number;
        penalty_reps: number;
        drop_threshold: number;
        up_gap: number;
        down_tolerance: number;
    };
    student: {
        id: number;
        display_name: string;
        username: string;
    };
    violation: {
        id: number;
        rule_title: string;
        push_up_count: number;
    };
};

const props = defineProps<{
    defaults: {
        sets: number;
        reps: number;
        rest_seconds: number;
        penalty_reps: number;
        drop_threshold: number;
        up_gap: number;
        down_tolerance: number;
    };
    station_state_url: string;
    claim_next_url: string;
}>();

type SerialPortLike = {
    open(options: { baudRate: number }): Promise<void>;
    readable: ReadableStream<Uint8Array> | null;
    writable: WritableStream<Uint8Array> | null;
};

const browserSerial = navigator as Navigator & {
    serial?: {
        requestPort(): Promise<SerialPortLike>;
        getPorts(): Promise<SerialPortLike[]>;
    };
};

const stationKeyStorageKey = 'air-push-up-station-key';
const stationNameStorageKey = 'air-push-up-station-name';
const launchedSessionStorageKey = 'air-push-up-station-launched-session-id';

const randomKey = () => `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
const stationKey = ref(localStorage.getItem(stationKeyStorageKey) || randomKey());
const stationName = ref(localStorage.getItem(stationNameStorageKey) || 'Counter station');
const storedLaunchedSessionId = Number(localStorage.getItem(launchedSessionStorageKey) ?? '');
const launchedSessionId = ref<number | null>(Number.isFinite(storedLaunchedSessionId) && storedLaunchedSessionId > 0 ? storedLaunchedSessionId : null);
const stationOnline = ref(false);
const serialConnected = ref(false);
const pendingCount = ref(0);
const currentSession = ref<PushUpSessionPayload | null>(null);
const statusText = ref('Idle');
const distanceText = ref('---');
const repCount = ref(0);
const movementText = ref('Standby');
const currentSet = ref(1);
const totalSets = ref(0);
const claimBusy = ref(false);
const launchBusy = ref(false);

let heartbeatTimer: number | null = null;
let port: SerialPortLike | null = null;
let writer: WritableStreamDefaultWriter<Uint8Array> | null = null;
let progressThrottleAt = 0;

const sessionStatusLabel = computed(() => {
    if (!currentSession.value) {
        return 'No claimed session';
    }

    return currentSession.value.status;
});

watch(stationName, (value) => {
    localStorage.setItem(stationNameStorageKey, value);
});

const ensureStationKey = () => {
    localStorage.setItem(stationKeyStorageKey, stationKey.value);
};

const setLaunchedSessionId = (sessionId: number | null) => {
    launchedSessionId.value = sessionId;

    if (sessionId === null) {
        localStorage.removeItem(launchedSessionStorageKey);
        return;
    }

    localStorage.setItem(launchedSessionStorageKey, String(sessionId));
};

const clearLaunchedSessionId = () => {
    setLaunchedSessionId(null);
};

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const postJson = async <T>(url: string, method: 'POST' | 'PATCH', body: Record<string, unknown>): Promise<T> => {
    const response = await window.fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(await response.text());
    }

    return await response.json() as T;
};

const sendSerial = async (message: string) => {
    if (!writer) {
        return;
    }

    await writer.write(new TextEncoder().encode(`${message}\n`));
};

const attachSerialPort = async (candidatePort: SerialPortLike) => {
    port = candidatePort;

    if (!port.readable || !port.writable) {
        try {
            await port.open({ baudRate: 115200 });
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            if (!message.includes('already open')) {
                throw error;
            }
        }
    }

    writer = port.writable?.getWriter() ?? null;
    serialConnected.value = true;
    statusText.value = 'Arduino connected';
    readLoop();
    await heartbeat();
    await ensureSessionLaunched();
};

const syncSessionState = (session: PushUpSessionPayload | null) => {
    currentSession.value = session;
    repCount.value = session?.current_rep ?? 0;
    currentSet.value = session?.current_set ?? 1;
    totalSets.value = session?.configuration.sets ?? 0;

    if (!session) {
        clearLaunchedSessionId();
    }
};

const heartbeat = async () => {
    ensureStationKey();

    const payload = await postJson<{
        accepted: boolean;
        pending_count: number;
        current_session: PushUpSessionPayload | null;
    }>(props.station_state_url, 'POST', {
        station_key: stationKey.value,
        station_name: stationName.value,
    });

    stationOnline.value = true;
    pendingCount.value = payload.pending_count;
    syncSessionState(payload.current_session);

    if (!payload.current_session && !claimBusy.value && payload.pending_count > 0) {
        await claimNext();
        return;
    }

    if (payload.current_session && serialConnected.value) {
        await ensureSessionLaunched();
    }
};

const claimNext = async () => {
    if (claimBusy.value) {
        return;
    }

    claimBusy.value = true;
    let claimedSession: PushUpSessionPayload | null = null;

    try {
        const payload = await postJson<{
            accepted: boolean;
            pending_count: number;
            session: PushUpSessionPayload | null;
        }>(props.claim_next_url, 'POST', {
            station_key: stationKey.value,
            station_name: stationName.value,
        });

        pendingCount.value = payload.pending_count;
        claimedSession = payload.session;
        syncSessionState(claimedSession);
        statusText.value = claimedSession
            ? (serialConnected.value ? 'Session claimed' : 'Session claimed, waiting for Arduino')
            : 'No pending sessions';
    } finally {
        claimBusy.value = false;
    }

    if (claimedSession && serialConnected.value) {
        await ensureSessionLaunched();
    }
};

const sendWorkoutConfig = async (session: PushUpSessionPayload) => {
    if (!serialConnected.value) {
        return;
    }

    const config = session.configuration;
    await sendSerial(`${config.reps},${config.penalty_reps},${config.drop_threshold},${config.up_gap},${config.down_tolerance},${config.rest_seconds},${config.sets}`);
};

const ensureSessionLaunched = async () => {
    if (!currentSession.value || !serialConnected.value || launchBusy.value) {
        return;
    }

    if (currentSession.value.status === 'running' && launchedSessionId.value === currentSession.value.id) {
        return;
    }

    launchBusy.value = true;

    try {
        const session = currentSession.value;
        statusText.value = `Starting ${session.student.display_name}`;

        const payload = await postJson<{ accepted: boolean; session: PushUpSessionPayload }>(
            route('push-up-station.sessions.start', session.id),
            'PATCH',
            {
                station_key: stationKey.value,
            },
        );

        syncSessionState(payload.session);
        await sendWorkoutConfig(payload.session);
        setLaunchedSessionId(payload.session.id);
        statusText.value = `Working ${payload.session.student.display_name}`;
    } finally {
        launchBusy.value = false;
    }
};

const pushProgress = async (currentRep: number) => {
    if (!currentSession.value) {
        return;
    }

    const now = Date.now();
    if (now - progressThrottleAt < 700) {
        return;
    }

    progressThrottleAt = now;

    const payload = await postJson<{ session: PushUpSessionPayload }>(
        route('push-up-station.sessions.progress', currentSession.value.id),
        'PATCH',
        {
            station_key: stationKey.value,
            current_rep: ((Math.max(currentSet.value, 1) - 1) * currentSession.value.configuration.reps) + currentRep,
            current_set: currentSet.value,
        },
    );

    currentSession.value = payload.session;
};

const completeCurrentSession = async () => {
    if (!currentSession.value) {
        return;
    }

    const payload = await postJson<{ session: PushUpSessionPayload }>(
        route('push-up-station.sessions.complete', currentSession.value.id),
        'PATCH',
        {
            station_key: stationKey.value,
        },
    );

    statusText.value = `Completed ${payload.session.student.display_name}`;
    clearLaunchedSessionId();
    currentSession.value = null;
    repCount.value = 0;
    movementText.value = 'Standby';
    currentSet.value = 1;
    totalSets.value = 0;
    await heartbeat();
};

const setWorkingStatus = () => {
    if (!currentSession.value) {
        return;
    }

    statusText.value = `Working ${currentSession.value.student.display_name}`;
};

const readLoop = async () => {
    if (!port?.readable) {
        return;
    }

    const decoder = new TextDecoder();
    const reader = port.readable.getReader();

    try {
        while (true) {
            const { value, done } = await reader.read();
            if (done) {
                break;
            }

            const chunk = decoder.decode(value);
            if (!chunk) {
                continue;
            }

            const normalizedChunk = chunk.replace(/\r/g, '\n');

            if (normalizedChunk.includes('STATE:ALL_COMPLETE')) {
                await completeCurrentSession();
            }

            if (normalizedChunk.includes('STATE:SEARCHING_BACK')) {
                statusText.value = 'Searching back position';
            }

            if (normalizedChunk.includes('STATE:BACK_DETECTED')) {
                statusText.value = 'Back detected, start moving';
            }

            if (normalizedChunk.includes('STATE:SET_START')) {
                statusText.value = `Set ${Math.max(currentSet.value, 1)}/${Math.max(totalSets.value, 1)} started`;
            }

            if (normalizedChunk.includes('STATE:RESTING')) {
                statusText.value = `Resting before set ${Math.min(currentSet.value + 1, Math.max(totalSets.value, currentSet.value + 1))}`;
            }

            if (normalizedChunk.includes('STATE:TOTAL_RESET_OK')) {
                statusText.value = 'Device reset';
            }

            for (const rawPart of normalizedChunk.split(/[;\n]+/)) {
                const part = rawPart.trim();
                if (!part) {
                    continue;
                }

                if (part.startsWith('D:')) {
                    distanceText.value = part.split(':')[1] ?? '---';
                }

                if (part.startsWith('W:')) {
                    const nextRep = Number(part.split(':')[1] ?? '0');
                    repCount.value = Number.isNaN(nextRep) ? repCount.value : nextRep;
                    if (repCount.value > 0) {
                        setWorkingStatus();
                    }
                    await pushProgress(repCount.value);
                }

                if (part.startsWith('S:')) {
                    movementText.value = part.split(':')[1] === '1' ? 'Up' : 'Down';
                }

                if (part.startsWith('SET:')) {
                    const nextSet = Number(part.split(':')[1] ?? '1');
                    currentSet.value = Number.isNaN(nextSet) ? currentSet.value : nextSet;
                    if (currentSet.value > 0) {
                        setWorkingStatus();
                    }
                }

                if (part.startsWith('ST:')) {
                    const state = part.split(':')[1] ?? 'IDLE';
                    movementText.value = state === 'REST' ? 'Rest' : state === 'WORK' ? 'Working' : 'Standby';

                    if (state === 'WORK') {
                        setWorkingStatus();
                    }

                    if (state === 'IDLE' && currentSession.value) {
                        statusText.value = `Starting ${currentSession.value.student.display_name}`;
                    }
                }
            }

            if (currentSession.value && serialConnected.value) {
                await ensureSessionLaunched();
            }
        }
    } finally {
        reader.releaseLock();
    }
};

const connectSerial = async () => {
    if (!browserSerial.serial) {
        statusText.value = 'Web Serial is not available in this browser.';
        return;
    }

    await attachSerialPort(await browserSerial.serial.requestPort());
};

const autoConnectSerial = async () => {
    if (!browserSerial.serial?.getPorts) {
        return;
    }

    const savedPorts = await browserSerial.serial.getPorts();
    const savedPort = savedPorts[0];

    if (!savedPort) {
        return;
    }

    try {
        await attachSerialPort(savedPort);
    } catch {
        statusText.value = 'Saved Arduino permission is available, but the port could not reopen automatically.';
    }
};

onMounted(async () => {
    ensureStationKey();
    await heartbeat();
    await autoConnectSerial();
    heartbeatTimer = window.setInterval(() => {
        heartbeat().catch(() => {
            stationOnline.value = false;
        });
    }, 3000);
});

onBeforeUnmount(() => {
    if (heartbeatTimer !== null) {
        window.clearInterval(heartbeatTimer);
    }
});
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-4 sm:px-6 sm:py-6">
        <div class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-stone-950">
                        Push-up counter station
                    </h1>
                    <p class="mt-1 text-sm text-stone-600">
                        Keep this page open on the machine connected to the Arduino counter.
                    </p>
                </div>

                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    <label class="space-y-1">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-stone-500">Station name</span>
                        <input
                            v-model="stationName"
                            type="text"
                            class="w-full rounded-[0.9rem] border border-stone-300 px-3 py-2 text-sm text-stone-900"
                        >
                    </label>
                    <div class="space-y-1">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-stone-500">Arduino</span>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-[0.9rem] border border-stone-900 px-3 py-2 text-sm font-semibold text-stone-950"
                            @click="connectSerial"
                        >
                            {{ serialConnected ? 'Connected' : 'Connect Arduino' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-4">
                <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Station</p>
                    <p class="mt-2 text-sm font-medium text-stone-950">{{ stationOnline ? 'Online' : 'Retrying' }}</p>
                </div>
                <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Queue</p>
                    <p class="mt-2 text-sm font-medium text-stone-950">{{ pendingCount }} pending</p>
                </div>
                <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Sensor</p>
                    <p class="mt-2 text-sm font-medium text-stone-950">{{ distanceText }} cm</p>
                </div>
                <div class="rounded-[1rem] bg-stone-50 p-4 ring-1 ring-stone-200">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Status</p>
                    <p class="mt-2 text-sm font-medium text-stone-950">{{ statusText }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-[1.25rem] bg-stone-950 px-5 py-6 text-center text-lime-400">
                <p class="text-[11px] uppercase tracking-[0.3em] text-lime-300">Rep count</p>
                <p class="mt-3 text-7xl font-semibold leading-none">{{ repCount }}</p>
                <p class="mt-3 text-sm uppercase tracking-[0.18em] text-lime-300">{{ movementText }}</p>
                <p class="mt-2 text-xs uppercase tracking-[0.16em] text-lime-200">
                    <template v-if="currentSession">
                        Set {{ currentSet }}/{{ totalSets }}
                    </template>
                </p>
            </div>

            <div class="mt-5 rounded-[1.25rem] bg-stone-50 p-5 ring-1 ring-stone-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Current task</p>
                        <p class="mt-2 text-sm font-medium text-stone-950">{{ sessionStatusLabel }}</p>
                    </div>
                </div>

                <div v-if="currentSession" class="mt-4 space-y-4">
                    <div class="rounded-[1rem] bg-white p-4 ring-1 ring-stone-200">
                        <p class="text-base font-semibold text-stone-950">
                            {{ currentSession.student.display_name }} ({{ currentSession.student.username }})
                        </p>
                        <p class="mt-1 text-sm text-stone-700">
                            {{ currentSession.violation.rule_title }} - {{ currentSession.required_push_ups }} push-ups
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-7">
                        <div class="rounded-[0.9rem] bg-white p-3 ring-1 ring-stone-200"><p class="text-[10px] uppercase tracking-[0.14em] text-stone-500">Sets</p><p class="mt-1 text-sm font-semibold text-stone-950">{{ currentSession.configuration.sets }}</p></div>
                        <div class="rounded-[0.9rem] bg-white p-3 ring-1 ring-stone-200"><p class="text-[10px] uppercase tracking-[0.14em] text-stone-500">Reps</p><p class="mt-1 text-sm font-semibold text-stone-950">{{ currentSession.configuration.reps }}</p></div>
                        <div class="rounded-[0.9rem] bg-white p-3 ring-1 ring-stone-200"><p class="text-[10px] uppercase tracking-[0.14em] text-stone-500">Rest</p><p class="mt-1 text-sm font-semibold text-stone-950">{{ currentSession.configuration.rest_seconds }}</p></div>
                        <div class="rounded-[0.9rem] bg-white p-3 ring-1 ring-stone-200"><p class="text-[10px] uppercase tracking-[0.14em] text-stone-500">Penalty</p><p class="mt-1 text-sm font-semibold text-stone-950">{{ currentSession.configuration.penalty_reps }}</p></div>
                        <div class="rounded-[0.9rem] bg-white p-3 ring-1 ring-stone-200"><p class="text-[10px] uppercase tracking-[0.14em] text-stone-500">Drop</p><p class="mt-1 text-sm font-semibold text-stone-950">{{ currentSession.configuration.drop_threshold }}</p></div>
                        <div class="rounded-[0.9rem] bg-white p-3 ring-1 ring-stone-200"><p class="text-[10px] uppercase tracking-[0.14em] text-stone-500">Up gap</p><p class="mt-1 text-sm font-semibold text-stone-950">{{ currentSession.configuration.up_gap }}</p></div>
                        <div class="rounded-[0.9rem] bg-white p-3 ring-1 ring-stone-200"><p class="text-[10px] uppercase tracking-[0.14em] text-stone-500">Down tol</p><p class="mt-1 text-sm font-semibold text-stone-950">{{ currentSession.configuration.down_tolerance }}</p></div>
                    </div>
                </div>

                <p v-else class="mt-4 text-sm text-stone-500">
                    No claimed task. Keep this window open and connected on the station machine.
                </p>
                <p v-if="currentSession" class="mt-3 text-xs text-stone-500">
                    The station auto-starts queued push-up tasks once the Arduino is connected. No manual calibration is required.
                </p>
            </div>
        </div>
    </div>
</template>
