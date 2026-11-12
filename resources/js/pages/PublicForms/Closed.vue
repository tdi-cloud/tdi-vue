<script setup lang="ts">
import PublicFormShell from '@/components/public-forms/PublicFormShell.vue';
import { type PublicFormSummary } from '@/lib/publicForms';
import { registrationTheme } from '@/lib/registrationThemes';
import { Head } from '@inertiajs/vue3';
import { CalendarX, Lock } from 'lucide-vue-next';
import { computed } from 'vue';

/** Shown instead of a public form that isn't taking responses. */
const props = defineProps<{
    form: PublicFormSummary;
    backgroundUrl: string | null;
}>();

const theme = registrationTheme('blue');

const content = computed(() =>
    props.form.closed_reason === 'deadline'
        ? {
              icon: CalendarX,
              heading: 'This Form Has Closed',
              message: props.form.closes_at_label ? `The last day to answer was ${props.form.closes_at_label}.` : 'The deadline has passed.',
          }
        : {
              icon: Lock,
              heading: 'This Form Is Not Open',
              message: "This form isn't taking responses right now.",
          },
);
</script>

<template>
    <Head :title="form.title" />

    <PublicFormShell :background-url="backgroundUrl" centered width="md">
        <div class="overflow-hidden rounded-2xl shadow-md">
            <div class="px-6 py-5" :class="theme.header">
                <p v-if="form.kicker" class="mb-1 text-xs font-semibold uppercase tracking-widest" :class="theme.kicker">{{ form.kicker }}</p>
                <h1 class="text-xl font-extrabold text-white">{{ form.title }}</h1>
            </div>
            <div class="border-t-4 bg-white px-6 py-8 text-center" :class="theme.strip">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                    <component :is="content.icon" class="h-7 w-7 text-gray-500" />
                </div>
                <h2 class="mb-2 text-xl font-extrabold text-gray-800">{{ content.heading }}</h2>
                <p class="text-sm leading-relaxed text-gray-500">{{ content.message }}</p>
                <p class="mt-3 text-sm leading-relaxed text-gray-500">
                    If you believe this is a mistake, please get in touch with the TESDA Development Institute.
                </p>
            </div>
        </div>
    </PublicFormShell>
</template>
