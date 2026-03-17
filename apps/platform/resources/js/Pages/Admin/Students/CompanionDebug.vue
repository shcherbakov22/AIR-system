<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    student: {
        id: number;
        display_name: string;
        username?: string | null;
    };
    devices: Array<{
        id: number;
        device_key: string;
        label: string;
        hostname?: string | null;
        platform: string;
        app_version?: string | null;
        last_seen_at?: string | null;
        last_seen_ip?: string | null;
        last_ipv4?: string | null;
        last_mac_address?: string | null;
        last_gateway_ipv4?: string | null;
        network_adapter_name?: string | null;
        internet_access_mode: 'allow_all' | 'block_all';
        revoked_at?: string | null;
        last_network_state: Record<string, unknown>;
        meta: Record<string, unknown>;
        policy: {
            mode: string;
            internet_allowed: boolean;
            reason: string;
            allowed_domains?: string[];
        };
        policy_snapshot: Record<string, unknown>;
        activity: {
            focused_app?: {
                app_name?: string | null;
                window_title?: string | null;
                browser_domain?: string | null;
                observed_at?: string | null;
            } | null;
            open_apps: Array<{ name?: string; app_name?: string; title?: string; window_title?: string }>;
        };
        heartbeats: Array<{
            id: number;
            received_at?: string | null;
            ip_address?: string | null;
            payload: Record<string, unknown>;
        }>;
        activity_events: Array<{
            id: number;
            event_type: string;
            app_name?: string | null;
            window_title?: string | null;
            browser_domain?: string | null;
            observed_at?: string | null;
            payload: Record<string, unknown>;
        }>;
        captures: Array<{
            id: number;
            capture_kind: string;
            captured_at?: string | null;
            uploaded_at?: string | null;
            task_title_snapshot?: string | null;
            app_name_snapshot?: string | null;
            window_title_snapshot?: string | null;
            browser_domain_snapshot?: string | null;
            source_label?: string | null;
            source_version?: string | null;
            mime_type?: string | null;
            size_bytes?: number | null;
            meta: Record<string, unknown>;
            show_url: string;
        }>;
        commands: Array<{
            id: number;
            command_type: string;
            status: string;
            payload: Record<string, unknown>;
            requested_at?: string | null;
            leased_at?: string | null;
            completed_at?: string | null;
            results: Array<{
                id: number;
                status: string;
                received_at?: string | null;
                payload: Record<string, unknown>;
            }>;
        }>;
    }>;
}>();

