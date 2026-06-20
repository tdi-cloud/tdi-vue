<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square bg-gradient-to-b from-blue-800 to-blue-500 size-8 items-center justify-center rounded-md  text-sidebar-primary-foreground font-extrabold">
        <!-- <AppLogoIcon class="size-5 fill-current text-white dark:text-black" /> -->
         TDI
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5  font-semibold leading-none text-xs">TESDA Development Institute</span>
    </div>
</template>
