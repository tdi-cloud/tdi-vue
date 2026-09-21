<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, Eye, ImageOff, Images, Layers, Loader2, RotateCcw, Search, SlidersHorizontal, Upload, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const { confirmDialog } = useConfirm();

interface ImageSlot {
    key: string;
    label: string;
    section: string;
    url: string;
    is_customized: boolean;
}

const props = defineProps<{
    sections: Record<string, ImageSlot[]>;
}>();

const fileInputs = ref<Record<string, HTMLInputElement | null>>({});
const modalFileInput = ref<HTMLInputElement | null>(null);
const busyKey = ref<string | null>(null);

const triggerUpload = (key: string) => {
    fileInputs.value[key]?.click();
};

const handleFileChange = (key: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    busyKey.value = key;
    router.post(
        route('site-images.update', key),
        { image: file },
        {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                busyKey.value = null;
                input.value = '';
            },
        },
    );
};

const resetImage = async (key: string) => {
    if (!(await confirmDialog('Reset this image back to its default?', { confirmText: 'Reset' }))) return;
    busyKey.value = key;
    router.delete(route('site-images.destroy', key), {
        preserveScroll: true,
        onFinish: () => {
            busyKey.value = null;
        },
    });
};

/* ---- Toolbar: search + status filter ---- */
const search = ref('');
const statusFilter = ref<'all' | 'customized' | 'default'>('all');
const statusOptions: { value: 'all' | 'customized' | 'default'; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'customized', label: 'Customized' },
    { value: 'default', label: 'Default' },
];
const hasActiveFilters = computed(() => search.value.trim() !== '' || statusFilter.value !== 'all');

const clearFilters = () => {
    search.value = '';
    statusFilter.value = 'all';
};

const matchesFilters = (slot: ImageSlot) => {
    const q = search.value.trim().toLowerCase();
    if (q && !slot.label.toLowerCase().includes(q)) return false;
    if (statusFilter.value === 'customized' && !slot.is_customized) return false;
    if (statusFilter.value === 'default' && slot.is_customized) return false;
    return true;
};

const filteredSections = computed(() => {
    const result: Record<string, ImageSlot[]> = {};
    for (const [sectionName, slots] of Object.entries(props.sections)) {
        const filtered = slots.filter(matchesFilters);
        if (filtered.length) result[sectionName] = filtered;
    }
    return result;
});

const hasAnySections = computed(() => Object.keys(props.sections).length > 0);
const hasVisibleResults = computed(() => Object.keys(filteredSections.value).length > 0);

/* ---- Header summary stats ---- */
const allSlots = computed(() => Object.values(props.sections).flat());
const totalCount = computed(() => allSlots.value.length);
const customizedCount = computed(() => allSlots.value.filter((s) => s.is_customized).length);

/* ---- Collapsible sections ---- */
const collapsedSections = ref<Set<string>>(new Set());
const toggleSection = (sectionName: string) => {
    const next = new Set(collapsedSections.value);
    if (next.has(sectionName)) {
        next.delete(sectionName);
    } else {
        next.add(sectionName);
    }
    collapsedSections.value = next;
};

/* ---- Preview modal ---- */
const previewKey = ref<string | null>(null);
const previewSlot = computed<ImageSlot | null>(() => allSlots.value.find((s) => s.key === previewKey.value) ?? null);

const openPreview = (slot: ImageSlot) => {
    previewKey.value = slot.key;
};

const closePreview = () => {
    previewKey.value = null;
};

const replaceFromModal = () => {
    if (!previewSlot.value) return;
    modalFileInput.value?.click();
};

const handleModalFileChange = (event: Event) => {
    if (!previewSlot.value) return;
    handleFileChange(previewSlot.value.key, event);
};

const onEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && previewSlot.value) {
        closePreview();
    }
};

onMounted(() => window.addEventListener('keydown', onEscape));
onUnmounted(() => window.removeEventListener('keydown', onEscape));
</script>

