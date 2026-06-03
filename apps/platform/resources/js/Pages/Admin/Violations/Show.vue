<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PageProps } from '@/types';
import { labelViolationResolutionAction } from '@/lib/labels';
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    violation: {
        id: number;
        status: string;
        rule_title: string;
        push_up_count: number;
        occurred_at_label?: string | null;
        notes?: string | null;
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        rule_definition?: {
            id: number;
            scope: string;
        } | null;
        latest_resolution?: {
            id: number;
            action: string;
            notes?: string | null;
            recorded_at_label?: string | null;
            created_by?: {
                id: number;
                name: string;
                username: string;
                role?: string | null;
            } | null;
        } | null;
        false_positive_url?: string | null;
    };
    resolutions: Array<{
        id: number;
        action: string;
        notes?: string | null;
        recorded_at_label?: string | null;
        created_by?: {
            id: number;
            name: string;
            username: string;
            role?: string | null;
        } | null;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
const errorMessage = computed(() => page.props.flash?.error ?? null);

const form = useForm({
    action: 'resolved',
    notes: '',
});

const submit = (action: 'resolved' | 'waived') => {
    form.action = action;
    form.patch(route('admin.violations.resolve', props.violation.id));
};

const markFalsePositive = () => {
    if (!props.violation.false_positive_url) {
        return;
    }

    router.patch(props.violation.false_positive_url, {}, {
        preserveScroll: true,
        preserveState: false,
    });
};

const deleteViolation = () => {
    if (!window.confirm(`Delete violation "${props.violation.rule_title}"? Review records linked to it will also be deleted.`)) {
        return;
    }

    router.delete(route('admin.violations.destroy', props.violation.id));
};
</script>

<template>
    <Head :title="`Violation: ${props.violation.rule_title}`" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-6xl px-6 py-10">
            <div
                v-if="successMessage"
                class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ successMessage }}
            </div>

            <div
                v-if="errorMessage"
                class="mb-5 rounded-[1.5rem] bg-rose-50 px-6 py-4 text-sm text-rose-800 ring-1 ring-rose-200"
            >
                {{ errorMessage }}
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <div class="grid gap-6 border-b border-stone-200 pb-8 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Student
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ props.violation.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ props.violation.student.username }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Status
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        props.violation.status === 'open'
                                            ? 'bg-amber-100 text-amber-800'
                                            : props.violation.status === 'resolved'
                                              ? 'bg-emerald-100 text-emerald-800'
                                              : props.violation.status === 'false_positive'
                                                ? 'bg-sky-100 text-sky-800'
                                                : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ props.violation.status === 'open' ? 'Open' : labelViolationResolutionAction(props.violation.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-6 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Rule
                            </p>
                            <h3 class="mt-2 text-xl font-semibold text-stone-950">
                                {{ props.violation.rule_title }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ props.violation.occurred_at_label || 'Violation time was not recorded.' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ props.violation.push_up_count }} push-ups
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    props.violation.rule_definition?.scope === 'student'
                                        ? 'Student-specific rule'
                                        : props.violation.rule_definition?.scope === 'global'
                                          ? 'Global rule'
                                          : 'Rule is no longer linked'
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Recorded notes
                            </p>
                            <p class="mt-2 text-sm leading-7 text-stone-600">
                                {{ props.violation.notes || 'No violation notes were provided.' }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Resolution
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        {{ props.violation.status === 'open' ? 'Close this violation' : 'Violation is closed' }}
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        {{
                            props.violation.status === 'open'
                                ? 'Choose whether this violation should be marked resolved or waived. This creates an audit entry and closes the violation.'
                                : 'This violation is already closed. The audit trail below shows how it was closed.'
                        }}
                    </p>

                    <form
                        v-if="props.violation.status === 'open'"
                        class="mt-8"
                        @submit.prevent="submit('resolved')"
                    >
                        <InputLabel for="notes" value="Resolution notes" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="6"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                        <InputError class="mt-2" :message="form.errors.action" />

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <PrimaryButton
                                type="submit"
                                :disabled="form.processing"
                                class="justify-center rounded-full bg-emerald-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-white hover:bg-emerald-400 focus:bg-emerald-400 active:bg-emerald-600"
                            >
                                Mark as resolved
                            </PrimaryButton>

                            <button
                                type="button"
                                class="inline-flex justify-center rounded-full bg-stone-950 px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                                :disabled="form.processing"
                                @click="submit('waived')"
                            >
                                Mark as waived
                            </button>

                            <button
                                v-if="props.violation.false_positive_url"
                                type="button"
                                class="inline-flex justify-center rounded-full border border-emerald-300 px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-emerald-700 transition hover:border-emerald-500 hover:text-emerald-900"
                                :disabled="form.processing"
                                @click="markFalsePositive"
                            >
                                False positive
                            </button>
                        </div>
                    </form>

                    <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-sm font-semibold text-stone-950">
                            {{
                                props.violation.latest_resolution
                                    ? `${labelViolationResolutionAction(props.violation.latest_resolution.action)} ${props.violation.latest_resolution.recorded_at_label || ''}`.trim()
                                    : 'Closed without a loaded resolution record.'
                            }}
                        </p>
                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            {{ props.violation.latest_resolution?.notes || 'No resolution notes were provided.' }}
                        </p>
                    </div>
                </section>
            </div>

            <section class="mt-6 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                    Audit log
                </p>
                <h3 class="mt-4 font-serif text-3xl text-stone-950">
                    Resolution history
                </h3>

                <div v-if="props.resolutions.length > 0" class="mt-8 divide-y divide-stone-200">
                    <article
                        v-for="resolution in props.resolutions"
                        :key="resolution.id"
                        class="grid gap-4 py-5 md:grid-cols-[0.8fr_1.2fr]"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        resolution.action === 'resolved'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ resolution.action === 'resolved' ? 'Resolved' : 'Waived' }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-stone-600">
                                {{ resolution.recorded_at_label || 'Resolution time was not recorded.' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    resolution.created_by
                                        ? `${resolution.created_by.name} (${resolution.created_by.username})`
                                        : 'Unknown mentor'
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm leading-7 text-stone-600">
                                {{ resolution.notes || 'No resolution notes were provided.' }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm text-stone-600">
                        There are no resolution records yet. The first resolve or waive action will appear here.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
