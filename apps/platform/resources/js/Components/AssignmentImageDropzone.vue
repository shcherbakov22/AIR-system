<script setup lang="ts">
const props = withDefaults(defineProps<{
    imageFile?: File | null;
    disabled?: boolean;
    compact?: boolean;
    active?: boolean;
}>(), {
    imageFile: null,
    disabled: false,
    compact: false,
    active: false,
});

const emit = defineEmits<{
    drop: [file: File];
    clear: [];
    dragEnter: [];
    dragLeave: [];
}>();

const acceptDrop = (event: DragEvent) => {
    if (props.disabled) {
        return;
    }

    const file = event.dataTransfer?.files?.[0];
    if (!file || !file.type.startsWith('image/')) {
        return;
    }

    emit('drop', file);
};
</script>

<template>
    <div
        class="rounded-[0.9rem] border border-dashed px-3 py-2 transition"
        :class="[
            disabled
                ? 'border-stone-200 bg-stone-50 text-stone-400'
                : active
                    ? 'border-sky-500 bg-sky-100 text-sky-900'
                    : 'border-sky-200 bg-white text-stone-600',
            compact ? 'text-[11px]' : 'text-sm',
        ]"
        @dragenter.prevent="emit('dragEnter')"
        @dragover.prevent
        @dragleave.prevent="emit('dragLeave')"
        @drop.prevent="acceptDrop($event); emit('dragLeave')"
    >
        <div class="flex items-center justify-between gap-3">
            <p class="min-w-0 truncate">
                {{ imageFile ? imageFile.name : 'Drop image here' }}
            </p>
            <button
                v-if="imageFile"
                type="button"
                class="shrink-0 rounded-full border border-stone-300 px-2 py-0.5 font-semibold uppercase tracking-[0.12em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                :class="compact ? 'text-[10px]' : 'text-xs'"
                :disabled="disabled"
                @click="emit('clear')"
            >
                Clear
            </button>
        </div>
        <p
            v-if="!imageFile"
            class="mt-1 text-stone-500"
            :class="compact ? 'text-[10px]' : 'text-xs'"
        >
            Images only. No file picker.
        </p>
    </div>
</template>
