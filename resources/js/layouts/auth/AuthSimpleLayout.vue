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
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-gradient-to-b from-blue-50 via-white to-white p-6 md:p-10"
    >
        <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-blue-200/40 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-blue-300/30 blur-3xl"></div>

        <div class="relative w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-3">
                    <Link :href="route('home')" class="flex flex-col items-center gap-3 font-medium">
                        <div class="relative flex h-16 w-16 items-center justify-center">
                            <div
                                class="absolute -inset-4 animate-[spin_9s_linear_infinite] rounded-full bg-[conic-gradient(from_0deg,transparent_0deg,rgba(37,99,235,0.55)_100deg,transparent_220deg,transparent_360deg)] opacity-60 blur-lg"
                            ></div>
                            <div
                                class="absolute -inset-2 animate-[spin_6s_linear_infinite_reverse] rounded-full bg-[conic-gradient(from_180deg,transparent_0deg,rgba(96,165,250,0.5)_80deg,transparent_180deg,transparent_360deg)] opacity-50 blur-md"
                            ></div>
                            <img
                                v-if="!logoFailed"
                                src="/storage/images/tesda-logo.png"
                                alt="TESDA"
                                class="relative z-10 h-16 w-16 rounded-full object-contain shadow-lg ring-4 ring-white"
                                @error="logoFailed = true"
                            />
                            <div
                                v-else
                                class="relative z-10 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-b from-blue-700 to-blue-900 font-extrabold text-white shadow-lg ring-4 ring-white"
                            >
                                TDI
                            </div>
                        </div>
                        <div class="text-center leading-tight">
                            <p class="text-xs font-bold uppercase tracking-widest text-blue-900">TESDA Development Institute</p>
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

                <p class="text-center text-xs text-muted-foreground">
                    © {{ new Date().getFullYear() }} TESDA Development Institute. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>
