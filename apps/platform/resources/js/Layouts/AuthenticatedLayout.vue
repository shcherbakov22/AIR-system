<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();

const user = computed(() => page.props.auth.user!);
const studentSettings = computed(() => user.value.student?.settings ?? null);

const navItems = computed(() => {
    const items = [
        {
            label: 'Обзор',
            href: route('dashboard'),
            active:
                user.value.role === 'admin'
                    ? route().current('admin.dashboard')
                    : route().current('student.home'),
        },
    ];

    if (user.value.role === 'admin') {
        items.push({
            label: 'Ученики',
            href: route('admin.students.index'),
            active: route().current('admin.students.*'),
        });

        items.push({
            label: 'Задания',
            href: route('admin.task-templates.index'),
            active: route().current('admin.task-templates.*'),
        });

        items.push({
            label: 'Правила',
            href: route('admin.rule-definitions.index'),
            active: route().current('admin.rule-definitions.*'),
        });

        items.push({
            label: 'Нарушения',
            href: route('admin.violations.index'),
            active: route().current('admin.violations.*'),
        });

        items.push({
            label: 'Штрафы',
            href: route('admin.penalties.index'),
            active: route().current('admin.penalties.*'),
        });

        items.push({
            label: 'Импорт',
            href: route('admin.imports.index'),
            active: route().current('admin.imports.*'),
        });

        items.push({
            label: 'Назначения',
            href: route('admin.task-assignments.index'),
            active: route().current('admin.task-assignments.*'),
        });

        items.push({
            label: 'Сессии',
            href: route('admin.task-sessions.index'),
            active: route().current('admin.task-sessions.*'),
        });

        items.push({
            label: 'Расписания',
            href: route('admin.schedule-templates.index'),
            active: route().current('admin.schedule-templates.*'),
        });
    } else {
        items.push({
            label: 'Штрафы',
            href: route('student.penalties.index'),
            active: route().current('student.penalties.*'),
        });

        if (studentSettings.value?.can_manage_own_schedule !== false) {
            items.push({
                label: 'Расписания',
                href: route('student.schedules.index'),
                active: route().current('student.schedules.*'),
            });
        }
    }

    items.push({
        label: 'Профиль',
        href: route('profile.edit'),
        active: route().current('profile.*'),
    });

    return items;
});
</script>

<template>
    <div class="min-h-screen bg-stone-100 text-stone-900">
        <nav class="border-b border-stone-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('dashboard')" class="flex items-center gap-3">
                        <ApplicationLogo class="h-11 w-11 text-amber-700" />
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-stone-500">
                                Школьная система
                            </p>
                            <p class="text-sm font-semibold text-stone-900">
                                {{ user.role === 'admin' ? 'Панель администратора' : 'Портал ученика' }}
                            </p>
                        </div>
                    </Link>

                    <span
                        class="hidden rounded-full bg-stone-900 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-stone-100 md:inline-flex"
                    >
                        {{ user.role_label }}
                    </span>
                </div>

                <div class="hidden items-center gap-2 md:flex">
                    <Link
                        v-for="item in navItems"
                        :key="item.label"
                        :href="item.href"
                        class="rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="
                            item.active
                                ? 'bg-amber-700 text-white'
                                : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-stone-900">
                            {{ user.name }}
                        </p>
                        <p class="text-xs uppercase tracking-[0.2em] text-stone-500">
                            {{ user.username }}
                        </p>
                    </div>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-900"
                    >
                        Выйти
                    </Link>
                </div>
            </div>

            <div class="border-t border-stone-200 px-6 py-3 md:hidden">
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="item in navItems"
                        :key="item.label"
                        :href="item.href"
                        class="rounded-full px-3 py-2 text-sm font-medium transition"
                        :class="
                            item.active
                                ? 'bg-amber-700 text-white'
                                : 'bg-white text-stone-600 hover:text-stone-900'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </div>
        </nav>

        <header v-if="$slots.header" class="border-b border-stone-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-8">
                <slot name="header" />
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
