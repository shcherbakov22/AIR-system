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
        summary?: string | null;
        instructions?: string | null;
        default_duration_minutes: number;
        is_active: boolean;
    };
}>();

const form = useForm({
    title: props.taskTemplate.title,
    summary: props.taskTemplate.summary ?? '',
    instructions: props.taskTemplate.instructions ?? '',
    default_duration_minutes: String(props.taskTemplate.default_duration_minutes),
    is_active: props.taskTemplate.is_active,
});

const submit = () => {
    form.put(route('admin.task-templates.update', props.taskTemplate.id));
};
</script>

<template>
    <Head :title="`Изменение: ${props.taskTemplate.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель администратора
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Изменение шаблона задания
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
                <div class="grid gap-6 border-b border-stone-200 pb-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                            Обслуживание шаблона задания
                        </p>
                        <h3 class="mt-4 font-serif text-3xl text-stone-950">
                            {{ props.taskTemplate.title }}
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-stone-600">
                            Измените повторно используемый шаблон задания, не назначая его ученикам.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Текущее значение по умолчанию
                        </p>
                        <p class="mt-3 text-lg font-semibold text-stone-950">
                            {{ props.taskTemplate.default_duration_minutes }} минут
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            {{ props.taskTemplate.is_active ? 'Шаблон активен.' : 'Шаблон неактивен.' }}
                        </p>
                    </div>
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

                    <div>
                        <InputLabel for="default_duration_minutes" value="Длительность по умолчанию (минуты)" />
                        <TextInput
                            id="default_duration_minutes"
                            v-model="form.default_duration_minutes"
                            type="number"
                            min="5"
                            max="480"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.default_duration_minutes" />
                    </div>

                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-3 pb-2">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="text-sm text-stone-700">
                                Оставить этот шаблон активным
                            </span>
                        </label>
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
                            Назначения ученикам и привязка к расписаниям остаются отдельными частями системы.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Сохранить изменения
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
