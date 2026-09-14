<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { Building2, CheckCircle2, ChevronLeft, ChevronRight, ExternalLink, FileText, History, Loader2, Search, UserRound, X } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const emit = defineEmits(['close']);

interface Submission {
    id: number;
    question: string | null;
    file_path: string;
}

interface NomineeRow {
    id: number;
    name: string;
    sex: 'male' | 'female' | 'other';
    position: string;
    agency: string;
    email: string | null;
    status: string;
    status_label: string;
    program_id: number | null;
    program_title: string | null;
    organizing_sponsor: string | null;
    submitted_at: string;
    requirements_total: number;
    requirements_submitted: number;
    submissions: Submission[];
}

interface NomineePage {
    data: NomineeRow[];
    current_page: number;
    last_page: number;
    total: number;
}

const statusOptions: Record<string, string> = {
    for_interview: 'For Interview',
    endorsed: 'Endorsed',
    waiting_result: 'Waiting Result',
    not_endorsed: 'Not Endorsed',
    accepted: 'Accepted',
    regret: 'Regret',
    cancelled: 'Cancelled',
};

const statusColors: Record<string, string> = {
    for_interview: 'bg-blue-100 text-blue-700',
    endorsed: 'bg-violet-100 text-violet-700',
    waiting_result: 'bg-cyan-100 text-cyan-700',
    not_endorsed: 'bg-red-100 text-red-700',
    accepted: 'bg-emerald-100 text-emerald-700',
    regret: 'bg-amber-100 text-amber-700',
    cancelled: 'bg-gray-200 text-gray-600',
};

const search = ref('');
const filterStatus = ref('');
const filterSponsor = ref('');
const sponsorOptions = ref<string[]>([]);

const loading = ref(false);
const nominees = ref<NomineePage | null>(null);
const expandedId = ref<number | null>(null);

let controller: AbortController | null = null;

async function fetchHistory(page = 1) {
    if (controller) controller.abort();
    const ctrl = new AbortController();
    controller = ctrl;
    loading.value = true;

    try {
        const { data } = await axios.get(route('foreign-programs.nomination-history'), {
            params: {
                search: search.value || undefined,
                status: filterStatus.value || undefined,
                organizing_sponsor: filterSponsor.value || undefined,
                page,
            },
            signal: ctrl.signal,
        });
        nominees.value = data.nominees;
        sponsorOptions.value = data.sponsorOptions ?? [];
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return;
        console.error('Failed to load nomination history:', err?.response?.data ?? err);
    } finally {
        if (controller === ctrl) {
            loading.value = false;
            controller = null;
        }
    }
}

let debounce: ReturnType<typeof setTimeout>;
watch([search, filterStatus, filterSponsor], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => fetchHistory(1), 300);
});

onMounted(() => fetchHistory(1));

function toggleExpand(n: NomineeRow) {
    expandedId.value = expandedId.value === n.id ? null : n.id;
}

function fileUrl(path: string) {
    return `/storage/${path}`;
}

