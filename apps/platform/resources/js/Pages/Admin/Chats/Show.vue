<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChatThread from '@/Components/ChatThread.vue';
import { Head, Link } from '@inertiajs/vue3';

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
    <Head :title="`Chat - ${studentThread.student.display_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Mentor dashboard
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        {{ studentThread.student.display_name }}
                    </h2>
                </div>

                <Link
                    :href="route('admin.chats.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Back to chats
                </Link>
            </div>
        </template>

        <ChatThread
            :title="studentThread.student.display_name"
            :subtitle="`Chat with ${studentThread.student.username}`"
            viewer-role="mentor"
            :send-route="route('admin.chats.store', studentThread.student.id)"
            :messages="studentThread.messages"
        />
    </AuthenticatedLayout>
</template>
