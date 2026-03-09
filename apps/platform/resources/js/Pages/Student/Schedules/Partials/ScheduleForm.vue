<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';

type FormEntry = {
    task_title: string;
    task_summary: string;
    task_instructions: string;
    start_time: string;
    duration_minutes: string;
    notes: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    scheduleTemplate?: {
        id: number;
        name: string;
        weekday: string;
        is_active: boolean;
        notes: string;
        entries: Array<{
            task_title: string;
            task_summary: string;
            task_instructions: string;
            start_time: string;
            duration_minutes: number;
            notes: string;
        }>;
    };
    weekdays: Array<{
        value: string;
        label: string;
    }>;
}>();

const addMinutesToTime = (time: string, durationMinutes: number): string => {
    const [hoursText, minutesText] = time.split(':');
    const hours = Number(hoursText);
    const minutes = Number(minutesText);

    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return '09:00';
    }

    const totalMinutes = Math.max(0, Math.min(1435, hours * 60 + minutes + durationMinutes));
    const nextHours = Math.floor(totalMinutes / 60);
    const nextMinutes = totalMinutes % 60;

    return `${String(nextHours).padStart(2, '0')}:${String(nextMinutes).padStart(2, '0')}`;
};

const nextSuggestedStartTime = (): string => {
    const lastEntry = form.entries[form.entries.length - 1];

    if (!lastEntry) {
        return '09:00';
    }

    return addMinutesToTime(lastEntry.start_time, Number(lastEntry.duration_minutes) || 0);
};

const buildEntry = (
    entry?: Partial<{
        task_title: string;
        task_summary: string;
        task_instructions: string;
        start_time: string;
        duration_minutes: number;
        notes: string;
    }>,
): FormEntry => {
    return {
        task_title: entry?.task_title ?? '',
        task_summary: entry?.task_summary ?? '',
        task_instructions: entry?.task_instructions ?? '',
        start_time: entry?.start_time ?? nextSuggestedStartTime(),
        duration_minutes: String(entry?.duration_minutes ?? 30),
        notes: entry?.notes ?? '',
    };
};

const form = useForm({
    name: props.scheduleTemplate?.name ?? 'План на день',
    weekday: props.scheduleTemplate?.weekday ?? props.weekdays[0]?.value ?? 'monday',
    is_active: props.scheduleTemplate?.is_active ?? true,
    notes: props.scheduleTemplate?.notes ?? '',
    entries:
        props.scheduleTemplate?.entries.map((entry) => buildEntry(entry)) ??
        [buildEntry({ start_time: '09:00' })],
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

const entryError = (index: number, field: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[`entries.${index}.${field}`];

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

        <div class="rounded-[1.75rem] bg-stone-100 p-6">
            <div class="flex flex-col gap-3 border-b border-stone-200 pb-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                        Блоки расписания
                    </p>
                    <h3 class="mt-2 font-serif text-3xl text-stone-950">
                        Упорядоченный план заданий
                    </h3>
                    <p class="mt-3 text-sm leading-7 text-stone-600">
                        Добавляйте блоки в том порядке, в котором собираетесь их выполнять.
                        Блоки должны идти по времени начала и не могут пересекаться.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    @click="addEntry"
                >
                    Добавить блок
                </button>
            </div>

            <InputError class="mt-4" :message="form.errors.entries" />

            <div class="mt-6 space-y-5">
                <article
                    v-for="(entry, index) in form.entries"
                    :key="index"
                    class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="flex flex-col gap-3 border-b border-stone-100 pb-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Блок {{ index + 1 }}
                            </p>
                            <p class="mt-2 text-lg font-semibold text-stone-950">
                                {{ entry.task_title || 'Блок задания' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex rounded-full border border-stone-300 px-3 py-2 text-sm font-medium text-stone-600 transition hover:border-rose-300 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.entries.length === 1"
                            @click="removeEntry(index)"
                        >
                            Удалить
                        </button>
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-[1.3fr_0.7fr_0.7fr]">
                        <div class="md:col-span-3">
                            <InputLabel :for="`task_title_${index}`" value="Название задания" />
                            <input
                                :id="`task_title_${index}`"
                                v-model="entry.task_title"
                                type="text"
                                class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                                autocomplete="off"
                            />
                            <InputError class="mt-2" :message="entryError(index, 'task_title')" />
                        </div>

                        <div>
                            <InputLabel :for="`start_time_${index}`" value="Время начала" />
                            <input
                                :id="`start_time_${index}`"
                                v-model="entry.start_time"
                                type="time"
                                class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <InputError class="mt-2" :message="entryError(index, 'start_time')" />
                        </div>

                        <div>
                            <InputLabel :for="`duration_minutes_${index}`" value="Длительность (минуты)" />
                            <input
                                :id="`duration_minutes_${index}`"
                                v-model="entry.duration_minutes"
                                type="number"
                                min="5"
                                max="480"
                                class="mt-2 block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <InputError class="mt-2" :message="entryError(index, 'duration_minutes')" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel :for="`task_summary_${index}`" value="Краткое описание задания" />
                            <textarea
                                :id="`task_summary_${index}`"
                                v-model="entry.task_summary"
                                rows="3"
                                class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <InputError class="mt-2" :message="entryError(index, 'task_summary')" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel :for="`task_instructions_${index}`" value="Инструкции к заданию" />
                            <textarea
                                :id="`task_instructions_${index}`"
                                v-model="entry.task_instructions"
                                rows="4"
                                class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <InputError class="mt-2" :message="entryError(index, 'task_instructions')" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel :for="`notes_${index}`" value="Заметка к блоку" />
                            <textarea
                                :id="`notes_${index}`"
                                v-model="entry.notes"
                                rows="3"
                                class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                            />
                            <InputError class="mt-2" :message="entryError(index, 'notes')" />
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <div class="flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm leading-7 text-stone-500">
                Порядок, который вы зададите здесь, станет порядком выполнения на главной странице ученика.
            </p>

            <PrimaryButton
                :disabled="form.processing"
                class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
            >
                {{ props.mode === 'create' ? 'Сохранить расписание' : 'Обновить расписание' }}
            </PrimaryButton>
        </div>
    </form>
</template>