function formatDateTime(date?: string | null) {
    if (!date) return '—';
    const d = new Date(date);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Transition name="backdrop" appear>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
            <Transition name="pop" appear>
                <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-background shadow-2xl">
                    <!-- Header -->
                    <div
                        class="flex shrink-0 items-center gap-3 border-b bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 px-6 py-4 text-white"
                    >
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 shadow backdrop-blur">
                            <History class="h-4 w-4 text-white" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold leading-none">Nomination History</h2>
                            <p class="mt-0.5 text-xs text-white/75">All nomination form submissions, latest to oldest</p>
                        </div>
                        <button class="ml-auto text-white/80 transition-colors hover:text-white" @click="emit('close')">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="flex shrink-0 flex-col gap-2 border-b p-4 sm:flex-row">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search name, agency, position, program..."
                                class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <select v-model="filterSponsor" class="shrink-0 rounded-lg border bg-background px-2 py-2 text-xs">
                            <option value="">All Sponsors</option>
                            <option v-for="s in sponsorOptions" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <select v-model="filterStatus" class="shrink-0 rounded-lg border bg-background px-2 py-2 text-xs">
                            <option value="">All Statuses</option>
                            <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>

                    <!-- List -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="loading" class="flex items-center justify-center gap-2 py-10 text-xs text-muted-foreground">
                            <Loader2 class="h-4 w-4 animate-spin" /> Loading...
                        </div>

                        <div v-else-if="nominees && nominees.data.length > 0" class="flex flex-col gap-2">
                            <div v-for="n in nominees.data" :key="n.id" class="overflow-hidden rounded-xl border">
                                <div
                                    role="button"
                                    tabindex="0"
                                    class="flex w-full cursor-pointer items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/40"
                                    @click="toggleExpand(n)"
                                    @keydown.enter="toggleExpand(n)"
                                >
                                    <div
                                        class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                        :class="n.sex === 'female' ? 'bg-pink-100 dark:bg-pink-950/50' : 'bg-sky-100 dark:bg-sky-950/50'"
                                    >
                                        <UserRound class="h-4 w-4" :class="n.sex === 'female' ? 'text-pink-500' : 'text-sky-600'" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-bold leading-tight">{{ n.name }}</p>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusColors[n.status]">
                                                {{ n.status_label }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-muted-foreground">{{ n.position }}</p>
                                        <p class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                            <Building2 class="h-3 w-3 shrink-0" /> {{ n.agency }}
                                        </p>
                                        <p v-if="n.program_title" class="mt-0.5 truncate text-[11px]">
                                            <Link
                                                v-if="n.program_id"
                                                :href="route('foreign-programs.show', n.program_id)"
                                                class="font-semibold text-blue-600 hover:underline"
                                                @click.stop
                                            >
                                                {{ n.program_title }}
                                            </Link>
                                            <span v-else class="text-muted-foreground/80">{{ n.program_title }}</span>
                                            <span v-if="n.organizing_sponsor" class="text-muted-foreground/80">· {{ n.organizing_sponsor }}</span>
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        <span class="whitespace-nowrap text-[10px] text-muted-foreground">{{ formatDateTime(n.submitted_at) }}</span>
                                        <span
                                            v-if="n.requirements_total > 0"
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="
                                                n.requirements_submitted >= n.requirements_total
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-amber-100 text-amber-700'
                                            "
                                        >
                                            <CheckCircle2 class="h-3 w-3" />
                                            {{ n.requirements_submitted }}/{{ n.requirements_total }} submitted
                                        </span>
                                    </div>
                                </div>

                                <!-- Expanded submissions -->
                                <div v-if="expandedId === n.id" class="border-t bg-muted/20 px-4 py-3">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">Submitted Requirements</p>
                                    <div v-if="n.submissions.length > 0" class="flex flex-col gap-1.5">
                                        <a
                                            v-for="sub in n.submissions"
                                            :key="sub.id"
                                            :href="fileUrl(sub.file_path)"
                                            target="_blank"
                                            class="flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-xs transition-colors hover:bg-blue-50 dark:hover:bg-blue-950/30"
                                        >
                                            <FileText class="h-3.5 w-3.5 shrink-0 text-blue-500" />
                                            <span class="min-w-0 flex-1 truncate font-medium">{{ sub.question ?? 'Requirement' }}</span>
                                            <ExternalLink class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                        </a>
                                    </div>
                                    <p v-else class="text-xs italic text-muted-foreground">No requirement documents submitted yet.</p>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="nominees.last_page > 1" class="flex items-center justify-between pt-2 text-xs">
                                <button
                                    type="button"
                                    class="flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
                                    :disabled="nominees.current_page <= 1"
                                    @click="fetchHistory(nominees.current_page - 1)"
                                >
                                    <ChevronLeft class="h-3 w-3" /> Previous
                                </button>
                                <span class="text-muted-foreground">
                                    Page {{ nominees.current_page }} of {{ nominees.last_page }} · {{ nominees.total }} total
                                </span>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
                                    :disabled="nominees.current_page >= nominees.last_page"
                                    @click="fetchHistory(nominees.current_page + 1)"
                                >
                                    Next <ChevronRight class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <p v-else class="py-10 text-center text-xs text-muted-foreground">No nomination submissions found.</p>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}
.pop-enter-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.pop-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.pop-enter-from {
    opacity: 0;
    transform: scale(0.94) translateY(8px);
}
.pop-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>
