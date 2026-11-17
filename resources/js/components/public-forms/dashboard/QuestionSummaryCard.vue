<script setup lang="ts">
import { questionTypeLabel, takeawayParts, type DashboardOptionRow, type DashboardQuestion } from '@/lib/publicForms';
import { ChevronDown, Inbox, MessageSquareQuote, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { formatPercent, people } from './charts';
import DashboardBarList from './DashboardBarList.vue';
import DashboardCard from './DashboardCard.vue';
import DashboardDonutChart from './DashboardDonutChart.vue';
import DashboardRankingChart from './DashboardRankingChart.vue';
import DashboardScaleBar from './DashboardScaleBar.vue';
import DashboardTable from './DashboardTable.vue';

/**
 * One question's summary: its number and text, who answered it, a one-line
 * takeaway, and the chart that fits its type (with a table view). Typed
 * "Other" answers are listed under the chart.
 */
const props = defineProps<{
    question: DashboardQuestion;
    /** Who the question's section is for, when not everyone (e.g. "For salary grade 18 and above"). */
    audience?: string | null;
}>();

defineEmits<{ showResponses: [] }>();

const MAX_DONUT_CHOICES = 5;

const answered = computed(() => props.question.answered > 0);

/** "Pick one" questions: a rating scale, a donut (5 or fewer choices) or bars. */
const singleChart = computed<'scale' | 'donut' | 'bars'>(() => {
    if (props.question.scale) return 'scale';
    const choices = props.question.options.filter((option) => !option.is_other && !option.removed);
    return choices.length <= MAX_DONUT_CHOICES ? 'donut' : 'bars';
});

function displayLabel(option: DashboardOptionRow): string {
    return option.removed ? `${option.label} (removed choice)` : option.label;
}

const rows = computed(() => props.question.options.map((option) => ({ ...option, label: displayLabel(option) })));

/** Multiple choice: the shares add up past 100% once anyone picked two or more. */
const sharesPast100 = computed(
    () => [...props.question.options, ...props.question.exclusive_options].reduce((sum, option) => sum + option.percent, 0) > 100.5,
);

const eyebrow = computed(() => {
    const type = questionTypeLabel(props.question.type);
    return props.question.number ? `Question ${props.question.number} · ${type}` : `Removed question · ${type}`;
});

const answeredLine = computed(() => {
    const { answered: count, total } = props.question;
    return `Answered by ${count.toLocaleString()} of ${total.toLocaleString()}`;
});

const otherOpen = ref(false);

const table = computed(() => {
    const question = props.question;

    if (question.type === 'ranking') {
        const rankColumns = Array.from({ length: question.rank ?? 0 }, (_, index) => ({
            key: `rank${index}`,
            label: `Rank ${index + 1}`,
            numeric: true,
        }));
        return {
            columns: [
                { key: 'label', label: 'Choice' },
                { key: 'score', label: 'Score', numeric: true },
                ...rankColumns,
                { key: 'count', label: 'Times ranked', numeric: true },
            ],
            rows: rows.value.map((option) => ({
                label: option.label,
                score: option.weighted_score.toLocaleString(),
                ...Object.fromEntries(option.rank_counts.map((count, index) => [`rank${index}`, count.toLocaleString()])),
                count: option.count.toLocaleString(),
            })),
        };
    }

    const all = [...rows.value, ...question.exclusive_options.map((option) => ({ ...option, label: `${option.label} (chosen on its own)` }))];
    const hasScores = all.some((option) => option.score !== null);

    return {
        columns: [
            { key: 'label', label: 'Choice' },
            ...(hasScores ? [{ key: 'score', label: 'Score', numeric: true }] : []),
            { key: 'count', label: 'People', numeric: true },
            { key: 'percent', label: question.type === 'multiple' ? '% of people who answered' : 'Share', numeric: true },
        ],
        rows: all.map((option) => ({
            label: option.label,
            score: option.score ?? '—',
            count: option.count.toLocaleString(),
            percent: formatPercent(option.percent),
        })),
    };
});
</script>

<template>
    <DashboardCard
        :eyebrow="eyebrow"
        :title="question.question"
        :print-chart="question.type !== 'single' || singleChart !== 'donut'"
        :accent="question.type === 'ranking' ? 'gold' : 'navy'"
    >
        <div class="-mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
            <span class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 font-semibold text-foreground">
                <Users class="h-3 w-3 text-muted-foreground" aria-hidden="true" /> {{ answeredLine }}
            </span>
            <span
                v-if="audience"
                class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-medium text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
            >
                {{ audience }}
            </span>
            <span v-if="question.type === 'multiple' && question.max" class="rounded-full border px-2 py-0.5 text-muted-foreground"
                >Up to {{ question.max }} choices</span
            >
        </div>

        <p class="text-sm font-semibold leading-relaxed text-foreground">
            <template v-for="(part, index) in takeawayParts(question.takeaway)" :key="index">
                <strong v-if="part.strong" class="font-extrabold text-[#0b2a5b] dark:text-blue-200">{{ part.text }}</strong>
                <template v-else>{{ part.text }}</template>
            </template>
        </p>

        <!-- Written answers: the latest ones as quotes (no names). -->
        <template v-if="question.type === 'text'">
            <ul v-if="question.latest_answers.length" class="flex flex-col gap-2" aria-label="Latest written answers">
                <li
                    v-for="(answer, index) in question.latest_answers"
                    :key="index"
                    class="break-inside-avoid rounded-r-xl border-l-[3px] border-[#b8860b] bg-muted/50 py-2 pl-3 pr-3"
                >
                    <p class="flex gap-1.5 whitespace-pre-line break-words text-xs leading-relaxed text-foreground">
                        <MessageSquareQuote class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <span>{{ answer.text }}</span>
                    </p>
                    <p class="mt-1 pl-5 text-[11px] text-muted-foreground">
                        {{ [answer.office, answer.region, answer.submitted_at].filter(Boolean).join(' · ') }}
                    </p>
                </li>
            </ul>
            <p v-else class="flex items-center gap-1.5 rounded-xl border border-dashed px-3 py-4 text-xs text-muted-foreground">
                <Inbox class="h-4 w-4" /> No one has written an answer yet.
            </p>
        </template>

        <p v-else-if="!answered" class="flex items-center gap-1.5 rounded-xl border border-dashed px-3 py-4 text-xs text-muted-foreground">
            <Inbox class="h-4 w-4" /> No answers yet.
        </p>

        <template v-if="question.type !== 'text' && answered" #chart>
            <DashboardScaleBar
                v-if="question.type === 'single' && singleChart === 'scale' && question.scale"
                :options="question.options"
                :scale="question.scale"
                :label="question.question"
            />
            <DashboardDonutChart v-else-if="question.type === 'single' && singleChart === 'donut'" :rows="rows" :label="question.question" />
            <DashboardBarList v-else-if="question.type === 'single'" :rows="rows" :label="question.question" />

            <div v-else-if="question.type === 'multiple'" class="flex flex-col gap-3">
                <DashboardBarList :rows="rows" measure="percent" :label="question.question" />
                <p v-if="sharesPast100" class="text-[11px] text-muted-foreground">
                    People could choose more than one, so the shares add up to more than 100%.
                </p>
                <div v-if="question.exclusive_options.length" class="rounded-xl border bg-muted/40 px-3 py-2">
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Chosen on its own</p>
                    <ul class="flex flex-col gap-1 text-xs">
                        <li v-for="option in question.exclusive_options" :key="option.value" class="flex items-start justify-between gap-3">
                            <span class="break-words">{{ option.label }}</span>
                            <span class="shrink-0 tabular-nums"
                                ><span class="font-semibold">{{ formatPercent(option.percent) }}</span
                                ><span class="text-muted-foreground"> · {{ option.count.toLocaleString() }}</span></span
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <DashboardRankingChart
                v-else-if="question.type === 'ranking'"
                :options="question.options"
                :rank="question.rank ?? 1"
                :label="question.question"
            />
        </template>

        <template v-if="question.type !== 'text' && answered" #table>
            <DashboardTable :caption="question.question" :columns="table.columns" :rows="table.rows" />
        </template>

        <template #footer>
            <!-- What people typed for "Other", grouped -->
            <div v-if="question.other && question.other.count > 0" class="border-t pt-3">
                <button
                    type="button"
                    class="pf-no-print flex w-full items-center justify-between gap-2 text-left text-xs font-semibold text-foreground hover:text-blue-700 dark:hover:text-blue-300"
                    :aria-expanded="otherOpen"
                    @click="otherOpen = !otherOpen"
                >
                    <span>What people typed for “{{ question.other.label }}” ({{ people(question.other.count) }})</span>
                    <ChevronDown class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': otherOpen }" aria-hidden="true" />
                </button>
                <p class="hidden text-xs font-semibold print:block">What people typed for “{{ question.other.label }}”</p>
                <ul class="mt-2 flex-col gap-1 text-xs" :class="otherOpen ? 'flex' : 'hidden print:flex'">
                    <li
                        v-for="answer in question.other.answers"
                        :key="answer.text"
                        class="flex items-start justify-between gap-3 rounded-lg bg-muted/50 px-2.5 py-1.5"
                    >
                        <span class="break-words">{{ answer.text }}</span>
                        <span class="shrink-0 tabular-nums text-muted-foreground">× {{ answer.count.toLocaleString() }}</span>
                    </li>
                    <li v-if="!question.other.answers.length" class="text-muted-foreground">
                        They picked “{{ question.other.label }}” without typing an answer.
                    </li>
                    <li v-if="question.other.answers.length === 10" class="text-[11px] text-muted-foreground">The 10 most common answers.</li>
                </ul>
            </div>

            <button
                v-if="question.type === 'text' && question.answered > 0"
                type="button"
                class="pf-no-print self-start text-xs font-semibold text-blue-700 hover:underline dark:text-blue-300"
                @click="$emit('showResponses')"
            >
                See all {{ question.answered.toLocaleString() }} in Responses →
            </button>
        </template>
    </DashboardCard>
</template>
