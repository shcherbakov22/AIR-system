<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    student: {
        id: number;
        display_name: string;
        username: string;
    };
    assignments: Array<{
        id: number;
        title: string;
        body?: string | null;
        status: string;
        created_at_label?: string | null;
        viewed_at_label?: string | null;
        started_at_label?: string | null;
        completed_at_label?: string | null;
        creator_name: string;
        complete_url?: string | null;
        delete_url: string;
    }>;
}>();

const form = useForm({
    student_id: String(props.student.id),
    title: '',
    body: '',
    return_to_student: true,
});

const submit = () => {
    form.post(route('admin.assignments.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'body'),
    });
};

const statusClasses = (status: string): string => {
    if (status === 'unread') return 'bg-sky-100 text-sky-900';
    if (status === 'viewed') return 'bg-stone-200 text-stone-800';
    if (status === 'in_progress') return 'bg-amber-100 text-amber-900';
    if (status === 'handed_in') return 'bg-indigo-100 text-indigo-900';
    return 'bg-emerald-100 text-emerald-900';
};

const completeAssignment = (url?: string | null) => {
    if (!url) {
        return;
    }

    router.patch(url, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head :title="`Assignments - ${student.display_name}`" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-5 py-6">
            <div class="mb-4">
                <Link :href="route('admin.assignments.index')" class="text-sm font-medium text-stone-700 underline underline-offset-2">
                    Back to assignments
                </Link>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.45fr)]">
                <section class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <h1 class="text-3xl font-semibold text-stone-950">
                        {{ student.display_name }}
                    </h1>
                    <p class="mt-2 text-sm text-stone-600">
                        {{ student.username }}
                    </p>

                    <form class="mt-5 space-y-3" @submit.prevent="submit">
                        <input
                            v-model="form.title"
                            type="text"
                            class="w-full rounded-[1rem] border-stone-300 px-4 py-3 text-sm shadow-sm focus:border-stone-950 focus:ring-stone-950"
                            placeholder="Assignment title"
                        >
                        <textarea
                            v-model="form.body"
                            rows="5"
                            class="w-full rounded-[1rem] border-stone-300 px-4 py-3 text-sm shadow-sm focus:border-stone-950 focus:ring-stone-950"
                            placeholder="Details"
                        />
                        <button
                            type="submit"
                            class="rounded-full bg-stone-950 px-5 py-2 text-sm font-semibold text-white transition hover:bg-stone-800"
                            :disabled="form.processing"
                        >
                            Create assignment
                        </button>
                    </form>
                </section>

                <section class="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <h2 class="text-2xl font-semibold text-stone-950">
                        Student assignments
                    </h2>

                    <div class="mt-5 space-y-4">
                        <article
                            v-for="assignment in assignments"
                            :key="assignment.id"
                            class="rounded-[1.25rem] bg-stone-50 p-4 ring-1 ring-stone-200"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold text-stone-950">
                                        {{ assignment.title }}
                                    </h3>
                                    <p class="mt-1 text-sm text-stone-600">
                                        Created by {{ assignment.creator_name }}<span v-if="assignment.created_at_label">, {{ assignment.created_at_label }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em]" :class="statusClasses(assignment.status)">
                                        {{ assignment.status }}
                                    </span>
                                    <button
                                        v-if="assignment.complete_url"
                                        type="button"
                                        class="rounded-full bg-emerald-700 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-white transition hover:bg-emerald-600"
                                        @click="completeAssignment(assignment.complete_url)"
                                    >
                                        Mark complete
                                    </button>
                                    <Link
                                        :href="assignment.delete_url"
                                        method="delete"
                                        as="button"
                                        class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    >
                                        Delete
                                    </Link>
                                </div>
                            </div>

                            <p v-if="assignment.body" class="mt-3 whitespace-pre-wrap text-sm leading-6 text-stone-800">
                                {{ assignment.body }}
                            </p>

                            <div class="mt-3 flex flex-wrap gap-3 text-xs text-stone-500">
                                <span v-if="assignment.viewed_at_label">Viewed {{ assignment.viewed_at_label }}</span>
                                <span v-if="assignment.started_at_label">Started {{ assignment.started_at_label }}</span>
                                <span v-if="assignment.completed_at_label">Completed {{ assignment.completed_at_label }}</span>
                            </div>
                        </article>

                        <div
                            v-if="assignments.length === 0"
                            class="rounded-[1.25rem] bg-stone-50 px-5 py-6 text-sm text-stone-600 ring-1 ring-stone-200"
                        >
                            No assignments yet.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
