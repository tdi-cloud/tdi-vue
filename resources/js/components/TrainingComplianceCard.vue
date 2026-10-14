<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import { CircleCheckBig, CircleX, Download, GraduationCap, LoaderCircle, Search, Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

/* ===================== PROPS — filters come from dashboard ===================== */

const props = defineProps<{
    target: 'Nationwide' | 'OPCR';
    region: string;
    selectedStatuses: string[];
    office: string;
    year: string;
}>();

/* ===================== STATS DATA ===================== */

interface ComplianceStats {
    total: number;
    trained: number;
    not_trained: number;
    trained_percentage: number;
    not_trained_percentage: number;
}

const loading = ref(false);

const stats = ref<ComplianceStats>({
    total: 0,
    trained: 0,
    not_trained: 0,
    trained_percentage: 0,
    not_trained_percentage: 0,
});

/* ===================== COUNT-UP ANIMATION ===================== */

const COUNT_UP_DURATION = 1100;
// Delay before the first count-up so it lines up with the staggered stat/chart reveal.
const FIRST_REVEAL_DELAY = 520;

const prefersReducedMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const animatedTrained = ref(0);
const animatedNotTrained = ref(0);
const animatedTotal = ref(0);
const animatedPercent = ref(0);
const chartPercent = ref(0);

// Bumped on every data reveal so the ring glow element remounts and replays.
const revealCount = ref(0);

const activeFrames = new Map<{ value: number }, number>();
let mountedAt = 0;
let hasRevealed = false;
let revealTimer: ReturnType<typeof setTimeout> | undefined;
let requestId = 0;

const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);

const animateNumber = (targetRef: { value: number }, to: number, duration = COUNT_UP_DURATION) => {
    const pending = activeFrames.get(targetRef);
    if (pending !== undefined) cancelAnimationFrame(pending);

    if (prefersReducedMotion || targetRef.value === to) {
        activeFrames.delete(targetRef);
        targetRef.value = to;
        return;
    }

    const from = targetRef.value;
    const start = performance.now();
    const step = (now: number) => {
        const progress = Math.min((now - start) / duration, 1);
        targetRef.value = progress < 1 ? Math.round(from + (to - from) * easeOutCubic(progress)) : to;
        if (progress < 1) {
            activeFrames.set(targetRef, requestAnimationFrame(step));
        } else {
            activeFrames.delete(targetRef);
        }
    };
    activeFrames.set(targetRef, requestAnimationFrame(step));
};

/** Starts the ring and all count-ups together so they read as one visualization. */
const revealStats = (data: ComplianceStats, pct: number) => {
    chartPercent.value = pct;
    revealCount.value++;
    animateNumber(animatedTrained, data.trained);
    animateNumber(animatedNotTrained, data.not_trained);
    animateNumber(animatedTotal, data.total);
    animateNumber(animatedPercent, pct);
};

