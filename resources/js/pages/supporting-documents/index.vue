<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref, watch, computed } from 'vue';
import {
    FileStack,
    Search,
    FileText,
    CalendarDays,
    Building2,
    Link2,
    ExternalLink,
    BookOpen,
    Hash,
    Layers,
    RotateCcw,
    Filter,
    Inbox,
    ChevronLeft,
    ChevronRight,
    Sparkles,
} from 'lucide-vue-next';

/* ===================== TYPES ===================== */

interface ProgramLite {
    id: number;
    program_code: string | null;
    title: string;
    category: string | null;
    modality: string | null;
}

interface SupportingDocument {
    id: number;
    program_id: number;
    program_code: string | null;
    document_type: string;
    subject: string;
    document_series: number;
    origin: string | null;
    document_number: string;
    date_issued: string | null;
    link: string | null;
    program?: ProgramLite | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    documents: Paginator<SupportingDocument>;
    filters: {
        search?: string;
        document_type?: string;
        document_series?: string | number;
        origin?: string;
    };
    documentTypes: string[];
    seriesYears: number[];
    origins: string[];
    stats: {
        total: number;
        thisYear: number;
        types: number;
        withLinks: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Supporting Documents', href: '/supporting-documents' },
];

/* ===================== FILTERS ===================== */

const search = ref(props.filters.search ?? '');
const documentType = ref(props.filters.document_type ?? '');
const documentSeries = ref(String(props.filters.document_series ?? ''));
const origin = ref(props.filters.origin ?? '');

// May naka-apply bang filter? Para sa "Reset" button at active badge.
const hasActiveFilters = computed(
    () => !!(search.value || documentType.value || documentSeries.value || origin.value),
);

const applyFilters = () => {
    router.get(
        route('supporting-documents.index'),
        {
            search: search.value || undefined,
            document_type: documentType.value || undefined,
            document_series: documentSeries.value || undefined,
            origin: origin.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

// Debounced search — para hindi mag-request kada keystroke
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});

// Instant apply kapag nagpalit ng dropdown
watch([documentType, documentSeries, origin], applyFilters);

const resetFilters = () => {
    search.value = '';
    documentType.value = '';
    documentSeries.value = '';
    origin.value = '';
};

/* ===================== DISPLAY HELPERS ===================== */

// Fixed na kulay per known type + rotating palette fallback para consistent ang badge colors
const TYPE_PALETTE = [
    'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30',
    'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/30',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
    'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/30',
    'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30',
    'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border-cyan-500/30',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30',
    'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/30',
];

const typeColor = (type: string) => {
    let hash = 0;
    for (let i = 0; i < type.length; i++) hash = (hash * 31 + type.charCodeAt(i)) >>> 0;
    return TYPE_PALETTE[hash % TYPE_PALETTE.length];
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Supporting Documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 px-6 py-6">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-md shadow-orange-500/20">
                        <FileStack class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-extrabold leading-tight text-sky-900 dark:text-yellow-500">
                            Supporting Documents
                        </h1>
                        <p class="text-xs text-muted-foreground">
                            Registry of all recorded supporting documents across programs · view only
                        </p>
                    </div>
                </div>

                <div
                    v-if="hasActiveFilters"
                    class="flex items-center gap-1.5 rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-600 dark:text-blue-400"
                >
                    <Filter class="h-3.5 w-3.5" />
                    Filters active — {{ documents.total }} result{{ documents.total === 1 ? '' : 's' }}
                </div>
            </div>

            <!-- ===================== STAT CARDS ===================== -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="flex items-center gap-3 rounded-2xl border p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10">
                        <FileText class="h-5 w-5 text-blue-500" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.total.toLocaleString() }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Total Documents</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-2xl border p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10">
                        <Sparkles class="h-5 w-5 text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.thisYear.toLocaleString() }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Series {{ new Date().getFullYear() }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-2xl border p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10">
                        <Layers class="h-5 w-5 text-violet-500" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.types.toLocaleString() }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Document Types</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-2xl border p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500/10">
                        <Link2 class="h-5 w-5 text-orange-500" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.withLinks.toLocaleString() }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">With Attached Links</p>
                    </div>
                </div>
            </div>

            <!-- ===================== SEARCH + FILTER BAR ===================== -->
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border p-4 shadow-sm">
                <!-- Search -->
                <div class="relative min-w-[220px] flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Search doc no., subject, program code, origin…"
                        class="pl-9"
                    />
                </div>

                <!-- Type filter -->
                <select
                    v-model="documentType"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Types</option>
                    <option v-for="t in documentTypes" :key="t" :value="t">{{ t }}</option>
                </select>

                <!-- Series year filter -->
                <select
                    v-model="documentSeries"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Years</option>
                    <option v-for="y in seriesYears" :key="y" :value="String(y)">{{ y }}</option>
                </select>

                <!-- Origin filter -->
                <select
                    v-model="origin"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Origins</option>
                    <option v-for="o in origins" :key="o" :value="o">{{ o }}</option>
                </select>

                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                    @click="resetFilters"
                >
                    <RotateCcw class="mr-1 h-4 w-4" /> Reset
                </Button>
            </div>

            <!-- ===================== TABLE ===================== -->
            <div class="overflow-hidden rounded-2xl border shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-3 font-semibold">
                                    <span class="flex items-center gap-1"><Hash class="h-3.5 w-3.5" /> Document No.</span>
                                </th>
                                <th class="px-4 py-3 font-semibold">
                                    <span class="flex items-center gap-1"><Layers class="h-3.5 w-3.5" /> Type</span>
                                </th>
                                <th class="px-4 py-3 font-semibold">Subject</th>
                                <th class="px-4 py-3 font-semibold">
                                    <span class="flex items-center gap-1"><CalendarDays class="h-3.5 w-3.5" /> Series</span>
                                </th>
                                <th class="px-4 py-3 font-semibold">
                                    <span class="flex items-center gap-1"><Building2 class="h-3.5 w-3.5" /> Origin</span>
                                </th>
                                <th class="px-4 py-3 font-semibold">Date Issued</th>
                                <th class="px-4 py-3 font-semibold">
                                    <span class="flex items-center gap-1"><BookOpen class="h-3.5 w-3.5" /> Program</span>
                                </th>
                                <th class="px-4 py-3 text-center font-semibold">Link</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="doc in documents.data"
                                :key="doc.id"
                                class="border-b transition-colors last:border-b-0 hover:bg-muted/40"
                            >
                                <!-- Document number -->
                                <td class="px-4 py-3 font-bold text-sky-900 dark:text-yellow-500">
                                    {{ doc.document_number }}
                                </td>

                                <!-- Type badge -->
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="typeColor(doc.document_type)"
                                    >
                                        {{ doc.document_type }}
                                    </span>
                                </td>

                                <!-- Subject -->
                                <td class="max-w-[320px] px-4 py-3">
                                    <p class="truncate leading-snug" :title="doc.subject">{{ doc.subject }}</p>
                                </td>

                                <!-- Series year -->
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md bg-slate-500/10 px-2 py-0.5 text-xs font-bold text-slate-600 dark:text-slate-300">
                                        {{ doc.document_series }}
                                    </span>
                                </td>

                                <!-- Origin -->
                                <td class="px-4 py-3 text-muted-foreground">{{ doc.origin || '—' }}</td>

                                <!-- Date issued -->
                                <td class="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                    {{ formatDate(doc.date_issued) }}
                                </td>

                                <!-- Connected program: clickable papunta sa programs.show -->
                                <td class="max-w-[240px] px-4 py-3">
                                    <Link
                                        v-if="doc.program"
                                        :href="route('programs.show', doc.program.id)"
                                        class="group flex flex-col"
                                        :title="doc.program.title"
                                    >
                                        <span class="flex items-center gap-1 text-xs font-bold text-blue-600 group-hover:underline dark:text-blue-400">
                                            <BookOpen class="h-3.5 w-3.5 shrink-0" />
                                            {{ doc.program.program_code ?? doc.program_code ?? '—' }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground">
                                            {{ doc.program.title }}
                                        </span>
                                    </Link>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <!-- External link -->
                                <td class="px-4 py-3 text-center">
                                    <a
                                        v-if="doc.link"
                                        :href="doc.link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border text-blue-500 transition-colors hover:bg-blue-500/10"
                                        title="Open document link"
                                    >
                                        <ExternalLink class="h-3.5 w-3.5" />
                                    </a>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                            </tr>

                            <!-- Empty state -->
                            <tr v-if="documents.data.length === 0">
                                <td colspan="8" class="px-4 py-14">
                                    <div class="flex flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                                        <Inbox class="h-8 w-8" />
                                        <p class="text-sm font-semibold">No supporting documents found.</p>
                                        <p class="text-xs">
                                            {{ hasActiveFilters
                                                ? 'Try adjusting the search or clearing the filters.'
                                                : 'Documents added inside a program will appear here.' }}
                                        </p>
                                        <Button
                                            v-if="hasActiveFilters"
                                            variant="outline"
                                            size="sm"
                                            class="mt-1"
                                            @click="resetFilters"
                                        >
                                            <RotateCcw class="mr-1 h-4 w-4" /> Clear filters
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ===================== PAGINATION ===================== -->
                <div
                    v-if="documents.total > 0"
                    class="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/30 px-4 py-3"
                >
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-semibold">{{ documents.from }}</span>–<span class="font-semibold">{{ documents.to }}</span>
                        of <span class="font-semibold">{{ documents.total }}</span> documents
                    </p>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in documents.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-semibold transition-colors"
                                :class="link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'hover:bg-muted'"
                            >
                                <ChevronLeft v-if="i === 0" class="h-3.5 w-3.5" />
                                <ChevronRight v-else-if="i === documents.links.length - 1" class="h-3.5 w-3.5" />
                                <span v-else v-html="link.label" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>