<script setup lang="ts">
import type { PageProps } from '@/types';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type StudentSettings = {
    id: number;
    display_name: string;
    username: string;
    settings: {
        screen_capture_interval_seconds: number;
        camera_capture_interval_seconds: number;
    };
    consequence_profile: {
        current_push_up_count: number;
        increment_push_up_count_per_violation: boolean;
    };
};

const props = defineProps<{
    students: StudentSettings[];
}>();

type StudentSettingsFormState = {
    increment_push_up_count_per_violation: boolean;
    screen_capture_interval_seconds: string;
    camera_capture_interval_seconds: string;
    processing: boolean;
    errors: Record<string, string>;
};

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const forms = reactive<Record<number, StudentSettingsFormState>>({});

props.students.forEach((student) => {
    forms[student.id] = {
        increment_push_up_count_per_violation: student.consequence_profile.increment_push_up_count_per_violation,
        screen_capture_interval_seconds: String(student.settings.screen_capture_interval_seconds),
        camera_capture_interval_seconds: String(student.settings.camera_capture_interval_seconds),
        processing: false,
        errors: {},
    };
});

const saveStudent = (student: StudentSettings) => {
    const form = forms[student.id];

    router.patch(route('admin.settings.students.update', student.id), {
        increment_push_up_count_per_violation: form.increment_push_up_count_per_violation,
        screen_capture_interval_seconds: form.screen_capture_interval_seconds,
        camera_capture_interval_seconds: form.camera_capture_interval_seconds,
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            form.processing = true;
            form.errors = {};
        },
        onError: (errors) => {
            form.errors = errors;
        },
        onFinish: () => {
            form.processing = false;
        },
    });
};
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 sm:py-6">
            <div
                v-if="successMessage"
                class="mb-4 rounded-[1.25rem] bg-emerald-50 px-5 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ successMessage }}
            </div>

            <section class="overflow-hidden rounded-[1.25rem] bg-white shadow-sm ring-1 ring-stone-200">
                <div class="border-b border-stone-200 px-4 py-4 sm:px-5">
                    <h1 class="text-lg font-semibold text-stone-950">
                        Admin settings
                    </h1>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">
                            <tr>
                                <th class="px-4 py-3 sm:px-5">
                                    Student
                                </th>
                                <th class="px-4 py-3">
                                    Push-ups
                                </th>
                                <th class="px-4 py-3">
                                    Screenshot interval
                                </th>
                                <th class="px-4 py-3">
                                    Webcam interval
                                </th>
                                <th class="px-4 py-3 text-right sm:px-5">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200">
                            <tr
                                v-for="student in props.students"
                                :key="student.id"
                                class="align-top"
                            >
                                <td class="px-4 py-4 sm:px-5">
                                    <div class="font-semibold text-stone-950">
                                        {{ student.username }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-stone-700">
                                        <Checkbox
                                            :checked="forms[student.id].increment_push_up_count_per_violation"
                                            @update:checked="(checked) => forms[student.id].increment_push_up_count_per_violation = Boolean(checked)"
                                        />
                                        <span>Auto increase</span>
                                    </label>
                                    <div class="mt-2 text-xs text-stone-500">
                                        Next count: {{ student.consequence_profile.current_push_up_count }}
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="forms[student.id].errors.increment_push_up_count_per_violation"
                                    />
                                </td>
                                <td class="px-4 py-4">
                                    <TextInput
                                        v-model="forms[student.id].screen_capture_interval_seconds"
                                        type="number"
                                        min="15"
                                        max="3600"
                                        step="1"
                                        class="w-28"
                                    />
                                    <div class="mt-1 text-xs text-stone-500">
                                        seconds
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="forms[student.id].errors.screen_capture_interval_seconds"
                                    />
                                </td>
                                <td class="px-4 py-4">
                                    <TextInput
                                        v-model="forms[student.id].camera_capture_interval_seconds"
                                        type="number"
                                        min="15"
                                        max="3600"
                                        step="1"
                                        class="w-28"
                                    />
                                    <div class="mt-1 text-xs text-stone-500">
                                        seconds
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="forms[student.id].errors.camera_capture_interval_seconds"
                                    />
                                </td>
                                <td class="px-4 py-4 text-right sm:px-5">
                                    <PrimaryButton
                                        type="button"
                                        :disabled="forms[student.id].processing"
                                        :class="{ 'opacity-60': forms[student.id].processing }"
                                        @click="saveStudent(student)"
                                    >
                                        Save
                                    </PrimaryButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
