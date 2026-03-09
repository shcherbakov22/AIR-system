<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    scheduleTemplate: {
        id: number;
        student_id: string;
        name: string;
        weekday: string;
        is_active: boolean;
        notes: string;
        entry: {
            task_template_id: string;
            start_time: string;
            duration_minutes: number;
            notes: string;
        };
    };
    students: Array<{
        id: number;
        display_name: string;
        username: string;
        is_active: boolean;
    }>;
    taskTemplates: Array<{
        id: number;
        title: string;
        default_duration_minutes: number;
        is_active: boolean;
    }>;
    weekdays: Array<{
        value: string;
        label: string;
    }>;
}>();

const form = useForm({
    student_id: props.scheduleTemplate.student_id,
    name: props.scheduleTemplate.name,
    weekday: props.scheduleTemplate.weekday,
    is_active: props.scheduleTemplate.is_active,
    notes: props.scheduleTemplate.notes,
    task_template_id: props.scheduleTemplate.entry.task_template_id,
    start_time: props.scheduleTemplate.entry.start_time,
    duration_minutes: String(props.scheduleTemplate.entry.duration_minutes),
    entry_notes: props.scheduleTemplate.entry.notes,
});

const submit = () => {
    form.put(route('admin.schedule-templates.update', props.scheduleTemplate.id));
};
</script>

<template>
    <Head :title="`Изменение: ${props.scheduleTemplate.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель администратора
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Изменение расписания
                    </h2>
                </div>

                <Link
                    :href="route('admin.schedule-templates.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к расписаниям
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="grid gap-6 border-b border-stone-200 pb-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                            Недельный планировщик
                        </p>
                        <h3 class="mt-4 font-serif text-3xl text-stone-950">
                            {{ props.scheduleTemplate.name }}
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-stone-600">
                            Измените шаблон расписания и его единственный блок, не переходя пока
                            к будущему редактору с несколькими блоками.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Текущее расписание
                        </p>
                        <p class="mt-3 text-lg font-semibold text-stone-950">
                            {{ props.scheduleTemplate.name }}
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            {{
                                props.weekdays.find(
                                    (weekday) => weekday.value === props.scheduleTemplate.weekday,
                                )?.label
                            }}
                        </p>
                    </div>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <InputLabel for="student_id" value="Ученик" />
                        <select
                            id="student_id"
                            v-model="form.student_id"
                            class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        >
                            <option
                                v-for="student in props.students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ student.display_name }} ({{ student.username }}){{
                                    student.is_active ? '' : ' - вход запрещён'
                                }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.student_id" />
                    </div>

                    <div>
                        <InputLabel for="weekday" value="День недели" />
                        <select
                            id="weekday"
                            v-model="form.weekday"
                            class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        >
                            <option
                                v-for="weekday in props.weekdays"
                                :key="weekday.value"
                                :value="weekday.value"
                            >
                                {{ weekday.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.weekday" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="name" value="Название расписания" />
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="notes" value="Заметки к расписанию" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <div class="md:col-span-2 rounded-[1.75rem] bg-stone-100 p-6">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Единственный блок расписания
                        </p>

                        <div class="mt-5 grid gap-6 md:grid-cols-3">
                            <div class="md:col-span-3">
                                <InputLabel for="task_template_id" value="Шаблон задания" />
                                <select
                                    id="task_template_id"
                                    v-model="form.task_template_id"
                                    class="mt-2 block w-full rounded-xl border-stone-300 bg-white shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                >
                                    <option
                                        v-for="taskTemplate in props.taskTemplates"
                                        :key="taskTemplate.id"
                                        :value="String(taskTemplate.id)"
                                    >
                                        {{ taskTemplate.title }} ({{ taskTemplate.default_duration_minutes }} min){{
                                            taskTemplate.is_active ? '' : ' - неактивен'
                                        }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.task_template_id" />
                            </div>

                            <div>
                                <InputLabel for="start_time" value="Время начала" />
                                <input
                                    id="start_time"
                                    v-model="form.start_time"
                                    type="time"
                                    class="mt-2 block w-full rounded-xl border-stone-300 bg-white shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                />
                                <InputError class="mt-2" :message="form.errors.start_time" />
                            </div>

                            <div>
                                <InputLabel for="duration_minutes" value="Длительность (минуты)" />
                                <input
                                    id="duration_minutes"
                                    v-model="form.duration_minutes"
                                    type="number"
                                    min="5"
                                    max="480"
                                    class="mt-2 block w-full rounded-xl border-stone-300 bg-white shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                />
                                <InputError class="mt-2" :message="form.errors.duration_minutes" />
                            </div>

                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-3 pb-2">
                                    <Checkbox v-model:checked="form.is_active" />
                                    <span class="text-sm text-stone-700">
                                        Оставить это расписание активным
                                    </span>
                                </label>
                            </div>

                            <div class="md:col-span-3">
                                <InputLabel for="entry_notes" value="Заметка к блоку" />
                                <textarea
                                    id="entry_notes"
                                    v-model="form.entry_notes"
                                    rows="4"
                                    class="mt-2 block w-full rounded-[1.25rem] border-stone-300 bg-white shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                />
                                <InputError class="mt-2" :message="form.errors.entry_notes" />
                            </div>
                        </div>
                    </div>

                    <div
                        class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-sm text-stone-500">
                            Редактирование нескольких блоков остаётся задачей на потом. Этот экран
                            поддерживает только текущую модель с одним блоком.
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
