<script setup lang="ts">
import { Users, GraduationCap, CheckCircle2, ClipboardList, AlertTriangle } from 'lucide-vue-next';

interface Kpi {
    total_personnel: number;
    currently_in_training: number;
    training_completed: number;
    requirements_pending: number;
    needs_attention: number;
}

defineProps<{
    kpi: Kpi;
    loading?: boolean;
}>();

const cards = [
    { key: 'total_personnel', label: 'Total Personnel', icon: Users, color: 'from-blue-600 to-indigo-600', text: 'text-blue-600 dark:text-blue-400' },
    { key: 'currently_in_training', label: 'Currently in Training', icon: GraduationCap, color: 'from-teal-600 to-cyan-600', text: 'text-teal-600 dark:text-teal-400' },
    { key: 'training_completed', label: 'Training Completed', icon: CheckCircle2, color: 'from-emerald-600 to-green-600', text: 'text-emerald-600 dark:text-emerald-400' },
    { key: 'requirements_pending', label: 'Requirements Pending', icon: ClipboardList, color: 'from-amber-600 to-orange-600', text: 'text-amber-600 dark:text-amber-400' },
    { key: 'needs_attention', label: 'Needs Attention', icon: AlertTriangle, color: 'from-rose-600 to-red-600', text: 'text-rose-600 dark:text-rose-400' },
] as const;
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div
            v-for="card in cards"
            :key="card.key"
            class="rounded-2xl border bg-white/90 dark:bg-background/90 backdrop-blur px-4 py-3 shadow-sm flex items-center gap-3"
        >
            <div class="h-10 w-10 rounded-xl bg-gradient-to-br shrink-0 flex items-center justify-center shadow-sm" :class="card.color">
                <component :is="card.icon" class="h-5 w-5 text-white" />
            </div>
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold truncate">{{ card.label }}</p>
                <div v-if="loading" class="h-5 w-12 mt-1 rounded bg-muted animate-pulse" />
                <p v-else class="text-lg font-extrabold leading-none" :class="card.text">{{ kpi[card.key] }}</p>
            </div>
        </div>
    </div>
</template>
