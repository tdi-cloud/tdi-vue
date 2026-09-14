<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';
import { BookOpen, Building2, CalendarDays, ChevronRight, ClipboardList, Layers, Trash2, UserCog, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const { confirmDialog } = useConfirm();

interface Program {
    id: number;
    program_code: string;
    title: string;
    description: string;
    initiated: string;
    provider: string | null;
    category: string | null;
    batches_count: number;
    participants_count: number;
    requirements_count: number;
    date_start: string | null;
    date_end: string | null;
    batch_statuses: string[];
    months: string[];
    cover_page: { image_url: string | null } | null;
    added_by: string | null;
}

const props = defineProps<{
    programs: Program[];
    search: string;
    filterInitiated: string;
    filterBatchStatus: string;
    filterMonth: string;
    filterProvider: string[];
    filterCategory: string[];
}>();

const filtered = computed(() => {
    const q = props.search.toLowerCase().trim();

    return props.programs.filter((p) => {
        // Search filter
        if (q) {
            const matches =
                p.title.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q) || p.program_code?.toLowerCase().includes(q);

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

        // Provider filter (multi-select — empty array = walang filter)
        if (props.filterProvider.length > 0 && (!p.provider || !props.filterProvider.includes(p.provider))) {
            return false;
        }

        // Category filter (multi-select — empty array = walang filter)
        if (props.filterCategory.length > 0 && (!p.category || !props.filterCategory.includes(p.category))) {
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

watch(
    () => [props.search, props.filterInitiated, props.filterBatchStatus, props.filterMonth, props.filterProvider, props.filterCategory],
    () => {
        currentPage.value = 1;
    },
);

const totalPages = computed(() => Math.ceil(filtered.value.length / perPage));

const pageNumbers = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    const delta = 1;
    const pages: (number | string)[] = [];

    const left = Math.max(2, current - delta);
    const right = Math.min(total - 1, current + delta);

    pages.push(1);
    if (left > 2) pages.push('...');
    for (let i = left; i <= right; i++) pages.push(i);
    if (right < total - 1) pages.push('...');
    if (total > 1) pages.push(total);

    return pages;
});

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

const deleteProgram = async (id: number) => {
    if (await confirmDialog('Are you sure you want to delete this program?')) {
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
        <p class="mt-1 text-xs">Click "Create Program" to add one.</p>
    </div>

    <template v-else>
        <div class="flex min-h-0 flex-1 flex-col gap-4">
            <!-- No search results -->
            <div v-if="filtered.length === 0" class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground">
                <p class="text-sm font-semibold">No programs found.</p>
                <p class="mt-1 text-xs">Try a different search term.</p>
            </div>

            <!-- Scrollable list -->
            <div v-else class="min-h-0 w-full max-w-full flex-1 overflow-y-auto overflow-x-hidden px-1 pb-4">
                <TransitionGroup
                    v-if="!isChangingPage"
                    tag="div"
                    class="flex flex-col divide-y overflow-hidden rounded-xl border bg-card shadow-lg"
                    appear
                >
                    <div
                        v-for="(program, index) in paginated"
                        :key="program.id"
                        class="group flex cursor-pointer items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                        :style="{ animationDelay: `${index * 60}ms` }"
                        @click="viewProgram(program.id)"
                    >
                        <!-- Cover image / book placeholder -->
                        <div class="cover-ring shrink-0">
                            <div class="cover-ring__inner">
                                <img v-if="program.cover_page?.image_url" :src="program.cover_page.image_url" :alt="program.title" />
                                <BookOpen v-else class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>

                        <!-- Text -->
                        <div class="min-w-0 flex-1 overflow-hidden">
                            <p class="line-clamp-1 break-words text-sm font-extrabold text-sky-900 dark:text-cyan-400">
                                {{ program.title }}
                            </p>
                            <p class="line-clamp-1 break-words text-xs text-muted-foreground">
                                {{ program.description || 'No description provided.' }}
                            </p>

                            <!-- Stats row -->
                            <div class="mt-1.5 flex flex-wrap items-center gap-3">
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
                                <span
                                    v-if="dateRange(program)"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                >
                                    <CalendarDays class="h-3 w-3 text-amber-500" />
                                    {{ dateRange(program) }}
                                </span>
                                <span
                                    v-if="program.added_by"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                >
                                    <UserCog class="h-3 w-3 text-rose-500" />
                                    Added by {{ program.added_by }}
                                </span>
                                <span
                                    v-if="program.provider"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                >
                                    <Building2 class="h-3 w-3 text-indigo-500" />
                                    {{ program.provider }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 shrink-0 text-muted-foreground opacity-0 transition-opacity hover:text-red-500 group-hover:opacity-100"
                            @click.stop="deleteProgram(program.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground/50" />
                    </div>
                </TransitionGroup>
            </div>

            <!-- Pagination fixed at bottom -->
            <div v-if="totalPages > 1" class="flex shrink-0 items-center justify-between border-t pt-4 text-xs text-muted-foreground">
                <span>
                    Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, filtered.length) }} of
                    {{ filtered.length }} programs
                </span>
                <div class="flex shrink-0 flex-nowrap items-center gap-1">
                    <button
                        class="rounded border border-blue-200 px-3 py-1 text-xs text-blue-700 transition-colors hover:bg-blue-50 disabled:opacity-40 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950/40"
                        :disabled="currentPage === 1"
                        @click="changePage(currentPage - 1)"
                    >
                        Previous
                    </button>
                    <template v-for="(page, index) in pageNumbers" :key="`${page}-${index}`">
                        <span v-if="page === '...'" class="select-none px-1 text-xs text-muted-foreground"> &hellip; </span>
                        <button
                            v-else
                            class="rounded border px-3 py-1 text-xs transition-colors"
                            :class="
                                page === currentPage
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950/40'
                            "
                            @click="changePage(Number(page))"
                        >
                            {{ page }}
                        </button>
                    </template>
                    <button
                        class="rounded border border-blue-200 px-3 py-1 text-xs text-blue-700 transition-colors hover:bg-blue-50 disabled:opacity-40 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950/40"
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
.cover-ring {
    width: 48px;
    height: 48px;
    border-radius: 9999px;
    padding: 2px;
    background: linear-gradient(135deg, #60a5fa, #1d3fc4);
}
.cover-ring__inner {
    width: 100%;
    height: 100%;
    border-radius: 9999px;
    overflow: hidden;
    background: #eef1fc;
    display: flex;
    align-items: center;
    justify-content: center;
}
:global(.dark) .cover-ring__inner {
    background: rgba(37, 99, 235, 0.15);
}
.cover-ring__inner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

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
