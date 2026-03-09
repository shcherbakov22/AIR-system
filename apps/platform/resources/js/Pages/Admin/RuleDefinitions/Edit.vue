<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    ruleDefinition: {
        id: number;
        title: string;
        description?: string | null;
        scope: string;
        student_id: string;
        default_penalty_units: number;
        is_active: boolean;
    };
    students: Array<{
        id: number;
        display_name: string;
        username: string;
    }>;
}>();

const form = useForm({
    title: props.ruleDefinition.title,
    description: props.ruleDefinition.description ?? '',
    scope: props.ruleDefinition.scope,
    student_id: props.ruleDefinition.student_id,
    default_penalty_units: String(props.ruleDefinition.default_penalty_units),
    is_active: props.ruleDefinition.is_active,
});

watch(
    () => form.scope,
    (scope) => {
        if (scope !== 'student') {
            form.student_id = '';
        }
    },
);

const submit = () => {
    form.put(route('admin.rule-definitions.update', props.ruleDefinition.id));
};
</script>

<template>
    <Head :title="`Изменение: ${props.ruleDefinition.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель администратора
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Изменение правила
                    </h2>
                </div>

                <Link
                    :href="route('admin.rule-definitions.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к правилам
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="grid gap-6 border-b border-stone-200 pb-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                            Обслуживание правила
                        </p>
                        <h3 class="mt-4 font-serif text-3xl text-stone-950">
                            {{ props.ruleDefinition.title }}
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-stone-600">
                            Настройте область действия, штраф по умолчанию и активность до того,
                            как на это правило начнут ссылаться нарушения.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] bg-stone-100 p-5">
                        <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                            Текущее значение
                        </p>
                        <p class="mt-3 text-lg font-semibold text-stone-950">
                            {{ props.ruleDefinition.default_penalty_units }} штрафных ед.
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            {{
                                props.ruleDefinition.scope === 'global'
                                    ? 'Правило действует глобально.'
                                    : 'Правило действует для конкретного ученика.'
                            }}
                        </p>
                    </div>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <InputLabel for="title" value="Название правила" />
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
                        <InputLabel for="scope" value="Область действия" />
                        <select
                            id="scope"
                            v-model="form.scope"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        >
                            <option value="global">
                                Глобальное
                            </option>
                            <option value="student">
                                Для конкретного ученика
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.scope" />
                    </div>

                    <div>
                        <InputLabel for="default_penalty_units" value="Штраф по умолчанию" />
                        <TextInput
                            id="default_penalty_units"
                            v-model="form.default_penalty_units"
                            type="number"
                            min="0"
                            max="100000"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.default_penalty_units" />
                    </div>

                    <div v-if="form.scope === 'student'" class="md:col-span-2">
                        <InputLabel for="student_id" value="Ученик" />
                        <select
                            id="student_id"
                            v-model="form.student_id"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        >
                            <option value="">
                                Выберите ученика
                            </option>
                            <option
                                v-for="student in props.students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ student.display_name }} ({{ student.username }})
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.student_id" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="description" value="Описание" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="5"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>

                    <div class="md:col-span-2 flex items-center justify-between gap-4 border-t border-stone-200 pt-6">
                        <label class="inline-flex items-center gap-3">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="text-sm text-stone-700">
                                Оставить это правило активным
                            </span>
                        </label>

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
