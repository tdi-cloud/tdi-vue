<script setup lang="ts">
import type { ComboboxOption } from '@/lib/positionDirectory';
import { onClickOutside } from '@vueuse/core';
import { Check, ChevronDown, ChevronLeft, ChevronRight, Loader2, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, useId } from 'vue';

/**
 * Accessible single-choice combobox in the Position Directory style: a glass
 * field on the navy hero ('hero') or a plain field in the sticky bar
 * ('compact'). Type to filter, arrow keys to move, Enter to choose, Escape to
 * close. Used for the position picker and for the Employees page filters.
 */
const props = withDefaults(
    defineProps<{
        options: ComboboxOption[];
        selected: string | null;
        /** Visible label on the hero; read by screen readers in the compact bar. */
        label: string;
        /** Shown when nothing is selected. */
        placeholder: string;
        /** Singular noun for the options, used in messages ("position"). */
        itemName: string;
        itemNamePlural?: string;
        loading?: boolean;
        /** 'hero' sits on the navy hero; 'compact' sits in the sticky bar. */
        variant?: 'hero' | 'compact';
        /** 'small': a shorter field for a group of filters. */
        size?: 'default' | 'small';
        /** Previous/next buttons beside the hero label. */
        steps?: boolean;
        /** Typing filters the options (off for short fixed lists). */
        searchable?: boolean;
        /** 'end': the option list lines up with the field's right edge (fields on the right of a row). */
        align?: 'start' | 'end';
    }>(),
    { itemNamePlural: undefined, loading: false, variant: 'hero', size: 'default', steps: false, searchable: true, align: 'start' },
);

const emit = defineEmits<{ select: [value: string] }>();

const baseId = useId();
const inputId = `${baseId}-input`;
const listId = `${baseId}-list`;

const plural = computed(() => props.itemNamePlural ?? `${props.itemName}s`);
const listLabel = computed(() => plural.value.charAt(0).toUpperCase() + plural.value.slice(1));

const rootRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const isOpen = ref(false);
const query = ref('');
const activeIndex = ref(0);

const filtered = computed(() => {
    const tokens = query.value.toLowerCase().split(/\s+/).filter(Boolean);
    if (tokens.length === 0) {
        return props.options;
    }
    return props.options.filter((option) => {
        const label = option.label.toLowerCase();
        return tokens.every((token) => label.includes(token));
    });
});

const selectedIndex = computed(() => props.options.findIndex((option) => option.value === props.selected));
const selectedLabel = computed(() => props.options[selectedIndex.value]?.label ?? props.selected ?? '');
const previousOption = computed(() => (selectedIndex.value > 0 ? props.options[selectedIndex.value - 1] : null));
const nextOption = computed(() =>
    selectedIndex.value >= 0 && selectedIndex.value < props.options.length - 1 ? props.options[selectedIndex.value + 1] : null,
);

const optionId = (index: number) => `${baseId}-option-${index}`;

/** Hero field: step the font down for long names so they stay readable in full. */
const fieldStyle = computed(() => {
    if (props.variant !== 'hero' || props.size !== 'default' || isOpen.value || !selectedLabel.value) {
        return undefined;
    }
    const length = selectedLabel.value.length;
    return { '--pd-selector-font-size': length > 34 ? '0.8rem' : length > 26 ? '0.88rem' : '0.98rem' };
});
const activeDescendant = computed(() => (isOpen.value && filtered.value[activeIndex.value] ? optionId(activeIndex.value) : undefined));
const showsQuery = computed(() => isOpen.value && props.searchable);

function scrollActiveIntoView(): void {
    nextTick(() => document.getElementById(optionId(activeIndex.value))?.scrollIntoView({ block: 'nearest' }));
}

/** Open upwards when there isn't room for the list below (e.g. low in the side panel). */
const opensUpward = ref(false);

function open(): void {
    if (isOpen.value) {
        return;
    }
    const rect = rootRef.value?.getBoundingClientRect();
    if (rect) {
        const spaceBelow = window.innerHeight - rect.bottom;
        opensUpward.value = spaceBelow < 320 && rect.top > spaceBelow;
    }
    query.value = '';
    isOpen.value = true;
    activeIndex.value = Math.max(
        0,
        filtered.value.findIndex((option) => option.value === props.selected),
    );
    scrollActiveIntoView();
}

