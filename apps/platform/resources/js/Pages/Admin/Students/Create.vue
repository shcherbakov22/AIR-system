<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    username: '',
    name: '',
    display_name: '',
    email: '',
    password: '',
    status: 'active',
    notes: '',
    is_active: true,
    can_manage_own_schedule: true,
    can_use_ad_hoc_timer: true,
    preferred_timezone: 'UTC',
    default_push_up_count: '0',
    rest_duration_seconds: '0',
    consequence_notes: '',
});

const submit = () => {
    form.post(route('admin.students.store'));
};
</script>

<template>
    <Head title="Create student" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Mentor dashboard
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Create student
                    </h2>
                </div>

                <Link
                    :href="route('admin.students.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Back to students
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Student creation
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Create a new student account
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        This form creates both the login account and the student profile in one step.
                    </p>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
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
                        <InputLabel for="email" value="Email (optional)" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full rounded-xl border-stone-300" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full rounded-xl border-stone-300" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="status" value="Student status" />
                        <select id="status" v-model="form.status" class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700">
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.status" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="notes" value="Notes" />
                        <textarea id="notes" v-model="form.notes" rows="5" class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700" />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-3">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="text-sm text-stone-700">
                                Allow this student to log in immediately after creation
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.is_active" />
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

                            <div class="md:col-span-2">
                                <InputLabel for="consequence_notes" value="Consequence notes" />
                                <textarea id="consequence_notes" v-model="form.consequence_notes" rows="4" class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700" />
                                <InputError class="mt-2" :message="form.errors.consequence_notes" />
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Password reset is not built yet, so set a usable starting password here.
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
