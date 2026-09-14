<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-gradient-to-b from-blue-800 to-blue-500 font-extrabold text-sidebar-primary-foreground"
    >
        <!-- <AppLogoIcon class="size-5 fill-current text-white dark:text-black" /> -->
        TDI
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 text-xs font-semibold leading-none">TESDA Development Institute</span>
    </div>
</template>
