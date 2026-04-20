<script setup lang="ts">
import AssignmentBoard from '@/Components/AssignmentBoard.vue';
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
        status: 'unread' | 'viewed' | 'in_progress' | 'handed_in' | 'completed';
        created_at_label?: string | null;
        viewed_at_label?: string | null;
        started_at_label?: string | null;
        completed_at_label?: string | null;
        creator_name: string;
        complete_url?: string | null;
        incomplete_url?: string | null;
        delete_url: string;
    }>;
}>();

const form = useForm({
    student_id: String(props.student.id),
    body: '',
    return_to_student: true,
});

const submit = () => {
    form.post(route('admin.assignments.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
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

const markIncomplete = (url?: string | null) => {
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
        <div class="mx-auto max-w-[112rem] px-3 py-3 sm:px-4 sm:py-4">
            <div class="mb-3">
                <Link :href="route('admin.assignments.index')" class="text-sm font-medium text-stone-700 underline underline-offset-2">
                    Back to assignments
                </Link>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(18rem,0.82fr)_minmax(0,2.18fr)]">
                <section class="rounded-[1.4rem] bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <h1 class="text-2xl font-semibold text-stone-950">
                        {{ student.display_name }}
                    </h1>
                    <p class="mt-1.5 text-sm text-stone-600">
                        {{ student.username }}
                    </p>

                    <form class="mt-4 space-y-3" @submit.prevent="submit">
                        <textarea
                            v-model="form.body"
                            rows="5"
                            class="w-full rounded-[1rem] border-stone-300 px-4 py-3 text-sm shadow-sm focus:border-stone-950 focus:ring-stone-950"
                            placeholder="Assignment"
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

                <section class="rounded-[1.4rem] bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <h2 class="text-xl font-semibold text-stone-950">
                        Assignment board
                    </h2>

                    <div class="mt-4">
                        <AssignmentBoard
                            :assignments="assignments"
                            mode="admin"
                            @complete="completeAssignment"
                            @incomplete="markIncomplete"
                        />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
