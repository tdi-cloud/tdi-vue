<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    ClipboardList, Search, AlertTriangle, CheckCircle2, ListChecks,
    Building2, MapPin, CalendarClock, Download, FileWarning,
    SlidersHorizontal, X,
} from 'lucide-vue-next';

interface TrackerItem {
    empcode: string;
    employee_name: string;
    office_division: string;
    region: string;
    program_id: number;
    program_code: string;
    program_title: string;
    batch_id: number;
    batch_label: string;
    batch_date_end: string;
    requirement_id: number;
    requirement_title: string;
    requirement_name: string;
    due_date: string;
    is_overdue: boolean;
    days_overdue: number;
}

interface PaginatedItems {
    data: TrackerItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    items: PaginatedItems;
    requirementTitles: string[];
    stats: { total_missing: number; overdue: number; on_time: number };
    filters: {
        search?: string;
        requirement_title?: string;
        overdue_only?: string;
    };
}>();

const search = ref(props.filters.search ?? '');
const requirementTitle = ref(props.filters.requirement_title ?? '');
const overdueOnly = ref(props.filters.overdue_only === '1');

let debounce: ReturnType<typeof setTimeout>;
watch([search, requirementTitle, overdueOnly], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('requirements-tracker.index'), {
            search: search.value || undefined,
            requirement_title: requirementTitle.value || undefined,
            overdue_only: overdueOnly.value ? '1' : undefined,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 350);
});

const clearFilters = () => {
    search.value = '';
    requirementTitle.value = '';
    overdueOnly.value = false;
};

const hasActiveFilters = () => search.value || requirementTitle.value || overdueOnly.value;

const exportUrl = () => route('requirements-tracker.export', {
    search: search.value || undefined,
    requirement_title: requirementTitle.value || undefined,
    overdue_only: overdueOnly.value ? '1' : undefined,
});

const formatDate = (d?: string | null) => {
    if (!d) return '—';
    const date = new Date(d.includes('T') ? d : d + 'T00:00:00');
    return isNaN(date.getTime()) ? d : date.toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
};

const avatarColor = (empcode: string) => {
    const colors = [
        'bg-violet-500', 'bg-blue-500', 'bg-emerald-500',
        'bg-rose-500', 'bg-amber-500', 'bg-indigo-500',
        'bg-teal-500', 'bg-pink-500',
    ];
    const idx = (empcode?.charCodeAt(0) ?? 0) % colors.length;
    return colors[idx];
};

const initials = (name: string) => {
    const parts = name?.trim().split(/\s+/) ?? [];
    return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
};
</script>

