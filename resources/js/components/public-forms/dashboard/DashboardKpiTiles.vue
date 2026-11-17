<script setup lang="ts">
import type { FormDashboardData } from '@/lib/publicForms';
import { ArrowDownRight, ArrowUpRight, Building2, CalendarDays, Clock, MailCheck, MailWarning, MapPin, Minus, Users } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

/** The headline numbers: one tile each, a large figure with a short label and a line of context. */
const props = defineProps<{
    overview: FormDashboardData['overview'];
    filtered: boolean;
}>();

interface Tile {
    key: string;
    label: string;
    value: string;
    /** Smaller figure for text values, e.g. "2 hours ago". */
    compact?: boolean;
    note: string;
    icon: Component;
    accent: string;
    trend?: { icon: Component; text: string; tone: string };
}

const weekTrend = computed<Tile['trend']>(() => {
    const { last_7_days: current, previous_7_days: previous } = props.overview;
    const before = `${previous.toLocaleString()} the week before`;

    if (current > previous) {
        return { icon: ArrowUpRight, text: `Up from ${before}`, tone: 'bg-blue-50 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200' };
    }
    if (current < previous) {
        return { icon: ArrowDownRight, text: `Down from ${before}`, tone: 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200' };
    }
    return { icon: Minus, text: `Same as ${before}`, tone: 'bg-muted text-muted-foreground' };
});

const tiles = computed<Tile[]>(() => {
    const overview = props.overview;

    return [
        {
            key: 'total',
            label: 'Total responses',
            value: overview.total.toLocaleString(),
            note: props.filtered
                ? `Of ${overview.all_responses.toLocaleString()} in all`
                : overview.first_response_at
                  ? `Since ${overview.first_response_at}`
                  : 'None yet',
            icon: Users,
            accent: 'tdi-accent-navy tdi-accent-to-royal',
        },
        {
            key: 'week',
            label: 'Last 7 days',
            value: overview.last_7_days.toLocaleString(),
            note: `${overview.today.toLocaleString()} today`,
            icon: CalendarDays,
            accent: 'tdi-accent-royal tdi-accent-to-sky',
            trend: weekTrend.value,
        },
        {
            key: 'regions',
            label: 'Regions represented',
            value: overview.regions.toLocaleString(),
            note: overview.regions === 1 ? 'One region so far' : 'With at least one response',
            icon: MapPin,
            accent: 'tdi-accent-navy tdi-accent-to-sky',
        },
        {
            key: 'offices',
            label: 'Offices represented',
            value: overview.offices.toLocaleString(),
            note: 'Offices and divisions',
            icon: Building2,
            accent: 'tdi-accent-royal tdi-accent-to-violet',
        },
        {
            key: 'latest',
            label: 'Latest response',
            value: overview.latest_response_ago ?? '—',
            compact: true,
            note: overview.latest_response_at ?? 'No responses yet',
            icon: Clock,
            accent: 'tdi-accent-gold tdi-accent-to-amber',
        },
        {
            key: 'emails',
            label: 'Emails pending',
            value: overview.emails_pending.toLocaleString(),
            note: overview.emails_pending ? 'Confirmation emails not sent yet' : 'Every confirmation email was sent',
            icon: overview.emails_pending ? MailWarning : MailCheck,
            accent: overview.emails_pending ? 'tdi-accent-amber tdi-accent-to-gold' : 'tdi-accent-navy tdi-accent-to-royal',
        },
    ];
});
</script>

<template>
    <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" aria-label="Headline numbers">
        <li
            v-for="tile in tiles"
            :key="tile.key"
            class="tdi-card pf-card relative flex flex-col gap-2 overflow-hidden rounded-2xl border p-4"
            :class="tile.accent"
        >
            <span class="tdi-icon-chip flex h-8 w-8 items-center justify-center rounded-lg" aria-hidden="true">
                <component :is="tile.icon" class="h-4 w-4" />
            </span>
            <p class="font-extrabold tabular-nums leading-none tracking-tight text-foreground" :class="tile.compact ? 'text-lg' : 'text-[1.75rem]'">
                {{ tile.value }}
            </p>
            <div class="flex flex-col gap-1">
                <p class="text-xs font-semibold text-foreground">{{ tile.label }}</p>
                <p class="text-[11px] leading-snug text-muted-foreground">{{ tile.note }}</p>
                <p
                    v-if="tile.trend"
                    class="inline-flex w-fit items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                    :class="tile.trend.tone"
                >
                    <component :is="tile.trend.icon" class="h-3 w-3" aria-hidden="true" /> {{ tile.trend.text }}
                </p>
            </div>
        </li>
    </ul>
</template>
