<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelStudentStatus, labelTaskSessionSourceType } from '@/lib/labels';
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    taskSessions: Array<{
        id: number;
        status: string;
        source_type: string;
        task_title: string;
        task_summary?: string | null;
        context_notes?: string | null;
        completion_notes?: string | null;
        planned_duration_minutes?: number | null;
        started_at_label?: string | null;
        ended_at_label?: string | null;
        duration_label?: string | null;
        student: {
            id: number;
            display_name: string;
            username: string;
            status: string;
            is_active: boolean;
        };
        task_assignment?: {
            id: number;
            status: string;
            due_on?: string | null;
        } | null;
        schedule_run?: {
            id: number;
            name: string;
            weekday_label: string;
        } | null;
        schedule_run_block?: {
            id: number;
            position: number;
            status: string;
        } | null;
        task_template?: {
            id: number;
            title: string;
        } | null;
    }>;
    students: Array<{
        id: number;
        display_name: string;
        username: string;
    }>;
    filters: {
        student_id: string;
        status: string;
    };
    metrics: {
        total: number;
        active: number;
        paused: number;
        completed: number;
    };
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const filters = reactive({
    student_id: props.filters.student_id,
    status: props.filters.status,
});

const applyFilters = () => {
    router.get(
        route('admin.task-sessions.index'),
        {
            student_id: filters.student_id || undefined,
            status: filters.status || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filters.student_id = '';
    filters.status = '';
    applyFilters();
};

const clearForm = useForm({
    filter: 'all',
});

const showClearConfirm = ref(false);

const clearSessions = () => {
    if (!showClearConfirm.value) {
        showClearConfirm.value = true;
        return;
    }

    clearForm.delete(route('admin.task-sessions.destroy-all'), {
        onSuccess: () => {
            showClearConfirm.value = false;
            clearForm.filter = 'all';
        },
    });
};
</script>

<template>
    <Head title="Сессии заданий" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Панель наставника
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Сессии заданий
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

            <div class="grid gap-5 md:grid-cols-3">
                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Всего сессий
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ props.metrics.total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Активные сессии
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ props.metrics.active }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Сессии на паузе
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ props.metrics.paused }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Завершённые сессии
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ props.metrics.completed }}
                    </p>
                </article>
            </div>

            <section class="mt-6 overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="border-b border-stone-200 px-6 py-5">
                    <p class="text-sm text-stone-600">
                        Просматривайте рабочие сессии учеников и фильтруйте список по ученику
                        или текущему статусу сессии.
                    </p>
                </div>

                <form class="grid gap-4 px-6 py-5 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="applyFilters">
                    <label class="flex flex-col gap-2 text-sm font-medium text-stone-700">
                        Ученик
                        <select
                            v-model="filters.student_id"
                            class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900 focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="">
                                Все ученики
                            </option>
                            <option
                                v-for="student in props.students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ student.display_name }} ({{ student.username }})
                            </option>
                        </select>
                    </label>

                    <label class="flex flex-col gap-2 text-sm font-medium text-stone-700">
                        Статус
                        <select
                            v-model="filters.status"
                            class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900 focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="">
                                Любой статус
                            </option>
                            <option value="active">
                                активно
                            </option>
                            <option value="paused">
                                пауза
                            </option>
                            <option value="completed">
                                завершено
                            </option>
                        </select>
                    </label>

                    <div class="flex items-end gap-3">
                        <button
                            type="submit"
                            class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                        >
                            Применить
                        </button>

                        <button
                            type="button"
                            class="inline-flex rounded-full border border-stone-300 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            @click="resetFilters"
                        >
                            Сбросить
                        </button>
                    </div>
                </form>
            </section>

            <section class="mt-6 overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 px-6 py-5">
                    <p class="text-sm text-stone-600">
                        Просматривайте рабочие сессии учеников и фильтруйте список по ученику
                        или текущему статусу сессии.
                    </p>

                    <div class="flex items-center gap-3">
                        <select
                            v-model="clearForm.filter"
                            class="rounded-2xl border border-stone-300 px-4 py-2 text-sm text-stone-900 focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="all">Все сессии</option>
                            <option value="active">Только активные</option>
                            <option value="completed">Только завершённые</option>
                        </select>

                        <button
                            type="button"
                            class="inline-flex rounded-full border border-rose-300 px-5 py-2 text-sm font-semibold uppercase tracking-[0.2em] text-rose-700 transition hover:border-rose-500 hover:bg-rose-50"
                            :disabled="clearForm.processing"
                            @click="clearSessions"
                        >
                            {{ showClearConfirm ? 'Подтвердить удаление' : 'Очистить логи' }}
                        </button>
                    </div>
                </div>

                <form id="clear-form" class="hidden" @submit.prevent="clearSessions"></form>

                <div class="border-b border-stone-200 px-6 py-5 md:hidden">
                    <p class="text-sm text-stone-600">
                        Здесь показаны все зафиксированные старты и остановки для проверки наставником.
                    </p>

                    <Link
                        :href="route('admin.task-assignments.index')"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Открыть назначения
                    </Link>
                </div>

                <div v-if="props.taskSessions.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="taskSession in props.taskSessions"
                        :key="taskSession.id"
                        class="grid gap-5 px-6 py-6 xl:grid-cols-[0.9fr_1fr_1fr_1fr]"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Ученик
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ taskSession.student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ taskSession.student.username }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        taskSession.student.is_active
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ taskSession.student.is_active ? 'вход разрешён' : 'вход запрещён' }}
                                </span>
                                <span
                                    class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700"
                                >
                                    {{ labelStudentStatus(taskSession.student.status) }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Задание
                            </p>
                            <h3 class="mt-2 text-xl font-semibold text-stone-950">
                                {{ taskSession.task_title }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ taskSession.task_summary || 'Описание задания не сохранено.' }}
                            </p>
                            <p class="mt-3 text-sm text-stone-600">
                                {{
                                    taskSession.planned_duration_minutes
                                        ? `Запланировано ${taskSession.planned_duration_minutes} мин.`
                                        : 'Плановая длительность не сохранена'
                                }}
                            </p>
                            <p
                                v-if="taskSession.schedule_run"
                                class="mt-2 text-sm text-stone-600"
                            >
                                {{
                                    `${taskSession.schedule_run.weekday_label}: ${taskSession.schedule_run.name}`
                                }}
                            </p>
                            <p
                                v-if="taskSession.task_assignment?.due_on"
                                class="mt-2 text-sm text-stone-600"
                            >
                                Срок: {{ taskSession.task_assignment.due_on }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Сессия
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        taskSession.status === 'active'
                                            ? 'bg-amber-100 text-amber-800'
                                            : taskSession.status === 'paused'
                                              ? 'bg-stone-200 text-stone-800'
                                              : 'bg-emerald-100 text-emerald-800'
                                    "
                                >
                                    {{
                                        taskSession.status === 'active'
                                            ? 'активна'
                                            : taskSession.status === 'paused'
                                              ? 'пауза'
                                              : 'завершена'
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700"
                                >
                                    {{ labelTaskSessionSourceType(taskSession.source_type) }}
                                </span>
                                <span
                                    v-if="taskSession.task_assignment"
                                    class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700"
                                >
                                    Назначение {{
                                        taskSession.task_assignment.status === 'assigned'
                                            ? 'назначено'
                                            : taskSession.task_assignment.status === 'paused'
                                              ? 'пауза'
                                              : 'завершено'
                                    }}
                                </span>
                                <span
                                    v-if="taskSession.schedule_run_block"
                                    class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700"
                                >
                                    Блок {{ taskSession.schedule_run_block.position }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-stone-600">
                                {{ taskSession.started_at_label || 'Время начала не сохранено' }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{
                                    taskSession.ended_at_label
                                        ? `Завершено: ${taskSession.ended_at_label}`
                                        : 'Ещё активно'
                                }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ taskSession.duration_label || 'Длительность ещё не рассчитана' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Заметки
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ taskSession.context_notes || 'Сохранённых заметок нет.' }}
                            </p>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{
                                    taskSession.completion_notes ||
                                    'Заметка о завершении для этой сессии не записана.'
                                }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-16 text-center">
                    <p class="text-lg font-semibold text-stone-950">
                        Сессий заданий пока нет
                    </p>
                    <p class="mt-3 text-sm leading-6 text-stone-600">
                        Рабочие сессии учеников появятся здесь после того, как ученик начнёт и
                        завершит работу в портале.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
