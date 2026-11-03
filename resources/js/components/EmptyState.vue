<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import { Inbox, SearchX } from 'lucide-vue-next';
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';

export interface EmptyStateAction {
    label: string;
    href?: string;
    onClick?: () => void;
    icon?: Component;
}

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        // 'first-use' = nothing exists yet; 'filtered' = data exists but the search/filters hide it.
        variant?: 'first-use' | 'filtered';
        action?: EmptyStateAction;
        // Smaller spacing for modals, panels and dropdowns.
        compact?: boolean;
        // Declared as a prop (not an emit) so we only show "Clear filters" when the page handles it.
        onClearFilters?: () => void;
        class?: HTMLAttributes['class'];
    }>(),
    { variant: 'first-use', compact: false },
);

const resolvedIcon = computed(() => props.icon ?? (props.variant === 'filtered' ? SearchX : Inbox));
</script>

<template>
    <div
        role="status"
        :class="
            cn(
                'flex flex-col items-center justify-center gap-2 text-center',
                compact ? 'px-4 py-8' : 'rounded-2xl border border-dashed border-border bg-card px-6 py-16',
                props.class,
            )
        "
    >
        <div class="flex items-center justify-center rounded-full bg-muted" :class="compact ? 'mb-1 h-10 w-10' : 'mb-2 h-14 w-14'">
            <component :is="resolvedIcon" class="text-muted-foreground" :class="compact ? 'h-5 w-5' : 'h-7 w-7'" aria-hidden="true" />
        </div>

        <p class="text-sm font-semibold text-foreground">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-xs leading-relaxed text-muted-foreground">{{ description }}</p>

        <!-- Extra content, e.g. "Did you mean …?" suggestions. -->
        <slot name="details" />

        <div v-if="action || onClearFilters || $slots.default" class="mt-2 flex flex-wrap items-center justify-center gap-2">
            <Button v-if="variant === 'filtered' && onClearFilters" variant="outline" size="sm" @click="onClearFilters()">Clear filters</Button>

            <template v-if="action">
                <Button v-if="action.href" size="sm" as-child>
                    <Link :href="action.href"><component :is="action.icon" v-if="action.icon" class="h-4 w-4" /> {{ action.label }}</Link>
                </Button>
                <Button v-else size="sm" @click="action.onClick?.()">
                    <component :is="action.icon" v-if="action.icon" class="h-4 w-4" /> {{ action.label }}
                </Button>
            </template>

            <slot />
        </div>
    </div>
</template>