<template>
    <Head title="Homepage Gallery" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-5 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-3 rounded-2xl border bg-background p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-fuchsia-600 shadow-md">
                        <Images class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold leading-none">Homepage Gallery</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Manage the visual assets displayed on the public homepage — superadmin only</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:pl-3">
                    <div class="flex items-center gap-1.5 rounded-lg border bg-muted/30 px-3 py-1.5 text-xs">
                        <Layers class="h-3.5 w-3.5 text-muted-foreground" />
                        <span class="font-semibold">{{ totalCount }}</span>
                        <span class="text-muted-foreground">image{{ totalCount === 1 ? '' : 's' }}</span>
                    </div>
                    <div
                        class="flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs dark:border-emerald-900 dark:bg-emerald-950/30"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                        <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ customizedCount }}</span>
                        <span class="text-emerald-700/80 dark:text-emerald-400/80">customized</span>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative max-w-sm flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search images by label..."
                        aria-label="Search images by label"
                        class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-fuchsia-500"
                    />
                </div>

                <div class="flex items-center gap-1.5 rounded-lg border bg-muted/30 p-1 text-xs">
                    <SlidersHorizontal class="ml-1.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                    <button
                        v-for="opt in statusOptions"
                        :key="opt.value"
                        type="button"
                        class="rounded-md px-2.5 py-1 font-semibold transition-colors"
                        :class="
                            statusFilter === opt.value
                                ? 'bg-fuchsia-600 text-white shadow-sm'
                                : 'text-muted-foreground hover:bg-background hover:text-foreground'
                        "
                        @click="statusFilter = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="flex items-center gap-1 px-2 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                    @click="clearFilters"
                >
                    <X class="h-3.5 w-3.5" /> Clear
                </button>
            </div>

            <!-- Empty state: no sections at all -->
            <div v-if="!hasAnySections" class="flex flex-col items-center justify-center gap-2 rounded-2xl border py-16 text-muted-foreground">
                <ImageOff class="h-10 w-10 opacity-30" />
                <p class="text-sm font-semibold">No image slots configured.</p>
            </div>

            <!-- Empty state: filters return nothing -->
            <div
                v-else-if="!hasVisibleResults"
                class="flex flex-col items-center justify-center gap-2 rounded-2xl border py-16 text-muted-foreground"
            >
                <Search class="h-10 w-10 opacity-30" />
                <p class="text-sm font-semibold">No images match your search or filter.</p>
                <button type="button" class="text-xs font-semibold text-fuchsia-600 hover:underline" @click="clearFilters">Clear filters</button>
            </div>

            <!-- Sections -->
            <div v-for="(slots, sectionName) in filteredSections" :key="sectionName" class="flex flex-col gap-3">
                <button type="button" class="flex w-full items-center gap-2 text-left" @click="toggleSection(sectionName)">
                    <ChevronDown
                        class="h-3.5 w-3.5 shrink-0 text-fuchsia-700 transition-transform dark:text-fuchsia-400"
                        :class="{ '-rotate-90': collapsedSections.has(sectionName) }"
                    />
                    <h2 class="text-sm font-bold uppercase tracking-wide text-fuchsia-700 dark:text-fuchsia-400">{{ sectionName }}</h2>
                    <span
                        class="rounded-full bg-fuchsia-100 px-2 py-0.5 text-[11px] font-semibold text-fuchsia-700 dark:bg-fuchsia-950/40 dark:text-fuchsia-300"
                    >
                        {{ slots.length }}
                    </span>
                    <span class="h-px flex-1 bg-border" />
                </button>

                <div v-if="!collapsedSections.has(sectionName)" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="slot in slots"
                        :key="slot.key"
                        class="group flex flex-col overflow-hidden rounded-2xl border bg-background shadow-sm transition-shadow hover:shadow-md"
                    >
                        <div class="group/preview relative aspect-video overflow-hidden bg-muted/40">
                            <button
                                type="button"
                                class="absolute inset-0 h-full w-full cursor-zoom-in"
                                :aria-label="`Preview ${slot.label}`"
                                @click="openPreview(slot)"
                            >
                                <img
                                    :src="slot.url"
                                    :alt="slot.label"
                                    class="h-full w-full object-cover transition-transform duration-300 ease-out group-hover/preview:scale-105"
                                />
                            </button>

                            <div
                                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/0 transition-colors duration-200 group-hover/preview:bg-black/20"
                            >
                                <Eye class="h-6 w-6 text-white opacity-0 transition-opacity duration-200 group-hover/preview:opacity-100" />
                            </div>

                            <span
                                v-if="slot.is_customized"
                                class="pointer-events-none absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white shadow"
                            >
                                <CheckCircle2 class="h-3 w-3" /> Customized
                            </span>

                            <div
                                v-if="busyKey === slot.key"
                                class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-[1px]"
                            >
                                <Loader2 class="h-6 w-6 animate-spin text-white" />
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-2 p-3">
                            <p class="text-xs font-semibold leading-tight" :title="slot.label">{{ slot.label }}</p>

                            <div class="mt-auto flex items-center gap-1.5">
                                <input
                                    :ref="
                                        (el) => {
                                            fileInputs[slot.key] = el as HTMLInputElement;
                                        }
                                    "
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleFileChange(slot.key, $event)"
                                />
                                <button
                                    type="button"
                                    :disabled="busyKey === slot.key"
                                    class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-fuchsia-600 px-2.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-fuchsia-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="triggerUpload(slot.key)"
                                >
                                    <Upload class="h-3.5 w-3.5" /> Replace
                                </button>
                                <button
                                    v-if="slot.is_customized"
                                    type="button"
                                    :disabled="busyKey === slot.key"
                                    title="Reset to default"
                                    aria-label="Reset to default"
                                    class="inline-flex items-center justify-center rounded-lg border p-1.5 text-muted-foreground transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="resetImage(slot.key)"
                                >
                                    <RotateCcw class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Preview Modal ===== -->
        <Teleport to="body">
            <div v-if="previewSlot" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4" @click.self="closePreview">
                <div class="flex w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-background shadow-2xl">
                    <div class="flex items-center gap-3 border-b px-5 py-4">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-fuchsia-600">
                            <Eye class="h-4 w-4 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-extrabold leading-none">{{ previewSlot.label }}</h3>
                            <p class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                <CheckCircle2 v-if="previewSlot.is_customized" class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                {{ previewSlot.is_customized ? 'Customized image' : 'Default image' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-label="Close preview"
                            class="ml-auto shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                            @click="closePreview"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="relative flex items-center justify-center bg-muted/40 p-4">
                        <img :src="previewSlot.url" :alt="previewSlot.label" class="max-h-[60vh] w-full rounded-lg object-contain" />
                        <div
                            v-if="busyKey === previewSlot.key"
                            class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-[1px]"
                        >
                            <Loader2 class="h-8 w-8 animate-spin text-white" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t px-5 py-4">
                        <input ref="modalFileInput" type="file" accept="image/*" class="hidden" @change="handleModalFileChange" />
                        <button
                            type="button"
                            class="rounded-lg border px-3.5 py-2 text-sm font-semibold transition-colors hover:bg-muted"
                            @click="closePreview"
                        >
                            Close
                        </button>
                        <button
                            type="button"
                            :disabled="busyKey === previewSlot.key"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-fuchsia-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-fuchsia-700 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="replaceFromModal"
                        >
                            <Upload class="h-3.5 w-3.5" /> Replace Image
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
