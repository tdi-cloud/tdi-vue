<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { cn } from '@/lib/utils';
import { PanelLeftClose, PanelLeftOpen } from 'lucide-vue-next';
import { computed, type HTMLAttributes } from 'vue';
import { useSidebar } from './utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const { toggleSidebar, state, isMobile, openMobile } = useSidebar();

// On phones the sidebar is a sheet, so "open" means the sheet is showing.
const isExpanded = computed(() => (isMobile.value ? openMobile.value : state.value === 'expanded'));
</script>

<template>
    <Button data-sidebar="trigger" variant="ghost" size="icon" :class="cn('h-7 w-7', props.class)" @click="toggleSidebar">
        <!-- Both icons share one grid cell and swap with a rotate + fade (styles in sidebar-motion.css). -->
        <span class="grid" aria-hidden="true">
            <PanelLeftClose class="sb-trigger-icon" :data-visible="isExpanded" />
            <PanelLeftOpen class="sb-trigger-icon" :data-visible="!isExpanded" />
        </span>
        <span class="sr-only">Toggle Sidebar</span>
    </Button>
</template>
