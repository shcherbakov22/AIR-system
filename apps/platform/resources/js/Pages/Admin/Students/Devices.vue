<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    student: {
        id: number;
        display_name: string;
        username?: string | null;
    };
    browser_accountability: {
        mode: string;
        default_unblock_scope: string;
        rules: Array<{
            id: number;
            effect: string;
            match_type: string;
            value: string;
            expires_at?: string | null;
        }>;
        pending_requests: Array<{
            id: number;
            requested_url: string;
            host: string;
            registrable_domain: string;
            reason?: string | null;
            created_at?: string | null;
        }>;
        recent_visits: Array<{
            id: number;
            mode: string;
            decision: string;
            host: string;
            registrable_domain: string;
            page_title?: string | null;
            visited_at?: string | null;
            matched_rule_id?: number | null;
        }>;
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
        revoked_at?: string | null;
        policy: {
            mode: string;
            internet_allowed: boolean;
            reason: string;
        };
        activity: {
            focused_app?: {
                app_name?: string | null;
                window_title?: string | null;
                browser_domain?: string | null;
                observed_at?: string | null;
            } | null;
            open_apps: Array<{ name?: string; app_name?: string; title?: string; window_title?: string }>;
        };
        latest_captures: Array<{
            id: number;
            capture_kind: string;
            captured_at?: string | null;
        }>;
        commands: Array<{
            id: number;
            command_type: string;
            status: string;
            requested_at?: string | null;
            completed_at?: string | null;
        }>;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
const errorMessage = computed(() => page.props.flash?.error ?? null);
const labels = reactive(Object.fromEntries(props.devices.map((device) => [device.id, device.label])));
const browserRuleForm = reactive({
    effect: 'allow',
    value: '',
});

const browserMode = computed(() => props.browser_accountability.mode === 'whitelist' ? 'whitelist' : 'blacklist');

const saveLabel = (deviceId: number) => {
    router.patch(route('admin.students.devices.update', [props.student.id, deviceId]), {
        label: labels[deviceId],
    }, {
        preserveScroll: true,
    });
};

const revokeDevice = (deviceId: number, label: string) => {
    if (!window.confirm(`Revoke ${label}?`)) {
        return;
    }

    router.patch(route('admin.students.devices.revoke', [props.student.id, deviceId]), {}, {
        preserveScroll: true,
    });
};

const queueCommand = (deviceId: number, commandType: string) => {
    router.post(route('admin.students.devices.command', [props.student.id, deviceId]), {
        command_type: commandType,
    }, {
        preserveScroll: true,
    });
};

const updateBrowserMode = (mode: 'whitelist' | 'blacklist') => {
    router.patch(route('admin.students.browser-mode.update', props.student.id), {
        mode,
    }, {
        preserveScroll: true,
    });
};

const saveBrowserRule = () => {
    router.post(route('admin.students.browser-rules.store', props.student.id), {
        effect: browserRuleForm.effect,
        value: browserRuleForm.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            browserRuleForm.value = '';
        },
    });
};

const deleteBrowserRule = (ruleId: number) => {
    router.delete(route('admin.students.browser-rules.destroy', [props.student.id, ruleId]), {
        preserveScroll: true,
    });
};

const approveBrowserRequest = (requestId: number) => {
    router.patch(route('admin.students.browser-access-requests.approve', [props.student.id, requestId]), {}, {
        preserveScroll: true,
    });
};

const denyBrowserRequest = (requestId: number) => {
    router.patch(route('admin.students.browser-access-requests.deny', [props.student.id, requestId]), {}, {
        preserveScroll: true,
    });
};

const formatDateTime = (value?: string | null) => value ? new Date(value).toLocaleString() : 'Never';
const prettyCommand = (value: string) => value.replaceAll('_', ' ');
</script>

