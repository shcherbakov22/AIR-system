<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    students: Array<{
        id: number;
        display_name: string;
        user: {
            id: number;
            username: string;
            name: string;
            last_login_at?: string | null;
        };
    }>;
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const deleteStudent = (studentId: number, displayName: string) => {
    if (!window.confirm(`Delete ${displayName}?`)) {
        return;
    }

    router.delete(route('admin.students.destroy', studentId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Students" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-7xl px-6 py-10">
            <div v-if="successMessage" class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
                {{ successMessage }}
            </div>

            <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-4 border-b border-stone-200 px-6 py-5 md:flex-row md:items-center md:justify-end">
                    <Link
                        :href="route('admin.students.create')"
                        class="inline-flex rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                    >
                        Add student
                    </Link>
                </div>

                <div v-if="props.students.length > 0" class="divide-y divide-stone-200">
                    <article
                        v-for="student in props.students"
                        :key="student.id"
                        class="flex flex-wrap items-center gap-3 px-6 py-4 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-base font-semibold text-stone-950">
                                {{ student.display_name }}
                            </h3>
                            <p class="truncate text-xs text-stone-500">
                                {{ student.user.username }}
                            </p>
                        </div>
                        <div class="shrink-0 text-xs text-stone-500">
                            <span v-if="student.user.last_login_at">
                                Last login: {{ new Date(student.user.last_login_at).toLocaleString() }}
                            </span>
                            <span v-else>Never logged in</span>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Link
                                :href="route('admin.students.edit', student.id)"
                                class="inline-flex rounded-full border border-stone-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Edit
                            </Link>
                            <Link
                                :href="route('admin.students.progress', student.id)"
                                class="inline-flex rounded-full border border-stone-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Progress
                            </Link>
                            <Link
                                :href="route('admin.students.devices.index', student.id)"
                                class="inline-flex rounded-full border border-stone-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Devices
                            </Link>
                            <button
                                type="button"
                                class="inline-flex rounded-full border border-rose-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-700 hover:text-rose-800"
                                @click="deleteStudent(student.id, student.display_name)"
                            >
                                Delete
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-500">
                        No students yet
                    </p>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Create the first student account to start filling out the platform.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
