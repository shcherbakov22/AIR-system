<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    metrics: {
        students_total: number;
        students_active: number;
        admins_total: number;
        task_templates_total: number;
        task_sessions_total: number;
        task_sessions_active: number;
        schedule_templates_total: number;
    };
}>();
</script>

<template>
    <Head title="Mentor dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Mentor dashboard
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Platform overview
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-6 py-10">
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-7">
                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Students
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.students_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Active students
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.students_active }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Mentor accounts
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.admins_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Task templates
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.task_templates_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Task sessions
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.task_sessions_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Active sessions
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.task_sessions_active }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Schedule templates
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.schedule_templates_total }}
                    </p>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
