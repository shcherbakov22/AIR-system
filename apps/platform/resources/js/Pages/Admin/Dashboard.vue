<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    metrics: {
        students_total: number;
        students_active: number;
        admins_total: number;
        task_templates_total: number;
        task_assignments_total: number;
        task_sessions_total: number;
        task_sessions_active: number;
        schedule_templates_total: number;
    };
}>();
</script>

<template>
    <Head title="Панель администратора" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Панель администратора
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Обзор платформы
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-6 py-10">
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-8">
                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Ученики
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.students_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Активные ученики
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.students_active }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Учётные записи администраторов
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.admins_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Шаблоны заданий
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.task_templates_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Назначения заданий
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.task_assignments_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Сессии заданий
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.task_sessions_total }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Активные сессии
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.task_sessions_active }}
                    </p>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Шаблоны расписаний
                    </p>
                    <p class="mt-4 text-4xl font-semibold text-stone-950">
                        {{ metrics.schedule_templates_total }}
                    </p>
                </article>
            </div>

            <div class="mt-8 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                <section class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Что уже есть
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Первая рабочая база
                    </h3>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-stone-600">
                        В новой версии уже есть защищённый доступ администратора, профили
                        учеников, маршрутизация по ролям, рабочая панель, библиотека шаблонов
                        заданий, расписания, которыми управляет ученик, учёт сессий заданий,
                        правила, нарушения и штрафной журнал под управлением администратора.
                    </p>
                </section>

                <section class="rounded-[2rem] bg-stone-950 p-8 text-white shadow-sm">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-300/70">
                        Контроль работы
                    </p>
                    <h3 class="mt-4 font-serif text-3xl leading-none">
                        Открытые сессии заданий
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-300">
                        Администратор может видеть, что ученики начали, что завершили и какая
                        работа всё ещё активна.
                    </p>

                    <Link
                        :href="route('admin.task-sessions.index')"
                        class="mt-8 inline-flex rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-stone-950 transition hover:bg-amber-300"
                    >
                        Открыть сессии
                    </Link>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
