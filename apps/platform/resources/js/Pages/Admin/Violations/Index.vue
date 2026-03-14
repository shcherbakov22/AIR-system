<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelViolationResolutionAction } from '@/lib/labels';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    students: Array<{
        id: number;
        display_name: string;
        username: string;
    }>;
    ruleDefinitions: Array<{
        id: number;
        title: string;
    }>;
    openViolationCounts: Record<string, number>;
    openViolations: Array<{
        id: number;
        status: string;
        rule_title: string;
        occurred_at_label?: string | null;
        notes?: string | null;
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        latest_resolution?: {
            id: number;
            action: string;
            notes?: string | null;
            recorded_at_label?: string | null;
        } | null;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const violationCountFor = (studentId: number, ruleDefinitionId: number) =>
    props.openViolationCounts[`${studentId}:${ruleDefinitionId}`] ?? 0;

const addViolation = (studentId: number, ruleDefinitionId: number) => {
    router.post(route('admin.violations.store'), {
        student_id: studentId,
        rule_definition_id: ruleDefinitionId,
        occurred_at: new Date().toISOString(),
        notes: null,
        toggle: true,
    }, {
        preserveScroll: true,
    });
};

const deleteViolation = (violationId: number, ruleTitle: string) => {
    if (!window.confirm(`Delete violation "${ruleTitle}"? Linked review records will also be deleted.`)) {
        return;
    }

    router.delete(route('admin.violations.destroy', violationId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Violations" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[100rem] px-4 py-4 sm:px-6 sm:py-6">
            <div
                v-if="successMessage"
                class="mb-4 rounded-[1.25rem] bg-emerald-50 px-5 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ successMessage }}
            </div>

            <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="border-b border-stone-200 px-4 py-4 sm:px-5">
                    <h1 class="text-lg font-semibold text-stone-950">
                        Violations matrix
                    </h1>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-stone-950 text-white">
                                <th class="sticky left-0 z-20 border-b border-stone-700 bg-stone-950 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.25em]">
                                    Student
                                </th>
                                <th
                                    v-for="ruleDefinition in props.ruleDefinitions"
                                    :key="ruleDefinition.id"
                                    class="min-w-28 border-b border-l border-stone-700 px-3 py-3 text-center align-bottom text-xs font-semibold uppercase tracking-[0.18em]"
                                >
                                    <span class="line-clamp-3 block whitespace-normal">
                                        {{ ruleDefinition.title }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="student in props.students"
                                :key="student.id"
                                class="odd:bg-stone-50/70"
                            >
                                <th class="sticky left-0 z-10 border-b border-stone-200 bg-inherit px-4 py-3 text-left">
                                    <div class="text-sm font-semibold text-stone-950">
                                        {{ student.username }}
                                    </div>
                                    <div class="text-xs text-stone-500">
                                        {{ student.display_name }}
                                    </div>
                                </th>
                                <td
                                    v-for="ruleDefinition in props.ruleDefinitions"
                                    :key="`${student.id}-${ruleDefinition.id}`"
                                    class="border-b border-l border-stone-200 p-0"
                                >
                                    <template v-if="violationCountFor(student.id, ruleDefinition.id) > 0">
                                        <button
                                            type="button"
                                            class="group flex h-14 w-full items-center justify-center bg-amber-100 px-2 transition hover:bg-amber-200"
                                            @click="addViolation(student.id, ruleDefinition.id)"
                                        >
                                            <span class="text-lg leading-none text-amber-800 transition group-hover:text-amber-950">
                                                ×
                                            </span>
                                        </button>
                                    </template>
                                    <button
                                        v-else
                                        type="button"
                                        class="group flex h-14 w-full items-center justify-center gap-2 px-2 transition hover:bg-amber-50"
                                        @click="addViolation(student.id, ruleDefinition.id)"
                                    >
                                        <span class="text-lg leading-none text-stone-300 transition group-hover:text-amber-700">
                                            +
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5 overflow-hidden rounded-[1.75rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="border-b border-stone-200 px-4 py-4 sm:px-5">
                    <h2 class="text-lg font-semibold text-stone-950">
                        Open violations
                    </h2>
                    <p class="mt-1 text-sm text-stone-600">
                        Review or delete the latest open cases here.
                    </p>
                </div>

                <div v-if="props.openViolations.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="violation in props.openViolations"
                        :key="violation.id"
                        class="flex flex-col gap-4 px-4 py-4 sm:px-5 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <h3 class="text-sm font-semibold text-stone-950">
                                    {{ violation.student.username }}
                                </h3>
                                <span class="text-sm text-stone-400">·</span>
                                <p class="text-sm text-stone-700">
                                    {{ violation.rule_title }}
                                </p>
                                <span class="text-sm text-stone-400">·</span>
                                <p class="text-sm text-stone-500">
                                    {{ violation.occurred_at_label || 'Time not recorded' }}
                                </p>
                            </div>
                            <p
                                v-if="violation.notes"
                                class="mt-2 text-sm leading-6 text-stone-600"
                            >
                                {{ violation.notes }}
                            </p>
                            <p
                                v-if="violation.latest_resolution"
                                class="mt-2 text-sm text-stone-500"
                            >
                                {{
                                    `${labelViolationResolutionAction(violation.latest_resolution.action)} ${violation.latest_resolution.recorded_at_label || ''}`.trim()
                                }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-3">
                            <Link
                                :href="route('admin.violations.show', violation.id)"
                                class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Review
                            </Link>
                            <button
                                type="button"
                                class="inline-flex rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-400 hover:text-rose-800"
                                @click="deleteViolation(violation.id, violation.rule_title)"
                            >
                                Delete
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else class="px-4 py-10 text-center sm:px-5">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        No open violations
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
