<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    title: '',
    instructions: '',
    default_duration_minutes: '30',
    requires_internet: false,
});

const submit = () => {
    form.post(route('admin.task-templates.store'));
};
</script>

<template>
    <Head title="Create task template" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <form class="grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <InputLabel for="title" value="Task title" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                            autofocus
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="default_duration_minutes" value="Default duration (minutes)" />
                        <TextInput
                            id="default_duration_minutes"
                            v-model="form.default_duration_minutes"
                            type="number"
                            min="1"
                            max="10000"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.default_duration_minutes" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="instructions" value="Instructions" />
                        <textarea
                            id="instructions"
                            v-model="form.instructions"
                            rows="7"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.instructions" />
                    </div>

                    <div class="md:col-span-2 rounded-[1.5rem] bg-stone-100 p-5">
                        <label class="inline-flex items-center gap-3">
                            <Checkbox v-model:checked="form.requires_internet" />
                            <span class="text-sm text-stone-700">
                                This task requires internet access while it is active.
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.requires_internet" />
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Focus on the task itself here. Student-specific rules are managed separately.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Create task template
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
