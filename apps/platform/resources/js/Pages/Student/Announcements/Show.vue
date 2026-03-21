<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChatThread from '@/Components/ChatThread.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    announcementThread: {
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
    <Head title="Announcements" />

    <AuthenticatedLayout>

        <ChatThread
            viewer-role="student"
            :messages="announcementThread.messages"
            :read-only="true"
            empty-message="No announcements yet."
        />
    </AuthenticatedLayout>
</template>
