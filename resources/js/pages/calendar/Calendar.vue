<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import type { CalendarOptions, DatesSetArg, EventClickArg, EventHoveringArg, EventMountArg } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';

import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    ArrowRight,
    Building2,
    CalendarClock,
    CalendarDays,
    CalendarRange,
    CalendarX2,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    CirclePlay,
    Clock3,
    Columns3,
    ExternalLink,
    Filter,
    GraduationCap,
    Layers3,
    List,
    MapPin,
    MousePointerClick,
    RotateCcw,
    Tag,
    Users,
    X,
} from 'lucide-vue-next';

interface CalendarEvent {
    id: number;
    title: string;
    start: string;
    end: string | null;
    allDay: boolean;
    backgroundColor: string;
    borderColor: string;
    extendedProps: {
        program_id: number | null;
        program_code: string;
        program_title: string | null;
        competency: string | null;
        category: string | null;
        provider: string | null;
        batch: string;
        status: string;
        modality: string;
        venue: string | null;
        date_start: string;
        date_end: string;
        time_start: string;
        time_end: string;
        days: string;
        hours: string;
        participants: number;
    };
}

type EventDetails = CalendarEvent['extendedProps'];

const props = defineProps<{
    events: CalendarEvent[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendar', href: '/calendar' }];

// Dialog state para sa clicked event
const showDialog = ref(false);
const selected = ref<CalendarEvent['extendedProps'] | null>(null);

// Stack order sa month view: mga hindi pa nagsisimula (upcoming) sa taas,
// mga nag-start na (ongoing/completed) sa ilalim — para hindi natatabunan
// ang paparating na programs ng mga lumang training kapag nagko-collapse
// sa "+x more".
//
// Note: dapat isang function ang ibigay sa `eventOrder` (hindi basta i-pre-sort
// ang `events` array + `eventOrder: false`) dahil internally, ID-based object
// ang ginagamit ni FullCalendar bilang event store — palaging pinag-uusapan
// ang mga integer-like key sa ascending numeric order sa JS regardless ng
// insertion order, kaya nawawala ang custom array order bago pa man ito
// makarating sa stacking algorithm.
const todayStart = new Date();
todayStart.setHours(0, 0, 0, 0);
const todayMs = todayStart.getTime();

function eventOrder(a: { start: number }, b: { start: number }): number {
    const aStarted = a.start <= todayMs;
    const bStarted = b.start <= todayMs;
    if (aStarted !== bStarted) return aStarted ? 1 : -1;
    return a.start - b.start;
}

// ── Filter by program category ──────────────────────────────────────────────
const selectedCategory = ref('all');

const categories = computed(() => [...new Set(props.events.map((e) => e.extendedProps.category).filter((c): c is string => !!c))].sort());

const filteredEvents = computed(() =>
    selectedCategory.value === 'all' ? props.events : props.events.filter((e) => e.extendedProps.category === selectedCategory.value),
);

const statusVariant = computed(() => statusMeta(selected.value?.status).badge);

// ── Status styling ──────────────────────────────────────────────────────────
interface StatusMeta {
    label: string;
    dot: string;
    badge: string;
    event: string;
}

const STATUS_META: Record<string, StatusMeta> = {
    upcoming: {
        label: 'Upcoming',
        dot: 'bg-indigo-500',
        badge: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-400/30',
        event: 'border-indigo-500 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20',
    },
    active: {
        label: 'Active',
        dot: 'bg-cyan-500',
        badge: 'bg-cyan-50 text-cyan-700 ring-cyan-600/20 dark:bg-cyan-500/15 dark:text-cyan-300 dark:ring-cyan-400/30',
        event: 'border-cyan-500 bg-cyan-50 hover:bg-cyan-100 dark:bg-cyan-500/10 dark:hover:bg-cyan-500/20',
    },
    completed: {
        label: 'Completed',
        dot: 'bg-emerald-500',
        badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-400/30',
        event: 'border-emerald-500 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20',
    },
    rescheduled: {
        label: 'Rescheduled',
        dot: 'bg-amber-500',
        badge: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-400/30',
        event: 'border-amber-500 bg-amber-50 hover:bg-amber-100 dark:bg-amber-500/10 dark:hover:bg-amber-500/20',
    },
};

const FALLBACK_STATUS: StatusMeta = {
    label: 'Unknown',
    dot: 'bg-slate-400',
    badge: 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-500/15 dark:text-slate-300 dark:ring-slate-400/30',
    event: 'border-slate-400 bg-slate-100 hover:bg-slate-200 dark:bg-slate-500/10 dark:hover:bg-slate-500/20',
};

function statusMeta(status: string | null | undefined): StatusMeta {
    const key = status?.toLowerCase() ?? '';
    const meta = STATUS_META[key];
    if (meta) return meta;
    return status ? { ...FALLBACK_STATUS, label: status } : FALLBACK_STATUS;
}

const legend = Object.values(STATUS_META);

// ── KPI counts (follow the category filter) ─────────────────────────────────
const statusCounts = computed(() => {
    const counts: Record<string, number> = { upcoming: 0, active: 0, completed: 0, rescheduled: 0 };
    for (const e of filteredEvents.value) {
        const key = e.extendedProps.status?.toLowerCase() ?? '';
        if (key in counts) counts[key]++;
    }
    return counts;
});

const kpis = computed(() => [
    {
        label: 'Total Programs',
        value: filteredEvents.value.length,
        icon: CalendarDays,
        tile: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        span: 'col-span-2 sm:col-span-1',
    },
    {
        label: 'Active',
        value: statusCounts.value.active,
        icon: CirclePlay,
        tile: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
        span: '',
    },
    {
        label: 'Upcoming',
        value: statusCounts.value.upcoming,
        icon: CalendarClock,
        tile: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
        span: '',
    },
    {
        label: 'Completed',
        value: statusCounts.value.completed,
        icon: CircleCheck,
        tile: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        span: '',
    },
    {
        label: 'Rescheduled',
        value: statusCounts.value.rescheduled,
        icon: RotateCcw,
        tile: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        span: '',
    },
]);

// ── Date / time formatting (columns are strings, so parse defensively) ─────
function parseDate(value: string | null | undefined): Date | null {
    if (!value) return null;
    const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    const date = iso ? new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3])) : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateRange(startValue: string | null | undefined, endValue: string | null | undefined, short = false): string {
    const start = parseDate(startValue);
    const end = parseDate(endValue);
    if (!start) return [startValue, endValue].filter(Boolean).join(' – ');

    const month = short ? 'short' : 'long';
    const fmt = (d: Date, opts: Intl.DateTimeFormatOptions) => d.toLocaleDateString('en-US', opts);

    if (!end || end.getTime() === start.getTime()) {
        return fmt(start, { month, day: 'numeric', year: 'numeric' });
    }
    if (start.getFullYear() === end.getFullYear()) {
        if (start.getMonth() === end.getMonth()) {
            return `${fmt(start, { month, day: 'numeric' })} – ${end.getDate()}, ${end.getFullYear()}`;
        }
        return `${fmt(start, { month: 'short', day: 'numeric' })} – ${fmt(end, { month: 'short', day: 'numeric', year: 'numeric' })}`;
    }
    return `${fmt(start, { month: 'short', day: 'numeric', year: 'numeric' })} – ${fmt(end, { month: 'short', day: 'numeric', year: 'numeric' })}`;
}

