<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Bell, BellOff, CheckCheck, ChevronRight, Inbox, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Full notification list for the dedicated Notifications page. Uses the theme
 * tokens (bg-card, text-foreground, …) so it follows light and dark mode.
 */
export interface NotificationRow {
    id: string;
    title: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string;
    created_at_full: string;
}

export interface NotificationPage {
    data: NotificationRow[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    notifications: NotificationPage;
    filter: 'all' | 'unread';
    counts: { all: number; unread: number };
}>();

const markingAll = ref(false);

const setFilter = (filter: 'all' | 'unread') => {
    if (filter === props.filter) return;
    router.get(route('notifications.page'), filter === 'unread' ? { filter } : {}, { preserveScroll: true, preserveState: true });
};

function open(n: NotificationRow) {
    if (!n.read) {
        n.read = true;
        router.post(route('notifications.read', n.id), {}, { preserveScroll: true, preserveState: true });
    }
    if (n.url) {
        router.visit(n.url);
    }
}

function markAllRead() {
    markingAll.value = true;
    router.post(
        route('notifications.read-all'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                markingAll.value = false;
            },
        },
    );
}

// Pagination labels from Laravel come as "&laquo; Previous" / "Next &raquo;".
const pageLabel = (label: string) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
</script>

<template>
    <section class="overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-sm">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
            <div class="inline-flex rounded-lg bg-muted p-1" role="tablist" aria-label="Filter notifications">
                <button
                    v-for="tab in [
                        { key: 'all', label: 'All', count: counts.all },
                        { key: 'unread', label: 'Unread', count: counts.unread },
                    ] as const"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="filter === tab.key"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="filter === tab.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="setFilter(tab.key)"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 text-[11px] font-semibold"
                        :class="
                            tab.key === 'unread' && tab.count
                                ? 'bg-blue-600 text-white dark:bg-blue-500'
                                : 'bg-muted-foreground/15 text-muted-foreground'
                        "
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium text-blue-600 transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                :disabled="!counts.unread || markingAll"
                @click="markAllRead"
            >
                <LoaderCircle v-if="markingAll" class="h-4 w-4 animate-spin" />
                <CheckCheck v-else class="h-4 w-4" />
                Mark all as read
            </button>
        </div>

        <!-- List -->
        <ul v-if="notifications.data.length" class="divide-y">
            <li v-for="n in notifications.data" :key="n.id">
                <button
                    type="button"
                    class="group flex w-full items-start gap-3 border-l-2 px-4 py-3.5 text-left transition-colors hover:bg-accent sm:px-5"
                    :class="!n.read ? 'border-l-blue-500 bg-blue-50/70 dark:border-l-blue-400 dark:bg-blue-500/10' : 'border-l-transparent'"
                    @click="open(n)"
                >
                    <span
                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                        :class="!n.read ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300' : 'bg-muted text-muted-foreground'"
                    >
                        <Bell class="h-4 w-4" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="flex items-start justify-between gap-3">
                            <span class="text-sm text-foreground" :class="!n.read ? 'font-semibold' : 'font-medium'">{{ n.title }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground" :title="n.created_at_full">{{ n.created_at }}</span>
                        </span>
                        <span class="text-sm text-muted-foreground">{{ n.message }}</span>
                        <span v-if="n.url" class="mt-1 inline-flex items-center gap-0.5 text-xs font-medium text-blue-600 dark:text-blue-400">
                            Open <ChevronRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                        </span>
                    </span>
                    <span v-if="!n.read" class="mt-2 h-2 w-2 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400" aria-label="Unread" />
                </button>
            </li>
        </ul>

        <!-- Empty -->
        <div v-else class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <BellOff v-if="filter === 'unread'" class="h-6 w-6" />
                <Inbox v-else class="h-6 w-6" />
            </span>
            <p class="text-sm font-semibold text-foreground">{{ filter === 'unread' ? 'You’re all caught up' : 'No notifications yet' }}</p>
            <p class="text-sm text-muted-foreground">
                {{ filter === 'unread' ? 'You have no unread notifications.' : 'Updates about your programs and tasks will appear here.' }}
            </p>
        </div>

        <!-- Pagination -->
        <div v-if="notifications.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 sm:px-5">
            <p class="text-xs text-muted-foreground">Showing {{ notifications.from }}–{{ notifications.to }} of {{ notifications.total }}</p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in notifications.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs font-medium transition"
                        :class="link.active ? 'border-blue-600 bg-blue-600 text-white' : 'text-foreground hover:bg-accent'"
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
        </div>
    </section>
</template>
