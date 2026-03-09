<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelStudentStatus } from '@/lib/labels';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    studentLedgers: Array<{
        student: {
            id: number;
            display_name: string;
            username: string;
            status: string;
            is_active: boolean;
        };
        current_balance_units: number;
        transaction_count: number;
        has_account: boolean;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
</script>

<template>
    <Head title="Штрафы" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Панель администратора
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Штрафной журнал
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
                <div class="border-b border-stone-200 px-6 py-5">
                    <p class="text-sm text-stone-600">
                        У каждого ученика есть штрафной баланс под управлением администратора,
                        который рассчитывается только по операциям журнала. Ученики не могут
                        очищать баланс сами.
                    </p>
                </div>

                <div v-if="props.studentLedgers.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="ledger in props.studentLedgers"
                        :key="ledger.student.id"
                        class="grid gap-5 px-6 py-6 lg:grid-cols-[1fr_0.9fr_0.9fr]"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Ученик
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ ledger.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ ledger.student.username }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        ledger.student.is_active
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ ledger.student.is_active ? 'вход разрешён' : 'вход запрещён' }}
                                </span>
                                <span
                                    class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700"
                                >
                                    {{ labelStudentStatus(ledger.student.status) }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Текущий баланс
                            </p>
                            <p class="mt-2 text-3xl font-semibold text-stone-950">
                                {{ ledger.current_balance_units }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                штрафных ед.
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Журнал
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    ledger.has_account
                                        ? `Записано операций: ${ledger.transaction_count}`
                                        : 'Журнальный счёт ещё не открыт'
                                }}
                            </p>
                            <Link
                                :href="route('admin.penalties.show', ledger.student.id)"
                                class="mt-4 inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Открыть журнал
                            </Link>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        Учеников пока нет
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Сначала создайте учеников, после чего их штрафные журналы можно будет смотреть здесь.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
