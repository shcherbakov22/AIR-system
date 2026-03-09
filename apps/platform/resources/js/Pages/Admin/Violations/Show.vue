<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PageProps } from '@/types';
import { labelViolationResolutionAction } from '@/lib/labels';
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    violation: {
        id: number;
        status: string;
        rule_title: string;
        penalty_units: number;
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
        penalty_transaction?: {
            id: number;
            type: string;
            delta_units: number;
            notes?: string | null;
            recorded_at_label?: string | null;
            created_by?: {
                id: number;
                name: string;
                username: string;
            } | null;
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
            } | null;
        } | null;
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
</script>

<template>
    <Head :title="`Нарушение: ${props.violation.rule_title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель администратора
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Проверка нарушения
                    </h2>
                </div>

                <Link
                    :href="route('admin.violations.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к нарушениям
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

            <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <div class="grid gap-6 border-b border-stone-200 pb-8 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Ученик
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
                                Статус
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        props.violation.status === 'open'
                                            ? 'bg-amber-100 text-amber-800'
                                            : props.violation.status === 'resolved'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ props.violation.status === 'open' ? 'открыто' : props.violation.status === 'resolved' ? 'решено' : 'отменено' }}
                                </span>
                                <span
                                    class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700"
                                >
                                    {{ props.violation.penalty_units }} ед.
                                </span>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        props.violation.penalty_transaction
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{
                                        props.violation.penalty_transaction
                                            ? 'запись в журнале есть'
                                            : 'записи в журнале нет'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-6 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Правило
                            </p>
                            <h3 class="mt-2 text-xl font-semibold text-stone-950">
                                {{ props.violation.rule_title }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ props.violation.occurred_at_label || 'Время нарушения не сохранено.' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    props.violation.rule_definition?.scope === 'student'
                                        ? 'Правило для конкретного ученика'
                                        : props.violation.rule_definition?.scope === 'global'
                                            ? 'Глобальное правило'
                                            : 'Правило больше не связано'
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Зафиксированные заметки
                            </p>
                            <p class="mt-2 text-sm leading-7 text-stone-600">
                                {{ props.violation.notes || 'Заметки о нарушении не указаны.' }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Решение
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        {{ props.violation.status === 'open' ? 'Закрыть это нарушение' : 'Нарушение закрыто' }}
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        {{
                            props.violation.status === 'open'
                                ? 'Выберите, нужно ли считать нарушение решённым или отменённым. Это создаст запись аудита и закроет нарушение.'
                                : 'Это нарушение уже закрыто. Ниже показан журнал того, как оно было закрыто.'
                        }}
                    </p>

                    <div class="mt-6 rounded-[1.5rem] bg-stone-100 p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    Штрафная запись
                                </p>
                                <p class="mt-3 text-sm font-semibold text-stone-950">
                                    {{
                                        props.violation.penalty_transaction
                                            ? `В журнал записано ${props.violation.penalty_transaction.delta_units} ед.`
                                            : props.violation.penalty_units > 0
                                                ? 'Для этого нарушения не найдена связанная штрафная проводка.'
                                                : 'Автоматическое начисление не создавалось, потому что размер штрафа равен нулю.'
                                    }}
                                </p>
                                <p class="mt-3 text-sm leading-6 text-stone-600">
                                    {{
                                        props.violation.penalty_transaction?.recorded_at_label ||
                                        'Изменения штрафов отслеживаются отдельно от статуса нарушения.'
                                    }}
                                </p>
                                <p class="mt-2 text-sm leading-6 text-stone-600">
                                    {{
                                        props.violation.penalty_transaction?.notes ||
                                        'Используйте штрафной журнал ученика, чтобы уменьшить или очистить штрафные единицы. Закрытие нарушения само по себе журнал не меняет.'
                                    }}
                                </p>
                            </div>

                            <Link
                                :href="route('admin.penalties.show', props.violation.student.id)"
                                class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Открыть штрафной журнал
                            </Link>
                        </div>
                    </div>

                    <form
                        v-if="props.violation.status === 'open'"
                        class="mt-8"
                        @submit.prevent="submit('resolved')"
                    >
                        <InputLabel for="notes" value="Заметки по решению" />
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
                                Отметить как решённое
                            </PrimaryButton>

                            <button
                                type="button"
                                class="inline-flex justify-center rounded-full bg-stone-950 px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                                :disabled="form.processing"
                                @click="submit('waived')"
                            >
                                Отметить как отменённое
                            </button>
                        </div>
                    </form>

                    <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-sm font-semibold text-stone-950">
                                {{
                                    props.violation.latest_resolution
                                        ? `${labelViolationResolutionAction(props.violation.latest_resolution.action)} ${props.violation.latest_resolution.recorded_at_label || ''}`.trim()
                                        : 'Закрыто без загруженной записи о решении.'
                                }}
                            </p>
                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            {{ props.violation.latest_resolution?.notes || 'Заметки по решению не указаны.' }}
                        </p>
                    </div>
                </section>
            </div>

            <section class="mt-6 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                    Журнал аудита
                </p>
                <h3 class="mt-4 font-serif text-3xl text-stone-950">
                    История решений
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
                                    {{ resolution.action === 'resolved' ? 'решено' : 'отменено' }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-stone-600">
                                {{ resolution.recorded_at_label || 'Время решения не сохранено.' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    resolution.created_by
                                        ? `${resolution.created_by.name} (${resolution.created_by.username})`
                                        : 'Неизвестный администратор'
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm leading-7 text-stone-600">
                                {{ resolution.notes || 'Заметки по решению не указаны.' }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm text-stone-600">
                        Записей о решении пока нет. Первое действие «решить» или «отменить»
                        появится здесь.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
