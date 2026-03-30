<script setup lang="ts">
import AssignmentBoard from '@/Components/AssignmentBoard.vue';
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
        <div class="mx-auto max-w-[96rem] px-5 py-6">
            <div class="rounded-[1.75rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="border-b border-stone-200 px-5 py-4">
                    <h1 class="text-3xl font-semibold text-stone-950">
                        Assignments
                    </h1>
                    <p class="mt-2 text-sm text-stone-600">
                        Opening this board marks unread assignments as viewed.
                    </p>
                </div>

                <div class="px-5 py-5">
                    <AssignmentBoard
                        :assignments="assignments"
                        mode="student"
                        @start="startAssignment"
                        @hand-in="handInAssignment"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
