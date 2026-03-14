<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScheduleTemplateForm from '@/Pages/Admin/ScheduleTemplates/Partials/ScheduleTemplateForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
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
    <Head title="Create schedule" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <ScheduleTemplateForm
                    mode="create"
                    :students="students"
                    :task-templates="taskTemplates"
                    :weekdays="weekdays"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
