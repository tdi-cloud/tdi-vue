<script setup lang="ts">
import axios from 'axios';
import { Download, FileSpreadsheet, Loader2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * "Reports" — isang button na nagbubukas ng modal kung saan pinipili muna
 * ang Report Time Range (Past 7/30/90 days o custom range), tapos sa baba
 * nito ang Download CSV — lahat ng programs kasama ang batches/participants/
 * attendance, naka-scope sa parehong shared filters (region/office/target/
 * plantilla status, mula sa Dashboard) at sa napiling date range dito.
 */
const props = defineProps<{
    region: string;
    office: string;
    target: string;
    selectedStatuses: string[];
}>();

type PresetKey = 'all' | '7' | '30' | '90' | 'custom';

const PRESETS: { key: PresetKey; label: string }[] = [
    { key: 'all', label: 'All Time' },
    { key: '7', label: 'Past 7 days' },
    { key: '30', label: 'Past 30 days' },
    { key: '90', label: 'Past 90 days' },
    { key: 'custom', label: 'Custom range' },
];

const open = ref(false);
const selectedPreset = ref<PresetKey>('all');
const rangeFrom = ref<string | null>(null);
const rangeTo = ref<string | null>(null);

const toISODate = (d: Date) => d.toISOString().slice(0, 10);

/** Kinu-compute ang [from, to] ng isang "past N days" preset (kasama ang ngayong araw). */
function presetRange(days: number): [string, string] {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (days - 1));
    return [toISODate(from), toISODate(to)];
}

function selectPreset(key: PresetKey) {
    selectedPreset.value = key;
    if (key === 'all') {
        rangeFrom.value = null;
        rangeTo.value = null;
    } else if (key === 'custom') {
        if (!rangeFrom.value || !rangeTo.value) {
            [rangeFrom.value, rangeTo.value] = presetRange(7);
        }
    } else {
        [rangeFrom.value, rangeTo.value] = presetRange(Number(key));
    }
}

function openModal() {
    selectedPreset.value = 'all';
    rangeFrom.value = null;
    rangeTo.value = null;
    open.value = true;
}

function close() {
    open.value = false;
}

const csvHref = computed(() =>
    route('dashboard.export-csv', {
        office_filter: props.target,
        region: props.region,
        office: props.office,
        date_from: rangeFrom.value,
        date_to: rangeTo.value,
        plant_status: props.selectedStatuses,
    }),
);

/**
 * Fetched via axios (hindi plain <a href>) para may loading state tayo na
 * matatapos lang kapag totoong natapos na ang download (blob response na-
 * resolve), imbes na agad-agad mawala kapag ka-click lang — pinipigilan din
 * nito ang paulit-ulit na pag-click ni user habang nagda-download pa.
 */
const downloading = ref(false);

async function downloadCsv() {
    if (downloading.value) return;
    downloading.value = true;

    try {
        const response = await axios.get(csvHref.value, { responseType: 'blob' });

        const disposition = response.headers['content-disposition'] as string | undefined;
        const match = disposition?.match(/filename="?([^"\n]+)"?/);
        const filename = match?.[1] ?? 'programs_batches_participants.csv';

        const blobUrl = URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(blobUrl);

        close();
    } catch (e) {
        console.error('Failed to download CSV:', e);
    } finally {
        downloading.value = false;
    }
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex h-9 items-center gap-1.5 rounded-lg border bg-background px-3.5 text-xs font-semibold shadow-sm transition-colors hover:bg-muted/50"
            @click="openModal"
        >
            <FileSpreadsheet class="h-3.5 w-3.5 text-muted-foreground" />
            Reports
        </button>

        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4" @click.self="close">
                <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-background shadow-2xl">
                    <div class="flex items-start justify-between gap-3 border-b px-5 py-4">
                        <div>
                            <h3 class="text-sm font-bold">Report Time Range</h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">Select a predefined or custom time range, then download the CSV.</p>
                        </div>
                        <button type="button" class="text-muted-foreground transition-colors hover:text-foreground" @click="close">
                            <X class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <div class="flex">
                        <!-- Preset list -->
                        <div class="w-40 shrink-0 border-r py-2">
                            <button
                                v-for="opt in PRESETS"
                                :key="opt.key"
                                type="button"
                                class="w-full border-l-2 px-4 py-2.5 text-left text-xs font-semibold transition-colors"
                                :class="
                                    selectedPreset === opt.key
                                        ? 'border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400'
                                        : 'border-transparent text-foreground hover:bg-muted/50'
                                "
                                @click="selectPreset(opt.key)"
                            >
                                {{ opt.label }}
                            </button>
                        </div>

                        <!-- Date inputs -->
                        <div class="flex flex-1 flex-col gap-3 p-5">
                            <p class="text-xs text-muted-foreground">
                                {{ selectedPreset === 'custom' ? 'Pick a start and end date.' : 'Computed automatically for this preset.' }}
                            </p>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="text-[11px] font-semibold text-muted-foreground">Start date</label>
                                    <input
                                        type="date"
                                        v-model="rangeFrom"
                                        :max="rangeTo ?? undefined"
                                        :disabled="selectedPreset !== 'custom'"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-muted/40 disabled:opacity-60"
                                    />
                                </div>
                                <span class="pb-2.5 text-muted-foreground">–</span>
                                <div class="flex-1">
                                    <label class="text-[11px] font-semibold text-muted-foreground">End date</label>
                                    <input
                                        type="date"
                                        v-model="rangeTo"
                                        :min="rangeFrom ?? undefined"
                                        :disabled="selectedPreset !== 'custom'"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-muted/40 disabled:opacity-60"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Download CSV — sa baba ng Report Time Range -->
                    <div class="flex items-center justify-between gap-3 border-t bg-muted/20 px-5 py-4">
                        <p class="text-xs text-muted-foreground">Programs, batches, participants &amp; attendance for the selected range.</p>
                        <button
                            type="button"
                            :disabled="downloading"
                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-xs font-bold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-70"
                            @click="downloadCsv"
                        >
                            <Loader2 v-if="downloading" class="h-3.5 w-3.5 animate-spin" />
                            <Download v-else class="h-3.5 w-3.5" />
                            {{ downloading ? 'Downloading…' : 'Download CSV' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
