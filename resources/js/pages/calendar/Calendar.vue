<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

import FullCalendar from '@fullcalendar/vue3';
import type { CalendarOptions, EventClickArg } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ExternalLink } from 'lucide-vue-next';

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

const props = defineProps<{
    events: CalendarEvent[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendar', href: '/calendar' },
];

// Dialog state para sa clicked event
const showDialog = ref(false);
const selected = ref<CalendarEvent['extendedProps'] | null>(null);

const statusVariant = computed(() => {
    const status = selected.value?.status?.toLowerCase() ?? '';
    if (status === 'active') return 'bg-cyan-100 text-cyan-700';
    if (status === 'completed') return 'bg-emerald-100 text-emerald-700';
    if (status === 'upcoming') return 'bg-indigo-100 text-indigo-700';
    if (status === 'rescheduled') return 'bg-amber-100 text-amber-700';
    return 'bg-gray-100 text-gray-700';
});

const calendarOptions: CalendarOptions = {
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listMonth',
    },
    buttonText: {
        today: 'Today',
        month: 'Month',
        week: 'Week',
        list: 'List',
    },
    events: props.events,
    dayMaxEvents: 3, // "+x more" link kapag masyadong maraming events sa isang araw
    height: 'auto',
    eventDisplay: 'block',
    eventClick: (info: EventClickArg) => {
        selected.value = info.event.extendedProps as CalendarEvent['extendedProps'];
        showDialog.value = true;
    },
};
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <FullCalendar :options="calendarOptions" />
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                <span class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-indigo-500" /> Upcoming
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-cyan-500" /> Active
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-emerald-500" /> Completed
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-amber-500" /> Rescheduled
                </span>
            </div>
        </div>

        <!-- Event details dialog -->
        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="pr-6">
                        {{ selected?.program_title ?? selected?.program_code }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ selected?.program_code }} · Batch {{ selected?.batch }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selected" class="grid gap-3 text-sm">
                    <div class="flex items-center gap-2">
                        <Badge :class="statusVariant" variant="secondary">
                            {{ selected.status }}
                        </Badge>
                        <Badge variant="outline">{{ selected.modality }}</Badge>
                        <Badge v-if="selected.category" variant="outline">
                            {{ selected.category }}
                        </Badge>
                    </div>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                        <div>
                            <p class="text-xs text-muted-foreground">Date</p>
                            <p class="font-medium">
                                {{ selected.date_start }}
                                <template v-if="selected.date_end !== selected.date_start">
                                    – {{ selected.date_end }}
                                </template>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Time</p>
                            <p class="font-medium">
                                {{ selected.time_start }} – {{ selected.time_end }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Duration</p>
                            <p class="font-medium">
                                {{ selected.days }} day(s) · {{ selected.hours }} hr(s)
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Participants</p>
                            <p class="font-medium">{{ selected.participants }}</p>
                        </div>
                        <div class="col-span-2" v-if="selected.venue">
                            <p class="text-xs text-muted-foreground">Venue</p>
                            <p class="font-medium">{{ selected.venue }}</p>
                        </div>
                        <div class="col-span-2" v-if="selected.competency">
                            <p class="text-xs text-muted-foreground">Competency</p>
                            <p class="font-medium">{{ selected.competency }}</p>
                        </div>
                        <div class="col-span-2" v-if="selected.provider">
                            <p class="text-xs text-muted-foreground">Provider</p>
                            <p class="font-medium">{{ selected.provider }}</p>
                        </div>
                    </div>
                </div>

                <DialogFooter v-if="selected?.program_id">
                    <Button as-child class="w-full bg-blue-600 hover:bg-blue-700 dark:text-white sm:w-auto">
                        <Link :href="`/programs/${selected.program_id}`">
                            <ExternalLink class="mr-2 h-4 w-4" />
                            View Program
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style>
/* I-blend ang FullCalendar sa shadcn/Tailwind theme mo */
.fc {
    --fc-border-color: hsl(var(--border));
    --fc-page-bg-color: transparent;
    --fc-today-bg-color: hsl(var(--accent) / 0.4);
    --fc-button-bg-color: #2563eb;
    --fc-button-border-color: #2563eb;
    --fc-button-hover-bg-color: #1d4ed8;
    --fc-button-hover-border-color: #1d4ed8;
    --fc-button-active-bg-color: #1e40af;
    --fc-button-active-border-color: #1e40af;
    font-size: 0.875rem;
}

.fc .fc-toolbar-title {
    font-size: 1.125rem;
    font-weight: 600;
}

.fc .fc-button {
    font-size: 0.8125rem;
    padding: 0.375rem 0.75rem;
    text-transform: capitalize;
}

.fc .fc-event {
    cursor: pointer;
    font-size: 0.75rem;
    padding: 1px 4px;
}

.fc .fc-daygrid-day-number,
.fc .fc-col-header-cell-cushion {
    color: hsl(var(--foreground));
    text-decoration: none;
}
</style>