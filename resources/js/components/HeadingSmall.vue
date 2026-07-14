<script setup lang="ts">
import type { Component } from 'vue';

interface Props {
    title: string;
    description?: string;
    icon?: Component;
    iconClass?: string;
}

withDefaults(defineProps<Props>(), {
    iconClass: 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400',
});
</script>

<template>
    <header class="flex items-center gap-3">
        <div v-if="icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="iconClass">
            <component :is="icon" class="h-5 w-5" />
        </div>
        <div>
            <h3 class="mb-0.5 text-base font-semibold">{{ title }}</h3>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
    </header>
</template>
