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
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-stone-950">{{ props.session.student.display_name }} · {{ props.session.device.label }}</h1>
                    <p class="mt-1 text-sm text-stone-500">{{ props.session.status }} · {{ props.session.device.target_host || 'Unknown host' }} · {{ formatDateTime(props.session.started_at) }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="route('admin.students.devices.index', props.session.student.id)"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Back to devices
                    </Link>
                    <button
                        type="button"
                        class="inline-flex rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-700 hover:text-rose-800"
                        @click="endSession"
                    >
                        End session
                    </button>
                </div>
            </div>

            <div v-if="props.session.failure_reason" class="mb-6 rounded-[1.5rem] bg-rose-50 px-6 py-4 text-sm text-rose-800 ring-1 ring-rose-200">
                {{ props.session.failure_reason }}
            </div>

            <div class="overflow-hidden rounded-[2rem] bg-stone-950 ring-1 ring-stone-800">
                <iframe
                    v-if="props.session.viewer_url"
                    :src="props.session.viewer_url"
                    class="h-[80vh] w-full border-0 bg-stone-950"
                    allowfullscreen
                />
                <div v-else class="flex h-[60vh] items-center justify-center px-6 text-sm text-stone-300">
                    No viewer is available for this session.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
