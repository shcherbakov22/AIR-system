<script setup lang="ts">
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    hideSidebar?: boolean;
    fullWidth?: boolean;
    sidebarDrawer?: boolean;
}>(), {
    hideSidebar: false,
    fullWidth: false,
    sidebarDrawer: false,
});

const page = usePage<PageProps>();

const user = computed(() => page.props.auth.user!);
const studentSettings = computed(() => user.value.student?.settings ?? null);
const mobileNavOpen = ref(false);

const navItems = computed(() => {
    const items = [
        {
            label: 'Overview',
            href: route('dashboard'),
            active:
                user.value.role === 'admin'
                    ? route().current('admin.dashboard')
                    : route().current('student.home'),
        },
    ];

    if (user.value.role === 'admin') {
        items.push({
            label: 'Students',
            href: route('admin.students.index'),
            active: route().current('admin.students.*'),
        });
        items.push({
            label: 'Chats',
            href: route('admin.chats.index'),
            active: route().current('admin.chats.*'),
        });
        items.push({
            label: 'Announcements',
            href: route('admin.announcements.index'),
            active: route().current('admin.announcements.*'),
        });
        items.push({
            label: 'Tasks',
            href: route('admin.task-templates.index'),
            active: route().current('admin.task-templates.*'),
        });
        items.push({
            label: 'Rules',
            href: route('admin.rule-definitions.index'),
            active: route().current('admin.rule-definitions.*'),
        });
        items.push({
            label: 'Violations',
            href: route('admin.violations.index'),
            active: route().current('admin.violations.*'),
        });
        items.push({
            label: 'Schedules',
            href: route('admin.schedule-templates.index'),
            active: route().current('admin.schedule-templates.*'),
        });
    } else {
        items.push({
            label: 'Chat',
            href: route('student.chat.show'),
            active: route().current('student.chat.*'),
        });
        items.push({
            label: 'Announcements',
            href: route('student.announcements.show'),
            active: route().current('student.announcements.*'),
        });
        items.push({
            label: 'Rules',
            href: route('student.rules.index'),
            active: route().current('student.rules.*'),
        });

        if (studentSettings.value?.can_manage_own_schedule !== false) {
            items.push({
                label: 'Schedules',
                href: route('student.schedules.index'),
                active: route().current('student.schedules.*'),
            });
        }
    }

    items.push({
        label: 'Profile',
        href: route('profile.edit'),
        active: route().current('profile.*'),
    });

    return items;
});

const navItemClasses = (active: boolean): string =>
    active
        ? 'bg-amber-700 text-white shadow-sm'
        : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900';

const closeMobileNav = () => {
    mobileNavOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-stone-100 text-stone-900">
        <div
            v-if="mobileNavOpen && !props.hideSidebar"
            class="fixed inset-0 z-40 bg-stone-950/45 lg:hidden"
            @click="closeMobileNav"
        />

        <div
            v-if="mobileNavOpen && props.sidebarDrawer && !props.hideSidebar"
            class="fixed inset-0 z-40 hidden bg-stone-950/45 lg:block"
            @click="closeMobileNav"
        />

        <aside
            v-if="!props.hideSidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-[15.5rem] max-w-[82vw] -translate-x-full flex-col border-r border-stone-200 bg-white transition-transform duration-200"
            :class="mobileNavOpen || !props.sidebarDrawer ? 'translate-x-0' : ''"
        >
            <div class="flex items-center justify-end border-b border-stone-200 px-4 py-4 lg:hidden">
                <button
                    type="button"
                    class="rounded-full border border-stone-200 p-2 text-stone-500 transition hover:border-stone-400 hover:text-stone-900"
                    @click="closeMobileNav"
                >
                    <span class="sr-only">Close menu</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M5 5L15 15" stroke-linecap="round" />
                        <path d="M15 5L5 15" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <div class="space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.label"
                        :href="item.href"
                        class="flex items-center rounded-[0.9rem] px-3 py-2.5 text-sm font-medium transition"
                        :class="navItemClasses(item.active)"
                        @click="closeMobileNav"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>

            <div class="border-t border-stone-200 px-3 py-4">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex w-full items-center justify-center rounded-[0.9rem] border border-stone-300 px-3 py-2.5 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-900"
                >
                    Log out
                </Link>
            </div>
        </aside>

        <div class="min-w-0" :class="props.hideSidebar || props.sidebarDrawer ? '' : 'lg:pl-[15.5rem]'">
            <button
                v-if="!props.hideSidebar"
                type="button"
                class="fixed left-3 top-3 z-30 inline-flex items-center justify-center rounded-full bg-white/90 p-2 text-stone-700 shadow-sm ring-1 ring-stone-200 transition hover:text-stone-900 lg:hidden"
                @click="mobileNavOpen = true"
            >
                <span class="sr-only">Open menu</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 6H16" stroke-linecap="round" />
                    <path d="M4 10H16" stroke-linecap="round" />
                    <path d="M4 14H16" stroke-linecap="round" />
                </svg>
            </button>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
