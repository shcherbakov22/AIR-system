<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    student: {
        display_name: string;
        status: string;
    };
    ruleSummary: {
        total: number;
        global: number;
        personal: number;
    };
    rules: Array<{
        id: number;
        title: string;
        description?: string | null;
        scope: string;
        student?: {
            id: number;
            display_name: string;
            username: string;
        } | null;
        created_at_label?: string | null;
    }>;
}>();
</script>

<template>
    <Head title="Правила" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Портал ученика
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Правила
                    </h2>
                </div>

                <Link
                    :href="route('student.home')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к обзору
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-6 py-10">
            <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                        Актуальные правила
                    </p>
                    <h3 class="font-serif text-3xl text-stone-950">
                        Что важно соблюдать
                    </h3>
                </div>

                <div v-if="rules.length > 0" class="mt-8 divide-y divide-stone-200">
                    <article
                        v-for="rule in rules"
                        :key="rule.id"
                        class="py-5"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h4 class="text-xl font-semibold text-stone-950">
                                    {{ rule.title }}
                                </h4>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        rule.scope === 'student'
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ rule.scope === 'student' ? 'Личное' : 'Общее' }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-7 text-stone-600">
                                {{ rule.description || 'Описание правила пока не добавлено.' }}
                            </p>
                            <p class="mt-3 text-sm text-stone-500">
                                {{
                                    rule.created_at_label
                                        ? `Добавлено ${rule.created_at_label}`
                                        : 'Дата добавления не сохранена.'
                                }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm text-stone-600">
                        Для этой учётной записи наставник пока не добавил активных правил.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
