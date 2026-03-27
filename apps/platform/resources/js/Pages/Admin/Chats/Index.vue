<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    students: Array<{
        id: number;
        display_name: string;
        username: string;
        latest_message?: {
            body?: string | null;
            created_at_label?: string | null;
            sender_name?: string | null;
            has_attachment: boolean;
        } | null;
    }>;
}>();
</script>

<template>
    <Head title="Chats" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div v-if="students.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="student in students"
                        :key="student.id"
                        class="flex items-center justify-between gap-4 px-6 py-5"
                    >
                        <div class="min-w-0">
                            <h3 class="truncate text-xl font-semibold text-stone-950">
                                {{ student.display_name }}
                            </h3>
                            <p v-if="student.latest_message?.body" class="mt-2 truncate text-sm text-stone-700">
                                {{ student.latest_message.sender_name }}: {{ student.latest_message.body }}
                            </p>
                            <p v-else-if="student.latest_message?.has_attachment" class="mt-2 text-sm text-stone-700">
                                {{ student.latest_message.sender_name }} sent an attachment.
                            </p>
                            <p v-else class="mt-2 text-sm text-stone-500">
                                No conversation yet.
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="mb-3 text-xs text-stone-500">
                                {{ student.latest_message?.created_at_label ?? '' }}
                            </p>
                            <Link
                                :href="route('admin.chats.show', student.id)"
                                class="inline-flex rounded-full bg-stone-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-800"
                            >
                                Open chat
                            </Link>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-10 text-sm text-stone-600">
                    No students available for chat.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
