<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    installer_download_url: string;
    bootstrap_script_url: string;
    root_ca_url: string;
    base_url: string;
}>();
</script>

<template>
    <Head title="Companion Enrollment" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-6 py-10">
            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <h1 class="text-2xl font-semibold text-stone-950">Companion enrollment</h1>
                <p class="mt-3 text-sm text-stone-600">
                    Download the installer bundle, unzip it, run <span class="font-mono text-xs">install-companion.ps1</span>
                    as administrator, then run the enrollment script on the same machine.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a
                        :href="installer_download_url"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Download installer bundle
                    </a>
                    <a
                        :href="bootstrap_script_url"
                        class="inline-flex rounded-full border border-amber-700 bg-amber-700 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-amber-800"
                    >
                        Download enrollment script
                    </a>
                    <a
                        :href="root_ca_url"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Download root CA
                    </a>
                </div>

                <div class="mt-8 rounded-[1.5rem] bg-stone-100 p-5 text-sm text-stone-700">
                    <p class="font-semibold text-stone-900">Enrollment page</p>
                    <p class="mt-2 font-mono text-xs">{{ base_url }}/student/companion/enroll</p>
                    <p class="mt-4 text-xs text-stone-500">
                        The enrollment script contains a one-time token and writes it into the local AIR Companion request file.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
