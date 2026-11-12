<script setup lang="ts">
import PublicFormShell from '@/components/public-forms/PublicFormShell.vue';
import { type PublicFormSummary } from '@/lib/publicForms';
import { registrationTheme } from '@/lib/registrationThemes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarClock, Clock, FileText, Lock } from 'lucide-vue-next';

/** Every public form of the TESDA Development Institute, with a link to answer the open ones. */
defineProps<{
    forms: PublicFormSummary[];
    /** The site-wide default background (Homepage Images). */
    backgroundUrl: string | null;
}>();

const theme = registrationTheme('blue');
</script>

<template>
    <Head title="Public Forms" />

    <PublicFormShell :background-url="backgroundUrl">
        <div class="overflow-hidden rounded-2xl shadow-md">
            <div class="px-6 pb-5 pt-6" :class="theme.header">
                <p class="mb-1 text-xs font-semibold uppercase tracking-widest" :class="theme.kicker">TESDA Development Institute</p>
                <h1 class="text-xl font-extrabold leading-tight text-white sm:text-2xl">Public Forms</h1>
                <p class="mt-1.5 text-sm leading-relaxed text-white/85">
                    Surveys and forms for TESDA employees. No login needed: find your name in the employee list and answer.
                </p>
            </div>
        </div>

        <ul class="space-y-3">
            <li v-for="form in forms" :key="form.slug" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50">
                        <FileText class="h-5 w-5" :class="theme.accentText" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p v-if="form.kicker" class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">{{ form.kicker }}</p>
                        <h2 class="text-base font-extrabold text-gray-800">{{ form.title }}</h2>
                        <p v-if="form.description" class="mt-0.5 text-sm leading-relaxed text-gray-500">{{ form.description }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span class="flex items-center gap-1"><Clock class="h-3.5 w-3.5" /> About {{ form.estimated_minutes }} minutes</span>
                            <span v-if="!form.closed_reason && form.closes_at_label" class="flex items-center gap-1">
                                <CalendarClock class="h-3.5 w-3.5" /> Open until {{ form.closes_at_label }}
                            </span>
                        </div>
                    </div>

                    <Link
                        v-if="!form.closed_reason"
                        :href="route('public-forms.show', form.slug)"
                        class="flex shrink-0 items-center justify-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-extrabold text-white transition"
                        :class="[theme.accentBg, theme.accentHover]"
                    >
                        Answer <ArrowRight class="h-4 w-4" />
                    </Link>
                    <span
                        v-else
                        class="flex shrink-0 items-center justify-center gap-1.5 rounded-xl bg-gray-100 px-5 py-2.5 text-sm font-semibold text-gray-500"
                    >
                        <Lock class="h-4 w-4" /> Closed
                    </span>
                </div>
            </li>
        </ul>
    </PublicFormShell>
</template>
