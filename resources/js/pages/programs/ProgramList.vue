<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Trash2, ChevronRight, Layers, Users, ClipboardList, CalendarDays } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';



interface Program {
    id: number;
    program_code: string;
    title: string;
    description: string;
    initiated: string;
    batches_count: number;
    participants_count: number;
    requirements_count: number;
    date_start: string | null;
    date_end: string | null;
    batch_statuses: string[];
    months: string[];
}



const props = defineProps<{
    programs: Program[];
    search: string;
    filterInitiated: string;
    filterBatchStatus: string;
    filterMonth: string;
}>();

const filtered = computed(() => {
    const q = props.search.toLowerCase().trim();

    return props.programs.filter((p) => {
        // Search filter
        if (q) {
            const matches =
                p.title.toLowerCase().includes(q) ||
                p.description?.toLowerCase().includes(q) ||
                p.program_code?.toLowerCase().includes(q);

            if (!matches) return false;
        }

        // Office Initiated filter
        if (props.filterInitiated !== 'all' && p.initiated !== props.filterInitiated) {
            return false;
        }

        // Batch Status filter
        if (props.filterBatchStatus !== 'all' && !p.batch_statuses.includes(props.filterBatchStatus)) {
            return false;
        }

        // Month filter
        if (props.filterMonth !== 'all' && !p.months.includes(props.filterMonth)) {
            return false;
        }

        return true;
    });
});

const viewProgram = (id: number) => {
    router.visit(route('programs.show', id));
};

const perPage = 12;
const currentPage = ref(1);
const isChangingPage = ref(false);

watch(() => [props.search, props.filterInitiated, props.filterBatchStatus, props.filterMonth], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => Math.ceil(filtered.value.length / perPage));

const paginated = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filtered.value.slice(start, start + perPage);
});

const changePage = (page: number) => {
    isChangingPage.value = true;
    setTimeout(() => {
        currentPage.value = page;
        isChangingPage.value = false;
    }, 10);
};

const deleteProgram = (id: number) => {
    if (confirm('Are you sure you want to delete this program?')) {
        router.delete(route('programs.destroy', id));
    }
};

/* ---------- Helpers ---------- */
const formatDate = (date: string | null) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const dateRange = (program: Program) => {
    const start = formatDate(program.date_start);
    const end = formatDate(program.date_end);

    if (!start && !end) return null;
    if (start === end) return start;
    return `${start} – ${end}`;
};
</script>

<template>
    <!-- No programs at all -->
    <div v-if="programs.length === 0" class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground">
        <p class="text-sm font-semibold">No programs yet.</p>
        <p class="text-xs mt-1">Click "Create Program" to add one.</p>
    </div>

    <template v-else>
        <div class="flex flex-col gap-4 flex-1 min-h-0">

            <!-- No search results -->
            <div v-if="filtered.length === 0" class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground">
                <p class="text-sm font-semibold">No programs found.</p>
                <p class="text-xs mt-1">Try a different search term.</p>
            </div>

            <!-- Scrollable list -->
            <div v-else class="overflow-y-auto overflow-x-hidden flex-1 min-h-0 w-full max-w-full pb-4 px-1">
                <TransitionGroup
                    v-if="!isChangingPage"
                    tag="div"
                    class="flex flex-col divide-y rounded-xl border bg-card overflow-hidden shadow-lg"
                    appear
                >
                    <div
                        v-for="(program, index) in paginated"
                        :key="program.id"
                        class="group flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-muted/50"
                        :style="{ animationDelay: `${index * 60}ms` }"
                        @click="viewProgram(program.id)"
                    >
                        <!-- Text -->
                        <div class="flex-1 min-w-0 overflow-hidden">
                            <p class="text-sm font-extrabold dark:text-cyan-400 text-sky-900 line-clamp-1 break-words">
                                {{ program.title }}
                            </p>
                            <p class="text-xs text-muted-foreground line-clamp-1 break-words">
                                {{ program.description || 'No description provided.' }}
                            </p>

                            <!-- Stats row -->
                            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                    <Layers class="h-3 w-3 text-blue-500" />
                                    {{ program.batches_count }} batch{{ program.batches_count === 1 ? '' : 'es' }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                    <Users class="h-3 w-3 text-purple-500" />
                                    {{ program.participants_count }} participant{{ program.participants_count === 1 ? '' : 's' }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                    <ClipboardList class="h-3 w-3 text-emerald-500" />
                                    {{ program.requirements_count }} requirement{{ program.requirements_count === 1 ? '' : 's' }}
                                </span>
                                <span v-if="dateRange(program)" class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                    <CalendarDays class="h-3 w-3 text-amber-500" />
                                    {{ dateRange(program) }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 shrink-0 text-muted-foreground hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                            @click.stop="deleteProgram(program.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground/50" />
                    </div>
                </TransitionGroup>
            </div>

            <!-- Pagination fixed at bottom -->
            <div v-if="totalPages > 1" class="shrink-0 flex items-center justify-between pt-4 border-t text-xs text-muted-foreground">
                <span>
                    Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, filtered.length) }} of {{ filtered.length }} programs
                </span>
                <div class="flex gap-1 flex-wrap">
                    <button
                        class="px-3 py-1 rounded border text-xs disabled:opacity-40 hover:bg-muted transition-colors"
                        :disabled="currentPage === 1"
                        @click="changePage(currentPage - 1)"
                    >
                        Previous
                    </button>
                    <button
                        v-for="page in totalPages"
                        :key="page"
                        class="px-3 py-1 rounded border text-xs transition-colors"
                        :class="page === currentPage ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        @click="changePage(page)"
                    >
                        {{ page }}
                    </button>
                    <button
                        class="px-3 py-1 rounded border text-xs disabled:opacity-40 hover:bg-muted transition-colors"
                        :disabled="currentPage === totalPages"
                        @click="changePage(currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>

        </div>
    </template>
</template>

<style scoped>
.v-enter-active {
    animation: slideIn 0.3s ease both;
}

.v-leave-active {
    animation: slideOut 0.25s ease both;
}

@keyframes slideIn {
    0% {
        opacity: 0;
        transform: translateX(-12px);
    }
    100% {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOut {
    0% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}
</style>