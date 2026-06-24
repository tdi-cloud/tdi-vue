<script setup lang="ts">
interface Config {
    organizing_sponsor: string;
    form_title: string;
    slug: string;
}

defineProps<{ config: Config }>();
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full space-y-4">

            <!-- Header -->
            <div class="rounded-2xl overflow-hidden shadow-md">
                <div class="bg-blue-700 px-6 py-5">
                    <p class="text-blue-200 text-xs font-semibold uppercase tracking-widest mb-1">
                        {{ config.organizing_sponsor }}
                    </p>
                    <h1 class="text-white text-xl font-extrabold">{{ config.form_title }}</h1>
                </div>
                <div class="bg-white border-t-4 border-blue-500 px-6 py-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                        <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-extrabold text-gray-800 mb-2">Nomination Submitted!</h2>
                    <p class="text-sm text-gray-500 leading-relaxed">
                        Thank you for submitting your nomination for
                        <strong class="text-gray-700">{{ config.organizing_sponsor }}</strong>.
                        Your application has been received. Please await further instructions from your agency.
                    </p>

                    <div class="mt-6 rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-700 text-left">
                        <p class="font-semibold mb-1">What happens next:</p>
                        <ul class="list-disc list-inside space-y-1 text-blue-600">
                            <li>Your nomination has been submitted with status <strong>For Interview</strong>.</li>
                            <li>You will receive a notification from your agency regarding the next steps.</li>
                            <li>Please ensure your contact number and email address are accessible.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>