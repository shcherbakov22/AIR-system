<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScheduleForm from '@/Pages/Student/Schedules/Partials/ScheduleForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    scheduleTemplate: {
        id: number;
        notes: string;
        entries: Array<{
            task_template_id?: number | null;
            task_title: string;
            task_instructions: string;
            duration_minutes: number;
            notes: string;
        }>;
    };
    taskTemplates: Array<{
        id: number;
        title: string;
        instructions?: string | null;
        default_duration_minutes: number;
    }>;
}>();
</script>

<template>
    <Head title="Edit schedule" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-5xl px-5 py-6">
            <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div>
                    <ScheduleForm
                        mode="edit"
                        :schedule-template="scheduleTemplate"
                        :task-templates="taskTemplates"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
