<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelPenaltyTransactionType } from '@/lib/labels';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    student: {
        display_name: string;
        status: string;
    };
    penaltySummary: {
        current_balance_units: number;
        open_violations: number;
        transaction_count: number;
    };
    violations: Array<{
        id: number;
        status: string;
        rule_title: string;
        penalty_units: number;
        occurred_at_label?: string | null;
        notes?: string | null;
        rule_scope?: string | null;
        posted_units: number;
    }>;
    transactions: Array<{
        id: number;
        type: string;
        delta_units: number;
        notes?: string | null;
        recorded_at_label?: string | null;
        violation?: {
            id: number;
            rule_title: string;
            status: string;
        } | null;
    }>;
}>();
</script>

<template>
    <Head title="Штрафы" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Портал ученика
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Штрафы
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
            <div class="grid gap-5 md:grid-cols-3">
                <section class="rounded-[2rem] bg-amber-50 p-8 shadow-sm ring-1 ring-amber-200">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-800/70">
                        Баланс
                    </p>
                    <h3 class="mt-4 font-serif text-4xl text-stone-950">
                        {{ penaltySummary.current_balance_units }}
                    </h3>
                    <p class="mt-2 text-sm font-semibold text-stone-700">
                        Текущие штрафные единицы
                    </p>
                </section>

                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                        Открытые нарушения
                    </p>
                    <h3 class="mt-4 font-serif text-4xl text-stone-950">
                        {{ penaltySummary.open_violations }}
                    </h3>
                    <p class="mt-2 text-sm text-stone-600">
                        Пункты, которые всё ещё открыты в журнале нарушений.
                    </p>
                </section>

                <section class="rounded-[2rem] bg-stone-950 p-8 text-white shadow-sm">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-300/70">
                        История журнала
                    </p>
                    <h3 class="mt-4 font-serif text-4xl">
                        {{ penaltySummary.transaction_count }}
                    </h3>
                    <p class="mt-2 text-sm text-stone-300">
                        Зафиксированные изменения баланса для {{ student.display_name }}.
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-300">
                        Только администратор может уменьшить или очистить штрафные единицы.
                    </p>
                </section>
            </div>

            <section class="mt-6 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                        Уведомления о нарушениях
                    </p>
                    <h3 class="font-serif text-3xl text-stone-950">
                        Зафиксированные штрафы
                    </h3>
                </div>

                <div v-if="violations.length > 0" class="mt-8 divide-y divide-stone-200">
                    <article
                        v-for="violation in violations"
                        :key="violation.id"
                        class="grid gap-5 py-5 md:grid-cols-[1fr_0.9fr]"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h4 class="text-xl font-semibold text-stone-950">
                                    {{ violation.rule_title }}
                                </h4>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        violation.status === 'open'
                                            ? 'bg-amber-100 text-amber-800'
                                            : violation.status === 'resolved'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ violation.status === 'open' ? 'открыто' : violation.status === 'resolved' ? 'решено' : 'отменено' }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-stone-600">
                                {{ violation.occurred_at_label || 'Время нарушения не сохранено.' }}
                            </p>
                            <p class="mt-2 text-sm leading-7 text-stone-600">
                                {{ violation.notes || 'Заметки о нарушении не указаны.' }}
                            </p>
                        </div>

                        <div class="rounded-[1.5rem] bg-stone-100 px-5 py-4">
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Влияние на штраф
                            </p>
                            <p class="mt-3 text-2xl font-semibold text-stone-950">
                                {{ violation.penalty_units }} ед.
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    violation.rule_scope === 'student'
                                        ? 'Правило для конкретного ученика'
                                        : violation.rule_scope === 'global'
                                            ? 'Глобальное правило'
                                            : 'Область действия правила недоступна'
                                }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    violation.posted_units > 0
                                        ? `Из этого нарушения в журнал было записано ${violation.posted_units} ед.`
                                        : 'Из этого нарушения штрафная проводка в журнал не создавалась.'
                                }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm text-stone-600">
                        Для этой учётной записи нарушения не зафиксированы.
                    </p>
                </div>
            </section>

            <section class="mt-6 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                        История журнала
                    </p>
                    <h3 class="font-serif text-3xl text-stone-950">
                        Изменения баланса
                    </h3>
                </div>

                <div v-if="transactions.length > 0" class="mt-8 divide-y divide-stone-200">
                    <article
                        v-for="transaction in transactions"
                        :key="transaction.id"
                        class="grid gap-5 py-5 md:grid-cols-[0.8fr_0.7fr_1.4fr]"
                    >
                        <div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                :class="
                                    transaction.delta_units >= 0
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-emerald-100 text-emerald-800'
                                "
                            >
                                {{ labelPenaltyTransactionType(transaction.type) }}
                            </span>
                            <p class="mt-3 text-sm text-stone-600">
                                {{ transaction.recorded_at_label || 'Время операции не сохранено.' }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-2xl font-semibold"
                                :class="
                                    transaction.delta_units >= 0
                                        ? 'text-amber-800'
                                        : 'text-emerald-800'
                                "
                            >
                                {{
                                    transaction.delta_units >= 0
                                        ? `+${transaction.delta_units}`
                                        : transaction.delta_units
                                }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                ед.
                            </p>
                        </div>

                        <div>
                            <p class="text-sm leading-7 text-stone-600">
                                {{ transaction.notes || 'Заметки к операции не указаны.' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    transaction.violation
                                        ? `Связано с нарушением: ${transaction.violation.rule_title} (${transaction.violation.status === 'open' ? 'открыто' : transaction.violation.status === 'resolved' ? 'решено' : 'отменено'})`
                                        : 'Ручное изменение журнала администратором'
                                }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm text-stone-600">
                        Изменения баланса пока не зафиксированы.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
