<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { PlusCircle, Pencil, Trash2, Activity } from 'lucide-vue-next';

/**
 * Internal na "daily monitoring" na page para sa Programs module.
 * SINADYANG walang link dito kahit saan sa navigation — direktang URL lang
 * ang paraan papasok dito, admin pa rin ang required.
 */
interface LogEntry {
    id: number;
    action: 'created' | 'updated' | 'deleted';
    program_code: string | null;
    title: string;
    performed_by: string | null;
    meta: { changed_fields?: string[] } | null;
    created_at: string;
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
    stats: { created: number; updated: number; deleted: number };
    logs: Paginated<LogEntry>;
    date: string;
}>();

const goToDate = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    if (!value) return;
    router.get(route('programs.activity-log'), { date: value }, { preserveState: true, preserveScroll: true });
};

const goToPage = (url: string | null) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
};

const formatTime = (iso: string) =>
    new Date(iso).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' });

const ACTION_META = {
    created: { label: 'Added', icon: PlusCircle, class: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40' },
    updated: { label: 'Edited', icon: Pencil, class: 'text-blue-600 bg-blue-50 dark:bg-blue-950/40' },
    deleted: { label: 'Deleted', icon: Trash2, class: 'text-rose-600 bg-rose-50 dark:bg-rose-950/40' },
} as const;
</script>

<template>
    <Head title="Programs Activity Log" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 max-w-4xl">

            <div class="flex items-center gap-2">
                <Activity class="h-4 w-4 text-muted-foreground" />
                <div>
                    <h1 class="text-sm font-bold">Programs Activity Log</h1>
                    <p class="text-xs text-muted-foreground">Internal daily monitoring — programs added, edited, and deleted. Not linked in navigation.</p>
                </div>
            </div>

            <!-- Date + stats -->
            <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                <input
                    type="date"
                    :value="date"
                    class="h-8 rounded-lg border px-2 text-xs bg-background"
                    @change="goToDate"
                />

                <div class="flex items-center gap-1.5 text-xs">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span class="font-bold">{{ stats.created }}</span> added
                </div>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    <span class="font-bold">{{ stats.updated }}</span> edited
                </div>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    <span class="font-bold">{{ stats.deleted }}</span> deleted
                </div>
            </div>

            <!-- Log list -->
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div v-if="logs.data.length === 0" class="py-12 text-center text-xs text-muted-foreground">
                    No activity recorded for this date.
                </div>

                <div v-else class="divide-y">
                    <div v-for="entry in logs.data" :key="entry.id" class="flex items-center gap-3 px-4 py-2.5">
                        <div class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0" :class="ACTION_META[entry.action].class">
                            <component :is="ACTION_META[entry.action].icon" class="h-3.5 w-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold truncate">
                                {{ ACTION_META[entry.action].label }} — {{ entry.title }}
                                <span v-if="entry.program_code" class="text-muted-foreground font-normal">({{ entry.program_code }})</span>
                            </p>
                            <p v-if="entry.meta?.changed_fields?.length" class="text-[11px] text-muted-foreground truncate">
                                Changed: {{ entry.meta.changed_fields.join(', ') }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-[11px] font-semibold">{{ entry.performed_by ?? 'Unknown' }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ formatTime(entry.created_at) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="logs.last_page > 1" class="flex items-center justify-between px-4 py-2.5 border-t text-xs">
                    <button
                        type="button"
                        class="px-2 py-1 rounded border disabled:opacity-40"
                        :disabled="!logs.prev_page_url"
                        @click="goToPage(logs.prev_page_url)"
                    >
                        Previous
                    </button>
                    <span class="text-muted-foreground">Page {{ logs.current_page }} of {{ logs.last_page }} · {{ logs.total }} total</span>
                    <button
                        type="button"
                        class="px-2 py-1 rounded border disabled:opacity-40"
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
