<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

/**
 * Read synchronously (not in onMounted): the layout remounts on every Inertia visit,
 * and starting open would make a collapsed sidebar animate closed again each time.
 */
function readStoredOpenState(): boolean {
    try {
        return localStorage.getItem('sidebar') !== 'false';
    } catch {
        // Storage blocked: fall back to the default open sidebar.
        return true;
    }
}

const isOpen = ref(readStoredOpenState());

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
    localStorage.setItem('sidebar', String(open));
};
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :open="isOpen" @update:open="handleSidebarChange">
        <slot />
    </SidebarProvider>
</template>
