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
            delete_url?: string | null;
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
    <Head :title="`Chat - ${studentThread.student.display_name}`" />

    <AuthenticatedLayout>

        <ChatThread
            viewer-role="mentor"
            :send-route="route('admin.chats.store', studentThread.student.id)"
            :messages="studentThread.messages"
        />
    </AuthenticatedLayout>
</template>
