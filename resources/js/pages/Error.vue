<script setup lang="ts">
import ReportProblemButton from '@/components/ReportProblemButton.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock, FileQuestion, LayoutDashboard, RotateCw, ServerCrash, ShieldAlert, TimerOff, Wrench } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ status: number }>();

interface ErrorCopy {
    title: string;
    description: string;
    icon: Component;
    // Retrying only helps when the problem is temporary (rate limit, server, maintenance).
    canRetry: boolean;
}

const copies: Record<number, ErrorCopy> = {
    403: {
        title: "You don't have access to this page",
        description:
            "Your account doesn't have permission to open this page. If you think you should have access, ask your administrator or report a problem below.",
        icon: ShieldAlert,
        canRetry: false,
    },
    404: {
        title: "We can't find that page",
        description: 'The link may be old, or the page may have been moved or removed. Check the address, or go back to the Dashboard.',
        icon: FileQuestion,
        canRetry: false,
    },
    // Normally handled by a redirect with a message (bootstrap/app.php); this is the fallback copy.
    419: {
        title: 'This page expired',
        description: 'For your security, pages expire after a while. Reload the page and try again; what you entered may need to be filled in again.',
        icon: TimerOff,
        canRetry: true,
    },
    429: {
        title: 'Too many requests',
        description: "You've made a lot of requests in a short time. Wait a minute, then try again.",
        icon: Clock,
        canRetry: true,
    },
    500: {
        title: 'Something went wrong on our side',
        description: "This isn't your fault, and your saved work is safe. Try again in a moment. If it keeps happening, report it so we can fix it.",
        icon: ServerCrash,
        canRetry: true,
    },
    503: {
        title: 'TDI is under maintenance',
        description: "We're making updates right now. Please check back in a few minutes.",
        icon: Wrench,
        canRetry: true,
    },
};

const copy = computed(() => copies[props.status] ?? copies[500]);

const heading = ref<HTMLElement | null>(null);

// Move focus to the heading so screen readers announce what happened.
onMounted(() => heading.value?.focus());

const retry = () => window.location.reload();
const goBack = () => window.history.back();
</script>

<template>
    <Head :title="copy.title" />

    <div class="flex min-h-screen items-center justify-center bg-background px-4 py-12 text-foreground">
        <main class="w-full max-w-md text-center" role="alert">
            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                <component :is="copy.icon" class="h-8 w-8 text-muted-foreground" aria-hidden="true" />
            </div>

            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Error {{ status }}</p>
            <h1 ref="heading" tabindex="-1" class="mb-3 text-2xl font-extrabold outline-none">{{ copy.title }}</h1>
            <p class="text-sm leading-relaxed text-muted-foreground">{{ copy.description }}</p>

            <div class="mt-8 flex flex-col items-stretch gap-2 sm:flex-row sm:justify-center">
                <Button v-if="copy.canRetry" @click="retry"> <RotateCw class="h-4 w-4" /> Try again </Button>
                <Button v-else variant="outline" @click="goBack"> <ArrowLeft class="h-4 w-4" /> Go back </Button>
                <Button :variant="copy.canRetry ? 'outline' : 'default'" as-child>
                    <Link :href="route('dashboard')"> <LayoutDashboard class="h-4 w-4" /> Go to Dashboard </Link>
                </Button>
            </div>

            <div class="mt-4 flex justify-center">
                <ReportProblemButton label="Report a problem" button-class="text-muted-foreground hover:bg-accent hover:text-accent-foreground" />
            </div>

            <footer class="mt-10 text-sm font-semibold text-muted-foreground">TESDA Development Institute</footer>
        </main>
    </div>
</template>