<template>
    <Head title="Post-Training Requirements Tracker" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shadow-sm shrink-0">
                    <ClipboardList class="h-5.5 w-5.5 text-white" />
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold">Post-Training Requirements Tracker</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        Monitor employees who have not yet submitted post-training requirements across all programs.
                    </p>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-2xl border p-4 flex items-center gap-3 bg-gradient-to-br from-slate-50 to-white dark:from-slate-900 dark:to-background">
                    <div class="h-11 w-11 rounded-xl bg-slate-200/70 dark:bg-slate-800 flex items-center justify-center shrink-0">
                        <ListChecks class="h-5 w-5 text-slate-600 dark:text-slate-300" />
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold leading-none">{{ stats.total_missing }}</p>
                        <p class="text-xs text-muted-foreground mt-1">Total Missing Submissions</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-red-200 dark:border-red-900 p-4 flex items-center gap-3 bg-gradient-to-br from-red-50 to-white dark:from-red-950/20 dark:to-background">
                    <div class="h-11 w-11 rounded-xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center shrink-0">
                        <AlertTriangle class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold leading-none text-red-600 dark:text-red-400">{{ stats.overdue }}</p>
                        <p class="text-xs text-muted-foreground mt-1">Past Due Date</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-emerald-200 dark:border-emerald-900 p-4 flex items-center gap-3 bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-950/20 dark:to-background">
                    <div class="h-11 w-11 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                        <CheckCircle2 class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold leading-none text-emerald-600 dark:text-emerald-400">{{ stats.on_time }}</p>
                        <p class="text-xs text-muted-foreground mt-1">Still Within Deadline</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search employee name, empcode, or program..."
                            class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-background shadow-sm"
                        />
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground px-2 py-1.5 rounded-lg border bg-muted/30">
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        <span>Filters</span>
                    </div>
                    <button
                        v-if="hasActiveFilters()"
                        type="button"
                        class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors px-2 py-1.5"
                        @click="clearFilters"
                    >
                        <X class="h-3.5 w-3.5" /> Clear all
                    </button>

                    <a
                        :href="exportUrl()"
                        class="ml-auto inline-flex items-center gap-1.5 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl shadow-sm transition-colors"
                    >
                        <Download class="h-4 w-4" /> Export CSV
                    </a>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 p-3 rounded-xl border bg-muted/30">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <ListChecks class="h-3 w-3" /> Requirement
                        </label>
                        <select v-model="requirementTitle" class="border rounded-lg px-2 py-1.5 text-xs bg-background shadow-sm">
                            <option value="">All</option>
                            <option v-for="t in requirementTitles" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <FileWarning class="h-3 w-3" /> Due Status
                        </label>
                        <button
                            type="button"
                            @click="overdueOnly = !overdueOnly"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold border shadow-sm transition-colors"
                            :class="overdueOnly
                                ? 'bg-red-600 border-red-600 text-white'
                                : 'bg-background text-muted-foreground hover:bg-muted/50'"
                        >
                            <FileWarning class="h-3.5 w-3.5" /> Overdue Only
                        </button>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing {{ items.from ?? 0 }}–{{ items.to ?? 0 }} of {{ items.total }} outstanding requirement(s)
                </p>
            </div>

            <!-- Table -->
            <div class="rounded-2xl border overflow-hidden shadow-sm bg-background">
                <table v-if="items.data.length" class="w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-blue-50 via-indigo-50 to-violet-50 dark:from-blue-950/40 dark:via-indigo-950/40 dark:to-violet-950/40 border-b-2 border-indigo-200 dark:border-indigo-900">
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Employee</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Program &amp; Batch</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Requirement</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Due Date</th>
                            <th class="text-right font-bold px-4 py-3 text-xs uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="item in items.data"
                            :key="`${item.empcode}-${item.requirement_id}-${item.batch_id}`"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                                        :class="avatarColor(item.empcode)">
                                        {{ initials(item.employee_name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-sm leading-tight truncate">{{ item.employee_name?.toUpperCase() }}</p>
                                        <p class="text-xs text-muted-foreground flex items-center gap-1 truncate">
                                            <Building2 class="h-3 w-3 shrink-0" /> {{ item.office_division }}
                                            <span class="mx-0.5">·</span>
                                            <MapPin class="h-3 w-3 shrink-0" /> {{ item.region }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('programs.show', item.program_id)"
                                    class="font-semibold text-sm leading-tight truncate max-w-xs block hover:text-blue-600 hover:underline transition-colors"
                                    title="View program details"
                                >
                                    {{ item.program_title }}
                                </Link>
                                <p class="text-xs text-muted-foreground">{{ item.batch_label }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-sm">{{ item.requirement_title }}</p>
                                <p class="text-xs text-muted-foreground truncate max-w-xs">{{ item.requirement_name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm flex items-center gap-1.5">
                                    <CalendarClock class="h-3.5 w-3.5 text-muted-foreground" /> {{ formatDate(item.due_date) }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span
                                    v-if="item.is_overdue"
                                    class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400"
                                >
                                    <AlertTriangle class="h-3 w-3" /> {{ item.days_overdue }}d overdue
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400"
                                >
                                    Not yet submitted
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty: nothing missing at all -->
                <div v-else class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                    <svg viewBox="0 0 200 160" class="h-40 w-auto" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                        <circle cx="100" cy="80" r="52" fill="currentColor" class="text-emerald-50 dark:text-emerald-900/20" />
                        <circle cx="100" cy="80" r="52" stroke="currentColor" stroke-width="5" fill="none" class="text-emerald-300 dark:text-emerald-700/60" />
                        <path d="M78 82 L94 98 L126 62" stroke="currentColor" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500" />
                    </svg>
                    <p class="text-sm font-bold text-slate-500">All caught up!</p>
                    <p class="text-xs text-slate-400 max-w-xs">
                        {{ hasActiveFilters() ? 'No outstanding requirements match your filters.' : 'No employees currently have outstanding post-training requirements.' }}
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="items.data.length" class="flex items-center justify-between text-sm">
                <p class="text-xs text-muted-foreground">
                    Showing {{ items.from ?? 0 }}–{{ items.to ?? 0 }} of {{ items.total }}
                </p>
                <div class="flex items-center gap-1">
                    <template v-for="link in items.links" :key="link.label">
                        <a v-if="link.url"
                            :href="link.url"
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-muted text-muted-foreground'"
                            v-html="link.label.includes('Previous') ? '&lsaquo;' : link.label.includes('Next') ? '&rsaquo;' : link.label"
                        />
                        <span v-else
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-xs text-muted-foreground opacity-40"
                            v-html="link.label.includes('Previous') ? '&lsaquo;' : link.label.includes('Next') ? '&rsaquo;' : link.label"
                        />
                    </template>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
