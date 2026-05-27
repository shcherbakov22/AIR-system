<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    student: {
        id: number;
        display_name: string;
        settings: {
            can_manage_own_schedule: boolean;
            can_use_ad_hoc_timer: boolean;
            look_away_event_threshold: number;
            preferred_timezone?: string | null;
        };
        consequence_profile: {
            default_push_up_count: number;
            increment_push_up_count_per_violation: boolean;
            rest_duration_seconds: number;
            legacy_owner_user_id?: number | null;
        };
        user: {
            id: number;
            username: string;
            name: string;
            last_login_at?: string | null;
        };
    };
}>();

const page = usePage<PageProps>();
const successMessage = computed(() => page.props.flash?.success ?? null);

const form = useForm({
    username: props.student.user.username,
    name: props.student.user.name,
    display_name: props.student.display_name,
    can_manage_own_schedule: props.student.settings.can_manage_own_schedule,
    can_use_ad_hoc_timer: props.student.settings.can_use_ad_hoc_timer,
    look_away_event_threshold: String(props.student.settings.look_away_event_threshold),
    preferred_timezone: props.student.settings.preferred_timezone ?? '',
    default_push_up_count: String(props.student.consequence_profile.default_push_up_count),
    increment_push_up_count_per_violation: props.student.consequence_profile.increment_push_up_count_per_violation,
    rest_duration_seconds: String(props.student.consequence_profile.rest_duration_seconds),
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('admin.students.update', props.student.id));
};

const submitPassword = () => {
    passwordForm.patch(route('admin.students.password.update', props.student.id), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const resetPasswordToTemp = () => {
    passwordForm.password = '0';
    passwordForm.password_confirmation = '0';
    submitPassword();
};

const deleteStudent = () => {
    if (!window.confirm(`Delete ${props.student.display_name}?`)) {
        return;
    }

    router.delete(route('admin.students.destroy', props.student.id));
};
</script>

<template>
    <Head :title="`Edit: ${props.student.display_name}`" />

    <AuthenticatedLayout>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div
                v-if="successMessage"
                class="mb-5 rounded-[1.5rem] bg-emerald-50 px-6 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200"
            >
                {{ successMessage }}
            </div>

            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="grid gap-6 border-b border-stone-200 pb-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <h3 class="font-serif text-3xl text-stone-950">
                            {{ props.student.display_name }}
                        </h3>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('admin.students.progress', props.student.id)"
                                class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Progress
                            </Link>
                            <Link
                                :href="route('admin.students.devices.index', props.student.id)"
                                class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                            >
                                Devices
                            </Link>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Current login
                        </p>
                        <p class="mt-3 text-lg font-semibold text-stone-950">
                            {{ props.student.user.username }}
                        </p>
                        <p v-if="props.student.user.last_login_at" class="mt-2 text-sm text-stone-500">
                            Last login: {{ new Date(props.student.user.last_login_at).toLocaleString() }}
                        </p>
                    </div>
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
                                <InputLabel for="look_away_event_threshold" value="Look-away events before violation" />
                                <TextInput id="look_away_event_threshold" v-model="form.look_away_event_threshold" type="number" min="1" max="1000" class="mt-2 block w-full rounded-xl border-stone-300" />
                                <InputError class="mt-2" :message="form.errors.look_away_event_threshold" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="preferred_timezone" value="Preferred timezone (optional)" />
                                <TextInput id="preferred_timezone" v-model="form.preferred_timezone" type="text" class="mt-2 block w-full rounded-xl border-stone-300" />
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
                                <label class="inline-flex items-center gap-3">
                                    <Checkbox v-model:checked="form.increment_push_up_count_per_violation" />
                                    <span class="text-sm text-stone-700">
                                        Increase push-up count after each violation
                                    </span>
                                </label>
                                <InputError class="mt-2" :message="form.errors.increment_push_up_count_per_violation" />
                            </div>

                        </div>
                    </div>

                    <div class="md:col-span-2 rounded-[1.5rem] bg-stone-100 p-6">
                        <div class="flex flex-col gap-2">
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Password
                            </p>
                            <p class="text-sm text-stone-600">
                                Stored passwords are not viewable. Use this form to set a new password or reset the student to the temporary password <code>0</code>.
                            </p>
                        </div>

                        <form class="mt-5 grid gap-5 md:grid-cols-2" @submit.prevent="submitPassword">
                            <div>
                                <InputLabel for="password" value="New password" />
                                <TextInput id="password" v-model="passwordForm.password" type="text" class="mt-2 block w-full rounded-xl border-stone-300" />
                                <InputError class="mt-2" :message="passwordForm.errors.password" />
                            </div>

                            <div>
                                <InputLabel for="password_confirmation" value="Confirm password" />
                                <TextInput id="password_confirmation" v-model="passwordForm.password_confirmation" type="text" class="mt-2 block w-full rounded-xl border-stone-300" />
                            </div>

                            <div class="md:col-span-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <button
                                    type="button"
                                    class="inline-flex justify-center rounded-full border border-stone-300 px-5 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                                    :disabled="passwordForm.processing"
                                    @click="resetPasswordToTemp"
                                >
                                    Reset To 0
                                </button>

                                <PrimaryButton :disabled="passwordForm.processing" class="justify-center rounded-full bg-stone-950 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-white hover:bg-stone-800 focus:bg-stone-800 active:bg-stone-950">
                                    Save password
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-3 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-full border border-rose-300 px-5 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-rose-700 transition hover:border-rose-700 hover:text-rose-800"
                            @click="deleteStudent"
                        >
                            Delete student
                        </button>

                        <PrimaryButton :disabled="form.processing" class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600">
                            Save changes
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
