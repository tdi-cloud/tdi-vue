<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { LoaderCircle } from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * Submit button with one consistent busy state: spinner, disabled, and action
 * wording ("Saving…"). Class, size, variant and form fall through to <Button>.
 */
withDefaults(
    defineProps<{
        processing?: boolean;
        processingLabel?: string;
        disabled?: boolean;
        icon?: Component;
        type?: 'submit' | 'button';
    }>(),
    { processing: false, processingLabel: 'Saving…', disabled: false, type: 'submit' },
);
</script>

<template>
    <Button :type="type" :disabled="processing || disabled" :aria-busy="processing">
        <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
        <component :is="icon" v-else-if="icon" class="h-4 w-4" aria-hidden="true" />
        <template v-if="processing">{{ processingLabel }}</template>
        <slot v-else />
    </Button>
</template>
