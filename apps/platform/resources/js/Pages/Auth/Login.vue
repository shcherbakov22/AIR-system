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
        <Head title="Вход" />

        <header class="mb-8">
            <p class="text-xs uppercase tracking-[0.3em] text-amber-700/80">
                Вход
            </p>
            <h2 class="mt-3 font-serif text-4xl leading-none text-stone-950">
                Доступ к платформе
            </h2>
            <p class="mt-4 text-sm leading-6 text-stone-600">
                Используйте выданные вам имя пользователя и пароль. Открытая регистрация отключена.
            </p>
        </header>

        <div v-if="status" class="mb-4 rounded-2xl bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="username" value="Имя пользователя" />

                <TextInput
                    id="username"
                    type="text"
                    class="mt-2 block w-full rounded-2xl border-stone-300"
                    v-model="form.username"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div>
                <InputLabel for="password" value="Пароль" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-2 block w-full rounded-2xl border-stone-300"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-3 rounded-2xl bg-stone-100 px-4 py-3">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span class="text-sm text-stone-700">Запомнить этот браузер</span>
            </label>

            <PrimaryButton
                class="w-full justify-center rounded-full bg-stone-950 px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white hover:bg-stone-800"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Войти
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
