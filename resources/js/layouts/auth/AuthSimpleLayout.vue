<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const logoFailed = ref(false);
</script>

<template>
    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-gradient-to-b from-blue-50 via-white to-white p-6 md:p-10">
        <div class="pointer-events-none absolute -top-32 -left-32 h-96 w-96 rounded-full bg-blue-200/40 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-32 -bottom-32 h-96 w-96 rounded-full bg-blue-300/30 blur-3xl"></div>

        <div class="relative w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-3">
                    <Link :href="route('home')" class="flex flex-col items-center gap-3 font-medium">
                        <img
                            v-if="!logoFailed"
                            src="/storage/images/tesda-logo.png"
                            alt="TESDA"
                            class="h-16 w-16 rounded-full object-contain shadow-lg ring-4 ring-white"
                            @error="logoFailed = true"
                        />
                        <div v-else class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-b from-blue-700 to-blue-900 font-extrabold text-white shadow-lg ring-4 ring-white">
                            TDI
                        </div>
                        <div class="text-center leading-tight">
                            <p class="text-xs font-bold tracking-widest text-blue-900 uppercase">TESDA</p>
                            <p class="text-[11px] text-muted-foreground">Technical Education and Skills Development Authority</p>
                        </div>
                    </Link>
                </div>

                <div class="rounded-2xl border border-blue-100 bg-white/80 p-8 shadow-xl shadow-blue-900/5 backdrop-blur-sm">
                    <div class="mb-6 space-y-1 text-center">
                        <h1 class="text-xl font-extrabold text-gray-900">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                    <slot />
                </div>

                <p class="text-center text-xs text-muted-foreground">© {{ new Date().getFullYear() }} TESDA Development Institute. All rights reserved.</p>
            </div>
        </div>
    </div>
</template>
