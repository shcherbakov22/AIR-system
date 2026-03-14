<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    ruleDefinition: {
        id: number;
        title: string;
        description?: string | null;
        is_active: boolean;
    };
}>();

const form = useForm({
    title: props.ruleDefinition.title,
    description: props.ruleDefinition.description ?? '',
    is_active: props.ruleDefinition.is_active,
});

const submit = () => {
    form.put(route('admin.rule-definitions.update', props.ruleDefinition.id));
};
</script>

<template>
    <Head :title="`Edit: ${props.ruleDefinition.title}`" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="grid gap-6 border-b border-stone-200 pb-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                            Rule maintenance
                        </p>
                        <h3 class="mt-4 font-serif text-3xl text-stone-950">
                            {{ props.ruleDefinition.title }}
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-stone-600">
                            Adjust the rule text and activation before violations continue to reference this rule.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Rule type
                        </p>
                        <p class="mt-3 text-lg font-semibold text-stone-950">
                            Global rule
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            This rule applies across the system.
                        </p>
                    </div>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <InputLabel for="title" value="Rule title" />
                        <TextInput id="title" v-model="form.title" type="text" class="mt-2 block w-full rounded-xl border-stone-300" autofocus autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.title" />
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
                            Save changes
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
