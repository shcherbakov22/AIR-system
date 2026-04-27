<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type AssignmentStatus = 'unread' | 'viewed' | 'in_progress' | 'handed_in' | 'completed';

type Assignment = {
    id: number;
    title: string;
    body?: string | null;
    attachment?: {
        name?: string | null;
        mime?: string | null;
        size?: number | null;
        url: string;
    } | null;
    status: AssignmentStatus;
    created_at_label?: string | null;
    viewed_at_label?: string | null;
    started_at_label?: string | null;
    completed_at_label?: string | null;
    creator_name: string;
    student?: {
        id: number;
        display_name: string;
        username: string;
    };
    start_url?: string | null;
    hand_in_url?: string | null;
    complete_url?: string | null;
    incomplete_url?: string | null;
    delete_url?: string | null;
    student_view_url?: string | null;
};

const props = withDefaults(defineProps<{
    assignments: Assignment[];
    mode?: 'student' | 'admin';
}>(), {
    mode: 'student',
});

const emit = defineEmits<{
    start: [url?: string | null];
    handIn: [url?: string | null];
    complete: [url?: string | null];
    incomplete: [url?: string | null];
}>();

const columns: Array<{ key: AssignmentStatus; label: string; tone: string }> = [
    { key: 'unread', label: 'To do', tone: 'border-sky-200 bg-sky-50/70' },
    { key: 'viewed', label: 'Ready', tone: 'border-stone-200 bg-stone-100/70' },
    { key: 'in_progress', label: 'In progress', tone: 'border-amber-200 bg-amber-50/80' },
    { key: 'handed_in', label: 'Submitted', tone: 'border-indigo-200 bg-indigo-50/80' },
    { key: 'completed', label: 'Done', tone: 'border-emerald-200 bg-emerald-50/80' },
];

const groupedAssignments = computed(() =>
    columns.map((column) => ({
        ...column,
        assignments: props.assignments.filter((assignment) => assignment.status === column.key),
    })),
);

</script>

<template>
    <div v-if="assignments.length === 0" class="rounded-[1.1rem] bg-stone-50 px-4 py-5 text-sm text-stone-600 ring-1 ring-stone-200">
        No assignments yet.
    </div>

    <div v-else>
        <div class="grid gap-3 xl:grid-cols-5 md:grid-cols-3 sm:grid-cols-2">
            <section
                v-for="column in groupedAssignments"
                :key="column.key"
                class="flex min-w-0 flex-col rounded-[1.2rem] border p-2.5"
                :class="column.tone"
            >
                <div class="mb-2 flex items-center justify-between gap-2 px-0.5">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-stone-700">
                        {{ column.label }}
                    </h3>
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-semibold text-stone-600 ring-1 ring-stone-200">
                        {{ column.assignments.length }}
                    </span>
                </div>

                <div class="space-y-2.5">
                    <article
                        v-for="assignment in column.assignments"
                        :key="assignment.id"
                        class="rounded-[1rem] bg-white p-3 shadow-sm ring-1 ring-stone-200"
                    >
                        <Link
                            v-if="mode === 'admin' && assignment.student && assignment.student_view_url"
                            :href="assignment.student_view_url"
                            class="block truncate text-[11px] font-semibold uppercase tracking-[0.12em] text-stone-600 underline underline-offset-2"
                        >
                            {{ assignment.student.display_name }}
                        </Link>
                        <p
                            v-else-if="mode === 'admin' && assignment.student"
                            class="truncate text-[11px] font-semibold uppercase tracking-[0.12em] text-stone-600"
                        >
                            {{ assignment.student.display_name }}
                        </p>

                        <p class="mt-2 whitespace-pre-wrap text-xs leading-5 text-stone-900">
                            {{ assignment.body || 'Assignment' }}
                        </p>
                        <a
                            v-if="assignment.attachment?.url"
                            :href="assignment.attachment.url"
                            target="_blank"
                            rel="noreferrer"
                            class="mt-2 block overflow-hidden rounded-[0.9rem] ring-1 ring-stone-200 transition hover:ring-stone-400"
                        >
                            <img
                                :src="assignment.attachment.url"
                                :alt="assignment.attachment.name || 'Assignment image'"
                                class="max-h-56 w-full object-cover"
                            >
                        </a>
                        <p
                            v-if="assignment.attachment?.name"
                            class="mt-1 truncate text-[11px] text-stone-500"
                        >
                            {{ assignment.attachment.name }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-if="assignment.start_url"
                                type="button"
                                class="rounded-full bg-stone-950 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-white transition hover:bg-stone-800"
                                @click="emit('start', assignment.start_url)"
                            >
                                Start
                            </button>
                            <button
                                v-if="assignment.hand_in_url"
                                type="button"
                                class="rounded-full bg-indigo-700 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-white transition hover:bg-indigo-600"
                                @click="emit('handIn', assignment.hand_in_url)"
                            >
                                Hand in
                            </button>
                            <button
                                v-if="assignment.complete_url"
                                type="button"
                                class="rounded-full bg-emerald-700 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-white transition hover:bg-emerald-600"
                                @click="emit('complete', assignment.complete_url)"
                            >
                                Mark complete
                            </button>
                            <button
                                v-if="assignment.incomplete_url"
                                type="button"
                                class="rounded-full bg-amber-600 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-white transition hover:bg-amber-500"
                                @click="emit('incomplete', assignment.incomplete_url)"
                            >
                                Mark incomplete
                            </button>
                            <Link
                                v-if="assignment.delete_url"
                                :href="assignment.delete_url"
                                method="delete"
                                as="button"
                                class="rounded-full border border-stone-300 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Delete
                            </Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
