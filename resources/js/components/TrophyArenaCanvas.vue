<script setup lang="ts">
import type { TrophyArenaHandle } from '@/lib/trophyArena';
import { Trophy } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    reducedMotion: boolean;
    paused?: boolean;
}>();

const containerRef = ref<HTMLDivElement | null>(null);
const isReady = ref(false);
const hasFailed = ref(false);

let arena: TrophyArenaHandle | null = null;
let isUnmounted = false;

// Three.js is loaded on demand so the dashboard bundle only pays for it when
// the ranking modal is opened.
onMounted(async () => {
    try {
        const { createTrophyArena } = await import('@/lib/trophyArena');
        if (isUnmounted || !containerRef.value) {
            return;
        }
        arena = createTrophyArena(containerRef.value, { reducedMotion: props.reducedMotion });
        arena.setPaused(!!props.paused);
        isReady.value = true;
    } catch {
        hasFailed.value = true;
    }
});

watch(
    () => props.paused,
    (paused) => arena?.setPaused(!!paused),
);

onBeforeUnmount(() => {
    isUnmounted = true;
    arena?.dispose();
    arena = null;
});
</script>

<template>
    <div class="relative h-full w-full">
        <div
            ref="containerRef"
            class="absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none"
            :class="isReady ? 'opacity-100' : 'opacity-0'"
            aria-hidden="true"
        />

        <!-- CSS fallback when WebGL is unavailable -->
        <div v-if="hasFailed" class="rr-fallback-trophy absolute inset-0 flex items-end justify-center pb-6" aria-hidden="true">
            <Trophy class="h-28 w-28 sm:h-36 sm:w-36" :stroke-width="1.25" />
        </div>
    </div>
</template>
