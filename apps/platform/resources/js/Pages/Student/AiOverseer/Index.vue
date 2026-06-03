<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type RequestType = 'skip_task' | 'remove_violation';

const props = defineProps<{
    decisions: Array<{
        id: number;
        request_type: RequestType | string;
        status: string;
        decision: string;
        confidence: number;
        student_reason?: string | null;
        student_message?: string | null;
        reason?: string | null;
        mentor_summary?: string | null;
        target_label?: string | null;
        created_at_label?: string | null;
        messages: Array<{
            id: number;
            sender: string;
            body: string;
            is_final_decision: boolean;
            created_at_label?: string | null;
        }>;
    }>;
    openViolations: Array<{
        id: number;
        rule_title: string;
        push_up_count: number;
        occurred_at_label?: string | null;
    }>;
    activeScheduleRun?: {
        id: number;
        status: string;
        schedule_name: string;
        blocks: Array<{
            id: number;
            position: number;
            status: string;
            task_title: string;
            start_time?: string | null;
            duration_minutes: number;
        }>;
    } | null;
    selectedTarget: {
        ai_overseer_decision_id?: number | null;
        violation_id?: number | null;
        schedule_run_block_id?: number | null;
    };
}>();

const page = usePage<PageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const selected = ref<{
    type: RequestType;
    id: number;
    label: string;
    decisionId?: number | null;
} | null>(null);

const form = useForm({
    intent: 'chat' as 'chat' | 'decide',
    ai_overseer_decision_id: null as number | null,
    request_type: 'remove_violation' as RequestType,
    student_reason: '',
    schedule_run_block_id: null as number | null,
    violation_id: null as number | null,
});

const selectViolation = (violation: (typeof props.openViolations)[number]) => {
    selected.value = {
        type: 'remove_violation',
        id: violation.id,
        label: violation.rule_title,
        decisionId: null,
    };
    form.clearErrors();
    form.ai_overseer_decision_id = null;
    form.request_type = 'remove_violation';
    form.violation_id = violation.id;
    form.schedule_run_block_id = null;
};

const selectScheduleBlock = (block: NonNullable<typeof props.activeScheduleRun>['blocks'][number]) => {
    selected.value = {
        type: 'skip_task',
        id: block.id,
        label: block.task_title,
        decisionId: null,
    };
    form.clearErrors();
    form.ai_overseer_decision_id = null;
    form.request_type = 'skip_task';
    form.violation_id = null;
    form.schedule_run_block_id = block.id;
};

const selectDecision = (decision: (typeof props.decisions)[number]) => {
    selected.value = {
        type: decision.request_type === 'skip_task' ? 'skip_task' : 'remove_violation',
        id: decision.id,
        label: decision.target_label || 'Violation review',
        decisionId: decision.id,
    };
    form.clearErrors();
    form.ai_overseer_decision_id = decision.id;
    form.request_type = decision.request_type === 'skip_task' ? 'skip_task' : 'remove_violation';
    form.schedule_run_block_id = null;
    form.violation_id = null;
};

const visibleDecisions = computed(() => props.decisions);

watch(
    () => props.selectedTarget,
    (target) => {
        if (target?.ai_overseer_decision_id) {
            const decision = visibleDecisions.value.find((item) => item.id === target.ai_overseer_decision_id);
            if (decision) {
                selectDecision(decision);
            }
        }

        if (target?.violation_id) {
            const violation = props.openViolations.find((item) => item.id === target.violation_id);
            if (violation) {
                selectViolation(violation);
            }
        }

        if (target?.schedule_run_block_id) {
            const block = props.activeScheduleRun?.blocks.find((item) => item.id === target.schedule_run_block_id);
            if (block) {
                selectScheduleBlock(block);
            }
        }

    },
    { immediate: true },
);

const activeDecision = computed(() =>
    selected.value?.decisionId
        ? visibleDecisions.value.find((decision) => decision.id === selected.value?.decisionId) ?? null
        : null,
);
const activeMessages = computed(() => {
    if (!activeDecision.value) {
        return [];
    }

    if (activeDecision.value.messages.length > 0) {
        return activeDecision.value.messages;
    }

    return [
        {
            id: activeDecision.value.id * 2,
            sender: 'student',
            body: activeDecision.value.student_reason || 'Asked for a decision.',
            is_final_decision: false,
            created_at_label: activeDecision.value.created_at_label,
        },
        {
            id: activeDecision.value.id * 2 + 1,
            sender: 'assistant',
            body: activeDecision.value.student_message || activeDecision.value.reason || activeDecision.value.decision || 'No response recorded.',
            is_final_decision: true,
            created_at_label: activeDecision.value.created_at_label,
        },
    ];
});
const composerPlaceholder = computed(() =>
    selected.value?.type === 'skip_task'
        ? 'Explain why this schedule task should be safe to skip...'
        : 'Ask about this violation, explain more, or ask for a decision...',
);
const canSubmit = computed(() =>
    selected.value !== null &&
    form.student_reason.trim().length > 0 &&
    !form.processing,
);

