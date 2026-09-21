<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';

interface Props {
    href: string;
    tabindex?: number;
    method?: string;
    as?: string;
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        :class="
            cn(
                'hover:decoration-current! text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out dark:decoration-neutral-500',
                props.class,
            )
        "
    >
        <slot />
    </Link>
</template>
