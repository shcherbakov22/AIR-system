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
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Mentor dashboard
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Announcements
                </h2>
            </div>
        </template>
        <ChatThread
            title="Global announcements"
            subtitle="Announcements, files, images, and links visible to every student"
            viewer-role="mentor"
            :send-route="route('admin.announcements.store')"
            :messages="announcementThread.messages"
            empty-message="No announcements yet."
        />
    </AuthenticatedLayout>
</template>