const formatDateTime = (value?: string | null) => value ? new Date(value).toLocaleString() : 'Never';
const pretty = (value: string) => value.replaceAll('_', ' ');
const formatJson = (value: unknown) => JSON.stringify(value ?? {}, null, 2);
const setInternetAccessMode = (deviceId: number, mode: 'allow_all' | 'block_all') => {
    router.patch(route('admin.students.devices.internet.update', [props.student.id, deviceId]), {
        internet_access_mode: mode,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Companion Debug: ${props.student.display_name}`" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <Link
                    :href="route('admin.students.devices.index', props.student.id)"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Back to devices
                </Link>
                <Link
                    :href="route('admin.students.edit', props.student.id)"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Student
                </Link>
            </div>

            <div class="mb-8 rounded-[2rem] bg-white px-6 py-5 shadow-sm ring-1 ring-stone-200">
                <h1 class="text-2xl font-semibold text-stone-950">{{ props.student.display_name }}</h1>
                <p class="mt-1 text-sm text-stone-500">{{ props.student.username || 'No username' }}</p>
            </div>

            <div class="space-y-6">
                <article
                    v-for="device in props.devices"
                    :key="device.id"
                    class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-stone-950">{{ device.label }}</h2>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ device.platform }}
                                <span v-if="device.hostname"> · {{ device.hostname }}</span>
                                <span v-if="device.app_version"> · {{ device.app_version }}</span>
                            </p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                            :class="device.revoked_at ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                        >
                            {{ device.revoked_at ? 'Revoked' : 'Active' }}
                        </span>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-4">
                        <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Last seen</p>
                            <p class="mt-2 font-semibold text-stone-950">{{ formatDateTime(device.last_seen_at) }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ device.last_seen_ip || 'No source IP' }}</p>
                        </div>
                        <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Adapter</p>
                            <p class="mt-2 font-semibold text-stone-950">{{ device.network_adapter_name || 'Unknown' }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ device.last_mac_address || 'No MAC' }}</p>
                        </div>
                        <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">IPv4</p>
                            <p class="mt-2 font-semibold text-stone-950">{{ device.last_ipv4 || 'Unknown' }}</p>
                            <p class="mt-1 text-xs text-stone-500">Gateway {{ device.last_gateway_ipv4 || 'Unknown' }}</p>
                        </div>
                        <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Internet policy</p>
                            <p class="mt-2 font-semibold text-stone-950">{{ device.policy.internet_allowed ? 'Allowed' : 'Blocked' }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ pretty(device.policy.reason) }}</p>
                            <div class="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] transition"
                                    :class="device.internet_access_mode === 'allow_all' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-stone-300 text-stone-700 hover:border-stone-950 hover:text-stone-950'"
                                    @click="setInternetAccessMode(device.id, 'allow_all')"
                                >
                                    Allow
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] transition"
                                    :class="device.internet_access_mode === 'block_all' ? 'border-rose-600 bg-rose-600 text-white' : 'border-stone-300 text-stone-700 hover:border-stone-950 hover:text-stone-950'"
                                    @click="setInternetAccessMode(device.id, 'block_all')"
                                >
                                    Block
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 xl:grid-cols-2">
                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-stone-500">Current policy snapshot</h3>
                            <pre class="overflow-x-auto rounded-[1.25rem] bg-stone-950 p-4 text-xs leading-5 text-stone-100">{{ formatJson(device.policy_snapshot) }}</pre>
                        </section>

                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-stone-500">Device meta</h3>
                            <pre class="overflow-x-auto rounded-[1.25rem] bg-stone-950 p-4 text-xs leading-5 text-stone-100">{{ formatJson({ last_network_state: device.last_network_state, meta: device.meta }) }}</pre>
                        </section>
                    </div>

                    <div class="mt-6 grid gap-6 xl:grid-cols-2">
                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-stone-500">Heartbeats</h3>
                            <div class="space-y-3">
                                <article
                                    v-for="heartbeat in device.heartbeats"
                                    :key="heartbeat.id"
                                    class="rounded-[1.25rem] bg-stone-100 p-4"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-stone-700">
                                        <span class="font-semibold text-stone-950">{{ formatDateTime(heartbeat.received_at) }}</span>
                                        <span class="text-xs text-stone-500">{{ heartbeat.ip_address || 'No IP' }}</span>
                                    </div>
                                    <pre class="mt-3 overflow-x-auto rounded-xl bg-white p-3 text-xs leading-5 text-stone-800 ring-1 ring-stone-200">{{ formatJson(heartbeat.payload) }}</pre>
                                </article>
                                <p v-if="device.heartbeats.length === 0" class="text-sm text-stone-500">No heartbeats stored yet.</p>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-stone-500">Activity events</h3>
                            <div class="space-y-3">
                                <article
                                    v-for="event in device.activity_events"
                                    :key="event.id"
                                    class="rounded-[1.25rem] bg-stone-100 p-4"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-stone-700">
                                        <span class="font-semibold text-stone-950">{{ pretty(event.event_type) }}</span>
                                        <span class="text-xs text-stone-500">{{ formatDateTime(event.observed_at) }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-stone-700">
                                        {{ event.app_name || 'No app' }}
                                        <span v-if="event.window_title"> · {{ event.window_title }}</span>
                                        <span v-if="event.browser_domain"> · {{ event.browser_domain }}</span>
                                    </p>
                                    <pre class="mt-3 overflow-x-auto rounded-xl bg-white p-3 text-xs leading-5 text-stone-800 ring-1 ring-stone-200">{{ formatJson(event.payload) }}</pre>
                                </article>
                                <p v-if="device.activity_events.length === 0" class="text-sm text-stone-500">No activity events stored yet.</p>
                            </div>
                        </section>
                    </div>

                    <div class="mt-6 grid gap-6 xl:grid-cols-2">
                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-stone-500">Captures</h3>
                            <div class="space-y-3">
                                <article
                                    v-for="capture in device.captures"
                                    :key="capture.id"
                                    class="rounded-[1.25rem] bg-stone-100 p-4"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-stone-700">
                                        <span class="font-semibold text-stone-950">{{ capture.capture_kind }}</span>
                                        <a
                                            :href="capture.show_url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 hover:text-amber-800"
                                        >
                                            Open
                                        </a>
                                    </div>
                                    <p class="mt-2 text-sm text-stone-700">
                                        {{ formatDateTime(capture.captured_at) }} · {{ capture.task_title_snapshot || 'No task snapshot' }}
                                    </p>
                                    <p class="mt-1 text-xs text-stone-500">
                                        {{ capture.app_name_snapshot || 'No app' }}
                                        <span v-if="capture.window_title_snapshot"> · {{ capture.window_title_snapshot }}</span>
                                        <span v-if="capture.browser_domain_snapshot"> · {{ capture.browser_domain_snapshot }}</span>
                                    </p>
                                    <pre class="mt-3 overflow-x-auto rounded-xl bg-white p-3 text-xs leading-5 text-stone-800 ring-1 ring-stone-200">{{ formatJson({ uploaded_at: capture.uploaded_at, source_label: capture.source_label, source_version: capture.source_version, mime_type: capture.mime_type, size_bytes: capture.size_bytes, meta: capture.meta }) }}</pre>
                                </article>
                                <p v-if="device.captures.length === 0" class="text-sm text-stone-500">No captures stored yet.</p>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-stone-500">Commands and results</h3>
                            <div class="space-y-3">
                                <article
                                    v-for="command in device.commands"
                                    :key="command.id"
                                    class="rounded-[1.25rem] bg-stone-100 p-4"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-stone-700">
                                        <span class="font-semibold text-stone-950">{{ pretty(command.command_type) }}</span>
                                        <span class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ command.status }}</span>
                                    </div>
                                    <pre class="mt-3 overflow-x-auto rounded-xl bg-white p-3 text-xs leading-5 text-stone-800 ring-1 ring-stone-200">{{ formatJson({ requested_at: command.requested_at, leased_at: command.leased_at, completed_at: command.completed_at, payload: command.payload }) }}</pre>
                                    <div class="mt-3 space-y-2">
                                        <div
                                            v-for="result in command.results"
                                            :key="result.id"
                                            class="rounded-xl bg-white p-3 ring-1 ring-stone-200"
                                        >
                                            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-stone-500">
                                                <span class="font-semibold uppercase tracking-[0.16em] text-stone-700">{{ result.status }}</span>
                                                <span>{{ formatDateTime(result.received_at) }}</span>
                                            </div>
                                            <pre class="mt-2 overflow-x-auto text-xs leading-5 text-stone-800">{{ formatJson(result.payload) }}</pre>
                                        </div>
                                        <p v-if="command.results.length === 0" class="text-xs text-stone-500">No result received yet.</p>
                                    </div>
                                </article>
                                <p v-if="device.commands.length === 0" class="text-sm text-stone-500">No commands stored yet.</p>
                            </div>
                        </section>
                    </div>
                </article>

                <div v-if="props.devices.length === 0" class="rounded-[2rem] bg-white px-6 py-12 text-center shadow-sm ring-1 ring-stone-200">
                    <p class="text-sm text-stone-500">No companion devices enrolled for this student yet.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
