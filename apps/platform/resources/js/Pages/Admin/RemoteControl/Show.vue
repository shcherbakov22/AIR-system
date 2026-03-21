<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    session: {
        id: number;
        status: string;
        viewer_url?: string | null;
        failure_reason?: string | null;
        started_at?: string | null;
        ended_at?: string | null;
        student: {
            id: number;
            display_name: string;
        };
        device: {
            id: number;
            label: string;
            target_host?: string | null;
        };
    };
}>();

const formatDateTime = (value?: string | null) => value ? new Date(value).toLocaleString() : 'Never';

const endSession = () => {
    router.delete(route('admin.remote-control-sessions.destroy', props.session.id));
};
</script>

<template>
    <Head :title="`Remote Control: ${props.session.student.display_name}`" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-stone-950">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-800 px-4 py-3 text-stone-100">
                <div>
                    <h1 class="text-lg font-semibold">{{ props.session.student.display_name }} · {{ props.session.device.label }}</h1>
                    <p class="mt-1 text-xs text-stone-400">{{ props.session.status }} · {{ props.session.device.target_host || 'Unknown host' }} · {{ formatDateTime(props.session.started_at) }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="route('admin.students.devices.index', props.session.student.id)"
                        class="inline-flex rounded-full border border-stone-600 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-100 transition hover:border-stone-300 hover:text-white"
                    >
                        Back to devices
                    </Link>
                    <button
                        type="button"
                        class="inline-flex rounded-full border border-rose-400 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-200 transition hover:border-rose-300 hover:text-white"
                        @click="endSession"
                    >
                        End session
                    </button>
                </div>
            </div>

            <div v-if="props.session.failure_reason" class="m-4 rounded-[1.5rem] bg-rose-950 px-6 py-4 text-sm text-rose-100 ring-1 ring-rose-800">
                {{ props.session.failure_reason }}
            </div>

            <div class="h-[calc(100vh-4.5rem)] overflow-hidden bg-black">
                <iframe
                    v-if="props.session.viewer_url"
                    :src="props.session.viewer_url"
                    class="h-full w-full border-0 bg-black"
                    allowfullscreen
                />
                <div v-else class="flex h-full items-center justify-center px-6 text-sm text-stone-300">
                    No viewer is available for this session.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
