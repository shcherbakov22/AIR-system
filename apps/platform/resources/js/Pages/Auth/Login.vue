<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <header class="mb-8 text-center">
            <p class="text-xs uppercase tracking-[0.3em] text-amber-700/80">
                Log in
            </p>
            <h2 class="mt-3 font-serif text-4xl leading-none text-stone-950">
                Access the platform
            </h2>
        </header>

        <div
            v-if="status"
            class="mb-4 rounded-2xl bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="username" value="Username" />

                <TextInput
                    id="username"
                    v-model="form.username"
                    type="text"
                    class="mt-2 block w-full rounded-2xl border-stone-300"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-2 block w-full rounded-2xl border-stone-300"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-3 rounded-2xl bg-stone-100 px-4 py-3">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span class="text-sm text-stone-700">Remember this browser</span>
            </label>

            <PrimaryButton
                class="w-full justify-center rounded-full bg-stone-950 px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white hover:bg-stone-800"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Log in
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
