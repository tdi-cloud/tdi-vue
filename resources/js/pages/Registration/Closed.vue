<script setup lang="ts">
import { registrationTheme } from '@/lib/registrationThemes';
import { Head } from '@inertiajs/vue3';
import { CalendarX, Lock, UsersRound } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';

interface PublicForm {
    title: string;
    theme: string;
    closes_at_label: string | null;
    batch: { batch: string };
    program: { title: string | null };
}

const props = defineProps<{
    form: PublicForm;
    reason: 'not_open' | 'deadline' | 'full';
}>();

const theme = computed(() => registrationTheme(props.form.theme));

const content = computed(() => {
    switch (props.reason) {
        case 'deadline':
            return {
                icon: CalendarX,
                heading: 'Registration Has Closed',
                message: props.form.closes_at_label
                    ? `The registration deadline was ${props.form.closes_at_label}.`
                    : 'The registration deadline has passed.',
            };
        case 'full':
            return {
                icon: UsersRound,
                heading: 'This Batch Is Full',
                message: 'All registration slots for this batch have been taken.',
            };
        default:
            return {
                icon: Lock,
                heading: 'Registration Is Not Open',
                message: "This registration form isn't accepting registrations right now.",
            };
    }
});

onMounted(() => {
    document.documentElement.classList.remove('dark');
});
</script>

<template>
    <Head :title="form.title" />

    <div class="flex min-h-screen items-center justify-center bg-gray-100 px-4 py-12 [color-scheme:light]">
        <div class="w-full max-w-md space-y-4">
            <div class="overflow-hidden rounded-2xl shadow-md">
                <div class="px-6 py-5" :class="theme.header">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest" :class="theme.kicker">{{ form.title }}</p>
                    <h1 class="text-xl font-extrabold text-white">{{ form.program.title }}</h1>
                    <p class="mt-0.5 text-xs text-white/80">{{ form.batch.batch }}</p>
                </div>
                <div class="border-t-4 bg-white px-6 py-8 text-center" :class="theme.strip">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <component :is="content.icon" class="h-7 w-7 text-gray-500" />
                    </div>
                    <h2 class="mb-2 text-xl font-extrabold text-gray-800">{{ content.heading }}</h2>
                    <p class="text-sm leading-relaxed text-gray-500">{{ content.message }}</p>
                    <p class="mt-3 text-sm leading-relaxed text-gray-500">
                        If you believe this is a mistake, please get in touch with the organizer of
                        <strong class="text-gray-700">{{ form.program.title }}</strong
                        >.
                    </p>
                </div>
            </div>

            <footer class="flex items-center justify-center gap-2.5 pt-2">
                <img
                    src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png"
                    alt="TESDA Development Institute"
                    class="h-7 w-7 object-contain"
                />
                <span class="text-sm font-semibold text-gray-600">TESDA Development Institute</span>
            </footer>
        </div>
    </div>
</template>
