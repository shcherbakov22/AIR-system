<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

type TaskTemplateOption = {
    id: number;
    title: string;
    instructions?: string | null;
    default_duration_minutes: number;
};

type FormEntry = {
    task_template_id: string;
    notes: string;
    current_title?: string;
    current_instructions?: string;
    current_duration_minutes?: number | null;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    scheduleTemplate?: {
        id: number;
        name: string;
        is_active: boolean;
        notes: string;
        entries: Array<{
            task_template_id?: number | null;
            task_title: string;
            task_instructions?: string | null;
            duration_minutes: number;
            notes: string;
        }>;
    };
    taskTemplates: TaskTemplateOption[];
}>();

const hasTaskTemplates = computed(() => props.taskTemplates.length > 0);

const findTaskTemplate = (taskTemplateId: string): TaskTemplateOption | null =>
    props.taskTemplates.find((taskTemplate) => String(taskTemplate.id) === taskTemplateId) ?? null;

const buildEntry = (
    entry?: Partial<{
        task_template_id?: number | null;
        task_title: string;
        task_instructions?: string | null;
        duration_minutes: number;
        notes: string;
    }>,
): FormEntry => ({
    task_template_id: entry?.task_template_id ? String(entry.task_template_id) : '',
    notes: entry?.notes ?? '',
    current_title: entry?.task_title ?? '',
    current_instructions: entry?.task_instructions ?? '',
    current_duration_minutes: entry?.duration_minutes ?? null,
});

const form = useForm({
    name: props.scheduleTemplate?.name ?? 'План на день',
    is_active: props.scheduleTemplate?.is_active ?? true,
    notes: props.scheduleTemplate?.notes ?? '',
    entries: props.scheduleTemplate?.entries.map((entry) => buildEntry(entry)) ?? [buildEntry()],
});

const addEntry = () => {
    form.entries.push(buildEntry());
};

const removeEntry = (index: number) => {
    if (form.entries.length === 1) {
        return;
    }

    form.entries.splice(index, 1);
};

const moveEntry = (index: number, direction: -1 | 1) => {
    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= form.entries.length) {
        return;
    }

    const [entry] = form.entries.splice(index, 1);
    form.entries.splice(targetIndex, 0, entry);
};

const entryError = (index: number, field: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[`entries.${index}.${field}`];

const selectedTaskTemplate = (entry: FormEntry): TaskTemplateOption | null =>
    findTaskTemplate(entry.task_template_id);

const previewInstructions = (entry: FormEntry): string | null =>
    selectedTaskTemplate(entry)?.instructions ?? entry.current_instructions ?? null;

const durationLabel = (entry: FormEntry): string => {
    const durationMinutes = selectedTaskTemplate(entry)?.default_duration_minutes ?? entry.current_duration_minutes;

    return durationMinutes === null || durationMinutes === undefined ? '-' : `${durationMinutes} минут`;
};

const isLegacyEntry = (entry: FormEntry): boolean =>
    entry.task_template_id === '' && Boolean(entry.current_title || entry.current_instructions);

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('student.schedules.store'));

        return;
    }

    form.put(route('student.schedules.update', props.scheduleTemplate!.id));
};
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
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

            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-3">
                    <Checkbox v-model:checked="form.is_active" />
                    <span class="text-sm text-stone-700">
                        Оставить это расписание активным
                    </span>
                </label>
                <InputError class="mt-2" :message="form.errors.is_active" />
            </div>
        </div>

        <div class="rounded-[1.75rem] bg-stone-100 p-4 md:p-5">
            <div class="flex flex-col gap-3 border-b border-stone-200 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Блоки расписания
                    </p>
                    <h3 class="mt-2 font-serif text-2xl text-stone-950">
                        Упорядоченный план заданий
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Блоки можно двигать. Длительность берётся из библиотеки заданий наставника.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!hasTaskTemplates"
                    @click="addEntry"
                >
                    Добавить блок
                </button>
            </div>

            <div
                v-if="!hasTaskTemplates"
                class="mt-5 rounded-[1.5rem] bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-950 ring-1 ring-amber-200"
            >
                В каталоге пока нет заданий. Сначала добавьте их в панели наставника.
            </div>

            <InputError class="mt-4" :message="form.errors.entries" />

            <div class="mt-4 space-y-3">
                <article
                    v-for="(entry, index) in form.entries"
                    :key="index"
                    class="rounded-[1.25rem] bg-white p-3 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="grid gap-3 md:grid-cols-[auto_auto_minmax(0,1.4fr)_auto_minmax(0,0.9fr)_auto] md:items-center">
                        <div class="text-sm font-semibold text-stone-500">
                            {{ index + 1 }}
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-stone-300 text-stone-600 transition hover:border-stone-950 hover:text-stone-950 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="index === 0"
                                @click="moveEntry(index, -1)"
                            >
                                ↑
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-stone-300 text-stone-600 transition hover:border-stone-950 hover:text-stone-950 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="index === form.entries.length - 1"
                                @click="moveEntry(index, 1)"
                            >
                                ↓
                            </button>
                        </div>

                        <div>
                            <select
                                :id="`task_template_id_${index}`"
                                v-model="entry.task_template_id"
                                class="block w-full rounded-xl border-stone-300 py-2 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            >
                                <option value="">
                                    Выберите задание
                                </option>
                                <option
                                    v-for="taskTemplate in props.taskTemplates"
                                    :key="taskTemplate.id"
                                    :value="String(taskTemplate.id)"
                                >
                                    {{ taskTemplate.title }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="entryError(index, 'task_template_id')" />
                        </div>

                        <div
                            :id="`duration_minutes_${index}`"
                            class="flex min-h-9 items-center rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm font-medium text-stone-700"
                        >
                            {{ durationLabel(entry) }}
                        </div>

                        <div>
                            <input
                                :id="`notes_${index}`"
                                v-model="entry.notes"
                                type="text"
                                placeholder="Заметка"
                                class="block w-full rounded-xl border-stone-300 py-2 text-sm shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <InputError class="mt-2" :message="entryError(index, 'notes')" />
                        </div>

                        <button
                            type="button"
                            class="inline-flex justify-center rounded-full border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 transition hover:border-rose-400 hover:text-rose-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.entries.length === 1"
                            @click="removeEntry(index)"
                        >
                            Удалить
                        </button>
                    </div>

                    <div
                        v-if="isLegacyEntry(entry)"
                        class="mt-3 rounded-[1rem] bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950 ring-1 ring-amber-200"
                    >
                        Этот блок раньше был задан вручную как "{{ entry.current_title }}". Выберите для него задание из каталога.
                    </div>

                    <div v-if="previewInstructions(entry)" class="mt-3 rounded-[1rem] border border-stone-200 bg-stone-50 px-4 py-3 text-sm leading-6 text-stone-700">
                        {{ previewInstructions(entry) }}
                    </div>
                </article>
            </div>
        </div>

        <div class="flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm leading-7 text-stone-500">
                Порядок блоков здесь определяет порядок выполнения на главной странице ученика.
            </p>

            <PrimaryButton
                :disabled="form.processing || !hasTaskTemplates"
                class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
            >
                {{ props.mode === 'create' ? 'Сохранить расписание' : 'Обновить расписание' }}
            </PrimaryButton>
        </div>
    </form>
</template>
