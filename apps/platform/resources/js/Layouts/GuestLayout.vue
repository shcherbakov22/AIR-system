<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-stone-950 text-stone-100">
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.22),_transparent_32rem),radial-gradient(circle_at_bottom_right,_rgba(234,88,12,0.18),_transparent_26rem)]"
        />

        <div class="relative mx-auto flex min-h-screen max-w-6xl items-center justify-center px-6 py-10">
            <section
                class="w-full max-w-xl rounded-[2rem] border border-white/10 bg-white/95 p-8 text-stone-900 shadow-2xl shadow-black/30 backdrop-blur sm:p-10"
            >
                <div class="mb-8 flex justify-center">
                    <Link href="/">
                        <ApplicationLogo class="h-12 w-12 text-amber-700" />
                    </Link>
                </div>

                <slot />
            </section>
        </div>
    </div>
</template>
