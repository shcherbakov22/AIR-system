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
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Student portal
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Create schedule
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
                        Build a plan from ordered blocks
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Choose tasks from the shared catalog and place them in the order you want to complete them.
                    </p>
                </div>

                <div class="mt-6">
                    <ScheduleForm mode="create" :task-templates="taskTemplates" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
