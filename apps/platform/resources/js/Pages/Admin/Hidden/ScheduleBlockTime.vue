<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

type BlockRow = {
    id: number;
    position: number;
    status: string;
    start_time: string | null;
    task_title: string;
    shown_duration_minutes: number;
    shown_duration_seconds: number;
};

const props = defineProps<{
    student_query: string;
    student: {
        id: number;
        display_name: string;
        username: string;
    } | null;
    schedule_run: {
        id: number;
        status: string;
        started_at_label: string | null;
    } | null;
    blocks: BlockRow[];
    status_message?: string | null;
    error_message?: string | null;
}>();

const form = useForm({
    student_username: props.student?.username ?? props.student_query ?? '',
    schedule_run_block_position: '',
    shown_duration_minutes: '',
    shown_duration_seconds: '',
});
</script>

<template>
    <Head title="Hidden Block Time Utility" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <h1 class="text-2xl font-semibold text-stone-950">Schedule block shown-time utility</h1>
                <p class="mt-3 text-sm text-stone-600">
                    Hidden tool: update shown block time using username + block position.
                </p>

                <form method="get" class="mt-6 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Student username</label>
                        <input
                            name="student"
                            :value="props.student_query"
                            class="mt-2 w-56 rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-stone-900 focus:outline-none"
                            placeholder="egor"
                        />
                    </div>
                    <button
                        type="submit"
                        class="inline-flex rounded-full border border-stone-900 bg-stone-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-700"
                    >
                        Load student
                    </button>
                </form>

                <div v-if="props.status_message" class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ props.status_message }}
                </div>
                <div v-if="props.error_message" class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ props.error_message }}
                </div>

                <div v-if="props.student" class="mt-6 rounded-2xl bg-stone-100 p-5 text-sm text-stone-700">
                    <p><span class="font-semibold text-stone-900">Student:</span> {{ props.student.display_name }} ({{ props.student.username }})</p>
                    <p v-if="props.schedule_run" class="mt-1">
                        <span class="font-semibold text-stone-900">Run:</span>
                        #{{ props.schedule_run.id }} / {{ props.schedule_run.status }}
                        <span v-if="props.schedule_run.started_at_label"> / started {{ props.schedule_run.started_at_label }}</span>
                    </p>
                </div>

                <div v-if="props.blocks.length > 0" class="mt-6 overflow-x-auto rounded-2xl border border-stone-200">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs uppercase tracking-[0.18em] text-stone-500">
                            <tr>
                                <th class="px-4 py-3">Block ID</th>
                                <th class="px-4 py-3">Pos</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Task</th>
                                <th class="px-4 py-3">Shown Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="block in props.blocks" :key="block.id">
                                <td class="px-4 py-3 font-mono">{{ block.id }}</td>
                                <td class="px-4 py-3">{{ block.position }}</td>
                                <td class="px-4 py-3">{{ block.status }}</td>
                                <td class="px-4 py-3">{{ block.task_title }}</td>
                                <td class="px-4 py-3">{{ block.shown_duration_minutes }}:{{ String(block.shown_duration_seconds % 60).padStart(2, '0') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    class="mt-8 flex flex-wrap items-end gap-3"
                    @submit.prevent="form.post(route('admin.hidden.schedule-block-time.update'))"
                >
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Student username</label>
                        <input
                            v-model="form.student_username"
                            class="mt-2 w-56 rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-stone-900 focus:outline-none"
                            placeholder="egor"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Block position</label>
                        <input
                            v-model="form.schedule_run_block_position"
                            class="mt-2 w-40 rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-stone-900 focus:outline-none"
                            placeholder="1"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Minutes</label>
                        <input
                            v-model="form.shown_duration_minutes"
                            class="mt-2 w-40 rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-stone-900 focus:outline-none"
                            placeholder="50"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Seconds</label>
                        <input
                            v-model="form.shown_duration_seconds"
                            class="mt-2 w-40 rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-stone-900 focus:outline-none"
                            placeholder="30"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex rounded-full border border-amber-700 bg-amber-700 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-amber-800 disabled:opacity-50"
                    >
                        Update shown time
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
