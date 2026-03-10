<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

defineProps<{
    scheduleTemplates: Array<{
        id: number;
        name: string;
        notes?: string | null;
        entries: Array<{
            id: number;
            position: number;
            task_title: string;
            task_instructions?: string | null;
            duration_minutes: number;
            notes?: string | null;
            task: {
                title: string;
                instructions?: string | null;
            };
        }>;
    }>;
}>();

const page = usePage<PageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const deleteSchedule = (scheduleTemplateId: number, scheduleName: string) => {
    if (!window.confirm(`Удалить расписание "${scheduleName}"? Все его блоки будут удалены.`)) {
        return;
    }

    router.delete(route('student.schedules.destroy', scheduleTemplateId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Мои расписания" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Портал ученика
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Мои расписания
                    </h2>
                </div>

                <Link
                    :href="route('student.schedules.create')"
                    class="inline-flex rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-950 transition hover:bg-amber-400"
                >
                    Новое расписание
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-5 py-8">
            <div
                v-if="flashSuccess"
                class="mb-4 rounded-[1.5rem] bg-emerald-50 px-5 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="mb-4 rounded-[1.5rem] bg-rose-50 px-5 py-3 text-sm text-rose-800 ring-1 ring-rose-200"
            >
                {{ flashError }}
            </div>

            <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-3xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Планировщик
                    </p>
                    <h3 class="mt-3 font-serif text-3xl text-stone-950">
                        Соберите порядок, в котором хотите работать
                    </h3>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Каждое расписание состоит из упорядоченных блоков. Запуск на главной странице идет строго по их порядку.
                    </p>
                </div>

                <div v-if="scheduleTemplates.length === 0" class="mt-6 rounded-[1.5rem] bg-stone-100 px-5 py-6">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Расписаний пока нет
                    </p>
                    <p class="mt-2 text-sm leading-7 text-stone-600">
                        Создайте первый план, чтобы собрать собственную последовательность заданий.
                    </p>
                    <Link
                        :href="route('student.schedules.create')"
                        class="mt-4 inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Создать расписание
                    </Link>
                </div>

                <div v-else class="mt-5 space-y-4">
                    <article
                        v-for="scheduleTemplate in scheduleTemplates"
                        :key="scheduleTemplate.id"
                        class="rounded-[1.5rem] bg-stone-100 p-4"
                    >
                        <div class="flex flex-col gap-3 border-b border-stone-200 pb-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    Расписание
                                </p>
                                <h4 class="mt-2 text-2xl font-semibold text-stone-950">
                                    {{ scheduleTemplate.name }}
                                </h4>
                                <p v-if="scheduleTemplate.notes" class="mt-2 text-sm leading-6 text-stone-600">
                                    {{ scheduleTemplate.notes }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <Link
                                    :href="route('student.schedules.edit', scheduleTemplate.id)"
                                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                >
                                    Изменить
                                </Link>

                                <button
                                    type="button"
                                    class="inline-flex rounded-full border border-rose-200 px-4 py-2 text-sm font-medium text-rose-700 transition hover:border-rose-400 hover:text-rose-800"
                                    @click="deleteSchedule(scheduleTemplate.id, scheduleTemplate.name)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 space-y-2">
                            <article
                                v-for="entry in scheduleTemplate.entries"
                                :key="entry.id"
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-[1rem] bg-white px-3 py-2 text-sm"
                            >
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-stone-500">
                                    Блок {{ entry.position }}
                                </p>
                                <p class="min-w-0 flex-1 truncate font-semibold text-stone-950">
                                    {{ entry.task.title }}
                                </p>
                                <p class="text-sm font-medium text-stone-600">
                                    {{ entry.duration_minutes }} минут
                                </p>
                                <p
                                    v-if="entry.notes"
                                    class="w-full truncate text-xs text-stone-500 md:w-auto md:max-w-[18rem]"
                                >
                                    {{ entry.notes }}
                                </p>
                            </article>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
