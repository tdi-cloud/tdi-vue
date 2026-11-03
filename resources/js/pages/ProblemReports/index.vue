<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import ProblemReportThread, { type ProblemReportReply } from '@/components/ProblemReportThread.vue';
import SearchEmptyState from '@/components/SearchEmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Mail,
    MessageSquareWarning,
    MessagesSquare,
    RotateCcw,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    user_id: number;
    user: ReporterUser | null;
    replies: ProblemReportReply[];
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
        // Set when opened from a reply notification: just that report is shown.
        report?: string;
    };
    // "Did you mean …?" when a search finds nothing (server-side SearchSuggester).
    suggestion?: string | null;
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
        router.get(
            route('problem-reports.index'),
            {
                search: search.value || undefined,
                status: status.value !== 'all' ? status.value : undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 350);
});

const formatDateTime = (d: string) => {
    return new Date(d).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const showAllReports = () => router.get(route('problem-reports.index'));

// The report whose conversation is open; a reply notification (?report=) opens it straight away.
const expandedId = ref<number | null>(props.filters.report ? Number(props.filters.report) : null);

const toggleThread = (report: ProblemReportRow) => {
    expandedId.value = expandedId.value === report.id ? null : report.id;
};

// The reporter wrote last, so the report is waiting for a super admin's answer.
const reporterRepliedLast = (report: ProblemReportRow) =>
    report.replies.length > 0 && report.replies[report.replies.length - 1].user_id === report.user_id;

const savingId = ref<number | null>(null);

const toggleStatus = (report: ProblemReportRow) => {
    const newStatus = report.status === 'open' ? 'resolved' : 'open';
    savingId.value = report.id;
    router.put(
        route('problem-reports.update-status', report.id),
        { status: newStatus },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                savingId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Problem Reports" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-600 shadow-md">
                    <MessageSquareWarning class="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold leading-none">Problem Reports</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">Issues submitted by users — super admin only</p>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="relative max-w-sm flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by reporter name or description..."
                            class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        />
                    </div>
                    <div class="flex items-center gap-1.5 rounded-lg border bg-muted/30 px-2 py-1.5 text-xs text-muted-foreground">
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        <span>Filters</span>
                    </div>
                    <button
                        v-if="hasActiveFilters"
                        class="flex items-center gap-1 px-2 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                        @click="clearFilters"
                    >
                        <X class="h-3.5 w-3.5" /> Clear all
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 rounded-xl border bg-muted/30 p-3 md:grid-cols-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Status</label>
                        <select v-model="status" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm">
                            <option value="all">All</option>
                            <option value="open">Open</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="filters.report && reports.data.length"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-muted/40 px-4 py-2.5 text-sm"
                >
                    <span class="text-muted-foreground">Showing the report from your notification.</span>
                    <button type="button" class="font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="showAllReports">
                        Show all reports
                    </button>
                </div>

                <p class="text-xs text-muted-foreground">Showing {{ reports.from ?? 0 }}–{{ reports.to ?? 0 }} of {{ reports.total }} report(s)</p>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-2xl border bg-background shadow-sm">
                <table v-if="reports.data.length" class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b-2 border-amber-200 bg-gradient-to-r from-amber-50 via-orange-50 to-yellow-50 dark:border-amber-900 dark:from-amber-950/40 dark:via-orange-950/40 dark:to-yellow-950/40"
                        >
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Reporter</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                                Description
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                                Submitted
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <template v-for="r in reports.data" :key="r.id">
                            <tr class="align-top transition-colors hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-bold leading-tight">{{ r.user?.name ?? 'Unknown user' }}</p>
                                    <p v-if="r.user?.email" class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                        <Mail class="h-3 w-3" /> {{ r.user.email }}
                                    </p>
                                </td>
                                <td class="max-w-md px-4 py-3">
                                    <p class="whitespace-pre-line">{{ r.description }}</p>
                                    <a
                                        v-if="r.page_url"
                                        :href="r.page_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                                    >
                                        <ExternalLink class="h-3 w-3" /> View page
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">{{ formatDateTime(r.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            r.status === 'resolved'
                                                ? 'border-emerald-200 bg-emerald-100 text-emerald-700'
                                                : 'border-amber-200 bg-amber-100 text-amber-700'
                                        "
                                    >
                                        {{ r.status === 'resolved' ? 'Resolved' : 'Open' }}
                                    </span>
                                    <span
                                        v-if="reporterRepliedLast(r)"
                                        class="mt-1.5 flex w-fit items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/20"
                                    >
                                        <MessagesSquare class="h-3 w-3" aria-hidden="true" /> Reporter replied
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/30"
                                            :aria-expanded="expandedId === r.id"
                                            :aria-controls="`report-thread-${r.id}`"
                                            @click="toggleThread(r)"
                                        >
                                            <MessagesSquare class="h-3.5 w-3.5" />
                                            {{ expandedId === r.id ? 'Hide replies' : 'Reply' }}
                                            <span v-if="r.replies.length" class="rounded-full bg-blue-100 px-1.5 text-[10px] dark:bg-blue-500/20">{{
                                                r.replies.length
                                            }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                            :class="
                                                r.status === 'open'
                                                    ? 'text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'
                                                    : 'text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/30'
                                            "
                                            :disabled="savingId === r.id"
                                            @click="toggleStatus(r)"
                                        >
                                            <CheckCircle2 v-if="r.status === 'open'" class="h-3.5 w-3.5" />
                                            <RotateCcw v-else class="h-3.5 w-3.5" />
                                            {{ r.status === 'open' ? 'Mark Resolved' : 'Reopen' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Conversation with the reporter -->
                            <tr v-if="expandedId === r.id" :id="`report-thread-${r.id}`">
                                <td colspan="5" class="bg-muted/20 px-4 py-4">
                                    <div class="mx-auto max-w-3xl">
                                        <ProblemReportThread :report="r" empty-text="No replies yet. Your reply will notify the reporter." />
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <template v-else>
                    <SearchEmptyState
                        v-if="filters.search"
                        :query="filters.search"
                        :suggestion="suggestion"
                        class="m-4"
                        @search="search = $event"
                        @clear-filters="clearFilters"
                    />
                    <EmptyState
                        v-else-if="filters.report"
                        variant="filtered"
                        title="Report not found"
                        description="It may have been removed."
                        :action="{ label: 'Show all reports', onClick: showAllReports }"
                        class="m-4"
                    />
                    <EmptyState
                        v-else-if="hasActiveFilters"
                        variant="filtered"
                        title="No problem reports found."
                        description="Try another status."
                        class="m-4"
                        @clear-filters="clearFilters"
                    />
                    <EmptyState
                        v-else
                        :icon="MessageSquareWarning"
                        title="No problem reports yet"
                        description="Reports that users send with the Report a problem button will appear here."
                        class="m-4"
                    />
                </template>
            </div>

            <!-- Pagination -->
            <div v-if="reports.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-xs text-muted-foreground">Page {{ reports.current_page }} of {{ reports.last_page }}</p>
                <div class="flex items-center gap-1">
                    <template v-for="link in reports.links" :key="link.label">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'border-amber-600 bg-amber-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                        >
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </a>
                        <span v-else class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs text-muted-foreground opacity-40">
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
