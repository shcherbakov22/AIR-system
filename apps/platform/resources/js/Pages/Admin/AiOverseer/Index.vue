<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PageProps } from '@/types';
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    decisions: Array<{
        id: number;
        request_type: string;
        status: string;
        decision?: string | null;
        confidence: number;
        student_reason?: string | null;
        student_message?: string | null;
        mentor_summary?: string | null;
        reason?: string | null;
        action_taken?: string | null;
        mentor_notified_at_label?: string | null;
        created_at_label?: string | null;
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        target: {
            violation?: {
                id: number;
                rule_title: string;
                status: string;
            } | null;
            schedule_run_block?: {
                id: number;
                task_title: string;
                status: string;
            } | null;
        };
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const review = (decisionId: number, status: 'approved' | 'denied') => {
    const notes = window.prompt('Mentor notes for this review?') ?? '';

    router.patch(route('admin.ai-overseer-decisions.update', decisionId), {
        status,
        notes,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="AI Overseer" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                v-if="successMessage"
                class="mb-5 rounded-[1.25rem] bg-emerald-50 px-5 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ successMessage }}
            </div>

            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-stone-950">
                    AI Overseer
                </h1>
                <p class="mt-2 text-sm text-stone-600">
                    Review AI decisions, escalations, and actions taken for schedule and violation requests.
                </p>
            </div>

            <div class="space-y-4">
                <article
                    v-for="decision in props.decisions"
                    :key="decision.id"
                    class="rounded-[1.25rem] bg-white p-5 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-stone-500">
                                <span>{{ decision.request_type.replace('_', ' ') }}</span>
                                <span>{{ decision.created_at_label }}</span>
                                <span>{{ decision.confidence }}%</span>
                            </div>
                            <h2 class="mt-2 text-lg font-semibold text-stone-950">
                                {{ decision.student.display_name }}
                                <span class="text-sm font-normal text-stone-500">({{ decision.student.username }})</span>
                            </h2>
                        </div>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]"
                            :class="
                                decision.status === 'approved'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : decision.status === 'denied'
                                      ? 'bg-rose-100 text-rose-800'
                                      : 'bg-amber-100 text-amber-800'
                            "
                        >
                            {{ decision.status.replace('_', ' ') }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">
                                Student request
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-700">
                                {{ decision.student_reason || 'No reason recorded.' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">
                                AI decision
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-700">
                                <strong>{{ decision.decision || 'No decision' }}</strong>
                                <span v-if="decision.action_taken"> - {{ decision.action_taken }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-[0.9rem] bg-stone-50 p-4 text-sm leading-6 text-stone-700">
                        <p>
                            {{ decision.mentor_summary || decision.reason || 'No mentor summary recorded.' }}
                        </p>
                        <p v-if="decision.target.violation" class="mt-2 text-xs text-stone-500">
                            Violation: {{ decision.target.violation.rule_title }} ({{ decision.target.violation.status }})
                        </p>
                        <p v-if="decision.target.schedule_run_block" class="mt-2 text-xs text-stone-500">
                            Schedule block: {{ decision.target.schedule_run_block.task_title }} ({{ decision.target.schedule_run_block.status }})
                        </p>
                        <p v-if="decision.mentor_notified_at_label" class="mt-2 text-xs text-stone-500">
                            Mentor notified: {{ decision.mentor_notified_at_label }}
                        </p>
                    </div>

                    <div
                        v-if="decision.status === 'mentor_review'"
                        class="mt-4 flex flex-wrap gap-3"
                    >
                        <button
                            type="button"
                            class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white hover:bg-emerald-500"
                            @click="review(decision.id, 'approved')"
                        >
                            Approve
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-rose-600 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white hover:bg-rose-500"
                            @click="review(decision.id, 'denied')"
                        >
                            Deny
                        </button>
                    </div>
                </article>

                <div
                    v-if="props.decisions.length === 0"
                    class="rounded-[1.25rem] bg-white p-8 text-sm text-stone-600 shadow-sm ring-1 ring-stone-200"
                >
                    No AI overseer decisions recorded yet.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
