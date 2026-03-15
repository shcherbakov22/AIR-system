<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    taskTemplates: Array<{
        id: number;
        title: string;
        instructions?: string | null;
        default_duration_minutes: number;
        requires_internet: boolean;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const deleteTaskTemplate = (taskTemplateId: number, taskTemplateTitle: string) => {
    if (!window.confirm(`Delete template "${taskTemplateTitle}"? This will also remove it from linked schedules and assignments.`)) {
        return;
    }

    router.delete(route('admin.task-templates.destroy', taskTemplateId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Task templates" />

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
                        :href="route('admin.task-templates.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Add template
                    </Link>
                </div>

                <div v-if="props.taskTemplates.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="taskTemplate in props.taskTemplates"
                        :key="taskTemplate.id"
                        class="grid gap-4 px-6 py-6 lg:grid-cols-[1.1fr_0.7fr_1.2fr]"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Task title
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ taskTemplate.title }}
                            </h3>

                            <div class="mt-4 flex flex-wrap gap-3">
                                <Link
                                    :href="route('admin.task-templates.edit', taskTemplate.id)"
                                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                >
                                    Edit template
                                </Link>

                                <button
                                    type="button"
                                    class="inline-flex rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-rose-700 transition hover:border-rose-400 hover:text-rose-800"
                                    @click="deleteTaskTemplate(taskTemplate.id, taskTemplate.title)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Default values
                            </p>
                            <p class="mt-2 text-sm font-semibold text-stone-950">
                                {{ taskTemplate.default_duration_minutes }} min
                            </p>
                            <p class="mt-2 text-xs uppercase tracking-[0.18em] text-stone-500">
                                {{ taskTemplate.requires_internet ? 'Internet allowed' : 'Internet blocked' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Instructions
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ taskTemplate.instructions || 'No task instructions have been added yet.' }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        No task templates yet
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Create the first task template so future student schedules can reference it.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