const fetchStats = async () => {
    const currentRequest = ++requestId;
    loading.value = true;
    try {
        const { data } = await axios.get(route('dashboard.training-compliance'), {
            params: {
                region: props.region,
                office_filter: props.target,
                plant_status: props.selectedStatuses,
                office: props.office,
                year: props.year,
            },
        });
        // Ignore responses superseded by a newer filter change.
        if (currentRequest !== requestId) return;
        stats.value = data;
        const pct = Math.round(data.trained_percentage);

        clearTimeout(revealTimer);
        const waitForEntrance = hasRevealed || prefersReducedMotion ? 0 : Math.max(0, FIRST_REVEAL_DELAY - (performance.now() - mountedAt));
        hasRevealed = true;
        if (waitForEntrance > 0) {
            revealTimer = setTimeout(() => revealStats(data, pct), waitForEntrance);
        } else {
            revealStats(data, pct);
        }
    } catch (e) {
        console.error('Failed to load training compliance stats:', e);
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
};

onMounted(() => {
    mountedAt = performance.now();
    fetchStats();
});

onBeforeUnmount(() => {
    clearTimeout(revealTimer);
    activeFrames.forEach((frame) => cancelAnimationFrame(frame));
    activeFrames.clear();
});
// Re-fetch kapag nagbago ang shared props
watch(() => [props.target, props.region, props.selectedStatuses, props.office, props.year], fetchStats, { deep: true });

/* ===================== EMPLOYEE LIST MODAL ===================== */

interface EmployeeRow {
    EMPCODE: string;
    LASTNAME: string;
    FIRSTNAME: string;
    MI: string;
    POSITION: string;
    office_division: string;
    REGION: string;
    SG: string;
    plantilla_status: string;
}

const showListModal = ref(false);
const listType = ref<'trained' | 'not_trained'>('trained');
const listLoading = ref(false);
const listSearch = ref('');
const employees = ref<EmployeeRow[]>([]);

const openList = async (type: 'trained' | 'not_trained') => {
    listType.value = type;
    listSearch.value = '';
    employees.value = [];
    showListModal.value = true;
    listLoading.value = true;
    try {
        const { data } = await axios.get(route('dashboard.training-compliance.list'), {
            params: {
                type,
                region: props.region,
                office_filter: props.target,
                plant_status: props.selectedStatuses,
                office: props.office,
                year: props.year,
            },
        });
        employees.value = data.employees;
    } catch (e) {
        console.error('Failed to load employee list:', e);
    } finally {
        listLoading.value = false;
    }
};

const filteredEmployees = computed(() => {
    const q = listSearch.value.trim().toLowerCase();
    if (!q) return employees.value;
    return employees.value.filter((e) =>
        [e.EMPCODE, e.LASTNAME, e.FIRSTNAME, e.MI, e.POSITION, e.office_division, e.REGION, e.SG, e.plantilla_status]
            .join(' ')
            .toLowerCase()
            .includes(q),
    );
});

const fullName = (e: EmployeeRow) => `${e.LASTNAME}, ${e.FIRSTNAME}${e.MI ? ' ' + e.MI : ''}`;

const downloadCsv = () => {
    const rows = filteredEmployees.value;
    if (!rows.length) return;
    const escape = (val: unknown) => {
        const s = String(val ?? '');
        return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const header = ['#', 'EMPCODE', 'NAME', 'POSITION', 'OFFICE/DIVISION', 'REGION', 'SG', 'STATUS'];
    const lines = rows.map((e, i) =>
        [i + 1, e.EMPCODE, fullName(e), e.POSITION, e.office_division, e.REGION, e.SG, e.plantilla_status].map(escape).join(','),
    );
    const csv = [header.join(','), ...lines].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${listType.value === 'trained' ? 'with-training' : 'no-training'}-employees.csv`;
    link.click();
    URL.revokeObjectURL(url);
};

/* ===================== APEXCHART ===================== */

const series = computed(() => [chartPercent.value]);

const chartOptions = {
    chart: {
        type: 'radialBar' as const,
        fontFamily: 'inherit',
        // Same duration as the count-up so the ring and center percentage settle together.
        animations: {
            enabled: !prefersReducedMotion,
            speed: COUNT_UP_DURATION,
            animateGradually: { enabled: false },
            dynamicAnimation: { enabled: !prefersReducedMotion, speed: COUNT_UP_DURATION },
        },
        sparkline: { enabled: true },
    },
    colors: ['#10B981'],
    stroke: { lineCap: 'round' as const },
    plotOptions: {
        radialBar: {
            startAngle: 0,
            endAngle: 360,
            hollow: { size: '60%' },
            track: { background: 'rgba(56, 189, 248, 0.22)', strokeWidth: '100%' },
            dataLabels: { name: { show: false }, value: { show: false } },
        },
    },
};
</script>

<template>
    <div class="tcc-card tdi-card tdi-accent-emerald tdi-accent-to-sky relative isolate overflow-hidden rounded-2xl border">
        <!-- Ambient motion layers — purely visual, behind content, never interactive -->
        <div class="pointer-events-none absolute inset-0 z-[-1] overflow-hidden" aria-hidden="true">
            <div class="tcc-ambient"></div>
            <span v-for="n in 5" :key="n" class="tcc-mote" :class="`tcc-mote--${n}`"></span>
            <div class="tcc-sweep"></div>
        </div>
        <!-- Decorative background texture — purely visual, sits behind existing content via negative z-index -->
        <div class="tdi-texture-learning pointer-events-none absolute inset-0 z-[-1] opacity-[0.025] dark:opacity-[0.04]" aria-hidden="true"></div>

        <!-- HEADER -->
        <div class="flex flex-wrap items-center gap-2 border-b border-border/80 px-5 py-3">
            <h2 class="tcc-reveal-x flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-[#0B4F8A] dark:text-sky-300">
                <span class="tcc-icon relative inline-flex"><GraduationCap class="h-4 w-4" /></span> Employees Training Compliance Rate
                <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin text-blue-500" />
            </h2>
        </div>

        <!-- CONTENT -->
        <div class="px-5 py-4">
            <div class="flex flex-col items-center justify-center gap-6 sm:flex-row sm:gap-10">
                <!-- LEFT: counts -->
                <div class="flex flex-row items-center gap-8 sm:flex-col sm:items-start sm:gap-6">
                    <button
                        type="button"
                        class="tcc-reveal tcc-stat group -mx-2 -my-1 cursor-pointer rounded-xl px-2 py-1 text-left ring-1 ring-transparent transition-[background-color,box-shadow,transform] duration-300 ease-out hover:-translate-y-0.5 hover:bg-emerald-50 hover:ring-emerald-200/80 focus-visible:-translate-y-0.5 focus-visible:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70 dark:hover:bg-emerald-950/40 dark:hover:ring-emerald-800/60 dark:focus-visible:bg-emerald-950/40"
                        style="--tcc-delay: 280ms"
                        @click="openList('trained')"
                    >
                        <span
                            class="mb-1 block w-fit translate-y-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-500 opacity-0 transition-[opacity,transform] duration-300 ease-out group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100 dark:bg-emerald-950/60"
                            >view</span
                        >
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-emerald-500">{{ animatedTrained.toLocaleString() }}</p>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-emerald-600">
                            <CircleCheckBig
                                class="h-4 w-4 transition-transform duration-300 ease-out group-hover:scale-110 group-focus-visible:scale-110"
                            />
                            With Training
                        </p>
                    </button>

                    <button
                        type="button"
                        class="tcc-reveal tcc-stat group -mx-2 -my-1 cursor-pointer rounded-xl px-2 py-1 text-left ring-1 ring-transparent transition-[background-color,box-shadow,transform] duration-300 ease-out hover:-translate-y-0.5 hover:bg-sky-50 hover:ring-sky-200/80 focus-visible:-translate-y-0.5 focus-visible:bg-sky-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/70 dark:hover:bg-sky-950/40 dark:hover:ring-sky-800/60 dark:focus-visible:bg-sky-950/40"
                        style="--tcc-delay: 400ms"
                        @click="openList('not_trained')"
                    >
                        <span
                            class="mb-1 block w-fit translate-y-1 rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-500 opacity-0 transition-[opacity,transform] duration-300 ease-out group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100 dark:bg-sky-950/60"
                            >view</span
                        >
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-sky-500">{{ animatedNotTrained.toLocaleString() }}</p>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-sky-600">
                            <CircleX class="h-4 w-4 transition-transform duration-300 ease-out group-hover:scale-110 group-focus-visible:scale-110" />
                            No Training
                        </p>
                    </button>
                </div>

                <!-- CENTER: chart -->
                <div class="tcc-reveal relative isolate" style="--tcc-delay: 520ms">
                    <div
                        v-if="revealCount"
                        :key="revealCount"
                        class="tcc-ring-glow pointer-events-none absolute inset-0 z-[-1]"
                        aria-hidden="true"
                    ></div>
                    <VueApexCharts type="radialBar" width="230" height="230" :options="chartOptions" :series="series" />
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-emerald-500">{{ animatedPercent }}%</p>
                        <p class="mt-1 text-xs font-semibold text-muted-foreground">With Training</p>
                    </div>
                </div>

                <!-- RIGHT: total -->
                <div class="tcc-reveal flex flex-col items-center gap-2 sm:items-start" style="--tcc-delay: 640ms">
                    <p class="text-3xl font-extrabold tabular-nums leading-none text-[#172033] dark:text-slate-50">
                        {{ animatedTotal.toLocaleString() }}
                    </p>
                    <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-muted-foreground"><Users class="h-4 w-4" /> Employees</p>
                </div>
            </div>

            <!-- Legend -->
            <div class="tcc-reveal mt-2 flex items-center justify-center gap-5" style="--tcc-delay: 760ms">
                <span class="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span> With Training
                </span>
                <span class="flex items-center gap-1.5 text-xs font-bold text-sky-500">
                    <span class="h-2.5 w-2.5 rounded-full bg-sky-300"></span> No Training
                </span>
            </div>
        </div>

        <!-- MODAL -->
        <Dialog :open="showListModal" @update:open="showListModal = $event">
            <DialogContent class="flex max-h-[85vh] !max-w-4xl flex-col gap-3 overflow-hidden !rounded-2xl">
                <DialogHeader class="shrink-0">
                    <DialogTitle class="text-lg font-extrabold">
                        <span :class="listType === 'trained' ? 'text-emerald-500' : 'text-cyan-500'">
                            {{ listType === 'trained' ? 'With Training' : 'No Training' }}
                        </span>
                        Employees
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground"> Based on current filter selection </DialogDescription>
                </DialogHeader>

                <div class="relative shrink-0">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="listSearch" class="h-10 rounded-xl pl-9 text-sm" placeholder="Search by name, position, office..." />
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <div v-if="listLoading" class="flex items-center justify-center py-16">
                        <LoaderCircle class="h-6 w-6 animate-spin text-blue-600" />
                    </div>
                    <table v-else-if="filteredEmployees.length" class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-background">
                            <tr class="border-b text-left text-xs text-muted-foreground">
                                <th class="w-10 px-3 py-2.5 font-bold">#</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">NAME</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">POSITION</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">OFFICE/DIVISION</th>
                                <th class="px-3 py-2.5 text-center font-bold tracking-wide">SG</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(emp, i) in filteredEmployees" :key="emp.EMPCODE + i" class="border-b last:border-b-0 hover:bg-muted/40">
                                <td class="px-3 py-2.5 text-muted-foreground">{{ i + 1 }}</td>
                                <td class="px-3 py-2.5 font-bold uppercase">{{ fullName(emp) }}</td>
                                <td class="px-3 py-2.5">{{ emp.POSITION }}</td>
                                <td class="px-3 py-2.5 text-xs text-muted-foreground">{{ emp.office_division }}</td>
                                <td class="px-3 py-2.5 text-center text-xs text-muted-foreground">{{ emp.SG }}</td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-block whitespace-nowrap rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-600 dark:bg-blue-950/60 dark:text-blue-300"
                                    >
                                        {{ emp.plantilla_status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="flex flex-col items-center justify-center py-16 text-center text-muted-foreground">
                        <p class="text-xs font-semibold">No employees found.</p>
                        <p class="mt-1 text-[11px]">Try a different search or adjust the filters.</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-between border-t pt-3">
                    <p class="text-sm text-muted-foreground">Showing {{ filteredEmployees.length.toLocaleString() }} employees</p>
                    <Button
                        size="sm"
                        class="rounded-full bg-emerald-500 px-4 text-white hover:bg-emerald-600"
                        :disabled="listLoading || filteredEmployees.length === 0"
                        @click="downloadCsv"
                    >
                        <Download class="mr-1 h-4 w-4" /> Download CSV
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
/* TDI Institutional Micro-Texture — same fine grid + radial-fade technique used
   in the homepage "Digital Resources" section, tinted emerald for the Learning
   Network motif (Employees → Learning → Competency → Development). */
.tdi-texture-learning {
    background-image:
        linear-gradient(rgba(52, 211, 153, 0.9) 1px, transparent 1px), linear-gradient(90deg, rgba(52, 211, 153, 0.9) 1px, transparent 1px);
    background-size: 26px 26px;
    -webkit-mask-image: radial-gradient(ellipse 220px 220px at 100% 0%, black 0%, transparent 75%);
    mask-image: radial-gradient(ellipse 220px 220px at 100% 0%, black 0%, transparent 75%);
    animation: tcc-fade-in 1200ms ease-out 200ms backwards;
}

/* ===================== ENTRANCE ===================== */

/* All entrance animations use `backwards` fill so content falls back to its
   normal, fully visible state if animations don't run, and hover transforms
   are not overridden once the sequence completes. */
.tcc-card {
    --tcc-ease: cubic-bezier(0.22, 1, 0.36, 1);
    --tcc-glow: 16 185 129;
    --tcc-glow-2: 56 189 248;
    animation: tcc-card-in 700ms var(--tcc-ease) backwards;
}

:global(.dark) .tcc-card {
    --tcc-glow: 52 211 153;
    --tcc-glow-2: 34 211 238;
}

/* Soft emerald border glow that fades after the entrance */
.tcc-card::after {
    position: absolute;
    inset: 0;
    z-index: 2;
    border-radius: inherit;
    box-shadow:
        inset 0 0 0 1px rgb(var(--tcc-glow) / 0.55),
        inset 0 0 28px rgb(var(--tcc-glow) / 0.12);
    content: '';
    opacity: 0;
    pointer-events: none;
    animation: tcc-glow-fade 1500ms ease-out 150ms;
}

.tcc-reveal {
    animation: tcc-rise 600ms var(--tcc-ease) var(--tcc-delay, 0ms) backwards;
}

.tcc-reveal-x {
    animation: tcc-slide-x 600ms var(--tcc-ease) 140ms backwards;
}

/* Brief highlight behind the graduation-cap icon */
.tcc-icon::after {
    position: absolute;
    inset: -6px;
    border-radius: 9999px;
    background: radial-gradient(circle, rgb(var(--tcc-glow) / 0.45), transparent 70%);
    content: '';
    opacity: 0;
    pointer-events: none;
    animation: tcc-pulse 1100ms ease-out 380ms;
}

/* Halo behind the radial ring — remounted on each data reveal */
.tcc-ring-glow {
    border-radius: 9999px;
    background: radial-gradient(circle, transparent 50%, rgb(var(--tcc-glow) / 0.28) 62%, transparent 74%);
    opacity: 0;
    animation: tcc-ring-glow 1300ms ease-out;
}

/* ===================== AMBIENT MOTION ===================== */

/* Slow emerald → sky light drift (transform-only, compositor friendly) */
.tcc-ambient {
    position: absolute;
    inset: -25%;
    background:
        radial-gradient(circle at 25% 30%, rgb(var(--tcc-glow) / 0.07), transparent 40%),
        radial-gradient(circle at 75% 70%, rgb(var(--tcc-glow-2) / 0.07), transparent 40%);
    will-change: transform;
    animation:
        tcc-fade-in 1400ms ease-out 300ms backwards,
        tcc-drift 22s ease-in-out 1.5s infinite alternate;
}

/* One-time light sweep across the surface during the entrance */
.tcc-sweep {
    position: absolute;
    top: 0;
    bottom: 0;
    left: -45%;
    width: 40%;
    background: linear-gradient(100deg, transparent, rgb(var(--tcc-glow) / 0.08) 45%, rgb(var(--tcc-glow-2) / 0.06) 55%, transparent);
    opacity: 0;
    animation: tcc-sweep 1300ms var(--tcc-ease) 350ms;
}

/* A handful of faint drifting highlights */
.tcc-mote {
    position: absolute;
    width: 4px;
    height: 4px;
    border-radius: 9999px;
    background: rgb(var(--tcc-glow) / 0.5);
    opacity: 0;
    animation: tcc-float 12s ease-in-out infinite;
}

.tcc-mote--1 {
    top: 70%;
    left: 12%;
    animation-delay: 1.4s;
}

.tcc-mote--2 {
    top: 35%;
    left: 30%;
    background: rgb(var(--tcc-glow-2) / 0.5);
    animation-duration: 14s;
    animation-delay: 4s;
}

.tcc-mote--3 {
    top: 80%;
    left: 62%;
    animation-duration: 11s;
    animation-delay: 2.5s;
}

.tcc-mote--4 {
    top: 25%;
    left: 85%;
    background: rgb(var(--tcc-glow-2) / 0.5);
    animation-duration: 15s;
    animation-delay: 6s;
}

.tcc-mote--5 {
    top: 55%;
    left: 92%;
    animation-duration: 13s;
    animation-delay: 8s;
}

/* ===================== KEYFRAMES ===================== */

@keyframes tcc-card-in {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
}

@keyframes tcc-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@keyframes tcc-slide-x {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
}

@keyframes tcc-fade-in {
    from {
        opacity: 0;
    }
}

@keyframes tcc-glow-fade {
    0% {
        opacity: 0;
    }
    30% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}

@keyframes tcc-pulse {
    0% {
        opacity: 0;
        transform: scale(0.6);
    }
    40% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: scale(1.4);
    }
}

@keyframes tcc-ring-glow {
    0% {
        opacity: 0;
        transform: scale(0.96);
    }
    45% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: scale(1.02);
    }
}

@keyframes tcc-sweep {
    0% {
        opacity: 0;
        transform: translateX(0);
    }
    20% {
        opacity: 1;
    }
    80% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: translateX(375%);
    }
}

@keyframes tcc-drift {
    from {
        transform: translate3d(-3%, -2%, 0);
    }
    to {
        transform: translate3d(3%, 2%, 0);
    }
}

@keyframes tcc-float {
    0%,
    100% {
        opacity: 0;
        transform: translate3d(0, 0, 0);
    }
    20%,
    70% {
        opacity: 0.6;
    }
    50% {
        transform: translate3d(6px, -28px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .tcc-card,
    .tcc-card::after,
    .tcc-reveal,
    .tcc-reveal-x,
    .tcc-icon::after,
    .tcc-ring-glow,
    .tcc-ambient,
    .tdi-texture-learning {
        animation: none;
    }

    .tcc-sweep,
    .tcc-mote {
        display: none;
    }

    .tcc-stat,
    .tcc-stat * {
        transition-duration: 0s !important;
    }
}
</style>
