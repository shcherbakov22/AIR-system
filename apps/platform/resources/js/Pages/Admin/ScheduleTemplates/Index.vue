<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    scheduleTemplates: Array<{
        id: number;
        weekday: {
            value: string;
            label: string;
        };
        notes?: string | null;
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        entries: Array<{
            id: number;
            position: number;
            start_time: string;
            end_time: string;
            duration_minutes: number;
            notes?: string | null;
            task_template: {
                id: number;
                title: string;
                summary?: string | null;
            };
        }>;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

function deleteSchedule(scheduleTemplateId: number, studentName: string): void {
    if (!window.confirm(`Delete ${studentName}'s schedule?`)) {
        return;
    }

    router.delete(route('admin.schedule-templates.destroy', scheduleTemplateId));
}
</script>

<template>
    <Head title="Schedules" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-7xl px-6 py-10">
            <div
                v-if="successMessage"
                class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ successMessage }}
            </div>

            <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-4 border-b border-stone-200 px-6 py-5 md:flex-row md:items-center md:justify-end">
                    <Link
                        :href="route('admin.schedule-templates.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Add or replace schedule
                    </Link>
                </div>

                <div v-if="props.scheduleTemplates.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="scheduleTemplate in props.scheduleTemplates"
                        :key="scheduleTemplate.id"
                        class="grid gap-5 px-6 py-6 lg:grid-cols-[1fr_0.8fr_1.2fr]"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">Schedule</p>

                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="route('admin.schedule-templates.edit', scheduleTemplate.id)"
                                        class="inline-flex rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-700 hover:text-rose-800"
                                        @click="deleteSchedule(scheduleTemplate.id, scheduleTemplate.student.display_name)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ scheduleTemplate.weekday.label }}
                            </p>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{ scheduleTemplate.notes || 'No schedule notes were provided.' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Student
                            </p>
                            <h3 class="mt-2 text-xl font-semibold text-stone-950">
                                {{ scheduleTemplate.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ scheduleTemplate.student.username }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Blocks
                            </p>
                            <div class="mt-3 space-y-2">
                                <article
                                    v-for="entry in scheduleTemplate.entries"
                                    :key="entry.id"
                                    class="flex flex-wrap items-center gap-3 rounded-[1rem] bg-stone-100 px-3 py-2 text-sm"
                                >
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        {{ entry.start_time }}-{{ entry.end_time }}
                                    </p>
                                    <h4 class="min-w-0 flex-1 truncate text-sm font-semibold text-stone-950">
                                        {{ entry.task_template.title }}
                                    </h4>
                                    <p class="text-sm font-medium text-stone-600">
                                        {{ entry.duration_minutes }} min
                                    </p>
                                    <p
                                        v-if="entry.notes"
                                        class="w-full truncate text-xs text-stone-500 md:w-auto md:max-w-[18rem]"
                                    >
                                        {{ entry.notes }}
                                    </p>
                                </article>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        No schedules yet
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Create the first weekly schedule so students can see repeating work blocks in their portal.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
