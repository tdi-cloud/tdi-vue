<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Check, Copy, ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * A public link to share: read-only box, Copy and Open. If the browser
 * blocks the clipboard (e.g. on plain http), the link is selected instead so
 * it can be copied by hand.
 */
const props = defineProps<{
    url: string;
    label: string;
}>();

const input = ref<HTMLInputElement | null>(null);
const copied = ref(false);

async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        input.value?.focus();
        input.value?.select();
    }
}
</script>

<template>
    <div class="flex items-center gap-1.5">
        <input
            ref="input"
            :value="url"
            readonly
            :aria-label="label"
            class="h-8 min-w-0 flex-1 rounded-md border bg-muted/40 px-2.5 font-mono text-xs text-muted-foreground outline-none focus:ring-2 focus:ring-ring"
            @focus="($event.target as HTMLInputElement).select()"
        />
        <Button type="button" size="sm" variant="outline" class="h-8 shrink-0 text-xs" @click="copyLink">
            <Check v-if="copied" class="mr-1 h-3 w-3 text-emerald-600" />
            <Copy v-else class="mr-1 h-3 w-3" />
            {{ copied ? 'Copied' : 'Copy' }}
        </Button>
        <Button as-child size="sm" variant="outline" class="h-8 shrink-0 text-xs">
            <a :href="url" target="_blank" rel="noopener">
                <ExternalLink class="mr-1 h-3 w-3" /> Open
                <span class="sr-only">(opens in a new tab)</span>
            </a>
        </Button>
    </div>
</template>
