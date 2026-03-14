<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScheduleForm from '@/Pages/Student/Schedules/Partials/ScheduleForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    taskTemplates: Array<{
        id: number;
        title: string;
        instructions?: string | null;
        default_duration_minutes: number;
        is_active: boolean;
    }>;
}>();
</script>

<template>
    <Head title="Create schedule" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-5xl px-5 py-6">
            <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div>
                    <ScheduleForm mode="create" :task-templates="taskTemplates" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
