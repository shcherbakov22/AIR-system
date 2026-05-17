<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    taskTemplate: {
        id: number;
        title: string;
        instructions?: string | null;
        default_duration_minutes: number;
        requires_internet: boolean;
        can_end_early: boolean;
        can_interrupt_schedule: boolean;
        browser_allowed_domains: string[];
    };
}>();

const form = useForm({
    title: props.taskTemplate.title,
    instructions: props.taskTemplate.instructions ?? '',
    default_duration_minutes: String(props.taskTemplate.default_duration_minutes),
    requires_internet: props.taskTemplate.requires_internet,
    can_end_early: props.taskTemplate.can_end_early,
    can_interrupt_schedule: props.taskTemplate.can_interrupt_schedule,
    browser_allowed_domains: props.taskTemplate.browser_allowed_domains.join('\n'),
});

const submit = () => {
    form.put(route('admin.task-templates.update', props.taskTemplate.id));
};
</script>

<template>
    <Head :title="`Edit: ${props.taskTemplate.title}`" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="grid gap-6 border-b border-stone-200 pb-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                            Task template maintenance
                        </p>
                        <h3 class="mt-4 font-serif text-3xl text-stone-950">
                            {{ props.taskTemplate.title }}
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-stone-600">
                            Update this reusable task template without assigning it directly to students.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Current default value
                        </p>
                        <p class="mt-3 text-lg font-semibold text-stone-950">
                            {{ props.taskTemplate.default_duration_minutes }} min
                        </p>
                    </div>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
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

                    <div class="md:col-span-2 grid gap-3 rounded-[1.5rem] bg-stone-100 p-5">
                        <label class="inline-flex items-center gap-3">
                            <Checkbox v-model:checked="form.can_end_early" />
                            <span class="text-sm text-stone-700">
                                Allow this task to be finished before 80% of its planned time.
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.can_end_early" />

                        <label class="inline-flex items-center gap-3">
                            <Checkbox v-model:checked="form.can_interrupt_schedule" />
                            <span class="text-sm text-stone-700">
                                Allow this task when switching away from a running schedule task.
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.can_interrupt_schedule" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="browser_allowed_domains" value="Allowed websites for whitelist mode" />
                        <textarea
                            id="browser_allowed_domains"
                            v-model="form.browser_allowed_domains"
                            rows="5"
                            placeholder="khanacademy.org&#10;docs.python.org"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <p class="mt-2 text-sm text-stone-500">
                            One domain per line. Each entry allows that domain and all subdomains only while this task is active.
                        </p>
                        <InputError class="mt-2" :message="form.errors.browser_allowed_domains" />
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Student assignments and schedule links remain separate parts of the system.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Save changes
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
