<script setup lang="ts">
import { ref, computed } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown, Search, X, Check } from 'lucide-vue-next';

/**
 * Generic na multi-select filter dropdown na may search box, "Select all"
 * (sa currently-filtered/visible na options), at "Clear" (buong selection).
 * Empty array ang modelValue ay nangangahulugang "walang filter" (lahat).
 */
const props = defineProps<{
    modelValue: string[];
    options: string[];
    label: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [string[]];
}>();

const open = ref(false);
const query = ref('');
const rootRef = ref<HTMLElement | null>(null);

onClickOutside(rootRef, () => {
    open.value = false;
});

const filteredOptions = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => o.toLowerCase().includes(q));
});

const isSelected = (option: string) => props.modelValue.includes(option);

function toggle(option: string) {
    if (isSelected(option)) {
        emit('update:modelValue', props.modelValue.filter((o) => o !== option));
    } else {
        emit('update:modelValue', [...props.modelValue, option]);
    }
}

/** Sine-select ang lahat ng CURRENTLY VISIBLE (na-search) na options — hindi kailangang lahat ng options overall. */
function selectAllVisible() {
    const merged = new Set([...props.modelValue, ...filteredOptions.value]);
    emit('update:modelValue', Array.from(merged));
}

/** Buong selection ang nili-clear, hindi lang yung nakikita sa search. */
function clearAll() {
    emit('update:modelValue', []);
}

const triggerLabel = computed(() => {
    if (props.modelValue.length === 0) return props.placeholder ?? 'All';
    if (props.modelValue.length === 1) return props.modelValue[0];
    return `${props.modelValue.length} selected`;
});

const allVisibleSelected = computed(() =>
    filteredOptions.value.length > 0 && filteredOptions.value.every((o) => isSelected(o))
);
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            class="h-8 w-full text-xs shadow-md rounded-md border bg-background px-3 flex items-center justify-between gap-2 hover:bg-muted/40 transition-colors"
            @click="open = !open"
        >
            <span class="truncate" :class="modelValue.length ? 'font-semibold' : 'text-muted-foreground'">{{ triggerLabel }}</span>
            <ChevronDown class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-64 max-w-[80vw] rounded-lg border bg-popover shadow-lg overflow-hidden flex flex-col"
        >
            <!-- Search -->
            <div class="p-2 border-b relative">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                <input
                    v-model="query"
                    type="text"
                    :placeholder="`Search ${label.toLowerCase()}...`"
                    class="w-full h-7 pl-7 pr-2 text-xs rounded border bg-background focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <!-- Select all / Clear -->
            <div class="flex items-center justify-between px-2 py-1.5 border-b text-[11px]">
                <button
                    type="button"
                    class="font-semibold text-blue-600 hover:text-blue-700 disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="filteredOptions.length === 0 || allVisibleSelected"
                    @click="selectAllVisible"
                >
                    Select all{{ query ? ' (results)' : '' }}
                </button>
                <button
                    type="button"
                    class="font-semibold text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
                    :disabled="modelValue.length === 0"
                    @click="clearAll"
                >
                    <X class="h-3 w-3" /> Clear
                </button>
            </div>

            <!-- Options -->
            <div class="max-h-56 overflow-y-auto py-1">
                <p v-if="filteredOptions.length === 0" class="px-3 py-4 text-xs text-center text-muted-foreground">
                    No matches.
                </p>
                <button
                    v-for="option in filteredOptions"
                    :key="option"
                    type="button"
                    class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-left hover:bg-muted/60 transition-colors"
                    @click="toggle(option)"
                >
                    <span
                        class="h-3.5 w-3.5 rounded border flex items-center justify-center shrink-0"
                        :class="isSelected(option) ? 'bg-blue-600 border-blue-600' : 'border-muted-foreground/40'"
                    >
                        <Check v-if="isSelected(option)" class="h-2.5 w-2.5 text-white" />
                    </span>
                    <span class="truncate">{{ option }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
