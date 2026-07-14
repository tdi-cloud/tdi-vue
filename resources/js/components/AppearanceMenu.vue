<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        buttonClass?: string;
    }>(),
    {
        buttonClass: 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
    },
);

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
] as const;

const currentIcon = computed(() => options.find((o) => o.value === appearance.value)?.icon ?? Monitor);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg transition"
                :class="buttonClass"
                aria-label="Change theme appearance"
            >
                <component :is="currentIcon" class="h-5 w-5" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-40 rounded-lg" side="bottom" align="end" :side-offset="4">
            <DropdownMenuItem
                v-for="opt in options"
                :key="opt.value"
                class="cursor-pointer"
                :class="appearance === opt.value ? 'font-semibold text-blue-600 dark:text-blue-400' : ''"
                @click="updateAppearance(opt.value)"
            >
                <component :is="opt.icon" class="h-4 w-4" />
                {{ opt.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
