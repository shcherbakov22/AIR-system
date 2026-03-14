<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScheduleTemplateForm from '@/Pages/Admin/ScheduleTemplates/Partials/ScheduleTemplateForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    scheduleTemplate: {
        id: number;
        student_id: string;
        name: string;
        weekday: string;
        notes: string;
        entries: Array<{
            task_template_id?: number | null;
            task_title: string;
            task_instructions?: string | null;
            duration_minutes: number;
            notes: string;
        }>;
    };
    students: Array<{
        id: number;
        display_name: string;
        username: string;
        is_active: boolean;
    }>;
    taskTemplates: Array<{
        id: number;
        title: string;
        instructions?: string | null;
        default_duration_minutes: number;
    }>;
    weekdays: Array<{
        value: string;
        label: string;
    }>;
}>();
</script>

<template>
    <Head :title="`Edit: ${scheduleTemplate.name}`" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <ScheduleTemplateForm
                    mode="edit"
                    :schedule-template="scheduleTemplate"
                    :students="students"
                    :task-templates="taskTemplates"
                    :weekdays="weekdays"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
