<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    username: '',
    name: '',
    display_name: '',
    password: '',
    can_manage_own_schedule: true,
    can_use_ad_hoc_timer: true,
    preferred_timezone: 'UTC',
    default_push_up_count: '0',
    rest_duration_seconds: '0',
});

const submit = () => {
    form.post(route('admin.students.store'));
};
</script>

<template>
    <Head title="Create student" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <form class="grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <InputLabel for="username" value="Username" />
                        <TextInput id="username" v-model="form.username" type="text" class="mt-2 block w-full rounded-xl border-stone-300" autofocus autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.username" />
                    </div>

                    <div>
                        <InputLabel for="display_name" value="Display name" />
                        <TextInput id="display_name" v-model="form.display_name" type="text" class="mt-2 block w-full rounded-xl border-stone-300" />
                        <InputError class="mt-2" :message="form.errors.display_name" />
                    </div>

                    <div>
                        <InputLabel for="name" value="Account name" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-2 block w-full rounded-xl border-stone-300" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full rounded-xl border-stone-300" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="md:col-span-2 rounded-[1.5rem] bg-stone-100 p-6">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Work permissions
                        </p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="inline-flex items-center gap-3">
                                    <Checkbox v-model:checked="form.can_manage_own_schedule" />
                                    <span class="text-sm text-stone-700">
                                        Let the student manage their own schedule
                                    </span>
                                </label>
                                <InputError class="mt-2" :message="form.errors.can_manage_own_schedule" />
                            </div>

                            <div>
                                <label class="inline-flex items-center gap-3">
                                    <Checkbox v-model:checked="form.can_use_ad_hoc_timer" />
                                    <span class="text-sm text-stone-700">
                                        Allow own timers while pausing a schedule
                                    </span>
                                </label>
                                <InputError class="mt-2" :message="form.errors.can_use_ad_hoc_timer" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="preferred_timezone" value="Preferred timezone (optional)" />
                                <TextInput id="preferred_timezone" v-model="form.preferred_timezone" type="text" class="mt-2 block w-full rounded-xl border-stone-300" placeholder="UTC" />
                                <InputError class="mt-2" :message="form.errors.preferred_timezone" />
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 rounded-[1.5rem] bg-stone-100 p-6">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Consequence profile
                        </p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <InputLabel for="default_push_up_count" value="Default push-up count" />
                                <TextInput id="default_push_up_count" v-model="form.default_push_up_count" type="number" min="0" max="1000" class="mt-2 block w-full rounded-xl border-stone-300" />
                                <InputError class="mt-2" :message="form.errors.default_push_up_count" />
                            </div>

                            <div>
                                <InputLabel for="rest_duration_seconds" value="Rest duration (seconds)" />
                                <TextInput id="rest_duration_seconds" v-model="form.rest_duration_seconds" type="number" min="0" max="86400" class="mt-2 block w-full rounded-xl border-stone-300" />
                                <InputError class="mt-2" :message="form.errors.rest_duration_seconds" />
                            </div>

                        </div>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Set a starting password here. You can change or reset it later from the edit screen.
                        </p>

                        <PrimaryButton :disabled="form.processing" class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600">
                            Create student
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
