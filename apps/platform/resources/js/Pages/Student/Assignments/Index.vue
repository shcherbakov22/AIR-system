<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps<{
    assignments: Array<{
        id: number;
        title: string;
        body?: string | null;
        status: 'unread' | 'viewed' | 'in_progress' | 'handed_in' | 'completed';
        created_at_label?: string | null;
        viewed_at_label?: string | null;
        started_at_label?: string | null;
        completed_at_label?: string | null;
        creator_name: string;
        start_url?: string | null;
        hand_in_url?: string | null;
    }>;
}>();

const statusLabel = (status: string): string => {
    if (status === 'in_progress') {
        return 'In progress';
    }

    if (status === 'handed_in') {
        return 'Handed in';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
};

const statusClasses = (status: string): string => {
    if (status === 'unread') {
        return 'bg-sky-100 text-sky-900';
    }

    if (status === 'viewed') {
        return 'bg-stone-200 text-stone-800';
    }

    if (status === 'in_progress') {
        return 'bg-amber-100 text-amber-900';
    }

    if (status === 'handed_in') {
        return 'bg-indigo-100 text-indigo-900';
    }

    return 'bg-emerald-100 text-emerald-900';
};

const startAssignment = (url?: string | null) => {
    if (!url) {
        return;
    }

    router.patch(url, {}, { preserveScroll: true });
};

const handInAssignment = (url?: string | null) => {
    if (!url) {
        return;
    }

    router.patch(url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Assignments" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-5 py-6">
            <div class="rounded-[1.75rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="border-b border-stone-200 px-5 py-4">
                    <h1 class="text-3xl font-semibold text-stone-950">
                        Assignments
                    </h1>
                    <p class="mt-2 text-sm text-stone-600">
                        Opening this page marks unread assignments as viewed.
                    </p>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <article
                        v-for="assignment in assignments"
                        :key="assignment.id"
                        class="rounded-[1.25rem] bg-stone-50 p-4 ring-1 ring-stone-200"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-stone-950">
                                    {{ assignment.title }}
                                </h2>
                                <p class="mt-1 text-sm text-stone-600">
                                    From {{ assignment.creator_name }}<span v-if="assignment.created_at_label">, {{ assignment.created_at_label }}</span>
                                </p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em]"
                                :class="statusClasses(assignment.status)"
                            >
                                {{ statusLabel(assignment.status) }}
                            </span>
                        </div>

                        <p v-if="assignment.body" class="mt-3 whitespace-pre-wrap text-sm leading-6 text-stone-800">
                            {{ assignment.body }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-stone-500">
                            <span v-if="assignment.viewed_at_label">Viewed {{ assignment.viewed_at_label }}</span>
                            <span v-if="assignment.started_at_label">Started {{ assignment.started_at_label }}</span>
                            <span v-if="assignment.completed_at_label">Completed {{ assignment.completed_at_label }}</span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-if="assignment.start_url"
                                type="button"
                                class="rounded-full bg-stone-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-stone-800"
                                @click="startAssignment(assignment.start_url)"
                            >
                                Start
                            </button>
                            <button
                                v-if="assignment.hand_in_url"
                                type="button"
                                class="rounded-full bg-indigo-700 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-indigo-600"
                                @click="handInAssignment(assignment.hand_in_url)"
                            >
                                Hand in
                            </button>
                        </div>
                    </article>

                    <div
                        v-if="assignments.length === 0"
                        class="rounded-[1.25rem] bg-stone-50 px-5 py-6 text-sm text-stone-600 ring-1 ring-stone-200"
                    >
                        No assignments yet.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
