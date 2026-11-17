<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PublicLinkField from '@/components/public-forms/PublicLinkField.vue';
import { Button } from '@/components/ui/button';
import { useLiteMode } from '@/composables/useLiteMode';
import { dashboardFilterLabels, hasDashboardFilters, type ClosedReason, type DashboardFilters, type FormDashboardData } from '@/lib/publicForms';
import { router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { BarChart3, ChevronDown, CircleCheck, Clock, Feather, Lock, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DashboardFiltersBar from './DashboardFilters.vue';
import DashboardKpiTiles from './DashboardKpiTiles.vue';
import DashboardProfile from './DashboardProfile.vue';
import DashboardSkeleton from './DashboardSkeleton.vue';
import DashboardTimeline from './DashboardTimeline.vue';
import QuestionSummaryCard from './QuestionSummaryCard.vue';

/**
 * The Dashboard tab of a public form: a one-page summary of its responses
 * that reads like the cover and body of an official report, and prints as one (A4).
 * `dashboard` is a deferred prop, so it is undefined until it loads.
 */
const props = defineProps<{
    form: { slug: string; title: string; kicker: string | null; public_url: string; closed_reason: ClosedReason | null };
    dashboard?: FormDashboardData;
}>();

defineEmits<{ showResponses: [] }>();

const { isLiteMode, setLiteMode } = useLiteMode();

/* ── Filters: kept in the link; only the dashboard is reloaded ─────────────── */

const updating = ref(false);

function applyFilters(filters: DashboardFilters): void {
    router.get(
        route('public-forms.admin.show', props.form.slug),
        {
            tab: 'dashboard',
            region: filters.region ?? undefined,
            office: filters.office ?? undefined,
            from: filters.from ?? undefined,
            to: filters.to ?? undefined,
        },
        {
            only: ['dashboard'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                updating.value = true;
            },
            onFinish: () => {
                updating.value = false;
            },
        },
    );
}

function clearFilters(): void {
    applyFilters({ region: null, office: null, from: null, to: null });
}

const filtered = computed(() => (props.dashboard ? hasDashboardFilters(props.dashboard.filters) : false));
const filterText = computed(() =>
    props.dashboard
        ? dashboardFilterLabels(props.dashboard.filters)
              .map((filter) => filter.label)
              .join(' · ')
        : '',
);

/* ── Report header ────────────────────────────────────────────────────────── */

const STATUS = {
    open: { icon: CircleCheck, badge: 'bg-emerald-300 text-emerald-950' },
    closing_soon: { icon: Clock, badge: 'bg-amber-300 text-amber-950' },
    closed: { icon: Lock, badge: 'bg-white/15 text-white ring-1 ring-white/25' },
};

const status = computed(() => (props.dashboard ? { ...props.dashboard.status, ...STATUS[props.dashboard.status.state] } : null));

/* ── Print: A4, light colors, tables in place of the interactive charts ──── */

const printedAt = ref('');
let restoreDark = false;

function stampPrintDate(): void {
    printedAt.value = new Date().toLocaleString('en-US', { dateStyle: 'long', timeStyle: 'short' });
}

// Printing always uses the light colors, even in dark mode.
useEventListener(window, 'beforeprint', () => {
    stampPrintDate();
    restoreDark = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
});
useEventListener(window, 'afterprint', () => {
    if (restoreDark) document.documentElement.classList.add('dark');
    restoreDark = false;
});

function printSummary(): void {
    stampPrintDate();
    window.print();
}

/* ── Questions ─────────────────────────────────────────────────────────────── */

const showRemoved = ref(false);
const questionCount = computed(() => props.dashboard?.sections.reduce((sum, section) => sum + section.questions.length, 0) ?? 0);
</script>

<template>
    <DashboardSkeleton v-if="!dashboard" />

    <div v-else class="tdi-dashboard pf-report flex flex-col gap-6 bg-transparent bg-none" :aria-busy="updating">
        <!-- Report header: the cover of the summary -->
        <header class="pf-report-header relative overflow-hidden rounded-2xl bg-[#0b1f45] text-white shadow-lg">
            <div class="h-1 bg-gradient-to-r from-[#8a6508] via-[#d4a72c] to-[#8a6508]" aria-hidden="true" />
            <svg
                class="pf-no-print pointer-events-none absolute -right-10 -top-10 h-56 w-56 text-white/[0.06]"
                viewBox="0 0 200 200"
                aria-hidden="true"
            >
                <circle cx="100" cy="100" r="96" fill="none" stroke="currentColor" stroke-width="2" />
                <circle cx="100" cy="100" r="74" fill="none" stroke="currentColor" stroke-width="1.5" />
                <circle cx="100" cy="100" r="52" fill="none" stroke="currentColor" stroke-width="1" />
            </svg>
            <div class="relative flex flex-col gap-4 p-5 sm:p-6 md:flex-row md:items-end md:justify-between">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <p class="pf-gold text-[11px] font-bold uppercase tracking-[0.2em] text-[#e6c766]">Summary of Responses</p>
                    <p v-if="form.kicker" class="text-xs text-white/70">{{ form.kicker }}</p>
                    <h2 class="break-words text-xl font-extrabold leading-tight sm:text-2xl">{{ form.title }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-white/80">
                        <span
                            v-if="status"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold"
                            :class="status.badge"
                        >
                            <component :is="status.icon" class="h-3 w-3" aria-hidden="true" /> {{ status.label }}
                        </span>
                        <span v-if="status">{{ status.detail }}</span>
                        <span>As of {{ dashboard.generated_at }}</span>
                    </div>
                    <p v-if="filtered" class="text-xs text-white/80">
                        <span class="font-semibold text-white"
                            >Showing {{ dashboard.overview.total.toLocaleString() }} of
                            {{ dashboard.overview.all_responses.toLocaleString() }} responses:</span
                        >
                        {{ filterText }}
                    </p>
                    <p class="hidden text-xs print:block">Generated on {{ printedAt }}</p>
                </div>
                <Button
                    v-if="dashboard.overview.all_responses > 0"
                    type="button"
                    size="sm"
                    variant="outline"
                    class="pf-no-print h-9 shrink-0 border-white/30 bg-white/10 text-xs font-semibold text-white hover:bg-white/20 hover:text-white"
                    @click="printSummary"
                >
                    <Printer class="mr-1 h-3.5 w-3.5" /> Print summary
                </Button>
            </div>
        </header>

        <!-- No responses at all yet: share the link -->
        <EmptyState
            v-if="dashboard.overview.all_responses === 0"
            :icon="BarChart3"
            title="Nothing to summarize yet"
            :description="
                form.closed_reason
                    ? 'This form is not open yet. Once it is open and employees answer, their responses are summarized here in charts.'
                    : 'As soon as employees answer, this page shows who responded and what they said, question by question. Share the public link to get started.'
            "
        >
            <div class="w-full max-w-md text-left">
                <PublicLinkField :url="form.public_url" :label="`Public link of ${form.title}`" />
            </div>
        </EmptyState>

        <template v-else>
            <DashboardFiltersBar :filters="dashboard.filters" :options="dashboard.filter_options" :busy="updating" @change="applyFilters" />

            <div
                v-if="isLiteMode"
                role="status"
                class="pf-no-print flex flex-col gap-2 rounded-xl border bg-card px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="flex items-center gap-2 text-muted-foreground">
                    <Feather class="h-4 w-4 shrink-0" aria-hidden="true" /> Lite mode is on, so the charts show as tables to save data.
                </p>
                <Button size="sm" variant="outline" class="h-8 shrink-0 text-xs" @click="setLiteMode(false)"
                    ><BarChart3 class="mr-1 h-3.5 w-3.5" /> Show charts</Button
                >
            </div>

            <EmptyState
                v-if="dashboard.overview.total === 0"
                variant="filtered"
                title="No responses match these filters"
                description="Try a wider date range, or another region or office."
                :on-clear-filters="clearFilters"
            />

            <!-- While the filters reload, the current numbers stay in place, dimmed. -->
            <div v-else class="flex flex-col gap-8 transition-opacity" :class="{ 'pointer-events-none opacity-60': updating }">
                <DashboardKpiTiles :overview="dashboard.overview" :filtered="filtered" class="print:grid-cols-3" />

                <DashboardTimeline v-if="dashboard.timeline.length" :timeline="dashboard.timeline" />

                <section class="flex flex-col gap-3" aria-labelledby="pf-dashboard-profile">
                    <div class="pf-heading flex items-center gap-3">
                        <h2 id="pf-dashboard-profile" class="text-base font-extrabold tracking-tight text-foreground">Who responded</h2>
                        <span class="h-px flex-1 bg-gradient-to-r from-[#b8860b]/60 to-transparent" aria-hidden="true" />
                    </div>
                    <DashboardProfile :profile="dashboard.profile" />
                </section>

                <section class="flex flex-col gap-5" aria-labelledby="pf-dashboard-questions">
                    <div class="pf-heading flex flex-col gap-0.5">
                        <div class="flex items-center gap-3">
                            <h2 id="pf-dashboard-questions" class="text-base font-extrabold tracking-tight text-foreground">Question by question</h2>
                            <span class="h-px flex-1 bg-gradient-to-r from-[#b8860b]/60 to-transparent" aria-hidden="true" />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ questionCount }} {{ questionCount === 1 ? 'question' : 'questions' }}. Each share is out of the people who answered
                            that question.
                        </p>
                    </div>

                    <div v-for="(section, index) in dashboard.sections" :key="section.key" class="flex flex-col gap-3">
                        <div class="pf-heading flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-md bg-[#0b1f45] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white dark:bg-blue-900"
                                >Part {{ index + 1 }}</span
                            >
                            <h3 class="text-sm font-bold text-foreground">{{ section.title }}</h3>
                            <span v-if="section.audience" class="text-[11px] text-muted-foreground">· {{ section.audience }}</span>
                        </div>
                        <div class="grid items-start gap-4 md:grid-cols-2 2xl:grid-cols-3 print:grid-cols-2">
                            <QuestionSummaryCard
                                v-for="question in section.questions"
                                :key="question.key"
                                :question="question"
                                :audience="section.audience"
                                @show-responses="$emit('showResponses')"
                            />
                        </div>
                    </div>

                    <!-- Removed questions: collapsed at the bottom -->
                    <div
                        v-if="dashboard.removed_questions.length"
                        class="flex flex-col gap-3 rounded-2xl border border-dashed p-4"
                        :class="{ 'pf-no-print': !showRemoved }"
                    >
                        <button
                            type="button"
                            class="flex items-center justify-between gap-2 text-left text-sm font-bold text-foreground"
                            :aria-expanded="showRemoved"
                            aria-controls="pf-removed-questions"
                            @click="showRemoved = !showRemoved"
                        >
                            <span>
                                Removed questions ({{ dashboard.removed_questions.length }})
                                <span class="block text-xs font-normal text-muted-foreground"
                                    >Taken off the form; their earlier answers are kept.</span
                                >
                            </span>
                            <ChevronDown class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': showRemoved }" aria-hidden="true" />
                        </button>
                        <div
                            v-if="showRemoved"
                            id="pf-removed-questions"
                            class="grid items-start gap-4 md:grid-cols-2 2xl:grid-cols-3 print:grid-cols-2"
                        >
                            <QuestionSummaryCard
                                v-for="question in dashboard.removed_questions"
                                :key="question.key"
                                :question="question"
                                @show-responses="$emit('showResponses')"
                            />
                        </div>
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>

<style>
/* Print summary (A4): only the report, in light colors, each card kept whole. */
@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    html,
    body {
        background: #fff !important;
    }

    body:has(.pf-report) .peer[data-side],
    body:has(.pf-report) [data-sidebar='sidebar'],
    body:has(.pf-report) main > header,
    .pf-no-print {
        display: none !important;
    }

    .pf-report {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        background-image: none !important;
    }

    .pf-report .pf-card,
    .pf-report .pf-heading {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .pf-report .pf-heading {
        break-after: avoid;
        page-break-after: avoid;
    }

    .pf-report .pf-card {
        box-shadow: none !important;
        background-image: none !important;
    }

    .pf-report .pf-report-header {
        border: 1px solid #cbd5e1;
        border-top: 0;
        background: #fff !important;
        box-shadow: none !important;
        color: #0b1f45 !important;
    }

    .pf-report .pf-report-header * {
        color: #0b1f45 !important;
    }

    .pf-report .pf-report-header .pf-gold {
        color: #8a6508 !important;
    }
}
</style>
