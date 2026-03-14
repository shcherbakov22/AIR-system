<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChatThread from '@/Components/ChatThread.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    studentThread: {
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        messages: Array<{
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
        }>;
    };
}>();
</script>

<template>
    <Head title="Chat" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Student portal
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Chat
                </h2>
            </div>
        </template>

        <ChatThread
            title="Mentor chat"
            subtitle="You can send messages, links, images, and attachments here"
            viewer-role="student"
            :send-route="route('student.chat.store')"
            :messages="studentThread.messages"
        />
    </AuthenticatedLayout>
</template>
