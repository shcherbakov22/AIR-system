<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScheduleForm from '@/Pages/Student/Schedules/Partials/ScheduleForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    scheduleTemplate: {
        id: number;
        name: string;
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
    <Head :title="`Edit: ${scheduleTemplate.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Student portal
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Edit schedule
                    </h2>
                </div>

                <Link
                    :href="route('student.schedules.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Back to schedules
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-5 py-6">
            <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-3xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Planner
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        {{ scheduleTemplate.name }}
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Update the block order and choose the current tasks from the mentor catalog.
                    </p>
                </div>

                <div class="mt-6">
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
