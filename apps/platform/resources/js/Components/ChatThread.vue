<script setup lang="ts">
import type { PageProps } from '@/types';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

type ChatMessage = {
    id: number;
    body?: string | null;
    created_at_label?: string | null;
    sent_by_role: 'mentor' | 'student';
    sent_by_name: string;
    attachment?: {
        name?: string | null;
        mime?: string | null;
        size?: number | null;
        is_image: boolean;
        url: string;
    } | null;
};

const props = withDefaults(defineProps<{
    title: string;
    subtitle: string;
    viewerRole: 'mentor' | 'student';
    sendRoute?: string | null;
    readOnly?: boolean;
    emptyMessage?: string;
    messages: ChatMessage[];
}>(), {
    sendRoute: null,
    readOnly: false,
    emptyMessage: 'No messages yet.',
});

const page = usePage<PageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const form = useForm<{
    body: string;
    attachment: File | null;
}>({
    body: '',
    attachment: null,
});

let refreshInterval: number | null = null;

const submit = () => {
    if (!props.sendRoute) {
        return;
    }

    form.post(props.sendRoute, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body', 'attachment');
        },
    });
};

const setAttachment = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
};

const formatFileSize = (size?: number | null): string | null => {
    if (!size) {
        return null;
    }

    if (size >= 1024 * 1024) {
        return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    }

    if (size >= 1024) {
        return `${Math.round(size / 1024)} KB`;
    }

    return `${size} B`;
};

const escapeHtml = (value: string): string =>
    value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

const renderMessageBody = (value?: string | null): string => {
    if (!value) {
        return '';
    }

    const escaped = escapeHtml(value);

    return escaped
        .replace(
            /(https?:\/\/[^\s<]+)/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="font-medium text-amber-800 underline underline-offset-2">$1</a>',
        )
        .replaceAll('\n', '<br>');
};

const reloadThread = () => {
    router.reload({
        only: ['studentThread'],
    });
};

onMounted(() => {
    refreshInterval = window.setInterval(reloadThread, 5000);
});

onBeforeUnmount(() => {
    if (refreshInterval !== null) {
        window.clearInterval(refreshInterval);
    }
});
</script>

<template>
    <div class="mx-auto max-w-5xl px-5 py-6">
        <div class="rounded-[1.75rem] bg-white shadow-sm ring-1 ring-stone-200">
            <div class="border-b border-stone-200 px-5 py-4">
                <p class="text-xs uppercase tracking-[0.28em] text-stone-500">
                    {{ subtitle }}
                </p>
                <h2 class="mt-2 text-3xl font-semibold text-stone-950">
                    {{ title }}
                </h2>
            </div>

            <div class="border-b border-stone-200 px-5 py-4">
                <form v-if="!readOnly" class="space-y-3" @submit.prevent="submit">
                    <textarea
                        v-model="form.body"
                        rows="3"
                        class="w-full rounded-[1.1rem] border-stone-300 px-4 py-3 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        placeholder="Write a message..."
                    />

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950">
                            <input
                                type="file"
                                class="hidden"
                                @change="setAttachment"
                            >
                            Attach file
                        </label>

                        <p v-if="form.attachment" class="text-sm text-stone-600">
                            {{ form.attachment.name }}
                        </p>

                        <button
                            type="submit"
                            class="inline-flex rounded-full bg-stone-950 px-5 py-2 text-sm font-semibold text-white transition hover:bg-stone-800"
                            :disabled="form.processing"
                        >
                            Send
                        </button>
                    </div>

                    <p v-if="flashSuccess" class="text-sm text-emerald-700">
                        {{ flashSuccess }}
                    </p>
                    <p v-if="flashError || form.errors.body || form.errors.attachment" class="text-sm text-rose-700">
                        {{ flashError || form.errors.body || form.errors.attachment }}
                    </p>
                </form>
                <div v-else class="text-sm text-stone-600">
                    Announcements are read-only for students.
                </div>
            </div>

            <div class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-5">
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex"
                    :class="message.sent_by_role === viewerRole ? 'justify-end' : 'justify-start'"
                >
                    <article
                        class="max-w-[42rem] rounded-[1.25rem] px-4 py-3 shadow-sm ring-1"
                        :class="
                            message.sent_by_role === viewerRole
                                ? 'bg-amber-50 ring-amber-200'
                                : 'bg-stone-50 ring-stone-200'
                        "
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
                                {{ message.sent_by_name }}
                            </p>
                            <p class="text-xs text-stone-500">
                                {{ message.created_at_label }}
                            </p>
                        </div>

                        <div
                            v-if="message.body"
                            class="mt-2 text-sm leading-6 text-stone-900"
                            v-html="renderMessageBody(message.body)"
                        />

                        <div v-if="message.attachment" class="mt-3">
                            <a
                                :href="message.attachment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 rounded-full border border-stone-300 px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                <span>{{ message.attachment.name || 'Attachment' }}</span>
                                <span v-if="formatFileSize(message.attachment.size)" class="text-xs text-stone-500">
                                    {{ formatFileSize(message.attachment.size) }}
                                </span>
                            </a>

                            <a
                                v-if="message.attachment.is_image"
                                :href="message.attachment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 block overflow-hidden rounded-[1rem] ring-1 ring-stone-200"
                            >
                                <img
                                    :src="message.attachment.url"
                                    :alt="message.attachment.name || 'Chat image'"
                                    class="max-h-80 w-full object-cover"
                                >
                            </a>
                        </div>
                    </article>
                </div>

                <div v-if="messages.length === 0" class="rounded-[1.25rem] bg-stone-50 px-5 py-6 text-sm text-stone-600 ring-1 ring-stone-200">
                    {{ emptyMessage }}
                </div>
            </div>
        </div>
    </div>
</template>
