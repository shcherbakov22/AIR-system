<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

type StudentSummary = {
    id: number;
    display_name: string;
    username?: string | null;
    status: string;
    browser_mode: string;
    device_count: number;
    last_seen_at_label: string;
    pending_access_request_count: number;
    visit_count_today: number;
    open_look_away_count: number;
    extension_url: string;
};

type FocusedStudent = {
    id: number;
    display_name: string;
    username?: string | null;
    browser_accountability: {
        mode: string;
        default_unblock_scope: string;
        mode_update_url: string;
        rule_store_url: string;
        history_clear_url: string;
        rules: Array<{
            id: number;
            effect: string;
            match_type: string;
            value: string;
            expires_at?: string | null;
            destroy_url: string;
        }>;
        access_requests: Array<{
            id: number;
            requested_url: string;
            display_url?: string | null;
            host: string;
            registrable_domain: string;
            reason?: string | null;
            status: string;
            task_title?: string | null;
            created_at_label?: string | null;
            decided_at_label?: string | null;
            approve_url: string;
            deny_url: string;
            destroy_url: string;
        }>;
        recent_visits: Array<{
            id: number;
            mode: string;
            decision: string;
            host: string;
            registrable_domain: string;
            url?: string | null;
            display_url?: string | null;
            page_title?: string | null;
            visited_at_label?: string | null;
            destroy_url: string;
            matched_rule?: { effect: string; value: string } | null;
        }>;
        current_visit?: {
            id: number;
            mode: string;
            decision: string;
            host: string;
            registrable_domain: string;
            url?: string | null;
            display_url?: string | null;
            page_title?: string | null;
            visited_at_label?: string | null;
            destroy_url: string;
            matched_rule?: { effect: string; value: string } | null;
        } | null;
    };
    devices: Array<{
        id: number;
        label: string;
        hostname?: string | null;
        platform?: string | null;
        app_version?: string | null;
        internet_access_mode?: string | null;
        last_seen_at_label: string;
        last_seen_ip?: string | null;
        last_policy_hash?: string | null;
        attention_calibrations: Array<{
            id: number;
            provider: string;
            status: string;
            sample_count: number;
            started_at_label?: string | null;
            completed_at_label?: string | null;
            model_ready_at_label?: string | null;
            model_version?: string | null;
        }>;
    }>;
    attention: {
        look_away_event_threshold: number;
        look_away_event_count: number;
        look_away_task_session_id?: number | null;
        violations: Array<{
            id: number;
            status: string;
            rule_title: string;
            penalty_units: number;
            occurred_at_label?: string | null;
            notes?: string | null;
        }>;
    };
};

