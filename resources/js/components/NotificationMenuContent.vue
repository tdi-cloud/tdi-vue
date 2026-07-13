<script setup lang="ts">
import { DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

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

onMounted(loadNotifications);

function handleClick(n: NotificationItem) {
    if (!n.read) {
        n.read = true;
        router.post(
            route('notifications.read', n.id),
            {},
            { preserveScroll: true, preserveState: true, only: ['unreadNotificationsCount'] },
        );
    }
    if (n.url) {
        router.visit(n.url);
    }
}

function markAllRead() {
    notifications.value = notifications.value.map((n) => ({ ...n, read: true }));
    router.post(
        route('notifications.read-all'),
        {},
        { preserveScroll: true, preserveState: true, only: ['unreadNotificationsCount'] },
    );
}
</script>

<template>
    <DropdownMenuLabel class="flex items-center justify-between p-2 font-normal">
        <span class="text-sm font-semibold">Notifications</span>
        <button type="button" class="text-xs font-medium text-blue-600 hover:underline" @click="markAllRead">
            Mark all as read
        </button>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />

    <div class="max-h-96 overflow-y-auto">
        <div v-if="loading" class="px-4 py-6 text-center text-sm text-muted-foreground">Loading…</div>
        <div v-else-if="!notifications.length" class="px-4 py-6 text-center text-sm text-muted-foreground">
            No notifications yet.
        </div>
        <button
            v-for="n in notifications"
            :key="n.id"
            type="button"
            class="flex w-full flex-col items-start gap-0.5 border-b border-border px-3 py-2.5 text-left last:border-0 hover:bg-accent"
            :class="!n.read ? 'bg-blue-50 dark:bg-blue-950/20' : ''"
            @click="handleClick(n)"
        >
            <span class="text-sm font-semibold text-foreground">{{ n.title }}</span>
            <span class="text-xs text-muted-foreground">{{ n.message }}</span>
            <span class="text-[10px] text-muted-foreground">{{ n.created_at }}</span>
        </button>
    </div>
</template>
