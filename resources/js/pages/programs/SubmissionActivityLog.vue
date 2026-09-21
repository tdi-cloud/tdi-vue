<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Activity, FilePlus2, Pencil, ShieldCheck, Trash2, X } from 'lucide-vue-next';

/**
 * Internal na "daily monitoring" na page para sa Requirements Submissions.
 * SINADYANG walang link dito kahit saan sa navigation — direktang URL lang
 * ang paraan papasok dito, admin pa rin ang required.
 */
interface LogEntry {
    id: number;
    action: 'encoded' | 'updated' | 'reviewed' | 'deleted';
    submission_id: number | null;
    participant_name: string | null;
    requirement_name: string | null;
    program_code: string | null;
    batch_label: string | null;
    status: string | null;
    performed_by: string | null;
    meta: { changed_fields?: string[]; file_uploaded?: boolean; remarks?: string | null } | null;
    created_at: string;
}

interface UserStat {
    performed_by: string | null;
    total: number;
    encoded: number;
    updated: number;
    reviewed: number;
    deleted: number;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

const props = defineProps<{
    stats: { encoded: number; updated: number; reviewed: number; deleted: number };
    userStats: UserStat[];
    logs: Paginated<LogEntry>;
    date: string;
    user: string | null;
}>();

const goToDate = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    if (!value) return;
    router.get(route('submissions.activity-log'), { date: value, user: props.user || undefined }, { preserveState: true, preserveScroll: true });
};

const filterByUser = (name: string | null) => {
    router.get(
        route('submissions.activity-log'),
        { date: props.date, user: name === props.user ? undefined : name || undefined },
        { preserveState: true, preserveScroll: true },
    );
};

const goToPage = (url: string | null) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
};

const formatTime = (iso: string) => new Date(iso).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' });

const ACTION_META = {
    encoded: { label: 'Encoded', icon: FilePlus2, class: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40' },
    updated: { label: 'Edited', icon: Pencil, class: 'text-blue-600 bg-blue-50 dark:bg-blue-950/40' },
    reviewed: { label: 'Reviewed', icon: ShieldCheck, class: 'text-violet-600 bg-violet-50 dark:bg-violet-950/40' },
    deleted: { label: 'Deleted', icon: Trash2, class: 'text-rose-600 bg-rose-50 dark:bg-rose-950/40' },
} as const;
</script>

<template>
    <Head title="Submissions Activity Log" />

    <AppLayout>
        <div class="flex max-w-4xl flex-1 flex-col gap-4 p-4">
            <div class="flex items-center gap-2">
                <Activity class="h-4 w-4 text-muted-foreground" />
                <div>
                    <h1 class="text-sm font-bold">Submissions Activity Log</h1>
                    <p class="text-xs text-muted-foreground">
                        Internal daily monitoring — requirement submissions encoded, edited, reviewed, and deleted, per user. Not linked in
                        navigation.
                    </p>
                </div>
            </div>

            <!-- Date + stats -->
            <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                <input type="date" :value="date" class="h-8 rounded-lg border bg-background px-2 text-xs" @change="goToDate" />

                <div class="flex items-center gap-1.5 text-xs">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span class="font-bold">{{ stats.encoded }}</span> encoded
                </div>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    <span class="font-bold">{{ stats.updated }}</span> edited
                </div>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                    <span class="font-bold">{{ stats.reviewed }}</span> reviewed
                </div>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    <span class="font-bold">{{ stats.deleted }}</span> deleted
                </div>
            </div>

            <!-- Per-user breakdown -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-2.5 text-xs font-bold text-muted-foreground">Per-user breakdown for this date</div>

                <div v-if="userStats.length === 0" class="py-8 text-center text-xs text-muted-foreground">No activity recorded for this date.</div>

                <table v-else class="w-full text-xs">
                    <thead>
                        <tr class="border-b bg-muted/30 text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-2 font-semibold">User</th>
                            <th class="px-2 py-2 text-right font-semibold">Encoded</th>
                            <th class="px-2 py-2 text-right font-semibold">Edited</th>
                            <th class="px-2 py-2 text-right font-semibold">Reviewed</th>
                            <th class="px-2 py-2 text-right font-semibold">Deleted</th>
                            <th class="px-4 py-2 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="row in userStats"
                            :key="row.performed_by ?? 'unknown'"
                            class="cursor-pointer transition-colors hover:bg-muted/40"
                            :class="{ 'bg-muted/50': user === row.performed_by }"
                            @click="filterByUser(row.performed_by)"
                        >
                            <td class="px-4 py-2 font-semibold">{{ row.performed_by ?? 'Unknown' }}</td>
                            <td class="px-2 py-2 text-right">{{ row.encoded }}</td>
                            <td class="px-2 py-2 text-right">{{ row.updated }}</td>
                            <td class="px-2 py-2 text-right">{{ row.reviewed }}</td>
                            <td class="px-2 py-2 text-right">{{ row.deleted }}</td>
                            <td class="px-4 py-2 text-right font-bold">{{ row.total }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Log list -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b px-4 py-2.5 text-xs font-bold text-muted-foreground">
                    <span>Activity log</span>
                    <button
                        v-if="user"
                        type="button"
                        class="inline-flex items-center gap-1 font-semibold text-foreground hover:underline"
                        @click="filterByUser(null)"
                    >
                        <X class="h-3 w-3" /> Filtering by "{{ user }}" — clear
                    </button>
                </div>

                <div v-if="logs.data.length === 0" class="py-12 text-center text-xs text-muted-foreground">No activity recorded for this date.</div>

                <div v-else class="divide-y">
                    <div v-for="entry in logs.data" :key="entry.id" class="flex items-center gap-3 px-4 py-2.5">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" :class="ACTION_META[entry.action].class">
                            <component :is="ACTION_META[entry.action].icon" class="h-3.5 w-3.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold">
                                {{ ACTION_META[entry.action].label }} —
                                {{ entry.requirement_name ?? 'Requirement' }}
                                <span v-if="entry.participant_name" class="font-normal text-muted-foreground">for {{ entry.participant_name }}</span>
                            </p>
                            <p class="truncate text-[11px] text-muted-foreground">
                                <span v-if="entry.program_code">{{ entry.program_code }}</span>
                                <span v-if="entry.batch_label"> · {{ entry.batch_label }}</span>
                                <span v-if="entry.status"> · {{ entry.status }}</span>
                            </p>
                            <p v-if="entry.meta?.changed_fields?.length" class="truncate text-[11px] text-muted-foreground">
                                Changed: {{ entry.meta.changed_fields.join(', ') }}
                            </p>
                            <p v-else-if="entry.meta?.remarks" class="truncate text-[11px] text-muted-foreground">
                                Remarks: {{ entry.meta.remarks }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-[11px] font-semibold">{{ entry.performed_by ?? 'Unknown' }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ formatTime(entry.created_at) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t px-4 py-2.5 text-xs">
                    <button
                        type="button"
                        class="rounded border px-2 py-1 disabled:opacity-40"
                        :disabled="!logs.prev_page_url"
                        @click="goToPage(logs.prev_page_url)"
                    >
                        Previous
                    </button>
                    <span class="text-muted-foreground">Page {{ logs.current_page }} of {{ logs.last_page }} · {{ logs.total }} total</span>
                    <button
                        type="button"
                        class="rounded border px-2 py-1 disabled:opacity-40"
                        :disabled="!logs.next_page_url"
                        @click="goToPage(logs.next_page_url)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
