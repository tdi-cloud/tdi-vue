<script setup lang="ts">
import PublicFormShell from '@/components/public-forms/PublicFormShell.vue';
import { type PublicFormSummary } from '@/lib/publicForms';
import { registrationTheme } from '@/lib/registrationThemes';
import { Head, Link } from '@inertiajs/vue3';
import { MailCheck, MailWarning } from 'lucide-vue-next';

/**
 * Shown after a public form is submitted. The receipt (reference number and
 * email) is only there right after submitting; a reload shows the plain thanks.
 */
defineProps<{
    form: PublicFormSummary;
    receipt: { reference: string; name: string; email: string; email_sent: boolean } | null;
    backgroundUrl: string | null;
}>();

const theme = registrationTheme('blue');
</script>

<template>
    <Head :title="`Thank you — ${form.title}`" />

    <PublicFormShell :background-url="backgroundUrl" centered width="md">
        <div class="overflow-hidden rounded-2xl shadow-md">
            <div class="px-6 py-5" :class="theme.header">
                <p v-if="form.kicker" class="mb-1 text-xs font-semibold uppercase tracking-widest" :class="theme.kicker">{{ form.kicker }}</p>
                <h1 class="text-xl font-extrabold text-white">{{ form.title }}</h1>
            </div>
            <div class="border-t-4 bg-white px-6 py-8 text-center" :class="theme.strip">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="mb-2 text-xl font-extrabold text-gray-800">Response Received!</h2>
                <p class="text-sm leading-relaxed text-gray-500">
                    Thank you<template v-if="receipt">, {{ receipt.name }}</template
                    >. Your answers to the <strong class="text-gray-700">{{ form.title }}</strong> were accepted.
                </p>

                <template v-if="receipt">
                    <div class="mx-auto mt-5 max-w-xs rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-blue-700">Reference number</p>
                        <p class="mt-0.5 text-lg font-extrabold tracking-wider text-gray-900">{{ receipt.reference }}</p>
                    </div>

                    <p v-if="receipt.email_sent" class="mt-4 flex items-start justify-center gap-2 text-left text-sm leading-relaxed text-gray-500">
                        <MailCheck class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                        <span>
                            We sent a confirmation with a copy of your answers to <strong class="break-all text-gray-700">{{ receipt.email }}</strong
                            >. If you don't see it, check your spam folder.
                        </span>
                    </p>
                    <p
                        v-else
                        class="mt-4 flex items-start justify-center gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-left text-sm leading-relaxed text-amber-800"
                    >
                        <MailWarning class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            Your answers are saved, but we couldn't send the confirmation email right now. Please keep your reference number as proof.
                        </span>
                    </p>
                </template>

                <Link :href="route('public-forms.index')" class="mt-6 inline-block text-xs font-semibold hover:underline" :class="theme.accentText">
                    See other forms
                </Link>
            </div>
        </div>
    </PublicFormShell>
</template>
