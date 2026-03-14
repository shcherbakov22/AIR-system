<script setup lang="ts">
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelStudentStatus } from '@/lib/labels';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    students: Array<{
        id: number;
        display_name: string;
        status: string;
        notes?: string | null;
        user: {
            id: number;
            username: string;
            name: string;
            email?: string | null;
            is_active: boolean;
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
        <template #header>
            <div class="flex flex-col gap-2">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                    Mentor dashboard
                </p>
                <h2 class="font-serif text-4xl leading-none text-stone-950">
                    Students
                </h2>
            </div>
        </template>

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
                        class="grid gap-4 px-6 py-6 md:grid-cols-[1.1fr_0.7fr_0.9fr]"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Display name
                            </p>
                            <h3 class="mt-2 text-2xl font-semibold text-stone-950">
                                {{ student.display_name }}
                            </h3>
                            <p class="mt-2 text-sm text-stone-600">
                                Account name: {{ student.user.name }}
                            </p>
                            <Link
                                :href="route('admin.students.edit', student.id)"
                                class="mt-4 inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Edit student
                            </Link>
                            <Link
                                :href="route('admin.students.progress', student.id)"
                                class="mt-4 ml-2 inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Progress
                            </Link>
                            <button
                                type="button"
                                class="mt-4 ml-2 inline-flex rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-rose-700 transition hover:border-rose-700 hover:text-rose-800"
                                @click="deleteStudent(student.id, student.display_name)"
                            >
                                Delete
                            </button>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Login
                            </p>
                            <p class="mt-2 text-sm font-semibold text-stone-950">
                                {{ student.user.username }}
                            </p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ student.user.email || 'No email address provided' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Status
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        student.status === 'active'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-stone-200 text-stone-700'
                                    "
                                >
                                    {{ labelStudentStatus(student.status) }}
                                </span>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="
                                        student.user.is_active
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-rose-100 text-rose-700'
                                    "
                                >
                                    {{ student.user.is_active ? 'login allowed' : 'login disabled' }}
                                </span>
                            </div>
                            <p v-if="student.notes" class="mt-3 text-sm leading-6 text-stone-600">
                                {{ student.notes }}
                            </p>
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
