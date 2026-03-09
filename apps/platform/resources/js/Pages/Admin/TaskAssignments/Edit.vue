<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    taskAssignment: {
        id: number;
        student_id: string;
        task_template_id: string;
        status: string;
        due_on: string;
        notes: string;
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
}>();

const form = useForm({
    student_id: props.taskAssignment.student_id,
    task_template_id: props.taskAssignment.task_template_id,
    status: props.taskAssignment.status,
    due_on: props.taskAssignment.due_on,
    notes: props.taskAssignment.notes,
});

const submit = () => {
    form.put(route('admin.task-assignments.update', props.taskAssignment.id));
};
</script>

<template>
    <Head :title="`Изменение назначения #${props.taskAssignment.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель администратора
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Изменение назначения
                    </h2>
                </div>

                <Link
                    :href="route('admin.task-assignments.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к назначениям
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="grid gap-6 border-b border-stone-200 pb-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                            Обслуживание назначения
                        </p>
                        <h3 class="mt-4 font-serif text-3xl text-stone-950">
                            Назначение #{{ props.taskAssignment.id }}
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-stone-600">
                            Измените ученика, задание, статус, дату или заметку без пересоздания
                            самого назначения.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Текущий статус
                        </p>
                        <p class="mt-3 text-lg font-semibold text-stone-950">
                            {{ props.taskAssignment.status }}
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            {{
                                props.taskAssignment.due_on
                                    ? `Срок: ${props.taskAssignment.due_on}`
                                    : 'Срок не задан'
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
                        <InputLabel for="task_template_id" value="Шаблон задания" />
                        <select
                            id="task_template_id"
                            v-model="form.task_template_id"
                            class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
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
                        <InputLabel for="status" value="Статус назначения" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        >
                            <option value="assigned">Назначено</option>
                            <option value="paused">Пауза</option>
                            <option value="completed">Завершено</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.status" />
                    </div>

                    <div>
                        <InputLabel for="due_on" value="Срок выполнения (необязательно)" />
                        <input
                            id="due_on"
                            v-model="form.due_on"
                            type="date"
                            class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.due_on" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="notes" value="Заметки к назначению" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="6"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Даже если старое назначение ссылается на неактивный шаблон, его всё
                            равно можно редактировать здесь.
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
