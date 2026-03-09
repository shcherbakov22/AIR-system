<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PageProps } from '@/types';
import { labelPenaltyTransactionType, labelStudentStatus } from '@/lib/labels';
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    student: {
        id: number;
        display_name: string;
        username: string;
        status: string;
        is_active: boolean;
    };
    penaltyAccount: {
        id: number;
        current_balance_units: number;
        transaction_count: number;
    };
    transactions: Array<{
        id: number;
        type: string;
        delta_units: number;
        recorded_at_label?: string | null;
        notes?: string | null;
        created_by?: {
            id: number;
            name: string;
            username: string;
        } | null;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
const errorMessage = computed(() => page.props.flash?.error ?? null);

const form = useForm({
    transaction_type: 'manual_charge',
    amount_units: '10',
    notes: '',
});

const submit = () => {
    form.post(route('admin.penalties.transactions.store', props.student.id));
};
</script>

<template>
    <Head :title="`Штрафной журнал: ${props.student.display_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель администратора
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Штрафной журнал
                    </h2>
                </div>

                <Link
                    :href="route('admin.penalties.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к штрафам
                </Link>
            </div>
        </template>

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

            <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <div class="grid gap-6 border-b border-stone-200 pb-8 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Ученик
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ props.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ props.student.username }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Текущий баланс
                            </p>
                            <p class="mt-2 text-4xl font-semibold text-stone-950">
                                {{ props.penaltyAccount.current_balance_units }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                штрафных ед.
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-6 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Состояние учётной записи
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    props.student.is_active
                                        ? 'Учётная запись ученика активна.'
                                        : 'Вход ученика отключён.'
                                }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                Статус ученика: {{ labelStudentStatus(props.student.status) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Итоги журнала
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                Записано операций: {{ props.penaltyAccount.transaction_count }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Действие администратора
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Добавить операцию в журнал
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Используйте начисление, чтобы увеличить штрафной баланс, или списание,
                        чтобы уменьшить его. Списание не может увести баланс ниже нуля.
                    </p>

                    <form class="mt-8" @submit.prevent="submit">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <InputLabel for="transaction_type" value="Тип операции" />
                                <select
                                    id="transaction_type"
                                    v-model="form.transaction_type"
                                    class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                >
                                    <option value="manual_charge">
                                        Ручное начисление
                                    </option>
                                    <option value="manual_credit">
                                        Ручное списание
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.transaction_type" />
                            </div>

                            <div>
                                <InputLabel for="amount_units" value="Количество единиц" />
                                <TextInput
                                    id="amount_units"
                                    v-model="form.amount_units"
                                    type="number"
                                    min="1"
                                    max="100000"
                                    class="mt-2 block w-full rounded-xl border-stone-300"
                                />
                                <InputError class="mt-2" :message="form.errors.amount_units" />
                            </div>
                        </div>

                        <div class="mt-6">
                            <InputLabel for="notes" value="Заметки" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="5"
                                class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>

                        <div class="mt-6">
                            <PrimaryButton
                                :disabled="form.processing"
                                class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                            >
                                Провести операцию
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>

            <section class="mt-6 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                    История журнала
                </p>
                <h3 class="mt-4 font-serif text-3xl text-stone-950">
                    Операции
                </h3>

                <div v-if="props.transactions.length > 0" class="mt-8 divide-y divide-stone-200">
                    <article
                        v-for="transaction in props.transactions"
                        :key="transaction.id"
                        class="grid gap-4 py-5 md:grid-cols-[0.8fr_0.7fr_1.5fr]"
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
                                    transaction.delta_units >= 0 ? 'text-amber-800' : 'text-emerald-800'
                                "
                            >
                                {{ transaction.delta_units >= 0 ? `+${transaction.delta_units}` : transaction.delta_units }}
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
                                    transaction.created_by
                                        ? `${transaction.created_by.name} (${transaction.created_by.username})`
                                        : 'Неизвестный администратор'
                                }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm text-stone-600">
                        Штрафных операций пока нет. Проведите первое ручное начисление или списание выше.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
