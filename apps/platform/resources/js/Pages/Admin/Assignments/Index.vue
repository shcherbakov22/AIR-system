<script setup lang="ts">
import AssignmentBoard from '@/Components/AssignmentBoard.vue';
import AssignmentImageDropzone from '@/Components/AssignmentImageDropzone.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    students: Array<{
        id: number;
        display_name: string;
        username: string;
    }>;
    selectedStudentId?: number | null;
    assignments: Array<{
        id: number;
        title: string;
        body?: string | null;
        attachment?: {
            name?: string | null;
            mime?: string | null;
            size?: number | null;
            url: string;
        } | null;
        status: 'unread' | 'viewed' | 'in_progress' | 'handed_in' | 'completed';
        created_at_label?: string | null;
        viewed_at_label?: string | null;
        started_at_label?: string | null;
        completed_at_label?: string | null;
        creator_name: string;
        student: {
            id: number;
            display_name: string;
            username: string;
        };
        complete_url?: string | null;
        incomplete_url?: string | null;
        delete_url: string;
        student_view_url: string;
    }>;
}>();

const form = useForm({
    student_id: props.selectedStudentId ? String(props.selectedStudentId) : '',
    body: '',
    image: null as File | null,
    return_to_student: false,
});
const imageDropActive = ref(false);

const submit = () => {
    form.post(route('admin.assignments.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('body', 'image'),
    });
};

const setDroppedImage = (file: File) => {
    form.image = file;
};

const clearDroppedImage = () => {
    form.image = null;
};

const completeAssignment = (url?: string | null) => {
    if (!url) {
        return;
    }

    router.patch(url, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const markIncomplete = (url?: string | null) => {
    if (!url) {
        return;
    }

    router.patch(url, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Assignments" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[112rem] px-3 py-3 sm:px-4 sm:py-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(18rem,0.82fr)_minmax(0,2.18fr)]">
                <section class="rounded-[1.4rem] bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <form class="space-y-3" @submit.prevent="submit">
                        <select
                            v-model="form.student_id"
                            class="w-full rounded-[1rem] border-stone-300 px-4 py-3 text-sm shadow-sm focus:border-stone-950 focus:ring-stone-950"
                        >
                            <option value="">
                                Choose a student
                            </option>
                            <option
                                v-for="student in students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ student.display_name }} ({{ student.username }})
                            </option>
                        </select>
                        <textarea
                            v-model="form.body"
                            rows="5"
                            class="w-full rounded-[1rem] border-stone-300 px-4 py-3 text-sm shadow-sm focus:border-stone-950 focus:ring-stone-950"
                            placeholder="Assignment"
                        />
                        <AssignmentImageDropzone
                            :image-file="form.image"
                            :disabled="form.processing"
                            :active="imageDropActive"
                            @drop="setDroppedImage"
                            @clear="clearDroppedImage"
                            @drag-enter="imageDropActive = true"
                            @drag-leave="imageDropActive = false"
                        />
                        <button
                            type="submit"
                            class="rounded-full bg-stone-950 px-5 py-2 text-sm font-semibold text-white transition hover:bg-stone-800"
                            :disabled="form.processing"
                        >
                            Create assignment
                        </button>
                    </form>
                </section>

                <section class="rounded-[1.4rem] bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xl font-semibold text-stone-950">
                            Assignment board
                        </h2>
                    </div>

                    <div class="mt-4">
                        <AssignmentBoard
                            :assignments="assignments"
                            mode="admin"
                            @complete="completeAssignment"
                            @incomplete="markIncomplete"
                        />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