const props = defineProps<{
    students: StudentSummary[];
    focusedStudent?: FocusedStudent | null;
    extension_download_url: string;
    default_unblock_scope: string;
    task_allowlists: Array<{
        id: number;
        title: string;
        domains: string[];
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
const errorMessage = computed(() => page.props.flash?.error ?? null);
const browserMode = computed(() => props.focusedStudent?.browser_accountability.mode === 'whitelist' ? 'whitelist' : 'blacklist');
const pendingRequests = computed(() => props.focusedStudent?.browser_accountability.access_requests.filter((request) => request.status === 'pending') ?? []);
const historyCollapsed = ref(true);

const browserRuleForm = reactive({
    effect: 'allow',
    value: '',
});
const globalRequestApprovals = reactive<Record<number, boolean>>({});

const updateBrowserMode = (mode: 'whitelist' | 'blacklist') => {
    if (!props.focusedStudent) {
        return;
    }

    router.patch(props.focusedStudent.browser_accountability.mode_update_url, { mode }, { preserveScroll: true });
};

const saveBrowserRule = () => {
    if (!props.focusedStudent) {
        return;
    }

    router.post(props.focusedStudent.browser_accountability.rule_store_url, {
        effect: browserRuleForm.effect,
        value: browserRuleForm.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            browserRuleForm.value = '';
        },
    });
};

const deleteBrowserRule = (destroyUrl: string) => {
    router.delete(destroyUrl, { preserveScroll: true });
};

const approveBrowserRequest = (request: FocusedStudent['browser_accountability']['access_requests'][number]) => {
    router.patch(request.approve_url, {
        global: Boolean(globalRequestApprovals[request.id]),
    }, { preserveScroll: true });
};

const denyBrowserRequest = (denyUrl: string) => {
    router.patch(denyUrl, {}, { preserveScroll: true });
};

const deleteBrowserRequest = (request: FocusedStudent['browser_accountability']['access_requests'][number]) => {
    if (!window.confirm(`Deny and hide ${request.registrable_domain} request?`)) {
        return;
    }

    router.delete(request.destroy_url, { preserveScroll: true });
};

const clearBrowserHistory = () => {
    if (!props.focusedStudent) {
        return;
    }

    if (!window.confirm(`Clear browser history for ${props.focusedStudent.display_name}? Rules and requests will stay.`)) {
        return;
    }

    router.delete(props.focusedStudent.browser_accountability.history_clear_url, { preserveScroll: true });
};

const deleteBrowserHistoryLog = (visit: FocusedStudent['browser_accountability']['recent_visits'][number]) => {
    if (!window.confirm(`Remove ${visit.host} from browser history?`)) {
        return;
    }

    router.delete(visit.destroy_url, { preserveScroll: true });
};

const pretty = (value?: string | null) => (value || 'unknown').replaceAll('_', ' ');

watch(
    () => props.focusedStudent?.id,
    () => {
        historyCollapsed.value = true;
    },
);
</script>

<template>
    <Head title="Extension" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <div v-if="successMessage" class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="mb-5 rounded-[1.5rem] bg-rose-50 px-6 py-4 text-sm text-rose-800 ring-1 ring-rose-200">
                {{ errorMessage }}
            </div>

            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">Extension</p>
                    <h1 class="mt-2 text-3xl font-semibold text-stone-950">Browser accountability</h1>
                    <p class="mt-2 max-w-2xl text-sm text-stone-600">
                        Domain approvals include the domain and all subdomains by default.
                    </p>
                </div>
                <a
                    :href="props.extension_download_url"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Download extension
                </a>
            </div>

            <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
                <aside class="space-y-3">
                    <Link
                        v-for="student in props.students"
                        :key="student.id"
                        :href="student.extension_url"
                        class="block rounded-[1.25rem] bg-white p-4 shadow-sm ring-1 transition"
                        :class="props.focusedStudent?.id === student.id ? 'ring-amber-700' : 'ring-stone-200 hover:ring-stone-400'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-stone-950">{{ student.display_name }}</p>
                                <p class="mt-1 truncate text-xs text-stone-500">{{ student.username || 'No username' }}</p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="student.browser_mode === 'whitelist' ? 'bg-amber-100 text-amber-800' : 'bg-stone-100 text-stone-700'">
                                {{ student.browser_mode }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs text-stone-600">
                            <div class="rounded-xl bg-stone-100 px-2 py-2">
                                <p class="font-semibold text-stone-950">{{ student.pending_access_request_count }}</p>
                                <p>Requests</p>
                            </div>
                            <div class="rounded-xl bg-stone-100 px-2 py-2">
                                <p class="font-semibold text-stone-950">{{ student.visit_count_today }}</p>
                                <p>Visits</p>
                            </div>
                            <div class="rounded-xl bg-stone-100 px-2 py-2">
                                <p class="font-semibold text-stone-950">{{ student.open_look_away_count }}</p>
                                <p>Look away</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-stone-500">{{ student.device_count }} device{{ student.device_count === 1 ? '' : 's' }} · {{ student.last_seen_at_label }}</p>
                    </Link>
                </aside>

                <div v-if="props.focusedStudent" class="space-y-6">
                    <section class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">Configuration</p>
                                <h2 class="mt-2 text-2xl font-semibold text-stone-950">{{ props.focusedStudent.display_name }}</h2>
                                <p class="mt-2 text-sm text-stone-600">Current mode is {{ browserMode }}.</p>
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

                        <div class="mt-6 grid gap-4 xl:grid-cols-2">
                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Blocklist and whitelist</p>
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
                                    <li v-for="rule in props.focusedStudent.browser_accountability.rules" :key="rule.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                                        <span>
                                            <strong class="uppercase tracking-[0.14em] text-stone-500">{{ rule.effect }}</strong>
                                            <span class="ml-2 font-semibold text-stone-950">{{ rule.value }}</span>
                                            <span class="ml-1 text-stone-500">and subdomains</span>
                                        </span>
                                        <button type="button" class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700" @click="deleteBrowserRule(rule.destroy_url)">Remove</button>
                                    </li>
                                    <li v-if="props.focusedStudent.browser_accountability.rules.length === 0" class="text-sm text-stone-500">No browser rules yet.</li>
                                </ul>
                            </div>

                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Pending requests</p>
                                <ul class="mt-3 space-y-2 text-sm text-stone-700">
                                    <li v-for="request in pendingRequests" :key="request.id" class="rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-stone-950">{{ request.registrable_domain }} and subdomains</p>
                                                <p class="mt-1 break-all text-xs text-stone-500">{{ request.display_url || request.requested_url }}</p>
                                                <p v-if="request.reason" class="mt-2 text-sm text-stone-700">{{ request.reason }}</p>
                                                <p class="mt-1 text-xs text-stone-500">
                                                    {{ request.task_title ? `Task: ${request.task_title}` : 'No task captured' }}
                                                </p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-500">
                                                    <input
                                                        v-model="globalRequestApprovals[request.id]"
                                                        type="checkbox"
                                                        class="rounded border-stone-300 text-amber-700 focus:ring-amber-700"
                                                    />
                                                    Global
                                                </label>
                                                <button type="button" class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700" @click="approveBrowserRequest(request)">Allow</button>
                                                <button type="button" class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700" @click="denyBrowserRequest(request.deny_url)">Deny</button>
                                                <button type="button" class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500 transition hover:text-rose-700" @click="deleteBrowserRequest(request)">Delete</button>
                                            </div>
                                        </div>
                                    </li>
                                    <li v-if="pendingRequests.length === 0" class="text-sm text-stone-500">No pending requests.</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs uppercase tracking-[0.22em] text-stone-500">Attention tracking</p>
                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Threshold</p>
                                <p class="mt-2 text-2xl font-semibold text-stone-950">{{ props.focusedStudent.attention.look_away_event_threshold }}</p>
                            </div>
                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Current count</p>
                                <p class="mt-2 text-2xl font-semibold text-stone-950">{{ props.focusedStudent.attention.look_away_event_count }}</p>
                            </div>
                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Task session</p>
                                <p class="mt-2 text-2xl font-semibold text-stone-950">{{ props.focusedStudent.attention.look_away_task_session_id ?? 'None' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 xl:grid-cols-2">
                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Look away violations</p>
                                <ul class="mt-3 space-y-2 text-sm text-stone-700">
                                    <li v-for="violation in props.focusedStudent.attention.violations" :key="violation.id" class="rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <p class="font-semibold text-stone-950">{{ violation.rule_title }}</p>
                                            <span class="text-xs uppercase tracking-[0.16em]" :class="violation.status === 'open' ? 'text-rose-700' : 'text-stone-500'">{{ violation.status }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-stone-500">{{ violation.penalty_units }} push-ups · {{ violation.occurred_at_label ?? 'Unknown time' }}</p>
                                    </li>
                                    <li v-if="props.focusedStudent.attention.violations.length === 0" class="text-sm text-stone-500">No look away violations yet.</li>
                                </ul>
                            </div>

                            <div class="rounded-[1.25rem] bg-stone-100 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Calibration</p>
                                <ul class="mt-3 space-y-2 text-sm text-stone-700">
                                    <li v-for="device in props.focusedStudent.devices" :key="device.id" class="rounded-xl bg-white px-3 py-2 ring-1 ring-stone-200">
                                        <p class="font-semibold text-stone-950">{{ device.label }}</p>
                                        <div class="mt-2 space-y-1">
                                            <p v-for="session in device.attention_calibrations" :key="session.id" class="text-xs text-stone-600">
                                                {{ pretty(session.provider) }} · {{ pretty(session.status) }} · {{ session.sample_count }} samples<span v-if="session.model_ready_at_label"> · ready {{ session.model_ready_at_label }}</span>
                                            </p>
                                            <p v-if="device.attention_calibrations.length === 0" class="text-xs text-stone-500">No calibration sessions yet.</p>
                                        </div>
                                    </li>
                                    <li v-if="props.focusedStudent.devices.length === 0" class="text-sm text-stone-500">No enrolled devices.</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">History</p>
                                <p class="mt-1 text-sm text-stone-600">
                                    {{ props.focusedStudent.browser_accountability.recent_visits.length }} recent website{{ props.focusedStudent.browser_accountability.recent_visits.length === 1 ? '' : 's' }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex rounded-full border border-stone-300 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    @click="historyCollapsed = !historyCollapsed"
                                >
                                    {{ historyCollapsed ? 'Show history' : 'Hide history' }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex rounded-full border border-rose-300 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700 transition hover:border-rose-600 hover:text-rose-800 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="props.focusedStudent.browser_accountability.recent_visits.length === 0"
                                    @click="clearBrowserHistory"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 rounded-[1.25rem] bg-stone-100 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Currently open</p>
                            <div v-if="props.focusedStudent.browser_accountability.current_visit" class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <p class="truncate font-semibold text-stone-950">
                                            {{ props.focusedStudent.browser_accountability.current_visit.page_title || props.focusedStudent.browser_accountability.current_visit.host }}
                                        </p>
                                        <span
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]"
                                            :class="props.focusedStudent.browser_accountability.current_visit.decision === 'blocked' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                                        >
                                            {{ props.focusedStudent.browser_accountability.current_visit.decision }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-stone-500">
                                        {{ props.focusedStudent.browser_accountability.current_visit.display_url || props.focusedStudent.browser_accountability.current_visit.url || props.focusedStudent.browser_accountability.current_visit.registrable_domain }}
                                    </p>
                                </div>
                                <p class="shrink-0 text-xs text-stone-500 sm:text-right">
                                    {{ props.focusedStudent.browser_accountability.current_visit.mode }} · {{ props.focusedStudent.browser_accountability.current_visit.visited_at_label ?? 'Unknown time' }}
                                </p>
                            </div>
                            <p v-else class="mt-2 text-sm text-stone-500">No website logged yet.</p>
                        </div>

                        <div v-if="!historyCollapsed" class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-500">Websites</h3>
                                <ul class="mt-3 space-y-1.5 text-sm text-stone-700">
                                    <li v-for="visit in props.focusedStudent.browser_accountability.recent_visits" :key="visit.id" class="rounded-xl bg-stone-100 px-3 py-2">
                                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                                            <div class="min-w-0">
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <p class="truncate font-semibold text-stone-950">{{ visit.host }}</p>
                                                    <span
                                                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]"
                                                        :class="visit.decision === 'blocked' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                                                    >
                                                        {{ visit.decision }}
                                                    </span>
                                                </div>
                                                <p class="mt-0.5 truncate text-xs text-stone-500">{{ visit.display_url || visit.url || visit.registrable_domain }}</p>
                                            </div>
                                            <p class="shrink-0 text-xs text-stone-500 sm:text-right">
                                                {{ visit.mode }} · {{ visit.visited_at_label ?? 'Unknown time' }}
                                            </p>
                                        </div>
                                        <div class="mt-2 flex justify-end">
                                            <button
                                                type="button"
                                                class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:text-rose-800"
                                                @click="deleteBrowserHistoryLog(visit)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </li>
                                    <li v-if="props.focusedStudent.browser_accountability.recent_visits.length === 0" class="text-sm text-stone-500">No websites logged yet.</li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-500">Requests</h3>
                                <ul class="mt-3 space-y-2 text-sm text-stone-700">
                                    <li v-for="request in props.focusedStudent.browser_accountability.access_requests" :key="request.id" class="rounded-xl bg-stone-100 px-3 py-2">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <p class="font-semibold text-stone-950">{{ request.registrable_domain }}</p>
                                            <span class="text-xs uppercase tracking-[0.16em]" :class="request.status === 'pending' ? 'text-amber-800' : request.status === 'approved' ? 'text-emerald-700' : 'text-rose-700'">{{ request.status }}</span>
                                        </div>
                                        <p class="mt-1 break-all text-xs text-stone-500">{{ request.display_url || request.requested_url }}</p>
                                        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs text-stone-500">{{ request.created_at_label ?? 'Unknown time' }}</p>
                                            <button
                                                type="button"
                                                class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500 transition hover:text-rose-700"
                                                @click="deleteBrowserRequest(request)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </li>
                                    <li v-if="props.focusedStudent.browser_accountability.access_requests.length === 0" class="text-sm text-stone-500">No access requests yet.</li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else class="rounded-[2rem] bg-white px-6 py-12 text-center shadow-sm ring-1 ring-stone-200">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">Select a student</p>
                    <p class="mt-4 text-sm text-stone-600">Choose a student to see extension configuration, browsing history, and attention data.</p>
                </div>
            </div>

            <section class="mt-6 rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">Per-task whitelists</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="task in props.task_allowlists" :key="task.id" class="rounded-[1.25rem] bg-stone-100 p-4">
                        <p class="font-semibold text-stone-950">{{ task.title }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-for="domain in task.domains" :key="domain" class="rounded-full bg-white px-3 py-1 text-xs text-stone-700 ring-1 ring-stone-200">
                                {{ domain }}
                            </span>
                        </div>
                    </div>
                    <p v-if="props.task_allowlists.length === 0" class="text-sm text-stone-500">No task allowlists configured yet.</p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
