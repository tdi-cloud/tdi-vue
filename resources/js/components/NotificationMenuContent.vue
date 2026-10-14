<script setup lang="ts">
import { DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface NotificationItem {
    id: string;
    title: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string;
}

const notifications = ref<NotificationItem[]>([]);
const loading = ref(true);

async function loadNotifications() {
    loading.value = true;
    try {
        const res = await fetch(route('notifications.index'), {
            headers: { Accept: 'application/json' },
        });
        notifications.value = res.ok ? await res.json() : [];
    } catch {
        notifications.value = [];
    } finally {
        loading.value = false;
    }
}

// Panatilihin sa itaas ang lahat ng unread (recency order pa rin), tapos
// hiwalay na grupo sa ilalim ang mga na-read na — huwag ihalo ang dalawa.
// Stable ang Array.sort sa modern JS, kaya napapanatili ang recency order
// sa loob ng bawat grupo.
const sortedNotifications = computed(() => [...notifications.value].sort((a, b) => Number(a.read) - Number(b.read)));

onMounted(loadNotifications);

function handleClick(n: NotificationItem) {
    if (!n.read) {
        n.read = true;
        router.post(route('notifications.read', n.id), {}, { preserveScroll: true, preserveState: true, only: ['unreadNotificationsCount'] });
    }
    if (n.url) {
        router.visit(n.url);
    }
}

function markAllRead() {
    notifications.value = notifications.value.map((n) => ({ ...n, read: true }));
    router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true, only: ['unreadNotificationsCount'] });
}
</script>

<template>
    <DropdownMenuLabel class="flex items-center justify-between p-2 font-normal">
        <span class="text-sm font-semibold">Notifications</span>
        <button type="button" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400" @click="markAllRead">
            Mark all as read
        </button>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />

    <div class="max-h-96 overflow-y-auto">
        <div v-if="loading" class="px-4 py-6 text-center text-sm text-muted-foreground">Loading…</div>
        <div v-else-if="!notifications.length" class="px-4 py-6 text-center text-sm text-muted-foreground">No notifications yet.</div>
        <template v-for="(n, idx) in sortedNotifications" :key="n.id">
            <div
                v-if="n.read && idx > 0 && !sortedNotifications[idx - 1].read"
                class="bg-muted/40 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
            >
                Read
            </div>
            <!-- Unread: tinted background + left accent + dot, readable in light and dark mode -->
            <button
                type="button"
                class="flex w-full gap-2.5 border-b border-l-2 border-b-border px-3 py-2.5 text-left transition-colors last:border-b-0 hover:bg-accent"
                :class="!n.read ? 'border-l-blue-500 bg-blue-50 dark:border-l-blue-400 dark:bg-blue-500/10' : 'border-l-transparent'"
                @click="handleClick(n)"
            >
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="!n.read ? 'bg-blue-600 dark:bg-blue-400' : 'bg-transparent'" />
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-sm text-foreground" :class="!n.read ? 'font-semibold' : 'font-medium'">{{ n.title }}</span>
                    <span class="text-xs text-muted-foreground">{{ n.message }}</span>
                    <span class="text-[10px] text-muted-foreground/80">{{ n.created_at }}</span>
                </span>
            </button>
        </template>
    </div>

    <DropdownMenuSeparator />
    <Link
        :href="route('notifications.page')"
        class="block rounded-sm px-2 py-2 text-center text-xs font-semibold text-blue-600 transition hover:bg-accent dark:text-blue-400"
    >
        View all notifications
    </Link>
</template>