function formatTime(value: string | null | undefined): string {
    if (!value) return '';
    const match = /^(\d{1,2}):(\d{2})(?::\d{2})?$/.exec(value.trim());
    if (!match) return value;
    const hours = Number(match[1]);
    const suffix = hours >= 12 ? 'PM' : 'AM';
    return `${hours % 12 || 12}:${match[2]} ${suffix}`;
}

function formatTimeRange(details: EventDetails): string {
    return [formatTime(details.time_start), formatTime(details.time_end)].filter(Boolean).join(' – ');
}

function formatDuration(details: EventDetails): string {
    const parts: string[] = [];
    if (details.days) parts.push(`${details.days} ${Number(details.days) === 1 ? 'Day' : 'Days'}`);
    if (details.hours) parts.push(`${details.hours} ${Number(details.hours) === 1 ? 'Hour' : 'Hours'}`);
    return parts.join(' · ');
}

// Some batch values already start with "Batch", so avoid rendering "Batch Batch 1"
function batchLabel(batch: string | null | undefined): string {
    const value = (batch ?? '').trim();
    return /^batch\b/i.test(value) ? value : `Batch ${value}`;
}

function displayTitle(details: EventDetails): string {
    return details.program_title ?? details.program_code;
}

function dateParts(value: string | null | undefined): { month: string; day: string; weekday: string } | null {
    const date = parseDate(value);
    if (!date) return null;
    return {
        month: date.toLocaleDateString('en-US', { month: 'short' }),
        day: String(date.getDate()),
        weekday: date.toLocaleDateString('en-US', { weekday: 'short' }),
    };
}

const selectedDate = computed(() => dateParts(selected.value?.date_start));

