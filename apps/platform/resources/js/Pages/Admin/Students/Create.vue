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
    <Head title="Создание ученика" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель наставника
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Создание ученика
                    </h2>
                </div>

                <Link
                    :href="route('admin.students.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к ученикам
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Создание ученика
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Создать новую учётную запись ученика
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Эта форма создаёт и учётную запись для входа, и профиль ученика за один шаг.
                    </p>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <InputLabel for="username" value="Имя пользователя" />
                        <TextInput
                            id="username"
                            v-model="form.username"
                            type="text"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                            autofocus
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.username" />
                    </div>

                    <div>
                        <InputLabel for="display_name" value="Отображаемое имя" />
                        <TextInput
                            id="display_name"
                            v-model="form.display_name"
                            type="text"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.display_name" />
                    </div>

                    <div>
                        <InputLabel for="name" value="Имя учётной записи" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Электронная почта (необязательно)" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Пароль" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="status" value="Статус ученика" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        >
                            <option value="active">Активен</option>
                            <option value="paused">Пауза</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.status" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="notes" value="Заметки" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="5"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-3">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="text-sm text-stone-700">
                                Разрешить этому ученику вход сразу после создания
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.is_active" />
                    </div>

                    <div class="md:col-span-2 rounded-[1.5rem] bg-stone-100 p-6">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Разрешения во время работы
                        </p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="inline-flex items-center gap-3">
                                    <Checkbox v-model:checked="form.can_manage_own_schedule" />
                                    <span class="text-sm text-stone-700">
                                        Разрешить ученику управлять своим расписанием
                                    </span>
                                </label>
                                <InputError class="mt-2" :message="form.errors.can_manage_own_schedule" />
                            </div>

                            <div>
                                <label class="inline-flex items-center gap-3">
                                    <Checkbox v-model:checked="form.can_use_ad_hoc_timer" />
                                    <span class="text-sm text-stone-700">
                                        Разрешить собственные таймеры при прерывании расписания
                                    </span>
                                </label>
                                <InputError class="mt-2" :message="form.errors.can_use_ad_hoc_timer" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="preferred_timezone" value="Предпочитаемый часовой пояс (необязательно)" />
                                <TextInput
                                    id="preferred_timezone"
                                    v-model="form.preferred_timezone"
                                    type="text"
                                    class="mt-2 block w-full rounded-xl border-stone-300"
                                    placeholder="UTC"
                                />
                                <InputError class="mt-2" :message="form.errors.preferred_timezone" />
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 rounded-[1.5rem] bg-stone-100 p-6">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Профиль последствий
                        </p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <InputLabel for="default_push_up_count" value="Количество отжиманий по умолчанию" />
                                <TextInput
                                    id="default_push_up_count"
                                    v-model="form.default_push_up_count"
                                    type="number"
                                    min="0"
                                    max="1000"
                                    class="mt-2 block w-full rounded-xl border-stone-300"
                                />
                                <InputError class="mt-2" :message="form.errors.default_push_up_count" />
                            </div>

                            <div>
                                <InputLabel for="rest_duration_seconds" value="Длительность отдыха (секунды)" />
                                <TextInput
                                    id="rest_duration_seconds"
                                    v-model="form.rest_duration_seconds"
                                    type="number"
                                    min="0"
                                    max="86400"
                                    class="mt-2 block w-full rounded-xl border-stone-300"
                                />
                                <InputError class="mt-2" :message="form.errors.rest_duration_seconds" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="consequence_notes" value="Заметки по последствиям" />
                                <textarea
                                    id="consequence_notes"
                                    v-model="form.consequence_notes"
                                    rows="4"
                                    class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                />
                                <InputError class="mt-2" :message="form.errors.consequence_notes" />
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Сброс пароля пока не реализован, поэтому задайте пригодный стартовый пароль.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Создать ученика
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
