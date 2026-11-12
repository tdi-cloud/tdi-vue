<script setup lang="ts">
import { Loader2, Search, X } from 'lucide-vue-next';
import { ref, useId } from 'vue';

/**
 * Free-text search field in the Position Directory style: a glass field with
 * a gold focus ring on the navy hero ('hero') or a plain field in the sticky
 * bar ('compact'). Escape clears the text.
 */
withDefaults(
    defineProps<{
        /** Visible label on the hero; read by screen readers in the compact bar. */
        label: string;
        placeholder: string;
        loading?: boolean;
        variant?: 'hero' | 'compact';
    }>(),
    { loading: false, variant: 'hero' },
);

const model = defineModel<string>({ required: true });

const inputId = `${useId()}-search`;
const inputRef = ref<HTMLInputElement | null>(null);

function clear(): void {
    model.value = '';
    inputRef.value?.focus();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && model.value !== '') {
        event.preventDefault();
        model.value = '';
    }
}

defineExpose({ focus: () => inputRef.value?.focus() });
</script>

<template>
    <div class="pd-search" :class="`pd-search--${variant}`">
        <label :for="inputId" :class="variant === 'hero' ? 'pd-search__label' : 'sr-only'">{{ label }}</label>

        <div class="pd-search__field">
            <Loader2 v-if="loading" class="pd-search__icon animate-spin" aria-hidden="true" />
            <Search v-else class="pd-search__icon" aria-hidden="true" />

            <input
                :id="inputId"
                ref="inputRef"
                v-model="model"
                class="pd-search__input"
                type="text"
                inputmode="search"
                enterkeyhint="search"
                autocomplete="off"
                spellcheck="false"
                :placeholder="placeholder"
                :aria-busy="loading"
                @keydown="onKeydown"
            />

            <button v-if="model" type="button" class="pd-search__clear" aria-label="Clear search" @click="clear">
                <X class="h-3.5 w-3.5" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.pd-search {
    position: relative;
    width: 100%;
}

.pd-search__label {
    display: block;
    margin-bottom: 0.4rem;
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(226, 194, 122, 0.95);
}

.pd-search__field {
    position: relative;
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.9rem;
    padding: 0 0.5rem 0 0.9rem;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease;
}

.pd-search__icon {
    width: 1.05rem;
    height: 1.05rem;
    flex-shrink: 0;
}

/*
 * Doubled class on purpose: app.css gives every text input a white background
 * and dark text with a high-specificity selector
 * (input:not([type='checkbox']):not(...)), which would otherwise win here.
 */
.pd-search .pd-search__field .pd-search__input.pd-search__input {
    min-width: 0;
    flex: 1;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: var(--pd-search-font-size);
    font-weight: 600;
    color: var(--pd-search-text);
    outline: none;
    box-shadow: none;
    text-overflow: ellipsis;
}

.pd-search .pd-search__field .pd-search__input.pd-search__input::placeholder {
    font-weight: 500;
    color: var(--pd-search-placeholder);
}

.pd-search__clear {
    display: inline-flex;
    height: 1.9rem;
    width: 1.9rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    transition: background-color 0.2s ease;
}

.pd-search__clear:focus-visible {
    outline: 2px solid #e2c27a;
    outline-offset: 2px;
}

/* Hero variant: glass field on the navy hero */
.pd-search--hero {
    --pd-search-font-size: 0.95rem;
    --pd-search-text: #ffffff;
    --pd-search-placeholder: rgba(226, 234, 255, 0.6);
}

.pd-search--hero .pd-search__field {
    height: 3.1rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: linear-gradient(120deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04));
    color: #f4f7ff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.pd-search--hero .pd-search__icon {
    color: #e2c27a;
}

.pd-search--hero .pd-search__clear:hover {
    background: rgba(255, 255, 255, 0.12);
}

.pd-search--hero .pd-search__field:focus-within {
    border-color: rgba(226, 194, 122, 0.75);
    box-shadow:
        0 0 0 4px rgba(226, 194, 122, 0.16),
        0 0 32px rgba(47, 107, 255, 0.35);
}

/* Compact variant: inside the sticky bar */
.pd-search--compact {
    --pd-search-font-size: 0.875rem;
    --pd-search-text: var(--pd-ink);
    --pd-search-placeholder: var(--pd-ink-soft);
}

.pd-search--compact .pd-search__field {
    height: 2.5rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    color: var(--pd-ink);
}

.pd-search--compact .pd-search__icon {
    color: var(--pd-royal);
}

.pd-search--compact .pd-search__clear:hover {
    background: var(--pd-hover);
}

.pd-search--compact .pd-search__field:focus-within {
    border-color: var(--pd-royal);
    box-shadow: 0 0 0 3px var(--pd-focus-ring);
}
</style>
