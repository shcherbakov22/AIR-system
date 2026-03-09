<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    taskTemplates: Array<{
        id: number;
        title: string;
        summary?: string | null;
        instructions?: string | null;
        default_duration_minutes: number;
        is_active: boolean;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
</script>

<template>
    <Head title="Шаблоны заданий" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Панель администратора
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Библиотека заданий
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-6 py-10">
            <div v-if="successMessage" class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
                {{ successMessage }}
            </div>

            <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-4 border-b border-stone-200 px-6 py-5 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-stone-600">
                        Повторно используемые определения заданий для будущих расписаний и назначений.
                    </p>

                    <Link
                        :href="route('admin.task-templates.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Добавить шаблон
                    </Link>
                </div>

                <div v-if="props.taskTemplates.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="taskTemplate in props.taskTemplates"
                        :key="taskTemplate.id"
                        class="grid gap-4 px-6 py-6 lg:grid-cols-[1.1fr_0.8fr_1.1fr]"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Название задания
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ taskTemplate.title }}
                            </h3>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{ taskTemplate.summary || 'Краткое описание пока не добавлено.' }}
                            </p>
                            <Link
                                :href="route('admin.task-templates.edit', taskTemplate.id)"
                                class="mt-4 inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Изменить шаблон
                            </Link>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Значения по умолчанию
                            </p>
                            <p class="mt-2 text-sm font-semibold text-stone-950">
                                {{ taskTemplate.default_duration_minutes }} минут
                            </p>
                            <div class="mt-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        taskTemplate.is_active
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ taskTemplate.is_active ? 'активен' : 'неактивен' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Инструкции
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ taskTemplate.instructions || 'Инструкции к заданию пока не добавлены.' }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        Шаблонов заданий пока нет
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Создайте первый шаблон задания, чтобы будущие расписания учеников могли на него ссылаться.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
