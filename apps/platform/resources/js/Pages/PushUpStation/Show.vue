<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
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
    };
};

const stationKeyStorageKey = 'air-push-up-station-key';
const stationNameStorageKey = 'air-push-up-station-name';

const randomKey = () => `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
const stationKey = ref(localStorage.getItem(stationKeyStorageKey) || randomKey());
const stationName = ref(localStorage.getItem(stationNameStorageKey) || 'Counter station');
const stationOnline = ref(false);
const serialConnected = ref(false);
const pendingCount = ref(0);
const currentSession = ref<PushUpSessionPayload | null>(null);
const statusText = ref('Idle');
const distanceText = ref('---');
const repCount = ref(0);
const movementText = ref('Standby');
const busy = ref(false);

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
    currentSession.value = payload.current_session;
    repCount.value = payload.current_session?.current_rep ?? 0;

    if (!payload.current_session && serialConnected.value && !busy.value && payload.pending_count > 0) {
        await claimNext();
    }
};

const claimNext = async () => {
    if (busy.value) {
        return;
    }

    busy.value = true;

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
        currentSession.value = payload.session;
        repCount.value = payload.session?.current_rep ?? 0;
        statusText.value = payload.session ? 'Session claimed' : 'No pending sessions';
    } finally {
        busy.value = false;
    }
};

const startCurrentSession = async () => {
    if (!currentSession.value || !serialConnected.value) {
        return;
    }

    const config = currentSession.value.configuration;

    await postJson(route('push-up-station.sessions.start', currentSession.value.id), 'PATCH', {
        station_key: stationKey.value,
    });

    statusText.value = `Running ${currentSession.value.student.display_name}`;
    repCount.value = 0;
    await sendSerial(`${config.reps},${config.penalty_reps},${config.drop_threshold},${config.up_gap},${config.down_tolerance}`);
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
            current_rep: currentRep,
            current_set: 1,
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
    currentSession.value = null;
    repCount.value = 0;
    movementText.value = 'Standby';
    await heartbeat();
};

const failCurrentSession = async () => {
    if (!currentSession.value) {
        return;
    }

    await postJson(route('push-up-station.sessions.fail', currentSession.value.id), 'PATCH', {
        station_key: stationKey.value,
        notes: 'Marked failed from station.',
    });

    statusText.value = 'Session failed';
    currentSession.value = null;
    repCount.value = 0;
    await heartbeat();
};

const readLoop = async () => {
    if (!port?.readable) {
        return;
    }

    const decoder = new TextDecoder();
    const reader = port.readable.getReader();
    let buffer = '';

    try {
        while (true) {
            const { value, done } = await reader.read();
            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            if (buffer.includes('FINISH_NOW')) {
                buffer = buffer.replace('FINISH_NOW', '');
                await completeCurrentSession();
            }

            const parts = buffer.split(';');
            buffer = parts.pop() ?? '';

            for (const part of parts) {
                if (part.startsWith('D:')) {
                    distanceText.value = part.split(':')[1] ?? '---';
                }

                if (part.startsWith('W:')) {
                    const nextRep = Number(part.split(':')[1] ?? '0');
                    repCount.value = Number.isNaN(nextRep) ? repCount.value : nextRep;
                    await pushProgress(repCount.value);
                }

                if (part.startsWith('S:')) {
                    movementText.value = part.split(':')[1] === '1' ? 'Up' : 'Down';
                }
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

    port = await browserSerial.serial.requestPort();
    await port.open({ baudRate: 115200 });
    writer = port.writable?.getWriter() ?? null;
    serialConnected.value = true;
    statusText.value = 'Arduino connected';
    readLoop();
    await heartbeat();
};

onMounted(async () => {
    ensureStationKey();
    await heartbeat();
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
    <Head title="Counter" />

    <AuthenticatedLayout>
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
                </div>

                <div class="mt-5 rounded-[1.25rem] bg-stone-50 p-5 ring-1 ring-stone-200">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Current task</p>
                            <p class="mt-2 text-sm font-medium text-stone-950">{{ sessionStatusLabel }}</p>
                        </div>
                        <button
                            v-if="!currentSession"
                            type="button"
                            class="rounded-full border border-stone-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-stone-700"
                            @click="claimNext"
                        >
                            Claim next
                        </button>
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

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                class="rounded-full border border-emerald-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700"
                                :disabled="!serialConnected"
                                @click="startCurrentSession"
                            >
                                Start
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-stone-700"
                                @click="claimNext"
                            >
                                Refresh
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-rose-700"
                                @click="failCurrentSession"
                            >
                                Fail
                            </button>
                        </div>
                    </div>

                    <p v-else class="mt-4 text-sm text-stone-500">
                        No claimed task. Keep this window open on the station machine.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
