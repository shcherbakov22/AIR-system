<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    ruleDefinitions: Array<{
        id: number;
        title: string;
        description?: string | null;
        scope: string;
        is_active: boolean;
        student?: {
            id: number;
            display_name: string;
            username: string;
        } | null;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const deleteRuleDefinition = (ruleDefinitionId: number, ruleDefinitionTitle: string) => {
    if (!window.confirm(`Delete rule "${ruleDefinitionTitle}"? Linked violations will remain as history without an active reference to this rule.`)) {
        return;
    }

    router.delete(route('admin.rule-definitions.destroy', ruleDefinitionId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Rules" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Mentor dashboard
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Rules
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
                <div class="flex flex-col gap-4 border-b border-stone-200 px-6 py-5 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-stone-600">
                        Define reusable behavior rules that violations can reference later.
                    </p>

                    <Link
                        :href="route('admin.rule-definitions.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Add rule
                    </Link>
                </div>

                <div v-if="props.ruleDefinitions.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="ruleDefinition in props.ruleDefinitions"
                        :key="ruleDefinition.id"
                        class="grid gap-5 px-6 py-6 lg:grid-cols-[1fr_0.8fr_1fr]"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    Rule
                                </p>

                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <Link
                                        :href="route('admin.rule-definitions.edit', ruleDefinition.id)"
                                        class="inline-flex rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-400 hover:text-rose-800"
                                        @click="deleteRuleDefinition(ruleDefinition.id, ruleDefinition.title)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ ruleDefinition.title }}
                            </h3>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{ ruleDefinition.description || 'No rule description has been added yet.' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Scope
                            </p>
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                {{ ruleDefinition.scope === 'global' ? 'Global' : 'Student-specific' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    ruleDefinition.student
                                        ? `${ruleDefinition.student.display_name} (${ruleDefinition.student.username})`
                                        : 'Applies to all students'
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Status
                            </p>
                            <div class="mt-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        ruleDefinition.is_active
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ ruleDefinition.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        No rules yet
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Create the first rule so future violations point to a clear definition instead of free text.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