const requestLabel = (requestType: string): string => ({
    skip_task: 'Skip request',
    remove_violation: 'Violation review',
}[requestType] ?? requestType);

const statusLabel = (status: string): string => ({
    approved: 'Approved',
    denied: 'Denied',
    mentor_review: 'Mentor review',
}[status] ?? status);

const responseToneClass = (status: string): string => ({
    approved: 'bg-emerald-50 text-emerald-950 ring-emerald-200',
    denied: 'bg-rose-50 text-rose-950 ring-rose-200',
    mentor_review: 'bg-amber-50 text-amber-950 ring-amber-200',
}[status] ?? 'bg-stone-100 text-stone-800 ring-stone-200');

const submit = (intent: 'chat' | 'decide') => {
    if (!canSubmit.value || !selected.value) {
        return;
    }

    form.intent = intent;
    form.student_reason = form.student_reason.trim();
    form.request_type = selected.value.type;
    form.ai_overseer_decision_id = selected.value.decisionId ?? null;
    form.schedule_run_block_id = !selected.value.decisionId && selected.value.type === 'skip_task' ? selected.value.id : null;
    form.violation_id = !selected.value.decisionId && selected.value.type === 'remove_violation' ? selected.value.id : null;

    form.post(route('student.ai-overseer-decisions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('student_reason');
        },
    });
};
</script>

