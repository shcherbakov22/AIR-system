<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-stone-950 text-stone-100">
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.22),_transparent_32rem),radial-gradient(circle_at_bottom_right,_rgba(234,88,12,0.18),_transparent_26rem)]"
        />
        <div
            class="relative mx-auto flex min-h-screen max-w-6xl items-center px-6 py-10"
        >
            <div class="grid w-full gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                <section class="hidden flex-col justify-center lg:flex">
                    <div class="mb-6 flex items-center gap-4">
                        <ApplicationLogo class="h-16 w-16 text-amber-300" />
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-amber-200/70">
                                Школьная система
                            </p>
                            <h1 class="mt-2 font-serif text-5xl leading-none text-stone-50">
                                Центр управления новой версией.
                            </h1>
                        </div>
                    </div>
                    <p class="max-w-xl text-lg leading-8 text-stone-300">
                        Эта ранняя версия платформы уже обрабатывает вход, маршрутизацию по ролям
                        и первые записи учеников. Поддержка расписаний, нарушений, штрафов и
                        мониторинга продолжает добавляться поэтапно.
                    </p>
                </section>

                <section
                    class="w-full rounded-[2rem] border border-white/10 bg-white/95 p-8 text-stone-900 shadow-2xl shadow-black/30 backdrop-blur sm:p-10"
                >
                    <div class="mb-8 flex items-center gap-4 lg:hidden">
                        <Link href="/">
                            <ApplicationLogo class="h-12 w-12 text-amber-700" />
                        </Link>
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-amber-700/70">
                                Школьная система
                            </p>
                            <p class="mt-1 text-sm text-stone-500">
                                Доступ к платформе
                            </p>
                        </div>
                    </div>

                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
