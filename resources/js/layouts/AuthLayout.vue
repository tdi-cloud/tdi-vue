<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import type { HTMLAttributes } from 'vue';

defineProps<{
    title?: string;
    description?: string;
    cardClass?: HTMLAttributes['class'];
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :card-class="cardClass">
        <slot />
    </AuthLayout>
</template>
