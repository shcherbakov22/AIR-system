<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps<{
    session: {
        id: number;
        status: string;
        viewer_url?: string | null;
        failure_reason?: string | null;
        student: {
            display_name: string;
        };
        device: {
            label: string;
        };
    };
}>();

onMounted(() => {
    if (props.session.viewer_url) {
        window.location.replace(props.session.viewer_url);
    }
});
</script>

<template>
    <Head :title="`Remote Control: ${props.session.student.display_name}`" />

    <div class="flex min-h-screen items-center justify-center bg-stone-950 px-6 text-stone-100">
        <div class="w-full max-w-xl rounded-[1.75rem] border border-stone-800 bg-stone-900/90 p-8 shadow-2xl shadow-black/30">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-400">Remote control</p>
            <h1 class="mt-3 text-2xl font-semibold text-white">
                {{ props.session.student.display_name }} · {{ props.session.device.label }}
            </h1>

            <p v-if="props.session.viewer_url" class="mt-4 text-sm text-stone-300">
                Opening the viewer directly so keyboard, mouse, and scaling are handled by noVNC without an embedded iframe.
            </p>
            <p v-else class="mt-4 text-sm text-rose-200">
                {{ props.session.failure_reason || 'No viewer is available for this session.' }}
            </p>

            <a
                v-if="props.session.viewer_url"
                :href="props.session.viewer_url"
                class="mt-6 inline-flex rounded-full border border-stone-500 px-5 py-3 text-sm font-semibold text-white transition hover:border-stone-300"
            >
                Open viewer
            </a>
        </div>
    </div>
</template>
