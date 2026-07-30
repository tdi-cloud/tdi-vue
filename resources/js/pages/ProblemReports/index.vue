<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Search, MessageSquareWarning, X, SlidersHorizontal,
    Mail, ExternalLink, CheckCircle2, RotateCcw, ChevronLeft, ChevronRight,
} from 'lucide-vue-next';

interface ReporterUser {
    id: number;
    name: string;
    email: string;
    empcode: string | null;
}

interface ProblemReportRow {
    id: number;
    description: string;
    page_url: string | null;
    status: 'open' | 'resolved';
    created_at: string;
    user: ReporterUser | null;
}

interface PaginatedReports {
    data: ProblemReportRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    reports: PaginatedReports;
    filters: {
        search?: string;
        status?: string;
    };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');

const hasActiveFilters = computed(() => search.value !== '' || status.value !== 'all');

const clearFilters = () => {
    search.value = '';
    status.value = 'all';
};

let debounce: ReturnType<typeof setTimeout>;
watch([search, status], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('problem-reports.index'), {
            search: search.value || undefined,
            status: status.value !== 'all' ? status.value : undefined,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 350);
});

const formatDateTime = (d: string) => {
    return new Date(d).toLocaleString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit',
    });
};

const savingId = ref<number | null>(null);

const toggleStatus = (report: ProblemReportRow) => {
    const newStatus = report.status === 'open' ? 'resolved' : 'open';
    savingId.value = report.id;
    router.put(route('problem-reports.update-status', report.id), { status: newStatus }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { savingId.value = null; },
    });
};
</script>

<template>
    <Head title="Problem Reports" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center h-10 w-10 rounded-xl bg-amber-600 shadow-md">
                    <MessageSquareWarning class="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold leading-none">Problem Reports</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Issues submitted by users — super admin only</p>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by reporter name or description..."
                            class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-background shadow-sm"
                        />
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground px-2 py-1.5 rounded-lg border bg-muted/30">
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        <span>Filters</span>
                    </div>
                    <button v-if="hasActiveFilters" class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors px-2 py-1.5" @click="clearFilters">
                        <X class="h-3.5 w-3.5" /> Clear all
                    </button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 p-3 rounded-xl border bg-muted/30">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Status</label>
                        <select v-model="status" class="border rounded-lg px-2 py-1.5 text-xs bg-background shadow-sm">
                            <option value="all">All</option>
                            <option value="open">Open</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing {{ reports.from ?? 0 }}–{{ reports.to ?? 0 }} of {{ reports.total }} report(s)
                </p>
            </div>

            <!-- Table -->
            <div class="rounded-2xl border overflow-hidden shadow-sm bg-background">
                <table v-if="reports.data.length" class="w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-amber-50 via-orange-50 to-yellow-50 dark:from-amber-950/40 dark:via-orange-950/40 dark:to-yellow-950/40 border-b-2 border-amber-200 dark:border-amber-900">
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Reporter</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Description</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Submitted</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Status</th>
                            <th class="text-right font-bold px-4 py-3 text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="r in reports.data" :key="r.id" class="hover:bg-muted/30 transition-colors align-top">
                            <td class="px-4 py-3">
                                <p class="font-bold text-sm leading-tight">{{ r.user?.name ?? 'Unknown user' }}</p>
                                <p v-if="r.user?.email" class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                    <Mail class="h-3 w-3" /> {{ r.user.email }}
                                </p>
                            </td>
                            <td class="px-4 py-3 max-w-md">
                                <p class="whitespace-pre-line">{{ r.description }}</p>
                                <a
                                    v-if="r.page_url"
                                    :href="r.page_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline mt-1"
                                >
                                    <ExternalLink class="h-3 w-3" /> View page
                                </a>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">{{ formatDateTime(r.created_at) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border"
                                    :class="r.status === 'resolved'
                                        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                        : 'bg-amber-100 text-amber-700 border-amber-200'"
                                >
                                    {{ r.status === 'resolved' ? 'Resolved' : 'Open' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="r.status === 'open'
                                        ? 'text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'
                                        : 'text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/30'"
                                    :disabled="savingId === r.id"
                                    @click="toggleStatus(r)"
                                >
                                    <CheckCircle2 v-if="r.status === 'open'" class="h-3.5 w-3.5" />
                                    <RotateCcw v-else class="h-3.5 w-3.5" />
                                    {{ r.status === 'open' ? 'Mark Resolved' : 'Reopen' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground gap-2">
                    <MessageSquareWarning class="h-10 w-10 opacity-30" />
                    <p class="text-sm font-semibold">No problem reports found.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="reports.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-muted-foreground text-xs">Page {{ reports.current_page }} of {{ reports.last_page }}</p>
                <div class="flex items-center gap-1">
                    <template v-for="link in reports.links" :key="link.label">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-amber-600 text-white border-amber-600' : 'hover:bg-muted text-muted-foreground'"
                        >
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </a>
                        <span v-else class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-xs text-muted-foreground opacity-40">
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </span>
                    </template>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
