<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    scheduleTemplates: Array<{
        id: number;
        name: string;
        weekday: {
            value: string;
            label: string;
        };
        is_active: boolean;
        notes?: string | null;
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        entries: Array<{
            id: number;
            position: number;
            start_time: string;
            end_time: string;
            duration_minutes: number;
            notes?: string | null;
            task_template: {
                id: number;
                title: string;
                summary?: string | null;
            };
        }>;
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);
</script>

<template>
    <Head title="Расписания" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Панель наставника
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Расписания
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
                        Еженедельные шаблоны расписания размещают блоки заданий в определенные дни недели для конкретного ученика.
                    </p>

                    <Link
                        :href="route('admin.schedule-templates.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Добавить расписание
                    </Link>
                </div>

                <div v-if="props.scheduleTemplates.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="scheduleTemplate in props.scheduleTemplates"
                        :key="scheduleTemplate.id"
                        class="grid gap-5 px-6 py-6 lg:grid-cols-[1fr_0.8fr_1.2fr]"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                    Расписание
                                </p>

                                <Link
                                    :href="route('admin.schedule-templates.edit', scheduleTemplate.id)"
                                    class="inline-flex rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                >
                                    Изменить
                                </Link>
                            </div>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ scheduleTemplate.name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ scheduleTemplate.weekday.label }}
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        scheduleTemplate.is_active
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ scheduleTemplate.is_active ? 'активно' : 'неактивно' }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{ scheduleTemplate.notes || 'Заметки к расписанию не указаны.' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Ученик
                            </p>
                            <h3 class="mt-2 text-xl font-semibold text-stone-950">
                                {{ scheduleTemplate.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ scheduleTemplate.student.username }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Блоки
                            </p>
                            <div class="mt-3 space-y-2">
                                <article
                                    v-for="entry in scheduleTemplate.entries"
                                    :key="entry.id"
                                    class="flex flex-wrap items-center gap-3 rounded-[1rem] bg-stone-100 px-3 py-2 text-sm"
                                >
                                    <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        {{ entry.start_time }}-{{ entry.end_time }}
                                    </p>
                                    <h4 class="min-w-0 flex-1 truncate text-sm font-semibold text-stone-950">
                                        {{ entry.task_template.title }}
                                    </h4>
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
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        Расписаний пока нет
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Создайте первое еженедельное расписание, чтобы ученики видели повторяющиеся блоки работы в своем портале.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