function close(): void {
    isOpen.value = false;
    query.value = '';
}

function choose(value: string): void {
    close();
    if (value !== props.selected) {
        emit('select', value);
    }
}

function step(direction: -1 | 1): void {
    const target = direction === -1 ? previousOption.value : nextOption.value;
    if (target) {
        emit('select', target.value);
    }
}

function onInput(event: Event): void {
    query.value = (event.target as HTMLInputElement).value;
    isOpen.value = true;
    activeIndex.value = 0;
}

function onKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            if (!isOpen.value) {
                open();
                return;
            }
            activeIndex.value = Math.min(filtered.value.length - 1, activeIndex.value + 1);
            scrollActiveIntoView();
            break;
        case 'ArrowUp':
            event.preventDefault();
            if (!isOpen.value) {
                open();
                return;
            }
            activeIndex.value = Math.max(0, activeIndex.value - 1);
            scrollActiveIntoView();
            break;
        case 'Enter': {
            const option = filtered.value[activeIndex.value];
            if (isOpen.value && option) {
                event.preventDefault();
                choose(option.value);
            }
            break;
        }
        case 'Escape':
            if (isOpen.value) {
                event.preventDefault();
                close();
            }
            break;
        case 'Tab':
            close();
            break;
    }
}

function toggle(): void {
    if (isOpen.value) {
        close();
    } else {
        open();
        inputRef.value?.focus();
    }
}

function clearQuery(): void {
    query.value = '';
    activeIndex.value = 0;
    inputRef.value?.focus();
}

onClickOutside(rootRef, close);

defineExpose({ focus: () => inputRef.value?.focus() });
</script>

<template>
    <div
        ref="rootRef"
        class="pd-selector"
        :class="[
            `pd-selector--${variant}`,
            `pd-selector--${size}`,
            { 'is-open': isOpen, 'opens-upward': opensUpward, 'aligns-end': align === 'end' },
        ]"
    >
        <!-- Hero: label and the previous/next buttons share one row, so the field gets the full width -->
        <div v-if="variant === 'hero'" class="pd-selector__label-row">
            <label :for="inputId" class="pd-selector__label">{{ label }}</label>
            <div v-if="steps" class="pd-selector__steps">
                <button
                    type="button"
                    class="pd-selector__step"
                    :disabled="!previousOption || loading"
                    :aria-label="previousOption ? `Previous ${itemName}: ${previousOption.label}` : `No previous ${itemName}`"
                    :title="previousOption?.label"
                    @click="step(-1)"
                >
                    <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="pd-selector__step"
                    :disabled="!nextOption || loading"
                    :aria-label="nextOption ? `Next ${itemName}: ${nextOption.label}` : `No next ${itemName}`"
                    :title="nextOption?.label"
                    @click="step(1)"
                >
                    <ChevronRight class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </div>
        <label v-else :for="inputId" class="sr-only">{{ label }}</label>

        <div class="pd-selector__row">
            <div class="pd-selector__field" :style="fieldStyle">
                <Loader2 v-if="loading" class="pd-selector__icon animate-spin" aria-hidden="true" />
                <Search v-else-if="searchable" class="pd-selector__icon" aria-hidden="true" />

                <input
                    :id="inputId"
                    ref="inputRef"
                    class="pd-selector__input"
                    type="text"
                    role="combobox"
                    autocomplete="off"
                    spellcheck="false"
                    :readonly="!searchable || undefined"
                    :aria-autocomplete="searchable ? 'list' : 'none'"
                    :aria-expanded="isOpen"
                    :aria-controls="listId"
                    :aria-activedescendant="activeDescendant"
                    :aria-busy="loading"
                    :value="showsQuery ? query : selectedLabel"
                    :title="showsQuery ? undefined : selectedLabel || undefined"
                    :placeholder="showsQuery ? `Search ${options.length.toLocaleString()} ${plural}…` : placeholder"
                    @focus="open"
                    @click="open"
                    @input="onInput"
                    @keydown="onKeydown"
                />

                <button v-if="isOpen && query" type="button" class="pd-selector__clear" aria-label="Clear search" @click="clearQuery">
                    <X class="h-3.5 w-3.5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="pd-selector__toggle"
                    tabindex="-1"
                    :aria-label="isOpen ? `Hide ${plural}` : `Show all ${plural}`"
                    @click="toggle"
                >
                    <ChevronDown class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': isOpen }" aria-hidden="true" />
                </button>
            </div>
        </div>

        <Transition name="pd-panel">
            <div v-show="isOpen" class="pd-selector__panel">
                <p v-if="searchable" class="pd-selector__meta" aria-live="polite">
                    {{ filtered.length.toLocaleString() }} of {{ options.length.toLocaleString() }} {{ plural }}
                </p>
                <ul :id="listId" role="listbox" :aria-label="listLabel" class="pd-selector__list">
                    <li
                        v-for="(option, index) in filtered"
                        :id="optionId(index)"
                        :key="option.value"
                        role="option"
                        :aria-selected="option.value === selected"
                        class="pd-selector__option"
                        :class="{ 'is-active': index === activeIndex, 'is-selected': option.value === selected }"
                        @mousedown.prevent="choose(option.value)"
                        @mousemove="activeIndex = index"
                    >
                        <span class="pd-selector__option-name">{{ option.label }}</span>
                        <span v-if="option.count != null" class="pd-selector__option-count">{{ option.count.toLocaleString() }}</span>
                        <Check v-if="option.value === selected" class="pd-selector__option-check" aria-hidden="true" />
                    </li>
                    <li v-if="filtered.length === 0" role="presentation" class="pd-selector__empty">No {{ itemName }} matches “{{ query }}”.</li>
                </ul>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pd-selector {
    position: relative;
    width: 100%;
}

