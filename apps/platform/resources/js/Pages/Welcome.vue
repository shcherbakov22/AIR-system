<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
}>();
</script>

<template>
    <Head title="Добро пожаловать" />

    <div class="min-h-screen bg-stone-950 text-stone-100">
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.18),_transparent_30rem),radial-gradient(circle_at_bottom_right,_rgba(234,88,12,0.18),_transparent_24rem)]"
        />

        <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-10">
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <ApplicationLogo class="h-14 w-14 text-amber-300" />
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-amber-200/70">
                            Школьная система
                        </p>
                        <p class="mt-1 text-sm text-stone-300">
                            Идёт современная переработка
                        </p>
                    </div>
                </div>

                <Link
                    v-if="canLogin"
                    :href="$page.props.auth.user ? route('dashboard') : route('login')"
                    class="rounded-full border border-white/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:border-amber-300 hover:text-amber-200"
                >
                    {{ $page.props.auth.user ? 'Открыть панель' : 'Войти' }}
                </Link>
            </header>

            <main class="grid flex-1 items-center gap-10 py-16 lg:grid-cols-[1.15fr_0.85fr]">
                <section>
                    <p class="text-sm uppercase tracking-[0.4em] text-amber-300/80">
                        Базовый этап 1
                    </p>
                    <h1 class="mt-6 max-w-4xl font-serif text-5xl leading-none text-white sm:text-6xl">
                        В новой версии уже есть реальная структура заданий и расписаний, а не только заготовка.
                    </h1>
                    <p class="mt-8 max-w-2xl text-lg leading-8 text-stone-300">
                        Платформа уже включает первые чисто выделенные доменные части: учётные
                        записи администратора и ученика, шаблоны заданий, еженедельные расписания,
                        которыми управляет сам ученик, рабочие сессии, нарушения и учёт штрафов.
                    </p>
                </section>

                <section class="grid gap-4">
                    <article class="rounded-[1.75rem] border border-white/10 bg-white/8 p-6 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-200/75">
                            Уже работает
                        </p>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-stone-200">
                            <li>Вход по имени пользователя</li>
                            <li>Панель администратора, управление учениками, библиотека заданий и правила</li>
                            <li>Расписания, которыми управляет ученик, с пошаговым выполнением</li>
                            <li>Пауза, возобновление и прерывание с собственным таймером</li>
                            <li>Фиксация нарушений с автоматической записью в штрафной журнал</li>
                            <li>Модель пользователей и выполнения на PostgreSQL</li>
                        </ul>
                    </article>

                    <article class="rounded-[1.75rem] border border-white/10 bg-white/8 p-6 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-200/75">
                            Ещё впереди
                        </p>
                        <p class="mt-4 text-sm leading-6 text-stone-300">
                            Впереди ещё мониторинг браузера, автоматическое создание нарушений,
                            интеграция с оборудованием и более поздний перенос старых данных.
                        </p>
                    </article>
                </section>
            </main>
        </div>
    </div>
</template>
