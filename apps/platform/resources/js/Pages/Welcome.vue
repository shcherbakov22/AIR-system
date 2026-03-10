<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import type { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    canLogin: boolean;
}>();

const page = usePage<PageProps>();

const authUser = computed(() => page.props.auth.user);
const showPrimaryAction = computed(() => Boolean(authUser.value) || props.canLogin);
const primaryHref = computed(() => (authUser.value ? route('dashboard') : route('login')));
const primaryLabel = computed(() => (authUser.value ? 'Dashboard' : 'Log in'));
</script>

<template>
    <Head title="School System" />

    <div class="relative min-h-screen overflow-hidden bg-stone-950 text-stone-100">
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.22),_transparent_32rem),radial-gradient(circle_at_bottom_right,_rgba(234,88,12,0.18),_transparent_26rem)]"
        />

        <div class="relative mx-auto flex min-h-screen max-w-6xl items-center justify-center px-6 py-10">
            <div class="flex flex-col items-center gap-8">
                <ApplicationLogo class="h-20 w-20 text-amber-300" />

                <Link
                    v-if="showPrimaryAction"
                    :href="primaryHref"
                    class="inline-flex items-center justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-stone-950 transition hover:bg-amber-400"
                >
                    {{ primaryLabel }}
                </Link>
            </div>
        </div>
    </div>
</template>
