<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    taskAssignments: Array<{
        id: number;
        status: string;
        due_on?: string | null;
        notes?: string | null;
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        task_template: {
            id: number;
            title: string;
            default_duration_minutes: number;
            summary?: string | null;
        };
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
</script>

<template>
    <Head title="Task assignments" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Mentor panel
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Task assignments
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-6 py-10">
            <div
                v-if="successMessage"
                class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ successMessage }}
            </div>

            <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div
                    class="flex flex-col gap-4 border-b border-stone-200 px-6 py-5 md:flex-row md:items-center md:justify-between"
                >
                    <p class="text-sm text-stone-600">
                        Link reusable task templates to specific students.
                    </p>

                    <Link
                        :href="route('admin.task-assignments.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Add assignment
                    </Link>
                </div>

                <div v-if="props.taskAssignments.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="taskAssignment in props.taskAssignments"
                        :key="taskAssignment.id"
                        class="grid gap-4 px-6 py-6 lg:grid-cols-[1fr_1fr_1fr]"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Student
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ taskAssignment.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ taskAssignment.student.username }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Task
                            </p>
                            <h3 class="mt-2 text-lg font-semibold text-stone-950">
                                {{ taskAssignment.task_template.title }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ taskAssignment.task_template.default_duration_minutes }} min
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ taskAssignment.task_template.summary || 'No description provided.' }}
                            </p>
                        </div>

                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    Assignment
                                </p>

                                <Link
                                    :href="route('admin.task-assignments.edit', taskAssignment.id)"
                                    class="inline-flex rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                >
                                    Edit
                                </Link>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        taskAssignment.status === 'assigned'
                                            ? 'bg-amber-100 text-amber-800'
                                            : taskAssignment.status === 'completed'
                                              ? 'bg-emerald-100 text-emerald-800'
                                              : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{
                                        taskAssignment.status === 'assigned'
                                            ? 'assigned'
                                            : taskAssignment.status === 'paused'
                                              ? 'paused'
                                              : 'completed'
                                    }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-stone-600">
                                {{
                                    taskAssignment.due_on
                                        ? `Due: ${taskAssignment.due_on}`
                                        : 'No due date set'
                                }}
                            </p>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{ taskAssignment.notes || 'No assignment notes provided.' }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        No assignments yet
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Assignments will appear here after you link a task
                        template to a student.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
