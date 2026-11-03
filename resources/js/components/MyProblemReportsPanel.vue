<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import ProblemReportThread, { type ProblemReportReply } from '@/components/ProblemReportThread.vue';
import ReportProblemButton from '@/components/ReportProblemButton.vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, CircleDot, ExternalLink, MessageSquareWarning } from 'lucide-vue-next';

export interface MyProblemReport {
    id: number;
    user_id: number;
    description: string;
    page_url: string | null;
    status: 'open' | 'resolved';
    created_at: string;
    replies: ProblemReportReply[];
}

export interface MyProblemReportPage {
    data: MyProblemReport[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
    reports: MyProblemReportPage;
    // `report` is set when the page was opened from a notification (just that report is shown).
    filters: { report?: string };
}>();

const formatDateTime = (d: string) =>
    new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

const pageLabel = (label: string) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
</script>

<template>
    <section class="flex flex-col gap-4">
        <div
            v-if="filters.report && reports.data.length"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-muted/40 px-4 py-2.5 text-sm"
        >
            <span class="text-muted-foreground">Showing the report from your notification.</span>
            <Link :href="route('problem-reports.mine')" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">
                Show all my reports
            </Link>
        </div>

        <template v-if="reports.data.length">
            <article
                v-for="r in reports.data"
                :key="r.id"
                class="flex flex-col gap-4 rounded-2xl border bg-card p-4 text-card-foreground shadow-sm sm:p-5"
                :aria-labelledby="`my-report-${r.id}`"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                        :class="
                            r.status === 'resolved'
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20'
                                : 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20'
                        "
                    >
                        <CheckCircle2 v-if="r.status === 'resolved'" class="h-3.5 w-3.5" aria-hidden="true" />
                        <CircleDot v-else class="h-3.5 w-3.5" aria-hidden="true" />
                        {{ r.status === 'resolved' ? 'Resolved' : 'Open' }}
                    </span>
                    <span class="text-xs text-muted-foreground">Sent {{ formatDateTime(r.created_at) }}</span>
                </div>

                <div>
                    <h2 :id="`my-report-${r.id}`" class="sr-only">Problem report sent {{ formatDateTime(r.created_at) }}</h2>
                    <p class="whitespace-pre-line break-words text-sm text-foreground">{{ r.description }}</p>
                    <a
                        v-if="r.page_url"
                        :href="r.page_url"
                        class="mt-1.5 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                    >
                        <ExternalLink class="h-3 w-3" aria-hidden="true" /> Page where it happened
                    </a>
                </div>

                <div class="border-t pt-4">
                    <ProblemReportThread :report="r" empty-text="No replies yet. You'll get a notification when a super admin replies." />
                </div>
            </article>
        </template>

        <EmptyState
            v-else-if="filters.report"
            variant="filtered"
            title="Report not found"
            description="It may have been removed. Your other reports are still on this page."
            :action="{ label: 'Show all my reports', href: route('problem-reports.mine') }"
        />
        <EmptyState
            v-else
            :icon="MessageSquareWarning"
            title="No problem reports yet"
            description="Reports you send with the Report a problem button, and the replies to them, will appear here."
        >
            <ReportProblemButton label="Report a problem" button-class="border text-foreground hover:bg-accent" />
        </EmptyState>

        <!-- Pagination -->
        <nav v-if="reports.last_page > 1" class="flex flex-wrap items-center justify-between gap-3" aria-label="My problem reports pagination">
            <p class="text-xs text-muted-foreground">Showing {{ reports.from }}–{{ reports.to }} of {{ reports.total }}</p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in reports.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs font-medium transition"
                        :class="link.active ? 'border-amber-600 bg-amber-600 text-white' : 'text-foreground hover:bg-accent'"
                        :aria-current="link.active ? 'page' : undefined"
                    >
                        {{ pageLabel(link.label) }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs text-muted-foreground opacity-50"
                    >
                        {{ pageLabel(link.label) }}
                    </span>
                </template>
            </div>
        </nav>
    </section>
</template>
