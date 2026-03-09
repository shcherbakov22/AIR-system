<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{
    scheduleTemplates: Array<{
        id: number;
        name: string;
        weekday: {
            value: string;
            label: string;
        };
        is_active: boolean;
        notes?: string | null;
        entries: Array<{
            id: number;
            position: number;
            task_title: string;
            task_summary?: string | null;
            task_instructions?: string | null;
            start_time: string;
            duration_minutes: number;
            notes?: string | null;
            task: {
                title: string;
                summary?: string | null;
                instructions?: string | null;
            };
        }>;
    }>;
}>();

const page = usePage<PageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
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

        <div class="mx-auto max-w-6xl px-6 py-10">
            <div
                v-if="flashSuccess"
                class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="mb-5 rounded-[1.5rem] bg-rose-50 px-6 py-4 text-sm text-rose-800 ring-1 ring-rose-200"
            >
                {{ flashError }}
            </div>

            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-3xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Недельный планировщик
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Соберите порядок, в котором хотите работать
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Задайте одно расписание на каждый день недели, затем добавьте блоки в том
                        порядке, в котором хотите их выполнять. На главной странице ученика можно
                        запускать расписание, проходить блоки по порядку и при необходимости
                        прерываться на собственный таймер.
                    </p>
                </div>

                <div v-if="scheduleTemplates.length === 0" class="mt-8 rounded-[1.75rem] bg-stone-100 px-6 py-8">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Расписаний пока нет
                    </p>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Создайте первый план на день недели, чтобы начать собирать своё расписание.
                    </p>
                    <Link
                        :href="route('student.schedules.create')"
                        class="mt-5 inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Создать расписание
                    </Link>
                </div>

                <div v-else class="mt-8 space-y-5">
                    <article
                        v-for="scheduleTemplate in scheduleTemplates"
                        :key="scheduleTemplate.id"
                        class="rounded-[1.75rem] bg-stone-100 p-6"
                    >
                        <div class="flex flex-col gap-4 border-b border-stone-200 pb-5 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    {{ scheduleTemplate.weekday.label }}
                                </p>
                                <h4 class="mt-2 text-2xl font-semibold text-stone-950">
                                    {{ scheduleTemplate.name }}
                                </h4>
                                <p class="mt-3 text-sm leading-7 text-stone-600">
                                    {{ scheduleTemplate.notes || 'Заметки к расписанию не указаны.' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        scheduleTemplate.is_active
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ scheduleTemplate.is_active ? 'активно' : 'неактивно' }}
                                </span>

                                <Link
                                    :href="route('student.schedules.edit', scheduleTemplate.id)"
                                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                >
                                    Изменить
                                </Link>
                            </div>
                        </div>

                        <div class="mt-5 space-y-4">
                            <article
                                v-for="entry in scheduleTemplate.entries"
                                :key="entry.id"
                                class="grid gap-4 rounded-[1.5rem] bg-white px-5 py-4 md:grid-cols-[0.7fr_1.3fr]"
                            >
                                <div>
                                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                        Блок {{ entry.position }}
                                    </p>
                                    <p class="mt-2 text-lg font-semibold text-stone-950">
                                        {{ entry.start_time }}
                                    </p>
                                    <p class="mt-2 text-sm text-stone-600">
                                        {{ entry.duration_minutes }} минут
                                    </p>
                                </div>

                                <div>
                                    <h5 class="text-lg font-semibold text-stone-950">
                                        {{ entry.task.title }}
                                    </h5>
                                    <p class="mt-2 text-sm leading-6 text-stone-600">
                                        {{ entry.task.summary || 'Описание задания не указано.' }}
                                    </p>
                                    <p class="mt-2 text-sm leading-6 text-stone-600">
                                        {{ entry.notes || 'Заметка для этого блока не указана.' }}
                                    </p>
                                </div>
                            </article>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
