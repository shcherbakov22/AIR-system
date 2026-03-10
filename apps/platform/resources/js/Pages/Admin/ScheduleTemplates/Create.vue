<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
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
    }>;
    weekdays: Array<{
        value: string;
        label: string;
    }>;
}>();

const form = useForm({
    student_id: props.students[0]?.id ? String(props.students[0].id) : '',
    name: 'Morning Block',
    weekday: props.weekdays[0]?.value ?? 'monday',
    notes: '',
    task_template_id: props.taskTemplates[0]?.id ? String(props.taskTemplates[0].id) : '',
    start_time: '09:00',
    duration_minutes: props.taskTemplates[0]?.default_duration_minutes
        ? String(props.taskTemplates[0].default_duration_minutes)
        : '30',
    entry_notes: '',
});

const canSubmit = computed(() => props.students.length > 0 && props.taskTemplates.length > 0);

const submit = () => {
    form.post(route('admin.schedule-templates.store'));
};
</script>

<template>
    <Head title="Создание расписания" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель наставника
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Создание расписания
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
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Недельный планировщик
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Добавить первый блок расписания
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Этот этап создаёт один еженедельный шаблон расписания с одним блоком
                        задания. Полноценный редактор с несколькими блоками появится позже.
                    </p>
                </div>

                <div v-if="!canSubmit" class="mt-8 rounded-[1.5rem] bg-stone-100 px-6 py-8">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Не хватает данных
                    </p>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Для создания блока расписания нужен как минимум один ученик и один
                        шаблон задания.
                    </p>
                </div>

                <form v-else class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
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
                            Первый блок расписания
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
                                        {{ taskTemplate.title }} ({{ taskTemplate.default_duration_minutes }} min)
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
                            Редактирование нескольких блоков и генерация дневного расписания
                            будут реализованы отдельно.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Создать расписание
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
