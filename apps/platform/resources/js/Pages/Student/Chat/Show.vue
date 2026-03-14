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

        <ChatThread
            viewer-role="student"
            :send-route="route('student.chat.store')"
            :messages="studentThread.messages"
        />
    </AuthenticatedLayout>
</template>
