<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    title: '',
    summary: '',
    instructions: '',
    default_duration_minutes: '30',
});

const submit = () => {
    form.post(route('admin.task-templates.store'));
};
</script>

<template>
    <Head title="Создание шаблона задания" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель наставника
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Создание шаблона задания
                    </h2>
                </div>

                <Link
                    :href="route('admin.task-templates.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к библиотеке заданий
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Проектирование задания
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Добавить повторно используемый шаблон задания
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Этот шаблон пока никому не назначает работу. Он только определяет задание,
                        на которое позже смогут ссылаться расписания и сессии ученика.
                    </p>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <InputLabel for="title" value="Название задания" />
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
                        <InputLabel for="default_duration_minutes" value="Длительность по умолчанию (минуты)" />
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
                        <InputLabel for="summary" value="Краткое описание" />
                        <textarea
                            id="summary"
                            v-model="form.summary"
                            rows="3"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.summary" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="instructions" value="Инструкции" />
                        <textarea
                            id="instructions"
                            v-model="form.instructions"
                            rows="7"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.instructions" />
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Сосредоточьтесь на самом задании. Правила для конкретного ученика задаются отдельно.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Создать шаблон задания
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