.pd-selector__label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.4rem;
}

.pd-selector__steps {
    display: flex;
    gap: 0.35rem;
}

.pd-selector__label {
    display: block;
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(226, 194, 122, 0.95);
}

.pd-selector__row {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
}

.pd-selector__field {
    position: relative;
    display: flex;
    flex: 1;
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

.pd-selector__icon {
    width: 1.05rem;
    height: 1.05rem;
    flex-shrink: 0;
}

/*
 * Doubled class on purpose: app.css gives every text input a white background
 * and dark text with a high-specificity selector
 * (input:not([type='checkbox']):not(...)), which would otherwise win here.
 */
.pd-selector .pd-selector__field .pd-selector__input.pd-selector__input {
    min-width: 0;
    flex: 1;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: var(--pd-selector-font-size);
    font-weight: 600;
    color: var(--pd-selector-text);
    outline: none;
    box-shadow: none;
    text-overflow: ellipsis;
    cursor: text;
}

.pd-selector .pd-selector__field .pd-selector__input.pd-selector__input[readonly] {
    cursor: pointer;
}

.pd-selector .pd-selector__field .pd-selector__input.pd-selector__input::placeholder {
    color: var(--pd-selector-placeholder);
}

.pd-selector__clear,
.pd-selector__toggle {
    display: inline-flex;
    height: 1.9rem;
    width: 1.9rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    transition: background-color 0.2s ease;
}

.pd-selector__step {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.06);
    color: #eaf1ff;
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        opacity 0.2s ease;
}

.pd-selector__step:hover:not(:disabled) {
    border-color: rgba(226, 194, 122, 0.6);
    background: rgba(255, 255, 255, 0.12);
}

.pd-selector__step:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.pd-selector__step:focus-visible,
.pd-selector__clear:focus-visible {
    outline: 2px solid #e2c27a;
    outline-offset: 2px;
}

/* Hero variant: glass field on the navy hero */
.pd-selector--hero .pd-selector__field {
    height: 3.25rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: linear-gradient(120deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04));
    color: #f4f7ff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.pd-selector--hero {
    --pd-selector-font-size: 0.98rem;
    --pd-selector-text: #ffffff;
    --pd-selector-placeholder: rgba(226, 234, 255, 0.6);
}

.pd-selector--hero .pd-selector__icon {
    color: #e2c27a;
}

.pd-selector--hero .pd-selector__clear:hover,
.pd-selector--hero .pd-selector__toggle:hover {
    background: rgba(255, 255, 255, 0.12);
}

