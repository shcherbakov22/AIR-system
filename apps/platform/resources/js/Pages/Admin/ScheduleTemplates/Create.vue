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
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Mentor dashboard
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Create schedule
                    </h2>
                </div>

                <Link
                    :href="route('admin.schedule-templates.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Back to schedules
                </Link>
            </div>
        </template>

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
