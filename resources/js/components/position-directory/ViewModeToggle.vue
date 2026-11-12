<script setup lang="ts">
import { GalleryHorizontalEnd, LayoutGrid } from 'lucide-vue-next';

/** Showcase (3D fan) / Grid switch above the results. */
const viewMode = defineModel<'showcase' | 'grid'>({ required: true });
</script>

<template>
    <div class="pd-view-toggle" role="group" aria-label="Layout">
        <button type="button" class="pd-view-toggle__button" :aria-pressed="viewMode === 'showcase'" @click="viewMode = 'showcase'">
            <GalleryHorizontalEnd class="h-4 w-4" aria-hidden="true" />
            Showcase
        </button>
        <button type="button" class="pd-view-toggle__button" :aria-pressed="viewMode === 'grid'" @click="viewMode = 'grid'">
            <LayoutGrid class="h-4 w-4" aria-hidden="true" />
            Grid
        </button>
    </div>
</template>

<style scoped>
.pd-view-toggle {
    display: inline-flex;
    gap: 0.25rem;
    border-radius: 9999px;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    padding: 0.25rem;
}

.pd-view-toggle__button {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 9999px;
    padding: 0.35rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--pd-ink-soft);
    transition:
        background-color 0.25s ease,
        color 0.25s ease;
}

.pd-view-toggle__button:hover {
    color: var(--pd-ink);
}

.pd-view-toggle__button[aria-pressed='true'] {
    background: linear-gradient(120deg, var(--pd-navy), var(--pd-royal));
    color: #ffffff;
    box-shadow: 0 6px 16px -8px var(--pd-royal);
}

.pd-view-toggle__button:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}
</style>