<template>
    <Head title="AI Chat" />

    <AuthenticatedLayout>
        <div class="mx-auto grid max-w-6xl gap-5 p-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="space-y-4">
                <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">
                        Conversations
                    </p>
                    <div class="mt-3 space-y-2">
                        <button
                            v-for="decision in visibleDecisions"
                            :key="decision.id"
                            type="button"
                            class="block w-full rounded-md border px-3 py-2 text-left text-sm transition"
                            :class="selected?.decisionId === decision.id ? 'border-stone-950 bg-stone-950 text-white' : 'border-stone-200 bg-white text-stone-800 hover:border-stone-400'"
                            @click="selectDecision(decision)"
                        >
                            <span class="block font-semibold">{{ decision.target_label || requestLabel(decision.request_type) }}</span>
                            <span class="mt-1 block text-xs opacity-75">
                                {{ statusLabel(decision.status) }}<span v-if="decision.created_at_label">, {{ decision.created_at_label }}</span>
                            </span>
                        </button>
                        <p v-if="visibleDecisions.length === 0" class="text-sm text-stone-500">
                            No AI conversations yet.
                        </p>
                    </div>
                </section>

                <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">
                        Schedule
                    </p>
                    <div v-if="activeScheduleRun" class="mt-3 space-y-2">
                        <button
                            v-for="block in activeScheduleRun.blocks"
                            :key="block.id"
                            type="button"
                            class="block w-full rounded-md border px-3 py-2 text-left text-sm transition"
                            :class="selected?.type === 'skip_task' && selected.id === block.id ? 'border-stone-950 bg-stone-950 text-white' : 'border-stone-200 bg-white text-stone-800 hover:border-stone-400'"
                            @click="selectScheduleBlock(block)"
                        >
                            <span class="block font-semibold">{{ block.task_title }}</span>
                            <span class="mt-1 block text-xs opacity-75">
                                #{{ block.position }}<span v-if="block.start_time">, {{ block.start_time }}</span>, {{ block.duration_minutes }} min
                            </span>
                        </button>
                        <p v-if="activeScheduleRun.blocks.length === 0" class="text-sm text-stone-500">
                            No pending schedule tasks.
                        </p>
                    </div>
                    <p v-else class="mt-3 text-sm text-stone-500">
                        No active schedule.
                    </p>
                </section>

                <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">
                        Violations
                    </p>
                    <div class="mt-3 space-y-2">
                        <button
                            v-for="violation in openViolations"
                            :key="violation.id"
                            type="button"
                            class="block w-full rounded-md border px-3 py-2 text-left text-sm transition"
                            :class="selected?.type === 'remove_violation' && selected.id === violation.id ? 'border-stone-950 bg-stone-950 text-white' : 'border-stone-200 bg-white text-stone-800 hover:border-stone-400'"
                            @click="selectViolation(violation)"
                        >
                            <span class="block font-semibold">{{ violation.rule_title }}</span>
                            <span class="mt-1 block text-xs opacity-75">
                                {{ violation.push_up_count }} push-ups<span v-if="violation.occurred_at_label">, {{ violation.occurred_at_label }}</span>
                            </span>
                        </button>
                        <p v-if="openViolations.length === 0" class="text-sm text-stone-500">
                            No open violations.
                        </p>
                    </div>
                </section>
            </aside>

            <section class="min-h-[calc(100vh-8rem)] rounded-lg bg-white shadow-sm ring-1 ring-stone-200">
                <div class="border-b border-stone-200 p-4">
                    <p class="text-lg font-semibold text-stone-950">AI Chat</p>
                    <p class="mt-1 text-sm text-stone-600">
                        Discuss the situation first. Use Ask for decision only when you want the AI to approve, deny, or send it to a mentor.
                    </p>
                </div>

                <div class="space-y-3 p-4">
                    <div
                        v-if="flashSuccess"
                        class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
                    >
                        {{ flashSuccess }}
                    </div>
                    <div
                        v-if="flashError || form.hasErrors"
                        class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200"
                    >
                        {{ flashError || 'The request was not sent. Check the selected item and message.' }}
                    </div>

                    <div class="max-h-[46vh] min-h-72 space-y-4 overflow-y-auto rounded-lg bg-stone-50 p-4 ring-1 ring-stone-200">
                        <template v-if="activeMessages.length > 0">
                            <div
                                v-for="message in activeMessages"
                                :key="message.id"
                                class="flex"
                                :class="message.sender === 'student' ? 'justify-end' : 'justify-start'"
                            >
                                <div
                                    class="max-w-[82%] rounded-lg px-4 py-3 text-sm ring-1"
                                    :class="message.sender === 'student'
                                        ? 'bg-stone-950 text-white ring-stone-950'
                                        : message.is_final_decision && activeDecision
                                            ? responseToneClass(activeDecision.status)
                                            : 'bg-white text-stone-800 ring-stone-200'"
                                >
                                    <div class="mb-1 flex flex-wrap items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.14em] opacity-70">
                                        <span>{{ message.sender === 'student' ? 'You' : (message.is_final_decision ? 'AI decision' : 'AI') }}</span>
                                        <span v-if="message.created_at_label">{{ message.created_at_label }}</span>
                                        <span v-if="message.is_final_decision && activeDecision">{{ activeDecision.confidence }}%</span>
                                    </div>
                                    <p>{{ message.body }}</p>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-sm text-stone-500">
                            Choose a conversation, violation, or schedule block, then send a message.
                        </p>
                    </div>

                    <form class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-stone-200" @submit.prevent="submit('chat')">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-stone-950">
                                {{ selected ? selected.label : 'Choose an item from the sidebar' }}
                            </p>
                            <p v-if="selected" class="text-xs uppercase tracking-[0.16em] text-stone-500">
                                {{ requestLabel(selected.type) }}
                            </p>
                        </div>
                        <textarea
                            v-model="form.student_reason"
                            rows="4"
                            class="block w-full resize-none rounded-md border-stone-300 text-sm text-stone-950 shadow-sm focus:border-stone-900 focus:ring-stone-900"
                            :placeholder="composerPlaceholder"
                            :disabled="!selected || form.processing"
                        />
                        <InputError :message="form.errors.student_reason" />
                        <InputError :message="form.errors.intent" />
                        <InputError :message="form.errors.ai_overseer_decision_id" />
                        <InputError :message="form.errors.request_type" />
                        <InputError :message="form.errors.schedule_run_block_id" />
                        <InputError :message="form.errors.violation_id" />

                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                type="submit"
                                class="rounded-md border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-800 transition hover:border-stone-950 hover:text-stone-950 disabled:opacity-50"
                                :disabled="!canSubmit"
                            >
                                {{ form.processing && form.intent === 'chat' ? 'Sending...' : 'Send message' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-stone-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:opacity-50"
                                :disabled="!canSubmit"
                                @click="submit('decide')"
                            >
                                {{ form.processing && form.intent === 'decide' ? 'Deciding...' : 'Ask for decision' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
