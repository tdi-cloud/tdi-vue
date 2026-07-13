<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import NotificationMenuContent from './NotificationMenuContent.vue';

withDefaults(
    defineProps<{
        buttonClass?: string;
    }>(),
    {
        buttonClass: 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
    },
);

const page = usePage<SharedData>();
const unreadCount = computed(() => page.props.unreadNotificationsCount);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg transition"
                :class="buttonClass"
                aria-label="Notifications"
            >
                <Bell class="h-5 w-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-80 rounded-lg" side="bottom" align="end" :side-offset="4">
            <NotificationMenuContent />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
