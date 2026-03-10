<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelViolationResolutionAction } from '@/lib/labels';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    violations: Array<{
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
        rule_definition?: {
            id: number;
            scope: string;
        } | null;
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

const deleteViolation = (violationId: number, ruleTitle: string) => {
    if (!window.confirm(`Удалить нарушение "${ruleTitle}"? Связанные записи разбора тоже будут удалены.`)) {
        return;
    }

    router.delete(route('admin.violations.destroy', violationId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Нарушения" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Панель наставника
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Нарушения
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
                <div
                    class="flex flex-col gap-4 border-b border-stone-200 px-6 py-5 md:flex-row md:items-center md:justify-between"
                >
                    <p class="text-sm text-stone-600">
                        Фиксируйте нарушения учеников и обрабатывайте их через действия «решено» или «отменено».
                    </p>

                    <Link
                        :href="route('admin.violations.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Добавить нарушение
                    </Link>
                </div>

                <div v-if="props.violations.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="violation in props.violations"
                        :key="violation.id"
                        class="grid gap-5 px-6 py-6 lg:grid-cols-[0.9fr_1fr_1fr]"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Ученик
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ violation.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ violation.student.username }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Нарушение
                            </p>
                            <h3 class="mt-2 text-xl font-semibold text-stone-950">
                                {{ violation.rule_title }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ violation.occurred_at_label || 'Время нарушения не сохранено.' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    violation.rule_definition?.scope === 'student'
                                        ? 'Правило для конкретного ученика'
                                        : violation.rule_definition?.scope === 'global'
                                            ? 'Глобальное правило'
                                            : 'Правило больше не связано'
                                }}
                            </p>
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-3">
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
                                    {{ violation.status === 'open' ? 'Открыто' : violation.status === 'resolved' ? 'Решено' : 'Отменено' }}
                                </span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-stone-600">
                                {{ violation.notes || 'Заметки о нарушении не указаны.' }}
                            </p>
                            <p
                                v-if="violation.latest_resolution"
                                class="mt-3 text-sm leading-6 text-stone-600"
                            >
                                {{
                                    `${labelViolationResolutionAction(violation.latest_resolution.action)} ${violation.latest_resolution.recorded_at_label || ''}`.trim()
                                }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <Link
                                    :href="route('admin.violations.show', violation.id)"
                                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                >
                                    Проверить
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-400 hover:text-rose-800"
                                    @click="deleteViolation(violation.id, violation.rule_title)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        Нарушений пока нет
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Создайте первое нарушение после настройки правил, чтобы наставник мог отслеживать открытые случаи и их решения.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
