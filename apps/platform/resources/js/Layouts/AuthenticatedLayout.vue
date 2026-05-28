<script setup lang="ts">
import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    hideSidebar?: boolean;
    fullWidth?: boolean;
    sidebarDrawer?: boolean;
    disableSidebarToggle?: boolean;
}>(), {
    hideSidebar: false,
    fullWidth: false,
    sidebarDrawer: false,
    disableSidebarToggle: false,
});

const page = usePage<PageProps>();

const user = computed(() => page.props.auth.user!);
const studentSettings = computed(() => user.value.student?.settings ?? null);
const studentNotifications = computed(() => page.props.student_notifications ?? null);
const unreadMentorChat = computed(() => studentNotifications.value?.unread_mentor_chat ?? null);
const mobileNavOpen = ref(false);
let studentNotificationPollInterval: number | null = null;

const notificationStorageKey = computed(() => {
    const studentId = user.value.student?.id;

    return studentId ? `air:last-notified-mentor-chat:${studentId}` : null;
});

const persistNotifiedMentorChatId = (messageId: number) => {
    if (!notificationStorageKey.value) {
        return;
    }

    window.localStorage.setItem(notificationStorageKey.value, String(messageId));
};

const readNotifiedMentorChatId = (): number | null => {
    if (!notificationStorageKey.value) {
        return null;
    }

    const raw = window.localStorage.getItem(notificationStorageKey.value);
    if (!raw) {
        return null;
    }

    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : null;
};

const requestBrowserNotificationPermission = async () => {
    if (!('Notification' in window) || Notification.permission !== 'default') {
        return;
    }

    try {
        await Notification.requestPermission();
    } catch (_error) {
    }
};

const shouldSuppressMentorChatBrowserNotification = (): boolean =>
    route().current('student.chat.*') && document.visibilityState === 'visible';

