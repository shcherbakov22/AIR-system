<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    students: Array<{
        id: number;
        display_name: string;
        username: string;
    }>;
    ruleDefinitions: Array<{
        id: number;
        title: string;
        scope: string;
        description?: string | null;
        student?: {
            id: number;
            display_name: string;
            username: string;
        } | null;
    }>;
}>();

const formatDefaultOccurredAt = (): string => {
    const now = new Date();
    now.setSeconds(0, 0);

    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60_000);

    return local.toISOString().slice(0, 16);
};

const form = useForm({
    student_id: '',
    rule_definition_id: '',
    occurred_at: formatDefaultOccurredAt(),
    notes: '',
});

const availableRuleDefinitions = computed(() => {
    if (!form.student_id) {
        return [] as typeof props.ruleDefinitions;
    }

    const studentId = Number(form.student_id);

    return props.ruleDefinitions.filter((ruleDefinition) => {
        if (ruleDefinition.scope === 'global') {
            return true;
        }

        return ruleDefinition.student?.id === studentId;
    });
});

const selectedRuleDefinition = computed(() => {
    if (!form.rule_definition_id) {
        return null;
    }

    return props.ruleDefinitions.find(
        (ruleDefinition) => ruleDefinition.id === Number(form.rule_definition_id),
    ) ?? null;
});

watch(
    () => form.student_id,
    () => {
        const currentRuleId = Number(form.rule_definition_id);
        const ruleStillAvailable = availableRuleDefinitions.value.some(
            (ruleDefinition) => ruleDefinition.id === currentRuleId,
        );

        if (!ruleStillAvailable) {
            form.rule_definition_id = '';
        }
    },
);

const submit = () => {
    form.post(route('admin.violations.store'));
};
</script>

<template>
    <Head title="Создание нарушения" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                        Панель наставника
                    </p>
                    <h2 class="font-serif text-4xl leading-none text-stone-950">
                        Создание нарушения
                    </h2>
                </div>

                <Link
                    :href="route('admin.violations.index')"
                    class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                >
                    Назад к нарушениям
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-700/70">
                        Журнал нарушений
                    </p>
                    <h3 class="mt-4 font-serif text-3xl text-stone-950">
                        Зафиксировать открытое нарушение
                    </h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">
                        Сначала выберите ученика, затем правило, которое к нему применяется. Открытое нарушение само по себе блокирует продолжение расписания, пока наставник его не закроет.
                    </p>
                </div>

                <form class="mt-10 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
                    <div>
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

                    <div>
                        <InputLabel for="rule_definition_id" value="Правило" />
                        <select
                            id="rule_definition_id"
                            v-model="form.rule_definition_id"
                            class="mt-2 block w-full rounded-[1.25rem] border-stone-300 shadow-sm focus:border-amber-700 focus:ring-amber-700"
                        >
                            <option value="">
                                Выберите правило
                            </option>
                            <option
                                v-for="ruleDefinition in availableRuleDefinitions"
                                :key="ruleDefinition.id"
                                :value="String(ruleDefinition.id)"
                            >
                                {{
                                    ruleDefinition.scope === 'global'
                                        ? `${ruleDefinition.title} (глобальное)`
                                        : `${ruleDefinition.title} (${ruleDefinition.student?.display_name})`
                                }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.rule_definition_id" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="occurred_at" value="Время нарушения" />
                        <TextInput
                            id="occurred_at"
                            v-model="form.occurred_at"
                            type="datetime-local"
                            class="mt-2 block w-full rounded-xl border-stone-300"
                        />
                        <InputError class="mt-2" :message="form.errors.occurred_at" />
                    </div>

                    <div class="md:col-span-2">
                        <div class="rounded-[1.5rem] bg-stone-100 p-5">
                            <p class="text-xs uppercase tracking-[0.25em] text-stone-500">
                                Выбранное правило
                            </p>
                            <template v-if="selectedRuleDefinition">
                                <p class="mt-3 text-lg font-semibold text-stone-950">
                                    {{ selectedRuleDefinition.title }}
                                </p>
                                <p class="mt-2 text-sm text-stone-600">
                                    {{
                                        selectedRuleDefinition.scope === 'global'
                                            ? 'Глобальное правило'
                                            : `Для ученика: ${selectedRuleDefinition.student?.display_name}`
                                    }}
                                </p>
                                <p class="mt-2 text-sm leading-6 text-stone-600">
                                    {{
                                        selectedRuleDefinition.description ||
                                        'Описание правила не указано.'
                                    }}
                                </p>
                            </template>
                            <p v-else class="mt-3 text-sm text-stone-600">
                                Выберите ученика и правило, чтобы перед сохранением увидеть контекст.
                            </p>
                        </div>
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

                    <div class="md:col-span-2 flex flex-col gap-4 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-stone-500">
                            Это создаёт открытую запись о нарушении, которую потом можно решить или отменить.
                        </p>

                        <PrimaryButton
                            :disabled="form.processing"
                            class="justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold tracking-[0.2em] text-stone-950 hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600"
                        >
                            Создать нарушение
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