// ── Upcoming programs panel ─────────────────────────────────────────────────
const upcomingPrograms = computed(() =>
    filteredEvents.value
        .filter((e) => (parseDate(e.start)?.getTime() ?? -1) >= todayMs)
        .sort((a, b) => (parseDate(a.start)?.getTime() ?? 0) - (parseDate(b.start)?.getTime() ?? 0))
        .slice(0, 6),
);

function openDetails(details: EventDetails): void {
    hovered.value = null;
    selected.value = details;
    showDialog.value = true;
}

// ── Custom toolbar (driven by the FullCalendar API) ─────────────────────────
const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null);
const calendarTitle = ref('');
const currentView = ref('dayGridMonth');
const isTodayInView = ref(true);

const viewOptions = [
    { value: 'dayGridMonth', label: 'Month', icon: CalendarDays },
    { value: 'timeGridWeek', label: 'Week', icon: Columns3 },
    { value: 'listMonth', label: 'List', icon: List },
];

function calendarApi() {
    return calendarRef.value?.getApi();
}

function goPrev(): void {
    calendarApi()?.prev();
}

function goNext(): void {
    calendarApi()?.next();
}

function goToday(): void {
    calendarApi()?.today();
}

function changeView(view: string): void {
    calendarApi()?.changeView(view);
}

const periodLabel = computed(() => {
    if (currentView.value === 'timeGridWeek') return 'week';
    return 'month';
});

// ── Hover popover (desktop / fine-pointer devices only) ─────────────────────
const canHover = ref(false);
const hovered = ref<EventDetails | null>(null);
const popoverStyle = ref<Record<string, string>>({});
const POPOVER_WIDTH = 296;

function showPopover(info: EventHoveringArg): void {
    if (!canHover.value || currentView.value.startsWith('list')) return;
    const rect = info.el.getBoundingClientRect();
    const left = Math.min(Math.max(12, rect.left), window.innerWidth - POPOVER_WIDTH - 12);
    const placeAbove = rect.bottom + 230 > window.innerHeight && rect.top > 230;
    popoverStyle.value = placeAbove
        ? { left: `${left}px`, bottom: `${window.innerHeight - rect.top + 8}px` }
        : { left: `${left}px`, top: `${rect.bottom + 8}px` };
    hovered.value = info.event.extendedProps as EventDetails;
}

function hidePopover(): void {
    hovered.value = null;
}

onMounted(() => {
    canHover.value = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    window.addEventListener('scroll', hidePopover, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', hidePopover, true);
});

// Keyboard access for events: focusable, and Enter/Space opens the dialog
function makeEventAccessible(info: EventMountArg): void {
    const details = info.event.extendedProps as EventDetails;
    info.el.setAttribute('tabindex', '0');
    info.el.setAttribute('role', 'button');
    info.el.setAttribute('aria-label', `${displayTitle(details)}, ${batchLabel(details.batch)}, ${statusMeta(details.status).label}. Open details.`);
    info.el.addEventListener('keydown', (e: KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            info.el.click();
        }
    });
}

