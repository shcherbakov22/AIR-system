<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScheduleForm from '@/Pages/Student/Schedules/Partials/ScheduleForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    scheduleTemplate: {
        id: number;
        name: string;
        is_active: boolean;
        notes: string;
        entries: Array<{
            task_template_id?: number | null;
            task_title: string;
            task_summary: string;
            task_instructions: string;
            duration_minutes: number;
            notes: string;
        }>;
    };
    taskTemplates: Array<{
        id: number;
        title: string;
        summary?: string | null;
        instructions?: string | null;
        default_duration_minutes: number;
        is_active: boolean;
    }>;
}>();
</script>

<template>
    <Head :title="`Изменение: ${scheduleTemplate.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Портал ученика
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Изменение расписания
                    </h2>
                </div>

                <Link
                    :href="route('student.schedules.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к расписаниям
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-3xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Планировщик
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        {{ scheduleTemplate.name }}
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Обновите порядок блоков и выберите актуальные задания из каталога наставника.
                    </p>
                </div>

                <div class="mt-10">
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
