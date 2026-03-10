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
}>();

const form = useForm({
    student_id: props.students[0]?.id ? String(props.students[0].id) : '',
    task_template_id: props.taskTemplates[0]?.id ? String(props.taskTemplates[0].id) : '',
    status: 'assigned',
    due_on: '',
    notes: '',
});

const canSubmit = computed(() => props.students.length > 0 && props.taskTemplates.length > 0);

const submit = () => {
    form.post(route('admin.task-assignments.store'));
};
</script>

<template>
    <Head title="Создание назначения" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель наставника
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Создание назначения
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
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Конструктор назначений
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Назначить задание ученику
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Выберите ученика, шаблон задания и задайте статус назначения.
                    </p>
                </div>

                <div v-if="!canSubmit" class="mt-8 rounded-[1.5rem] bg-stone-100 px-6 py-8">
                    <p class="text-sm uppercase tracking-[0.25em] text-stone-500">
                        Не хватает данных
                    </p>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Для создания назначения нужен как минимум один ученик и один шаблон задания.
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
                                {{ taskTemplate.title }} ({{ taskTemplate.default_duration_minutes }} min)
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

                    <div
                        class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-sm text-stone-500">
                            Расписания и автоматическая последовательность появятся позже. Здесь
                            создаётся только прямое назначение.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Создать назначение
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