const calendarOptions = computed<CalendarOptions>(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: false,
    buttonText: {
        today: 'Today',
        month: 'Month',
        week: 'Week',
        list: 'List',
    },
    events: filteredEvents.value,
    eventOrder,
    dayMaxEvents: 3, // "+x more" link kapag masyadong maraming events sa isang araw
    height: 'auto',
    eventDisplay: 'block',
    eventClassNames: 'tdi-event',
    views: {
        dayGridMonth: { dayHeaderFormat: { weekday: 'short' } },
        timeGridWeek: { dayHeaderFormat: { weekday: 'short', day: 'numeric' } },
    },
    noEventsText: 'No programs scheduled for this period',
    datesSet: (arg: DatesSetArg) => {
        calendarTitle.value = arg.view.title;
        currentView.value = arg.view.type;
        const now = Date.now();
        isTodayInView.value = now >= arg.view.currentStart.getTime() && now < arg.view.currentEnd.getTime();
        hidePopover();
    },
    eventDidMount: makeEventAccessible,
    eventMouseEnter: showPopover,
    eventMouseLeave: hidePopover,
    eventClick: (info: EventClickArg) => {
        hidePopover();
        selected.value = info.event.extendedProps as CalendarEvent['extendedProps'];
        showDialog.value = true;
    },
}));
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 max-w-full flex-1 flex-col gap-4 px-3 pb-10 pt-3 sm:gap-6 sm:px-6 sm:pt-4">
            <!-- ===================== HEADER ===================== -->
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-700 text-white shadow-sm dark:bg-blue-600 sm:h-12 sm:w-12"
                    >
                        <CalendarDays class="h-5 w-5 sm:h-6 sm:w-6" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xl font-semibold leading-tight tracking-tight text-foreground sm:text-2xl">
                            Learning &amp; Development Calendar
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">Track training programs, batches, schedules, and learning activities.</p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-1.5 sm:w-auto">
                    <label for="category-filter" class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <Filter class="h-3.5 w-3.5" />
                        Program Category
                    </label>
                    <Select v-model="selectedCategory">
                        <SelectTrigger id="category-filter" class="h-10 w-full bg-card text-sm font-medium sm:w-64">
                            <SelectValue placeholder="All Categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            <SelectItem v-for="c in categories" :key="c" :value="c">
                                {{ c }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </section>

            <!-- ===================== KPI CARDS ===================== -->
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-5" aria-label="Program summary">
                <div
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    class="flex min-w-0 items-center gap-3 rounded-xl border bg-card p-3 shadow-sm sm:p-4"
                    :class="kpi.span"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="kpi.tile">
                        <component :is="kpi.icon" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-muted-foreground">{{ kpi.label }}</p>
                        <p class="text-xl font-semibold tabular-nums leading-tight text-foreground sm:text-2xl">{{ kpi.value }}</p>
                    </div>
                </div>
            </section>

            <!-- ===================== CALENDAR + UPCOMING ===================== -->
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <!-- Calendar -->
                <section class="tdi-calendar min-w-0 rounded-xl border bg-card shadow-sm" aria-label="Training calendar">
                    <!-- Toolbar -->
                    <div class="flex flex-col gap-3 border-b px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:py-4">
                        <div class="flex min-w-0 items-center gap-2">
                            <div class="flex shrink-0 items-center">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="h-10 w-10 rounded-r-none sm:h-9 sm:w-9"
                                    :aria-label="`Previous ${periodLabel}`"
                                    :title="`Previous ${periodLabel}`"
                                    @click="goPrev"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="-ml-px h-10 w-10 rounded-l-none sm:h-9 sm:w-9"
                                    :aria-label="`Next ${periodLabel}`"
                                    :title="`Next ${periodLabel}`"
                                    @click="goNext"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </Button>
                            </div>
                            <h2 class="min-w-0 truncate text-base font-semibold text-foreground sm:text-lg" aria-live="polite">
                                {{ calendarTitle }}
                            </h2>
                        </div>

                        <div class="flex items-center justify-between gap-2 sm:justify-end">
                            <Button variant="outline" class="h-10 px-4 sm:h-9" :disabled="isTodayInView" @click="goToday">Today</Button>
                            <div class="inline-flex rounded-lg border bg-muted/50 p-0.5" role="group" aria-label="Calendar view">
                                <button
                                    v-for="view in viewOptions"
                                    :key="view.value"
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-8"
                                    :class="
                                        currentView === view.value
                                            ? 'bg-blue-700 text-white shadow-sm dark:bg-blue-600'
                                            : 'text-muted-foreground hover:bg-background hover:text-foreground'
                                    "
                                    :aria-pressed="currentView === view.value"
                                    @click="changeView(view.value)"
                                >
                                    <component :is="view.icon" class="hidden h-4 w-4 sm:block" />
                                    {{ view.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-2 sm:p-4">
                        <FullCalendar ref="calendarRef" :options="calendarOptions">
                            <template #eventContent="arg">
                                <!-- List view: detailed row -->
                                <div
                                    v-if="arg.view.type.startsWith('list')"
                                    class="flex flex-col gap-2 py-1 md:flex-row md:items-center md:justify-between md:gap-6"
                                >
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="mt-1 h-10 w-1 shrink-0 rounded-full" :class="statusMeta(arg.event.extendedProps.status).dot" />
                                        <div class="min-w-0">
                                            <span
                                                class="mb-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset"
                                                :class="statusMeta(arg.event.extendedProps.status).badge"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full" :class="statusMeta(arg.event.extendedProps.status).dot" />
                                                {{ statusMeta(arg.event.extendedProps.status).label }}
                                            </span>
                                            <p class="font-semibold leading-snug text-foreground">{{ displayTitle(arg.event.extendedProps) }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ arg.event.extendedProps.program_code }} · {{ batchLabel(arg.event.extendedProps.batch) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1 pl-4 text-xs text-muted-foreground md:shrink-0 md:items-end md:pl-0">
                                        <span class="inline-flex items-center gap-1.5">
                                            <CalendarDays class="h-3.5 w-3.5" />
                                            {{ formatDateRange(arg.event.extendedProps.date_start, arg.event.extendedProps.date_end, true) }}
                                        </span>
                                        <span v-if="formatTimeRange(arg.event.extendedProps)" class="inline-flex items-center gap-1.5">
                                            <Clock3 class="h-3.5 w-3.5" />
                                            {{ formatTimeRange(arg.event.extendedProps) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <Users class="h-3.5 w-3.5" />
                                            {{ arg.event.extendedProps.participants }} Participants
                                        </span>
                                    </div>
                                </div>

                                <!-- Month / week: compact card with a status accent -->
                                <div
                                    v-else
                                    class="w-full min-w-0 overflow-hidden rounded-[5px] border-l-[3px] px-1.5 py-1 text-foreground transition-colors"
                                    :class="statusMeta(arg.event.extendedProps.status).event"
                                >
                                    <p class="truncate text-[11px] font-semibold leading-tight sm:text-xs">
                                        {{ displayTitle(arg.event.extendedProps) }}
                                    </p>
                                    <p class="truncate text-[10px] leading-tight text-muted-foreground sm:text-[11px]">
                                        {{ batchLabel(arg.event.extendedProps.batch) }}
                                    </p>
                                    <p class="mt-0.5 hidden items-center gap-1 truncate text-[11px] leading-tight text-muted-foreground lg:flex">
                                        <template v-if="arg.event.extendedProps.time_start">
                                            <Clock3 class="h-3 w-3 shrink-0" />
                                            <span class="truncate">{{ formatTime(arg.event.extendedProps.time_start) }}</span>
                                            <span aria-hidden="true">·</span>
                                        </template>
                                        <Users class="h-3 w-3 shrink-0" />
                                        <span>{{ arg.event.extendedProps.participants }}</span>
                                    </p>
                                </div>
                            </template>
                        </FullCalendar>
                    </div>

                    <!-- Legend -->
                    <div class="flex flex-col gap-2 border-t px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <ul class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-muted-foreground sm:flex sm:flex-wrap sm:items-center sm:gap-x-5">
                            <li v-for="item in legend" :key="item.label" class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :class="item.dot" />
                                {{ item.label }}
                            </li>
                        </ul>
                        <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MousePointerClick class="h-3.5 w-3.5" />
                            Select a program to view full details
                        </p>
                    </div>
                </section>

                <!-- Upcoming Programs -->
                <aside class="flex min-w-0 flex-col rounded-xl border bg-card shadow-sm xl:self-start" aria-labelledby="upcoming-heading">
                    <div class="flex items-center justify-between border-b px-4 py-3 sm:px-5 sm:py-4">
                        <h2 id="upcoming-heading" class="text-base font-semibold text-foreground">Upcoming Programs</h2>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground">
                            {{ upcomingPrograms.length }}
                        </span>
                    </div>

                    <ul v-if="upcomingPrograms.length" class="grid divide-y md:grid-cols-2 md:divide-y-0 xl:grid-cols-1 xl:divide-y">
                        <li v-for="event in upcomingPrograms" :key="event.id" class="md:border-b xl:border-b-0">
                            <button
                                type="button"
                                class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:px-5"
                                @click="openDetails(event.extendedProps)"
                            >
                                <div
                                    v-if="dateParts(event.start)"
                                    class="flex w-12 shrink-0 flex-col items-center rounded-lg border bg-background py-1.5 leading-none"
                                >
                                    <span class="text-[10px] font-semibold uppercase text-blue-700 dark:text-blue-400">{{
                                        dateParts(event.start)?.month
                                    }}</span>
                                    <span class="mt-0.5 text-lg font-semibold tabular-nums text-foreground">{{ dateParts(event.start)?.day }}</span>
                                    <span class="mt-0.5 text-[10px] text-muted-foreground">{{ dateParts(event.start)?.weekday }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="line-clamp-2 text-sm font-semibold leading-snug text-foreground">
                                            {{ displayTitle(event.extendedProps) }}
                                        </p>
                                        <span
                                            class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                            :class="statusMeta(event.extendedProps.status).badge"
                                        >
                                            {{ statusMeta(event.extendedProps.status).label }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ batchLabel(event.extendedProps.batch) }}</p>
                                    <div class="mt-1.5 flex flex-col gap-1 text-xs text-muted-foreground">
                                        <span v-if="formatTimeRange(event.extendedProps)" class="inline-flex items-center gap-1.5">
                                            <Clock3 class="h-3.5 w-3.5 shrink-0" />
                                            {{ formatTimeRange(event.extendedProps) }}
                                        </span>
                                        <span v-if="event.extendedProps.venue" class="inline-flex min-w-0 items-center gap-1.5">
                                            <MapPin class="h-3.5 w-3.5 shrink-0" />
                                            <span class="truncate">{{ event.extendedProps.venue }}</span>
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </li>
                    </ul>

                    <div v-else class="flex flex-col items-center gap-2 px-6 py-10 text-center">
                        <CalendarX2 class="h-8 w-8 text-muted-foreground/60" />
                        <p class="text-sm font-medium text-foreground">No upcoming programs</p>
                        <p class="text-xs text-muted-foreground">Programs scheduled from today onward will appear here.</p>
                    </div>
                </aside>
            </div>
        </div>

        <!-- ===================== HOVER POPOVER ===================== -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 translate-y-1"
                leave-active-class="transition duration-75 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="hovered"
                    class="pointer-events-none fixed z-50 w-[296px] rounded-xl border bg-popover p-4 text-popover-foreground shadow-lg"
                    :style="popoverStyle"
                    role="tooltip"
                >
                    <p class="text-sm font-semibold leading-snug">{{ displayTitle(hovered) }}</p>
                    <p class="text-xs text-muted-foreground">{{ hovered.program_code }} · {{ batchLabel(hovered.batch) }}</p>
                    <div class="mt-3 space-y-1.5 text-xs">
                        <p class="flex items-center gap-2">
                            <CalendarDays class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            {{ formatDateRange(hovered.date_start, hovered.date_end, true) }}
                        </p>
                        <p v-if="formatTimeRange(hovered)" class="flex items-center gap-2">
                            <Clock3 class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            {{ formatTimeRange(hovered) }}
                        </p>
                        <p class="flex items-center gap-2">
                            <Users class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            {{ hovered.participants }} Participants
                        </p>
                        <p v-if="hovered.venue" class="flex items-center gap-2">
                            <MapPin class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            <span class="truncate">{{ hovered.venue }}</span>
                        </p>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t pt-3">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                            :class="statusMeta(hovered.status).badge"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="statusMeta(hovered.status).dot" />
                            {{ statusMeta(hovered.status).label }}
                        </span>
                        <span class="text-[11px] text-muted-foreground">Click to view details</span>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ===================== EVENT DETAILS DIALOG ===================== -->
        <Dialog v-model:open="showDialog">
            <DialogContent
                class="flex max-h-[calc(100dvh-2rem)] w-[calc(100%-1.5rem)] flex-col gap-0 overflow-hidden rounded-xl p-0 sm:max-w-2xl [&>button:last-child]:hidden"
            >
                <template v-if="selected">
                    <!-- Header -->
                    <div class="shrink-0 border-b bg-muted/40 px-4 pb-4 pt-4 sm:px-6 sm:pb-5 sm:pt-5">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Program Schedule</p>
                            <DialogClose
                                class="-mr-2 inline-flex h-10 w-10 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                aria-label="Close"
                            >
                                <X class="h-5 w-5" />
                            </DialogClose>
                        </div>

                        <div class="mt-1 flex items-start gap-4">
                            <div
                                v-if="selectedDate"
                                class="hidden w-16 shrink-0 flex-col items-center rounded-lg border bg-background py-2 leading-none shadow-sm sm:flex"
                            >
                                <span class="text-[11px] font-semibold uppercase text-blue-700 dark:text-blue-400">{{ selectedDate.month }}</span>
                                <span class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ selectedDate.day }}</span>
                                <span class="mt-1 text-[11px] uppercase text-muted-foreground">{{ selectedDate.weekday }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <DialogTitle class="text-lg font-semibold leading-snug text-foreground sm:text-xl">
                                    {{ selected.program_title ?? selected.program_code }}
                                </DialogTitle>
                                <DialogDescription class="mt-1 text-sm text-muted-foreground">
                                    {{ selected.program_code }} · {{ batchLabel(selected.batch) }}
                                </DialogDescription>
                                <span
                                    class="mt-2.5 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset"
                                    :class="statusVariant"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusMeta(selected.status).dot" />
                                    {{ statusMeta(selected.status).label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Scrollable body -->
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4 sm:space-y-6 sm:px-6 sm:py-5">
                        <!-- Schedule -->
                        <div class="grid grid-cols-1 gap-3 rounded-lg border bg-blue-50/60 p-3 dark:bg-blue-500/5 sm:grid-cols-2 sm:p-4">
                            <div class="flex items-start gap-3">
                                <CalendarDays class="mt-0.5 h-5 w-5 shrink-0 text-blue-700 dark:text-blue-400" />
                                <div class="min-w-0">
                                    <p class="text-xs text-muted-foreground">Date</p>
                                    <p class="text-sm font-semibold text-foreground">{{ formatDateRange(selected.date_start, selected.date_end) }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <Clock3 class="mt-0.5 h-5 w-5 shrink-0 text-blue-700 dark:text-blue-400" />
                                <div class="min-w-0">
                                    <p class="text-xs text-muted-foreground">Time</p>
                                    <p class="text-sm font-semibold text-foreground">{{ formatTimeRange(selected) || 'Not specified' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick facts -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <Users class="h-5 w-5 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold tabular-nums text-foreground">{{ selected.participants }}</p>
                                    <p class="text-xs text-muted-foreground">Participants</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <CalendarRange class="h-5 w-5 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-foreground">{{ formatDuration(selected) || '—' }}</p>
                                    <p class="text-xs text-muted-foreground">Duration</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <Layers3 class="h-5 w-5 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ selected.modality || '—' }}</p>
                                    <p class="text-xs text-muted-foreground">Modality</p>
                                </div>
                            </div>
                        </div>

                        <!-- Training information -->
                        <section>
                            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Training Information</h3>
                            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="flex items-start gap-3">
                                    <Tag class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0">
                                        <dt class="text-xs text-muted-foreground">Category</dt>
                                        <dd
                                            class="text-sm font-medium"
                                            :class="selected.category ? 'text-foreground' : 'italic text-muted-foreground'"
                                        >
                                            {{ selected.category || 'Not specified' }}
                                        </dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <GraduationCap class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0">
                                        <dt class="text-xs text-muted-foreground">Competency</dt>
                                        <dd
                                            class="text-sm font-medium"
                                            :class="selected.competency ? 'text-foreground' : 'italic text-muted-foreground'"
                                        >
                                            {{ selected.competency || 'Not specified' }}
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </section>

                        <!-- Location & provider -->
                        <section>
                            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Location &amp; Provider</h3>
                            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="flex items-start gap-3">
                                    <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0">
                                        <dt class="text-xs text-muted-foreground">Venue</dt>
                                        <dd class="text-sm font-medium" :class="selected.venue ? 'text-foreground' : 'italic text-muted-foreground'">
                                            {{ selected.venue || 'Not specified' }}
                                        </dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <Building2 class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0">
                                        <dt class="text-xs text-muted-foreground">Provider</dt>
                                        <dd
                                            class="text-sm font-medium"
                                            :class="selected.provider ? 'text-foreground' : 'italic text-muted-foreground'"
                                        >
                                            {{ selected.provider || 'Not specified' }}
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <!-- Footer -->
                    <div class="flex shrink-0 flex-col-reverse gap-2 border-t bg-card px-4 py-3 sm:flex-row sm:justify-end sm:px-6 sm:py-4">
                        <DialogClose as-child>
                            <Button variant="outline" class="hidden h-10 sm:inline-flex">Close</Button>
                        </DialogClose>
                        <Button
                            v-if="selected.program_id"
                            as-child
                            class="h-11 w-full bg-blue-700 text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700 sm:h-10 sm:w-auto"
                        >
                            <Link :href="`/programs/${selected.program_id}`">
                                <ExternalLink class="mr-2 h-4 w-4" />
                                View Program Details
                                <ArrowRight class="ml-1 h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style>
/* Blend FullCalendar into the shadcn/Tailwind theme */
.tdi-calendar .fc {
    --fc-border-color: hsl(var(--border));
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: hsl(var(--muted) / 0.5);
    --fc-list-event-hover-bg-color: hsl(var(--muted) / 0.6);
    --fc-today-bg-color: rgb(37 99 235 / 0.05);
    --fc-event-bg-color: transparent;
    --fc-event-border-color: transparent;
    --fc-more-link-bg-color: transparent;
    font-size: 0.875rem;
    color: hsl(var(--foreground));
}

.tdi-calendar .fc .fc-scrollgrid {
    border-radius: 0.5rem;
    overflow: hidden;
}

/* Day headers */
.tdi-calendar .fc .fc-col-header-cell {
    background: hsl(var(--muted) / 0.5);
    padding: 0.5rem 0;
}

.tdi-calendar .fc .fc-col-header-cell-cushion {
    color: hsl(var(--muted-foreground));
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    text-decoration: none;
}

/* Date numbers */
.tdi-calendar .fc .fc-daygrid-day-number {
    color: hsl(var(--foreground));
    font-size: 0.8125rem;
    font-weight: 500;
    padding: 0.375rem 0.5rem;
    text-decoration: none;
}

.tdi-calendar .fc .fc-day-other .fc-daygrid-day-number {
    color: hsl(var(--muted-foreground) / 0.6);
}

.tdi-calendar .fc .fc-daygrid-day-frame {
    min-height: 6.5rem;
}

/* Today indicator */
.tdi-calendar .fc .fc-day-today .fc-daygrid-day-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.75rem;
    height: 1.75rem;
    margin: 0.25rem;
    padding: 0 0.375rem;
    border-radius: 9999px;
    background: #1d4ed8;
    color: #fff;
    font-weight: 600;
}

/* Events — visual styling lives in the eventContent slot */
.tdi-calendar .fc .tdi-event {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    cursor: pointer;
    padding: 0;
    border-radius: 6px;
}

.tdi-calendar .fc .fc-daygrid-event.tdi-event {
    margin: 2px 4px 0;
}

.tdi-calendar .fc .fc-timegrid .tdi-event {
    margin: 1px 2px;
}

.tdi-calendar .fc .tdi-event:focus-visible {
    outline: 2px solid hsl(var(--ring));
    outline-offset: 1px;
}

.tdi-calendar .fc .fc-event-main {
    color: inherit;
}

/* "+x more" link */
.tdi-calendar .fc .fc-daygrid-more-link {
    margin: 2px 4px 0;
    padding: 2px 6px;
    border-radius: 4px;
    color: #1d4ed8;
    font-size: 0.75rem;
    font-weight: 600;
}

.dark .tdi-calendar .fc .fc-daygrid-more-link {
    color: #60a5fa;
}

.tdi-calendar .fc .fc-daygrid-more-link:hover {
    background: hsl(var(--muted));
    text-decoration: none;
}

/* "+x more" popover */
.tdi-calendar .fc .fc-popover {
    background: hsl(var(--popover));
    border-color: hsl(var(--border));
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.15);
    overflow: hidden;
}

.tdi-calendar .fc .fc-popover-header {
    background: hsl(var(--muted) / 0.6);
    color: hsl(var(--foreground));
    font-weight: 600;
    padding: 0.5rem 0.75rem;
}

.tdi-calendar .fc .fc-popover-body {
    padding: 0.25rem 0 0.5rem;
    max-width: 18rem;
}

/* Time grid */
.tdi-calendar .fc .fc-timegrid-slot-label-cushion,
.tdi-calendar .fc .fc-timegrid-axis-cushion {
    color: hsl(var(--muted-foreground));
    font-size: 0.75rem;
}

/* List view */
.tdi-calendar .fc .fc-list {
    border-radius: 0.5rem;
    overflow: hidden;
}

.tdi-calendar .fc .fc-list-day-cushion {
    background: hsl(var(--muted) / 0.6);
    padding: 0.5rem 1rem;
}

.tdi-calendar .fc .fc-list-day-text,
.tdi-calendar .fc .fc-list-day-side-text {
    color: hsl(var(--foreground));
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
}

.tdi-calendar .fc .fc-list-event-time,
.tdi-calendar .fc .fc-list-event-graphic {
    display: none;
}

.tdi-calendar .fc .fc-list-event td {
    padding: 0.625rem 1rem;
}

.tdi-calendar .fc .fc-list-empty {
    background: transparent;
    color: hsl(var(--muted-foreground));
    min-height: 12rem;
}

/* Tablet */
@media (max-width: 1023px) {
    .tdi-calendar .fc .fc-daygrid-day-frame {
        min-height: 5.5rem;
    }
}

/* Mobile */
@media (max-width: 640px) {
    .tdi-calendar .fc {
        font-size: 0.8125rem;
    }

    .tdi-calendar .fc .fc-col-header-cell {
        padding: 0.375rem 0;
    }

    .tdi-calendar .fc .fc-col-header-cell-cushion {
        font-size: 0.625rem;
        letter-spacing: 0;
        padding: 0;
    }

    .tdi-calendar .fc .fc-daygrid-day-number {
        font-size: 0.6875rem;
        padding: 0.25rem;
    }

    .tdi-calendar .fc .fc-day-today .fc-daygrid-day-number {
        min-width: 1.375rem;
        height: 1.375rem;
        margin: 0.125rem;
        padding: 0 0.25rem;
    }

    .tdi-calendar .fc .fc-daygrid-day-frame {
        min-height: 4.5rem;
    }

    .tdi-calendar .fc .fc-daygrid-event.tdi-event,
    .tdi-calendar .fc .fc-daygrid-more-link {
        margin: 2px 1px 0;
    }

    .tdi-calendar .fc .fc-daygrid-more-link {
        padding: 2px;
        font-size: 0.625rem;
    }

    .tdi-calendar .fc .fc-list-event td {
        padding: 0.625rem 0.75rem;
    }
}
</style>
