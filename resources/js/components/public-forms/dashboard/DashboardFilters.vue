<script setup lang="ts">
import { dashboardFilterLabels, type DashboardFilters } from '@/lib/publicForms';
import { Building2, CalendarRange, LoaderCircle, MapPin, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * One row of filters above everything they scope: a date range (presets
 * first, then a custom range), the region and the office/division. The
 * active ones show as chips with "Clear all".
 */
const props = defineProps<{
    filters: DashboardFilters;
    options: { regions: string[]; offices: string[] };
    busy: boolean;
}>();

const emit = defineEmits<{ change: [filters: DashboardFilters] }>();

function isoDay(daysAgo: number): string {
    const day = new Date();
    day.setDate(day.getDate() - daysAgo);
    return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
}

type Preset = 'all' | 'today' | '7' | '30' | 'custom';

const PRESETS: { value: Preset; label: string; daysAgo?: number }[] = [
    { value: 'all', label: 'All time' },
    { value: 'today', label: 'Today', daysAgo: 0 },
    { value: '7', label: 'Last 7 days', daysAgo: 6 },
    { value: '30', label: 'Last 30 days', daysAgo: 29 },
    { value: 'custom', label: 'Custom range' },
];

const customOpen = ref(false);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const today = isoDay(0);

watch(
    () => props.filters,
    (filters) => {
        from.value = filters.from ?? '';
        to.value = filters.to ?? '';
    },
);

const activePreset = computed<Preset>(() => {
    const { from: start, to: end } = props.filters;
    if (!start && !end) return customOpen.value ? 'custom' : 'all';
    const preset = PRESETS.find((candidate) => candidate.daysAgo !== undefined && start === isoDay(candidate.daysAgo) && end === today);
    return preset && !customOpen.value ? preset.value : 'custom';
});

function apply(changes: Partial<DashboardFilters>): void {
    emit('change', { ...props.filters, ...changes });
}

function choosePreset(preset: (typeof PRESETS)[number]): void {
    if (preset.value === 'custom') {
        customOpen.value = true;
        return;
    }
    customOpen.value = false;
    apply(preset.daysAgo === undefined ? { from: null, to: null } : { from: isoDay(preset.daysAgo), to: today });
}

function applyCustomRange(): void {
    apply({ from: from.value || null, to: to.value || null });
}

const chips = computed(() => dashboardFilterLabels(props.filters));

function removeChip(key: 'region' | 'office' | 'dates'): void {
    if (key === 'dates') {
        customOpen.value = false;
        apply({ from: null, to: null });
    } else {
        apply({ [key]: null });
    }
}

function clearAll(): void {
    customOpen.value = false;
    emit('change', { region: null, office: null, from: null, to: null });
}

const selectClass =
    'h-9 w-full min-w-0 rounded-lg border bg-background pl-8 pr-2 text-xs text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-60 sm:w-56';
</script>

<template>
    <div class="pf-no-print flex flex-col gap-2.5" role="group" aria-label="Filter the summary">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap items-center gap-1 rounded-xl border bg-background p-1 shadow-sm" role="radiogroup" aria-label="Date range">
                <CalendarRange class="ml-1.5 mr-0.5 h-3.5 w-3.5 text-muted-foreground" aria-hidden="true" />
                <button
                    v-for="preset in PRESETS"
                    :key="preset.value"
                    type="button"
                    role="radio"
                    :aria-checked="activePreset === preset.value"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        activePreset === preset.value
                            ? 'bg-[#0b2a5b] text-white dark:bg-blue-600'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    @click="choosePreset(preset)"
                >
                    {{ preset.label }}
                </button>
            </div>

            <form v-if="activePreset === 'custom'" class="flex flex-wrap items-center gap-1.5" @submit.prevent="applyCustomRange">
                <label class="sr-only" for="dashboard-from">From</label>
                <input
                    id="dashboard-from"
                    v-model="from"
                    type="date"
                    :max="to || today"
                    class="h-9 rounded-lg border bg-background px-2 text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                <span class="text-xs text-muted-foreground">to</span>
                <label class="sr-only" for="dashboard-to">To</label>
                <input
                    id="dashboard-to"
                    v-model="to"
                    type="date"
                    :min="from || undefined"
                    :max="today"
                    class="h-9 rounded-lg border bg-background px-2 text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                <button
                    type="submit"
                    class="h-9 rounded-lg bg-[#0b2a5b] px-3 text-xs font-semibold text-white transition-colors hover:bg-[#123a7a] disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500"
                    :disabled="!from && !to"
                >
                    Apply
                </button>
            </form>

            <div class="relative w-full sm:w-auto">
                <MapPin class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <select
                    :value="filters.region ?? ''"
                    :class="selectClass"
                    aria-label="Region"
                    :disabled="!options.regions.length"
                    @change="apply({ region: ($event.target as HTMLSelectElement).value || null })"
                >
                    <option value="">All regions</option>
                    <option v-for="region in options.regions" :key="region" :value="region">{{ region }}</option>
                </select>
            </div>

            <div class="relative w-full sm:w-auto">
                <Building2
                    class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <select
                    :value="filters.office ?? ''"
                    :class="selectClass"
                    aria-label="Office or division"
                    :disabled="!options.offices.length"
                    @change="apply({ office: ($event.target as HTMLSelectElement).value || null })"
                >
                    <option value="">All offices and divisions</option>
                    <option v-for="office in options.offices" :key="office" :value="office">{{ office }}</option>
                </select>
            </div>

            <span v-if="busy" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground" role="status">
                <LoaderCircle class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Updating…
            </span>
        </div>

        <div v-if="chips.length" class="flex flex-wrap items-center gap-1.5">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Showing only</span>
            <span
                v-for="chip in chips"
                :key="chip.key"
                class="inline-flex max-w-full items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-0.5 pl-2.5 pr-1 text-xs font-medium text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100"
            >
                <span class="truncate">{{ chip.label }}</span>
                <button
                    type="button"
                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full transition-colors hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:hover:bg-blue-900/60"
                    :aria-label="`Remove filter ${chip.label}`"
                    @click="removeChip(chip.key)"
                >
                    <X class="h-3 w-3" aria-hidden="true" />
                </button>
            </span>
            <button type="button" class="ml-1 text-xs font-semibold text-blue-700 hover:underline dark:text-blue-300" @click="clearAll">
                Clear all
            </button>
        </div>
    </div>
</template>
