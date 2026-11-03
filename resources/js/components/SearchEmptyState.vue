<script setup lang="ts">
import EmptyState, { type EmptyStateAction } from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';

/**
 * "No results" for a search: names the query, offers a one-click typo fix
 * ("Did you mean …?"), then next steps (clear filters, broaden, browse all).
 * Pass the server's `suggestion` prop, or one from lib/searchSuggestion.ts
 * for lists filtered in the browser.
 */
const props = withDefaults(
    defineProps<{
        query: string;
        suggestion?: string | null;
        description?: string;
        // Extra next steps, e.g. { label: 'Search all years', onClick } or { label: 'Browse all programs', href }.
        nextSteps?: EmptyStateAction[];
        compact?: boolean;
        onClearFilters?: () => void;
        class?: HTMLAttributes['class'];
    }>(),
    { suggestion: null, description: 'Check the spelling, or try fewer or more general words.', nextSteps: () => [], compact: false },
);

const emit = defineEmits<{
    // Re-run the search with this term (the suggestion).
    (e: 'search', term: string): void;
}>();
</script>

<template>
    <EmptyState
        variant="filtered"
        :title="`No results for “${query.trim()}”`"
        :description="description"
        :compact="compact"
        :class="props.class"
        :on-clear-filters="onClearFilters"
    >
        <template v-if="suggestion" #details>
            <p class="text-sm">
                Did you mean
                <button
                    type="button"
                    class="font-semibold text-blue-600 underline-offset-2 hover:underline dark:text-blue-400"
                    @click="emit('search', suggestion)"
                >
                    “{{ suggestion }}”</button
                >?
            </p>
        </template>

        <template v-for="step in nextSteps" :key="step.label">
            <Button v-if="step.href" variant="outline" size="sm" as-child>
                <Link :href="step.href">{{ step.label }}</Link>
            </Button>
            <Button v-else variant="outline" size="sm" @click="step.onClick?.()">{{ step.label }}</Button>
        </template>
    </EmptyState>
</template>
