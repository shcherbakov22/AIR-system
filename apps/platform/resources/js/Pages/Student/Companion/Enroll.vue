<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

const props = defineProps<{
    installer_download_url: string;
    browser_extension_download_url: string;
    bootstrap_script_url: string;
    root_ca_url: string;
    base_url: string;
    browser_extension_setup: {
        platform_url: string;
        device_token: string;
    };
}>();

const extensionSetupStatus = ref<string | null>(null);
const extensionSetupOk = ref(false);

const handleExtensionSetupResult = (event: MessageEvent) => {
    if (event.source !== window || event.data?.type !== 'air_browser_extension_configured') {
        return;
    }

    extensionSetupOk.value = Boolean(event.data.ok);
    extensionSetupStatus.value = event.data.ok
        ? 'Browser extension configured and policy synced.'
        : event.data.error || 'Browser extension setup failed. Make sure the extension is installed, then try again.';
};

window.addEventListener('message', handleExtensionSetupResult);

onBeforeUnmount(() => {
    window.removeEventListener('message', handleExtensionSetupResult);
});

const configureBrowserExtension = () => {
    extensionSetupOk.value = false;
    extensionSetupStatus.value = 'Sending setup to the browser extension...';
    window.postMessage({
        type: 'air_browser_extension_configure',
        platformUrl: props.browser_extension_setup.platform_url,
        deviceToken: props.browser_extension_setup.device_token,
    }, window.location.origin);

    window.setTimeout(() => {
        if (extensionSetupStatus.value === 'Sending setup to the browser extension...') {
            extensionSetupStatus.value = 'No response from the extension. Install it, then reload this enrollment page and click Configure browser extension again.';
        }
    }, 3000);
};
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
                        :href="browser_extension_download_url"
                        download="air-look-extension.zip"
                        class="inline-flex rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-950 hover:text-stone-950"
                    >
                        Download blocklist browser extension
                    </a>
                    <button
                        type="button"
                        class="inline-flex rounded-full border border-emerald-700 bg-emerald-700 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-emerald-800"
                        @click="configureBrowserExtension"
                    >
                        Configure browser extension
                    </button>
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
                        Install the browser extension first, then click Configure browser extension on this page. The page sends the local server URL and browser extension token directly to Chrome.
                    </p>
                    <p
                        v-if="extensionSetupStatus"
                        class="mt-4 rounded-2xl px-4 py-3 text-xs"
                        :class="extensionSetupOk ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >
                        {{ extensionSetupStatus }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
