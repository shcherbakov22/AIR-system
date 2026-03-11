<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    students: Array<{
        id: number;
        display_name: string;
        username: string;
    }>;
}>();

const form = useForm({
    title: '',
    description: '',
    scope: 'global',
    student_id: '',
    is_active: true,
});

watch(
    () => form.scope,
    (scope) => {
        if (scope !== 'student') {
            form.student_id = '';
        }
    },
);

const submit = () => {
    form.post(route('admin.rule-definitions.store'));
};
</script>

<template>
    <Head title="Create rule" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Mentor dashboard
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Create rule
                    </h2>
                </div>

                <Link
                    :href="route('admin.rule-definitions.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Back to rules
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Rule design
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Add a reusable behavior rule
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        This creates only the rule definition. Violations are recorded separately.
                    </p>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <InputLabel for="title" value="Rule title" />
                        <TextInput id="title" v-model="form.title" type="text" class="mt-2 block w-full rounded-xl border-stone-300" autofocus autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="scope" value="Scope" />
                        <select id="scope" v-model="form.scope" class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700">
                            <option value="global">Global</option>
                            <option value="student">Student-specific</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.scope" />
                    </div>

                    <div v-if="form.scope === 'student'" class="md:col-span-2">
                        <InputLabel for="student_id" value="Student" />
                        <select id="student_id" v-model="form.student_id" class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700">
                            <option value="">Choose a student</option>
                            <option v-for="student in props.students" :key="student.id" :value="String(student.id)">
                                {{ student.display_name }} ({{ student.username }})
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.student_id" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="description" value="Description" />
                        <textarea id="description" v-model="form.description" rows="5" class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700" />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>

                    <div class="md:col-span-2 flex items-center justify-between gap-4 border-t border-stone-200 pt-6">
                        <label class="inline-flex items-center gap-3">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="text-sm text-stone-700">
                                Keep this rule active
                            </span>
                        </label>

                        <PrimaryButton :disabled="form.processing" class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600">
                            Create rule
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