.pd-selector--hero.is-open .pd-selector__field,
.pd-selector--hero .pd-selector__field:focus-within {
    border-color: rgba(226, 194, 122, 0.75);
    box-shadow:
        0 0 0 4px rgba(226, 194, 122, 0.16),
        0 0 32px rgba(47, 107, 255, 0.35);
}

/* Compact variant: inside the sticky bar */
.pd-selector--compact .pd-selector__field {
    height: 2.5rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    color: var(--pd-ink);
}

.pd-selector--compact {
    --pd-selector-font-size: 0.875rem;
    --pd-selector-text: var(--pd-ink);
    --pd-selector-placeholder: var(--pd-ink-soft);
}

.pd-selector--compact .pd-selector__icon {
    color: var(--pd-royal);
}

.pd-selector--compact .pd-selector__clear:hover,
.pd-selector--compact .pd-selector__toggle:hover {
    background: var(--pd-hover);
}

.pd-selector--compact.is-open .pd-selector__field,
.pd-selector--compact .pd-selector__field:focus-within {
    border-color: var(--pd-royal);
    box-shadow: 0 0 0 3px var(--pd-focus-ring);
}

/* Small size: a group of filters */
.pd-selector--small {
    --pd-selector-font-size: 0.85rem;
}

.pd-selector--small .pd-selector__label-row {
    margin-bottom: 0.3rem;
}

.pd-selector--hero.pd-selector--small .pd-selector__field {
    height: 2.6rem;
    border-radius: 0.8rem;
    padding-left: 0.75rem;
}

.pd-selector--small .pd-selector__panel {
    min-width: min(17rem, calc(100vw - 2rem));
}

/* Option panel */
.pd-selector__panel {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 0.5rem);
    z-index: 40;
    overflow: hidden;
    border-radius: 1rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    color: var(--pd-ink);
    box-shadow:
        0 24px 60px -20px rgba(8, 23, 64, 0.55),
        0 0 0 1px rgba(226, 194, 122, 0.12);
}

.pd-selector.opens-upward .pd-selector__panel {
    top: auto;
    bottom: calc(100% + 0.5rem);
}

/* Grows to the left when it needs more room than the field */
.pd-selector.aligns-end .pd-selector__panel {
    left: auto;
}

.pd-selector--compact .pd-selector__panel {
    min-width: min(26rem, calc(100vw - 2rem));
}

.pd-selector__meta {
    border-bottom: 1px solid var(--pd-border);
    padding: 0.55rem 0.9rem;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pd-ink-soft);
}

.pd-selector__list {
    max-height: min(22rem, 55vh);
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0.35rem;
}

.pd-selector__option {
    position: relative;
    display: flex;
    cursor: pointer;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.65rem;
    padding: 0.55rem 0.7rem;
    font-size: 0.875rem;
}

.pd-selector__option.is-active {
    background: var(--pd-hover);
}

.pd-selector__option.is-selected {
    font-weight: 700;
    color: var(--pd-royal);
}

.pd-selector__option.is-selected::before {
    content: '';
    position: absolute;
    left: 0;
    top: 22%;
    bottom: 22%;
    width: 3px;
    border-radius: 9999px;
    background: var(--pd-gold);
}

.pd-selector__option-name {
    min-width: 0;
    flex: 1;
}

.pd-selector__option-count {
    flex-shrink: 0;
    border-radius: 9999px;
    background: var(--pd-chip);
    padding: 0.1rem 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--pd-ink-soft);
}

.pd-selector__option-check {
    height: 1rem;
    width: 1rem;
    flex-shrink: 0;
    color: var(--pd-gold);
}

.pd-selector__empty {
    padding: 1rem;
    text-align: center;
    font-size: 0.875rem;
    color: var(--pd-ink-soft);
}

.pd-panel-enter-active,
.pd-panel-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.pd-panel-enter-from,
.pd-panel-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.98);
}

@media (prefers-reduced-motion: reduce) {
    .pd-panel-enter-active,
    .pd-panel-leave-active {
        transition: opacity 0.1s linear;
    }

    .pd-panel-enter-from,
    .pd-panel-leave-to {
        transform: none;
    }
}
</style>