const showMentorChatBrowserNotification = (message: NonNullable<PageProps['student_notifications']>['unread_mentor_chat']) => {
    if (!message || !('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    if (shouldSuppressMentorChatBrowserNotification()) {
        return;
    }

    const bodyParts = [];

    if (message.body) {
        bodyParts.push(message.body.trim());
    }

    if (message.has_attachment) {
        bodyParts.push('Attachment included.');
    }

    const notification = new Notification(message.sender_name || 'Mentor', {
        body: bodyParts.filter(Boolean).join('\n\n') || 'New mentor message',
        tag: `mentor-chat-${message.id}`,
    });

    notification.onclick = () => {
        window.focus();
        notification.close();

        if (studentNotifications.value?.chat_url) {
            window.location.assign(studentNotifications.value.chat_url);
        }
    };
};

const syncStudentNotifications = () => {
    if (user.value.role !== 'student') {
        return;
    }

    router.reload({
        only: ['student_notifications'],
    });
};

const navItems = computed(() => {
    const items = [
        {
            label: 'Overview',
            href: route('dashboard'),
            external: false,
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
            external: false,
            active: route().current('admin.students.*'),
        });
        items.push({
            label: 'Logs',
            href: route('admin.logs.index'),
            external: false,
            active: route().current('admin.logs.*'),
        });
        items.push({
            label: 'Settings',
            href: route('admin.settings.index'),
            external: false,
            active: route().current('admin.settings.*'),
        });
        items.push({
            label: 'Extension',
            href: route('admin.extension.index'),
            external: false,
            active: route().current('admin.extension.*'),
        });
        items.push({
            label: 'Chats',
            href: route('admin.chats.index'),
            external: false,
            active: route().current('admin.chats.*'),
        });
        items.push({
            label: 'Announcements',
            href: route('admin.announcements.index'),
            external: false,
            active: route().current('admin.announcements.*'),
        });
        items.push({
            label: 'Assignments',
            href: route('admin.assignments.index'),
            external: false,
            active: route().current('admin.assignments.*') || route().current('admin.students.assignments.*'),
        });
        items.push({
            label: 'Tasks',
            href: route('admin.task-templates.index'),
            external: false,
            active: route().current('admin.task-templates.*'),
        });
        items.push({
            label: 'Rules',
            href: route('admin.rule-definitions.index'),
            external: false,
            active: route().current('admin.rule-definitions.*'),
        });
        items.push({
            label: 'Violations',
            href: route('admin.violations.index'),
            external: false,
            active: route().current('admin.violations.*'),
        });
        items.push({
            label: 'AI Overseer',
            href: route('admin.ai-overseer-decisions.index'),
            external: false,
            active: route().current('admin.ai-overseer-decisions.*'),
        });
        items.push({
            label: 'Database',
            href: route('admin.database'),
            external: true,
            active: false,
        });
        items.push({
            label: 'Schedules',
            href: route('admin.schedule-templates.index'),
            external: false,
            active: route().current('admin.schedule-templates.*'),
        });
    } else {
        items.push({
            label: 'Chat',
            href: route('student.chat.show'),
            external: false,
            active: route().current('student.chat.*'),
        });
        items.push({
            label: 'Announcements',
            href: route('student.announcements.show'),
            external: false,
            active: route().current('student.announcements.*'),
        });
        items.push({
            label: 'AI Chat',
            href: route('student.ai-overseer-decisions.index'),
            external: false,
            active: route().current('student.ai-overseer-decisions.*'),
        });
        items.push({
            label: 'Assignments',
            href: route('student.assignments.index'),
            external: false,
            active: route().current('student.assignments.*'),
        });
        items.push({
            label: 'Rules',
            href: route('student.rules.index'),
            external: false,
            active: route().current('student.rules.*'),
        });

        if (studentSettings.value?.can_manage_own_schedule !== false) {
            items.push({
                label: 'Schedules',
                href: route('student.schedules.index'),
                external: false,
                active: route().current('student.schedules.*'),
            });
        }
    }

    items.push({
        label: 'Profile',
        href: route('profile.edit'),
        external: false,
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

onMounted(() => {
    if (user.value.role !== 'student') {
        return;
    }

    void requestBrowserNotificationPermission();

    if (unreadMentorChat.value?.id) {
        if (readNotifiedMentorChatId() === null) {
            persistNotifiedMentorChatId(unreadMentorChat.value.id);
        }
    }

    studentNotificationPollInterval = window.setInterval(syncStudentNotifications, 5000);
});

onBeforeUnmount(() => {
    if (studentNotificationPollInterval !== null) {
        window.clearInterval(studentNotificationPollInterval);
    }
});

watch(
    unreadMentorChat,
    async (message) => {
        if (user.value.role !== 'student' || !message?.id) {
            return;
        }

        const lastNotifiedId = readNotifiedMentorChatId();
        if (lastNotifiedId === null) {
            persistNotifiedMentorChatId(message.id);
            return;
        }

        if (message.id <= lastNotifiedId) {
            return;
        }

        if ('Notification' in window && Notification.permission === 'default') {
            await requestBrowserNotificationPermission();
        }

        showMentorChatBrowserNotification(message);
        persistNotifiedMentorChatId(message.id);
    },
);
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
                    <component
                        v-for="item in navItems"
                        :key="item.label"
                        :is="item.external ? 'a' : Link"
                        :href="item.href"
                        class="flex items-center rounded-[0.9rem] px-3 py-2.5 text-sm font-medium transition"
                        :class="navItemClasses(item.active)"
                        @click="closeMobileNav"
                    >
                        {{ item.label }}
                    </component>
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
                class="fixed left-2 top-1.5 z-30 inline-flex items-center justify-center p-0 text-stone-700 transition disabled:cursor-not-allowed disabled:opacity-40"
                :class="props.sidebarDrawer ? '' : 'lg:hidden'"
                :disabled="props.disableSidebarToggle"
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
