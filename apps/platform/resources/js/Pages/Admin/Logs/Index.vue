<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{
    filters: {
        student_id?: number | null;
        category?: string | null;
        action?: string | null;
        search?: string | null;
        date_from?: string | null;
        date_to?: string | null;
    };
    students: Array<{
        id: number;
        display_name: string;
        username: string;
    }>;
    categories: string[];
    actions: string[];
    logs: {
        data: Array<{
            id: number;
            occurred_at_label?: string | null;
            category: string;
            action: string;
            description: string;
            student?: {
                id: number;
                display_name: string;
                username: string;
            } | null;
            actor?: {
                id: number;
                name?: string | null;
                username: string;
            } | null;
            subject: {
                type?: string | null;
                id?: number | null;
            };
            metadata: Record<string, unknown>;
        }>;
        links: Array<{
            url?: string | null;
            label: string;
            active: boolean;
        }>;
    };
}>();

const form = reactive({
    student_id: props.filters.student_id ? String(props.filters.student_id) : '',
    category: props.filters.category ?? '',
    action: props.filters.action ?? '',
    search: props.filters.search ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const applyFilters = () => {
    router.get(route('admin.logs.index'), {
        student_id: form.student_id || undefined,
        category: form.category || undefined,
        action: form.action || undefined,
        search: form.search || undefined,
        date_from: form.date_from || undefined,
        date_to: form.date_to || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    form.student_id = '';
    form.category = '';
    form.action = '';
    form.search = '';
    form.date_from = '';
    form.date_to = '';
    router.get(route('admin.logs.index'), {}, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
};

const clearVisibleCategory = () => {
    if (!form.category) {
        window.alert('Select a category before clearing logs.');
        return;
    }

    if (!window.confirm(`Clear all ${formatAction(form.category)} logs?`)) {
        return;
    }

    router.delete(route('admin.logs.destroy-category'), {
        data: { category: form.category },
        preserveScroll: true,
    });
};

const formatAction = (value: string) => value.replaceAll('_', ' ');
</script>

<template>
    <Head title="Logs" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[112rem] px-4 py-4 sm:px-6 sm:py-6">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-stone-950">
                        Logs
                    </h1>
                    <p class="mt-1 text-sm text-stone-600">
                        Recent platform actions across students, tasks, assignments, violations, and messages.
                    </p>
                </div>
            </div>

            <form class="mb-4 grid gap-3 rounded-[1.25rem] bg-white p-4 shadow-sm ring-1 ring-stone-200 md:grid-cols-6" @submit.prevent="applyFilters">
                <select
                    v-model="form.student_id"
                    class="rounded-lg border-stone-300 text-sm focus:border-stone-950 focus:ring-stone-950"
                >
                    <option value="">All students</option>
                    <option v-for="student in students" :key="student.id" :value="String(student.id)">
                        {{ student.display_name }}
                    </option>
                </select>
                <select
                    v-model="form.category"
                    class="rounded-lg border-stone-300 text-sm focus:border-stone-950 focus:ring-stone-950"
                >
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category" :value="category">
                        {{ category }}
                    </option>
                </select>
                <select
                    v-model="form.action"
                    class="rounded-lg border-stone-300 text-sm focus:border-stone-950 focus:ring-stone-950"
                >
                    <option value="">All actions</option>
                    <option v-for="action in actions" :key="action" :value="action">
                        {{ formatAction(action) }}
                    </option>
                </select>
                <input
                    v-model="form.search"
                    type="search"
                    class="rounded-lg border-stone-300 text-sm focus:border-stone-950 focus:ring-stone-950"
                    placeholder="Search"
                >
                <div class="grid grid-cols-2 gap-2">
                    <input
                        v-model="form.date_from"
                        type="date"
                        class="min-w-0 rounded-lg border-stone-300 text-sm focus:border-stone-950 focus:ring-stone-950"
                    >
                    <input
                        v-model="form.date_to"
                        type="date"
                        class="min-w-0 rounded-lg border-stone-300 text-sm focus:border-stone-950 focus:ring-stone-950"
                    >
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-stone-950 px-4 py-2 text-sm font-semibold text-white">
                        Filter
                    </button>
                    <button type="button" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700" @click="clearFilters">
                        Reset
                    </button>
                    <button type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!form.category" @click="clearVisibleCategory">
                        Clear
                    </button>
                </div>
            </form>

            <div class="overflow-hidden rounded-[1.25rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-stone-950 text-xs uppercase tracking-[0.16em] text-white">
                            <tr>
                                <th class="px-4 py-3">Time</th>
                                <th class="px-4 py-3">Action</th>
                                <th class="px-4 py-3">Student</th>
                                <th class="px-4 py-3">Actor</th>
                                <th class="px-4 py-3">Details</th>
                                <th class="px-4 py-3">Subject</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id" class="border-b border-stone-100 align-top last:border-0">
                                <td class="whitespace-nowrap px-4 py-3 text-stone-600">
                                    {{ log.occurred_at_label }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-stone-950">{{ formatAction(log.action) }}</div>
                                    <div class="text-xs text-stone-500">{{ log.category }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="log.student">
                                        {{ log.student.display_name }}
                                        <span class="text-stone-500">({{ log.student.username }})</span>
                                    </span>
                                    <span v-else class="text-stone-400">System</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="log.actor">
                                        {{ log.actor.name || log.actor.username }}
                                    </span>
                                    <span v-else class="text-stone-400">System</span>
                                </td>
                                <td class="max-w-xl px-4 py-3 text-stone-700">
                                    {{ log.description }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-stone-500">
                                    {{ log.subject.type || 'Item' }} #{{ log.subject.id || log.id }}
                                </td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-stone-500">
                                    No logs match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.links.length > 3" class="flex flex-wrap gap-2 border-t border-stone-200 p-4">
                    <Link
                        v-for="link in logs.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-lg border px-3 py-1 text-sm"
                        :class="link.active ? 'border-stone-950 bg-stone-950 text-white' : 'border-stone-300 text-stone-700'"
                        preserve-scroll
                    >
                        <span v-html="link.label" />
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
