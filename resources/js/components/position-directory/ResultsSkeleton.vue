<script setup lang="ts">
import type { MotionLevel } from '@/lib/positionDirectory';

/** Shimmering placeholder cards shown while results load (partial reloads). */
withDefaults(
    defineProps<{
        /** Read out by screen readers, e.g. "Loading employees…". */
        label: string;
        motionLevel: MotionLevel;
        cards?: number;
    }>(),
    { cards: 6 },
);
</script>

<template>
    <div class="pd-results__loading" :data-motion="motionLevel" role="status">
        <span class="sr-only">{{ label }}</span>
        <div class="pd-skeleton-header" aria-hidden="true">
            <span class="pd-skeleton pd-skeleton--index" />
            <span class="pd-skeleton pd-skeleton--title" />
        </div>
        <div class="pd-skeleton-grid" aria-hidden="true">
            <div v-for="index in cards" :key="index" class="pd-skeleton-card" :style="{ animationDelay: `${index * 90}ms` }">
                <span class="pd-skeleton pd-skeleton--photo" />
                <span class="pd-skeleton pd-skeleton--line" />
                <span class="pd-skeleton pd-skeleton--line is-short" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.pd-results__loading {
    border-radius: 1.75rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface);
    padding: 1.75rem;
}

.pd-skeleton-header {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.pd-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 13rem), 1fr));
    gap: 1.25rem;
}

.pd-skeleton-card {
    display: grid;
    gap: 0.6rem;
    border-radius: 1.25rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-card-surface);
    padding: 0 0 1rem;
    overflow: hidden;
    animation: pd-skeleton-rise 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.pd-skeleton {
    position: relative;
    display: block;
    overflow: hidden;
    border-radius: 0.5rem;
    background: var(--pd-skeleton);
}

.pd-skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, transparent 30%, var(--pd-skeleton-shine) 50%, transparent 70%);
    transform: translateX(-100%);
    animation: pd-skeleton-shine 1.4s ease-in-out infinite;
}

.pd-skeleton--index {
    width: 3rem;
    height: 2.25rem;
}

.pd-skeleton--title {
    width: min(20rem, 60%);
    height: 1.5rem;
}

.pd-skeleton--photo {
    aspect-ratio: 10 / 11;
    border-radius: 0;
}

.pd-skeleton--line {
    height: 0.8rem;
    margin: 0 1rem;
}

.pd-skeleton--line.is-short {
    width: 50%;
}

@keyframes pd-skeleton-shine {
    to {
        transform: translateX(100%);
    }
}

@keyframes pd-skeleton-rise {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
}

.pd-results__loading[data-motion='still'] .pd-skeleton::after,
.pd-results__loading[data-motion='still'] .pd-skeleton-card {
    animation: none;
}
</style>
