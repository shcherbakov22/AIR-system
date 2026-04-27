<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{
    scheduleTemplates: Array<{
        id: number;
        notes?: string | null;
        entries: Array<{
            id: number;
            position: number;
            task_title: string;
            task_instructions?: string | null;
            duration_minutes: number;
            notes?: string | null;
            task: {
                title: string;
                instructions?: string | null;
            };
        }>;
    }>;
}>();

const page = usePage<PageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
</script>

<template>
    <Head title="My schedule" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-6xl px-5 py-8">
            <div
                v-if="flashSuccess"
                class="mb-4 rounded-[1.5rem] bg-emerald-50 px-5 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="mb-4 rounded-[1.5rem] bg-rose-50 px-5 py-3 text-sm text-rose-800 ring-1 ring-rose-200"
            >
                {{ flashError }}
            </div>

            <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-3 border-b border-stone-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-stone-950">
                            Schedule
                        </h2>
                    </div>

                    <Link
                        :href="route('student.schedules.create')"
                        class="inline-flex items-center justify-center rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Create or replace schedule
                    </Link>
                </div>

                <div v-if="scheduleTemplates.length === 0" class="mt-6 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-stone-500">No schedule yet</p>
                </div>

                <div v-else class="space-y-4">
                    <article
                        v-for="scheduleTemplate in scheduleTemplates"
                        :key="scheduleTemplate.id"
                        class="rounded-[1.5rem] bg-stone-100 p-4"
                    >
                        <div class="flex flex-col gap-3 border-b border-stone-200 pb-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">Schedule</p>
                                <p v-if="scheduleTemplate.notes" class="mt-2 text-sm leading-6 text-stone-600">
                                    {{ scheduleTemplate.notes }}
                                </p>
                            </div>

                            <p class="text-sm text-stone-500">
                                Locked after creation
                            </p>
                        </div>

                        <div class="mt-4 space-y-2">
                            <article
                                v-for="entry in scheduleTemplate.entries"
                                :key="entry.id"
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-[1rem] bg-white px-3 py-2 text-sm"
                            >
                                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">
                                    Block {{ entry.position }}
                                </p>
                                <p class="min-w-0 flex-1 truncate font-semibold text-stone-950">
                                    {{ entry.task.title }}
                                </p>
                                <p class="text-xs text-stone-600">
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
                    </article>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
