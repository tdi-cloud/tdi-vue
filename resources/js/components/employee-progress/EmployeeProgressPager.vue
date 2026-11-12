<script setup lang="ts">
import type { PaginationLink } from '@/lib/employeeProgress';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Pagination in the Position Directory style. The links stay real links (open
 * in a new tab works); a normal click asks the page for an Inertia visit.
 */
const props = defineProps<{
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    loading: boolean;
}>();

const emit = defineEmits<{ visit: [url: string] }>();

// Laravel's paginator always puts "Previous" first and "Next" last.
const pages = computed(() =>
    props.links.map((link, index) => {
        const isPrevious = index === 0;
        const isNext = index === props.links.length - 1;
        const isGap = !isPrevious && !isNext && !/^\d+$/.test(link.label);
        return {
            ...link,
            key: `${index}-${link.label}`,
            isPrevious,
            isNext,
            isGap,
            ariaLabel: isPrevious ? 'Previous page' : isNext ? 'Next page' : isGap ? undefined : `Page ${link.label}`,
        };
    }),
);

function onClick(event: MouseEvent, url: string): void {
    // Let modified clicks (new tab, new window) behave like a normal link.
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
        return;
    }
    event.preventDefault();
    if (!props.loading) {
        emit('visit', url);
    }
}
</script>

<template>
    <nav class="pd-pager" aria-label="Pagination">
        <p class="pd-pager__summary">
            Showing
            <strong>{{ (from ?? 0).toLocaleString() }}–{{ (to ?? 0).toLocaleString() }}</strong>
            of
            <strong>{{ total.toLocaleString() }}</strong>
        </p>

        <ul v-if="links.length > 3" class="pd-pager__links">
            <li v-for="page in pages" :key="page.key">
                <span v-if="page.isGap" class="pd-pager__gap" aria-hidden="true">…</span>
                <a
                    v-else-if="page.url && !page.active"
                    :href="page.url"
                    class="pd-pager__link"
                    :class="{ 'is-arrow': page.isPrevious || page.isNext }"
                    :aria-label="page.ariaLabel"
                    @click="onClick($event, page.url)"
                >
                    <ChevronLeft v-if="page.isPrevious" class="h-4 w-4" aria-hidden="true" />
                    <ChevronRight v-else-if="page.isNext" class="h-4 w-4" aria-hidden="true" />
                    <template v-else>{{ page.label }}</template>
                </a>
                <span
                    v-else
                    class="pd-pager__link"
                    :class="{ 'is-active': page.active, 'is-disabled': !page.active, 'is-arrow': page.isPrevious || page.isNext }"
                    :aria-current="page.active ? 'page' : undefined"
                    :aria-label="page.ariaLabel"
                    :aria-disabled="page.active ? undefined : 'true'"
                >
                    <ChevronLeft v-if="page.isPrevious" class="h-4 w-4" aria-hidden="true" />
                    <ChevronRight v-else-if="page.isNext" class="h-4 w-4" aria-hidden="true" />
                    <template v-else>{{ page.label }}</template>
                </span>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.pd-pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem 1rem;
    border-radius: 1.1rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface);
    padding: 0.55rem 0.75rem;
}

.pd-pager__summary {
    font-size: 0.78rem;
    color: var(--pd-ink-soft);
}

.pd-pager__summary strong {
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--pd-ink);
}

.pd-pager__links {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
}

.pd-pager__link,
.pd-pager__gap {
    display: inline-flex;
    min-width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    padding: 0 0.55rem;
    font-size: 0.78rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pd-pager__link {
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    color: var(--pd-ink);
    transition:
        border-color 0.2s ease,
        background-color 0.2s ease;
}

.pd-pager__link.is-arrow {
    padding: 0;
}

a.pd-pager__link:hover {
    border-color: var(--pd-royal);
}

a.pd-pager__link:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

.pd-pager__link.is-active {
    border-color: transparent;
    background: linear-gradient(120deg, var(--pd-navy), var(--pd-royal));
    color: #ffffff;
    box-shadow: 0 0 0 2px var(--pd-gold-halo);
}

.pd-pager__link.is-disabled {
    opacity: 0.4;
}

.pd-pager__gap {
    min-width: 1.25rem;
    padding: 0;
    color: var(--pd-ink-soft);
}
</style>
