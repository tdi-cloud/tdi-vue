<script setup lang="ts">
interface Config {
    organizing_sponsor: string;
    form_title: string;
    slug: string;
}

defineProps<{ config: Config }>();
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-100 px-4 py-12">
        <div class="w-full max-w-md space-y-4">
            <!-- Header -->
            <div class="overflow-hidden rounded-2xl shadow-md">
                <div class="bg-blue-700 px-6 py-5">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-blue-200">
                        {{ config.organizing_sponsor }}
                    </p>
                    <h1 class="text-xl font-extrabold text-white">{{ config.form_title }}</h1>
                </div>
                <div class="border-t-4 border-blue-500 bg-white px-6 py-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                        <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="mb-2 text-xl font-extrabold text-gray-800">Nomination Submitted!</h2>
                    <p class="text-sm leading-relaxed text-gray-500">
                        Thank you for submitting your nomination for
                        <strong class="text-gray-700">{{ config.organizing_sponsor }}</strong
                        >. Your application has been received. Please await further instructions from your agency.
                    </p>

                    <div class="mt-6 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-left text-xs text-blue-700">
                        <p class="mb-1 font-semibold">What happens next:</p>
                        <ul class="list-inside list-disc space-y-1 text-blue-600">
                            <li>Your nomination has been submitted with status <strong>For Interview</strong>.</li>
                            <li>You will receive a notification from the sponsoring organization regarding the next steps.</li>
                            <li>Please ensure your contact number and email address are accessible.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