<template>
    <Head :title="`Devices: ${props.student.display_name}`" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <div v-if="successMessage" class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="mb-5 rounded-[1.5rem] bg-rose-50 px-6 py-4 text-sm text-rose-800 ring-1 ring-rose-200">
                {{ errorMessage }}
            </div>
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <Link
                    :href="route('admin.students.edit', props.student.id)"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Back to student
                </Link>
                <Link
                    :href="route('admin.students.progress', props.student.id)"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Progress
                </Link>
                <Link
                    :href="route('admin.students.devices.debug', props.student.id)"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Companion debug
                </Link>
            </div>

            <section class="mb-6 rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Browser accountability</p>
                        <h2 class="mt-2 text-2xl font-semibold text-stone-950">
                            {{ browserMode === 'whitelist' ? 'Whitelist mode' : 'Blacklist mode' }}
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm text-stone-600">
                            Approvals allow the requested domain and all subdomains by default.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] transition"
                            :class="browserMode === 'whitelist' ? 'border-emerald-700 bg-emerald-50 text-emerald-800' : 'border-stone-300 text-stone-700 hover:border-stone-950 hover:text-stone-950'"
                            @click="updateBrowserMode('whitelist')"
                        >
                            Whitelist
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] transition"
                            :class="browserMode === 'blacklist' ? 'border-emerald-700 bg-emerald-50 text-emerald-800' : 'border-stone-300 text-stone-700 hover:border-stone-950 hover:text-stone-950'"
                            @click="updateBrowserMode('blacklist')"
                        >
                            Blacklist
                        </button>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_1fr]">
                    <div class="rounded-[1.25rem] bg-stone-100 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Rules</p>
                        <form class="mt-3 flex flex-wrap gap-3" @submit.prevent="saveBrowserRule">
                            <select
                                v-model="browserRuleForm.effect"
                                class="rounded-xl border-stone-300 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            >
                                <option value="allow">Allow</option>
                                <option value="block">Block</option>
                            </select>
                            <input
                                v-model="browserRuleForm.value"
                                type="text"
                                placeholder="example.com"
                                class="min-w-[14rem] flex-1 rounded-xl border-stone-300 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <button
                                type="submit"
                                class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Save rule
                            </button>
                        </form>
                        <ul class="mt-4 space-y-2 text-sm text-stone-700">
                            <li v-for="rule in props.browser_accountability.rules" :key="rule.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                                <span>
                                    <strong class="uppercase tracking-[0.14em] text-stone-500">{{ rule.effect }}</strong>
                                    <span class="ml-2 font-semibold text-stone-950">{{ rule.value }}</span>
                                    <span class="ml-1 text-stone-500">and subdomains</span>
                                </span>
                                <button type="button" class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700" @click="deleteBrowserRule(rule.id)">Remove</button>
                            </li>
                            <li v-if="props.browser_accountability.rules.length === 0" class="text-sm text-stone-500">No browser rules yet.</li>
                        </ul>
                    </div>

                    <div class="rounded-[1.25rem] bg-stone-100 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Pending requests</p>
                        <ul class="mt-3 space-y-2 text-sm text-stone-700">
                            <li v-for="request in props.browser_accountability.pending_requests" :key="request.id" class="rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-stone-950">{{ request.registrable_domain }} and subdomains</p>
                                        <p class="mt-1 break-all text-xs text-stone-500">{{ request.requested_url }}</p>
                                        <p v-if="request.reason" class="mt-2 text-sm text-stone-700">{{ request.reason }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700" @click="approveBrowserRequest(request.id)">Allow</button>
                                        <button type="button" class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700" @click="denyBrowserRequest(request.id)">Deny</button>
                                    </div>
                                </div>
                            </li>
                            <li v-if="props.browser_accountability.pending_requests.length === 0" class="text-sm text-stone-500">No pending requests.</li>
                        </ul>
                    </div>
                </div>

                <div class="mt-4 rounded-[1.25rem] bg-stone-100 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Recent websites</p>
                    <ul class="mt-3 grid gap-2 text-sm text-stone-700 md:grid-cols-2">
                        <li v-for="visit in props.browser_accountability.recent_visits.slice(0, 12)" :key="visit.id" class="rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="font-semibold text-stone-950">{{ visit.host }}</span>
                                <span class="text-xs uppercase tracking-[0.16em]" :class="visit.decision === 'blocked' ? 'text-rose-700' : 'text-emerald-700'">{{ visit.decision }}</span>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">{{ visit.registrable_domain }} · {{ formatDateTime(visit.visited_at) }}</p>
                        </li>
                        <li v-if="props.browser_accountability.recent_visits.length === 0" class="text-sm text-stone-500">No browser visits logged yet.</li>
                    </ul>
                </div>
            </section>

            <div class="grid gap-5">
                <article
                    v-for="device in props.devices"
                    :key="device.id"
                    class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-2xl font-semibold text-stone-950">
                                        {{ device.label }}
                                    </h2>
                                    <p class="mt-1 text-sm text-stone-500">
                                        {{ device.platform }}<span v-if="device.hostname"> · {{ device.hostname }}</span><span v-if="device.app_version"> · {{ device.app_version }}</span>
                                    </p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="device.revoked_at ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'">
                                    {{ device.revoked_at ? 'Revoked' : 'Active' }}
                                </span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Last seen</p>
                                    <p class="mt-2 font-semibold text-stone-950">{{ formatDateTime(device.last_seen_at) }}</p>
                                    <p v-if="device.last_seen_ip" class="mt-1 text-xs text-stone-500">{{ device.last_seen_ip }}</p>
                                </div>
                                <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Policy</p>
                                    <p class="mt-2 font-semibold text-stone-950">Internet control removed</p>
                                    <p class="mt-1 text-xs text-stone-500">{{ prettyCommand(device.policy.reason) }}</p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Network identity</p>
                                    <p class="mt-2 font-semibold text-stone-950">{{ device.last_ipv4 || 'No IPv4 reported yet' }}</p>
                                    <p v-if="device.last_mac_address" class="mt-1 text-xs text-stone-500">{{ device.last_mac_address }}</p>
                                </div>
                                <div class="rounded-[1.25rem] bg-stone-100 p-4 text-sm text-stone-700">
                                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Gateway route</p>
                                    <p class="mt-2 font-semibold text-stone-950">{{ device.last_gateway_ipv4 || 'Unknown gateway' }}</p>
                                    <p v-if="device.network_adapter_name" class="mt-1 text-xs text-stone-500">{{ device.network_adapter_name }}</p>
                                </div>
                            </div>

                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Rename device</p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <input
                                        v-model="labels[device.id]"
                                        type="text"
                                        class="min-w-[16rem] flex-1 rounded-xl border-stone-300 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                        @click="saveLabel(device.id)"
                                    >
                                        Save label
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-700 hover:text-rose-800"
                                        :disabled="!!device.revoked_at"
                                        @click="revokeDevice(device.id, device.label)"
                                    >
                                        Revoke
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Actions</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="inline-flex rounded-full border border-stone-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950" @click="queueCommand(device.id, 'refresh_policy')">Refresh policy</button>
                                    <button type="button" class="inline-flex rounded-full border border-stone-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950" @click="queueCommand(device.id, 'request_screenshot')">Request screenshot</button>
                                    <button type="button" class="inline-flex rounded-full border border-stone-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950" @click="queueCommand(device.id, 'request_camera_capture')">Request camera</button>
                                </div>
                            </div>

                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Focused activity</p>
                                <p class="mt-2 text-sm font-semibold text-stone-950">
                                    {{ device.activity.focused_app?.app_name || 'No focused app reported yet' }}
                                </p>
                                <p v-if="device.activity.focused_app?.window_title" class="mt-1 text-sm text-stone-600">
                                    {{ device.activity.focused_app.window_title }}
                                </p>
                                <p v-if="device.activity.focused_app?.browser_domain" class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-500">
                                    {{ device.activity.focused_app.browser_domain }}
                                </p>
                            </div>

                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Open apps</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-for="(app, index) in device.activity.open_apps" :key="index" class="rounded-full bg-white px-3 py-1 text-xs text-stone-700 ring-1 ring-stone-200">
                                        {{ app.name || app.app_name || app.title || app.window_title || 'Unknown app' }}
                                    </span>
                                    <span v-if="device.activity.open_apps.length === 0" class="text-sm text-stone-500">No open app snapshot yet.</span>
                                </div>
                            </div>

                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Recent commands</p>
                                <ul class="mt-3 space-y-2 text-sm text-stone-700">
                                    <li v-for="command in device.commands" :key="command.id" class="flex items-center justify-between gap-4 rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                                        <span class="font-medium text-stone-950">{{ prettyCommand(command.command_type) }}</span>
                                        <span class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ command.status }}</span>
                                    </li>
                                    <li v-if="device.commands.length === 0" class="text-sm text-stone-500">No commands queued yet.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </article>

                <div v-if="props.devices.length === 0" class="rounded-[2rem] bg-white px-6 py-12 text-center shadow-sm ring-1 ring-stone-200">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">No devices enrolled yet</p>
                    <p class="mt-4 text-sm text-stone-600">The companion app will create devices here after student enrollment.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
