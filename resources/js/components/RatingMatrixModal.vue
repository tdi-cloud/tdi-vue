<script setup lang="ts">
import { Eye, Gauge, Heart, MessageCircle, Scale, Shirt, Sparkles, X } from 'lucide-vue-next';
import { type Component, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Interview Rating Matrix — the guide NHRDC members (or their representatives)
 * follow when rating a nominee's interview. Criteria keys match
 * ForeignNomineeInterviewRating::CRITERIA (each scored 1–5).
 */
const open = defineModel<boolean>('open', { default: false });

interface Level {
    score: number;
    label: string;
    // Column / chip styling per level, from green (5) to red (1)
    chip: string;
    head: string;
    cell: string;
}

const LEVELS: Level[] = [
    {
        score: 5,
        label: 'Outstanding',
        chip: 'bg-emerald-500 text-white',
        head: 'from-emerald-500 to-emerald-600',
        cell: 'bg-emerald-50/70 dark:bg-emerald-500/[0.07]',
    },
    {
        score: 4,
        label: 'Very Satisfactory',
        chip: 'bg-teal-500 text-white',
        head: 'from-teal-500 to-cyan-600',
        cell: 'bg-teal-50/70 dark:bg-teal-500/[0.07]',
    },
    {
        score: 3,
        label: 'Satisfactory',
        chip: 'bg-blue-500 text-white',
        head: 'from-blue-500 to-indigo-600',
        cell: 'bg-blue-50/70 dark:bg-blue-500/[0.07]',
    },
    {
        score: 2,
        label: 'Needs Improvement',
        chip: 'bg-amber-500 text-white',
        head: 'from-amber-500 to-orange-500',
        cell: 'bg-amber-50/70 dark:bg-amber-500/[0.07]',
    },
    {
        score: 1,
        label: 'Poor',
        chip: 'bg-rose-500 text-white',
        head: 'from-rose-500 to-red-600',
        cell: 'bg-rose-50/70 dark:bg-rose-500/[0.07]',
    },
];

interface Criterion {
    key: string;
    label: string;
    icon: Component;
    // Descriptors in LEVELS order: 5, 4, 3, 2, 1
    descriptors: [string, string, string, string, string];
}

const CRITERIA: Criterion[] = [
    {
        key: 'communication_skills',
        label: 'Communication Skills',
        icon: MessageCircle,
        descriptors: [
            'Communicates ideas clearly, confidently, and logically; excellent articulation, listening, and responsiveness; maintains professionalism throughout',
            'Communicates clearly with minimal lapses; ideas generally organized and understandable',
            'Able to communicate basic ideas adequately but with occasional difficulty in clarity or organization',
            'Frequently unclear, disorganized, or unable to effectively express ideas',
            'Unable to communicate ideas clearly; responses are incoherent or inappropriate',
        ],
    },
    {
        key: 'alertness',
        label: 'Alertness',
        icon: Eye,
        descriptors: [
            'Highly attentive, responsive, and mentally engaged; demonstrates strong situational awareness and quick comprehension',
            'Generally attentive and responsive with minor lapses',
            'Shows average attentiveness and understanding of discussions or instructions',
            'Often distracted or slow to comprehend and respond',
            'Appears disengaged, inattentive, or unaware of surroundings/discussions',
        ],
    },
    {
        key: 'judgement',
        label: 'Judgement',
        icon: Scale,
        descriptors: [
            'Demonstrates sound reasoning, maturity, and excellent decision-making; responses reflect critical thinking and discretion',
            'Shows good judgement and reasonable decision-making in most situations',
            'Demonstrates acceptable judgement with occasional lapses',
            'Frequently demonstrates poor reasoning or questionable decisions',
            'Consistently displays poor judgement and inability to assess situations properly',
        ],
    },
    {
        key: 'self_confidence',
        label: 'Self-Confidence',
        icon: Gauge,
        descriptors: [
            'Projects strong confidence and composure without arrogance; speaks and acts decisively',
            'Generally confident and composed with minor signs of hesitation',
            'Displays moderate confidence; occasional uncertainty observed',
            'Often hesitant, timid, or unsure in responses and actions',
            'Lacks confidence significantly; unable to present self effectively',
        ],
    },
    {
        key: 'emotional_stability',
        label: 'Emotional Stability',
        icon: Heart,
        descriptors: [
            'Remains calm, composed, and professional even under pressure; manages emotions exceptionally well',
            'Generally composed and emotionally controlled with minimal signs of stress',
            'Shows acceptable emotional control under normal circumstances',
            'Easily affected by pressure, criticism, or challenging situations',
            'Demonstrates poor emotional control or inappropriate reactions',
        ],
    },
    {
        key: 'appearance',
        label: 'Appearance',
        icon: Shirt,
        descriptors: [
            'Exceptionally neat, professional, well-groomed, and appropriate for the setting',
            'Neat and professional with very minimal issues in grooming or attire',
            'Acceptable appearance and grooming standards',
            'Appearance is somewhat untidy or not fully appropriate',
            'Poor grooming, unprofessional, or inappropriate appearance',
        ],
    },
];

// Small screens: one criterion at a time
const activeCriterion = ref(CRITERIA[0].key);

const onKeydown = (e: KeyboardEvent) => {
    if (open.value && e.key === 'Escape') open.value = false;
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/70 p-3 backdrop-blur-sm sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="rating-matrix-title"
                @click.self="open = false"
            >
                <div
                    class="flex max-h-[92vh] w-full max-w-7xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-slate-900 dark:ring-white/10"
                >
                    <!-- Header -->
                    <div
                        class="relative shrink-0 overflow-hidden bg-gradient-to-br from-[#14096b] via-[#1d1aa8] to-[#2563eb] px-6 py-5 text-white sm:px-8 sm:py-6"
                    >
                        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                            <div
                                class="absolute inset-0 opacity-[0.08]"
                                style="
                                    background-image: linear-gradient(white 1px, transparent 1px), linear-gradient(90deg, white 1px, transparent 1px);
                                    background-size: 24px 24px;
                                    mask-image: linear-gradient(to left, black, transparent 70%);
                                "
                            />
                            <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-sky-400/30 blur-3xl" />
                            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-300 via-amber-400 to-transparent" />
                        </div>

                        <div class="relative flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div
                                    class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-300 to-amber-500 text-[#14096b] shadow-lg shadow-amber-500/30 sm:flex"
                                >
                                    <Sparkles class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-200">Interview Rating Guide</p>
                                    <h2 id="rating-matrix-title" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Rating Matrix</h2>
                                    <p class="mt-1.5 max-w-3xl text-sm text-blue-100/85">
                                        The applicants/nominees shall be evaluated by the HRDC or representatives based on the following criteria.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-xl bg-white/10 p-2 text-white/80 ring-1 ring-white/15 transition hover:bg-white/20 hover:text-white"
                                aria-label="Close rating matrix"
                                @click="open = false"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Scale legend -->
                        <div class="relative mt-5 flex flex-wrap gap-2">
                            <span
                                v-for="level in LEVELS"
                                :key="level.score"
                                class="inline-flex items-center gap-2 rounded-full bg-white/10 py-1 pl-1 pr-3 text-xs font-semibold ring-1 ring-white/15"
                            >
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-extrabold" :class="level.chip">
                                    {{ level.score }}
                                </span>
                                {{ level.label }}
                            </span>
                        </div>
                    </div>

                    <!-- Body: full matrix on large screens -->
                    <div class="hidden min-h-0 flex-1 overflow-auto p-5 lg:block">
                        <table class="w-full table-fixed border-separate border-spacing-1.5 text-left">
                            <colgroup>
                                <col class="w-[15%]" />
                                <col v-for="level in LEVELS" :key="level.score" class="w-[17%]" />
                            </colgroup>
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th class="rounded-xl bg-slate-800 px-4 py-3 text-sm font-bold text-white dark:bg-slate-700">Criteria</th>
                                    <th
                                        v-for="level in LEVELS"
                                        :key="level.score"
                                        class="rounded-xl bg-gradient-to-br px-3 py-3 text-center text-white shadow-sm"
                                        :class="level.head"
                                    >
                                        <span class="block text-2xl font-extrabold leading-none">{{ level.score }}</span>
                                        <span class="mt-1 block text-xs font-bold uppercase tracking-wide">{{ level.label }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="criterion in CRITERIA" :key="criterion.key" class="group">
                                    <th
                                        scope="row"
                                        class="rounded-xl bg-slate-100 px-4 py-3 align-middle transition-colors group-hover:bg-indigo-100 dark:bg-slate-800 dark:group-hover:bg-indigo-500/20"
                                    >
                                        <span class="flex items-center gap-2.5">
                                            <span
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-sm dark:bg-slate-900 dark:text-indigo-300"
                                            >
                                                <component :is="criterion.icon" class="h-4 w-4" />
                                            </span>
                                            <span class="text-sm font-bold leading-tight text-slate-900 dark:text-slate-50">{{
                                                criterion.label
                                            }}</span>
                                        </span>
                                    </th>
                                    <td
                                        v-for="(descriptor, i) in criterion.descriptors"
                                        :key="LEVELS[i].score"
                                        class="rounded-xl px-3 py-3 text-center align-middle text-[12.5px] leading-relaxed text-slate-700 ring-1 ring-inset ring-black/[0.04] transition group-hover:ring-indigo-200 dark:text-slate-300 dark:ring-white/5 dark:group-hover:ring-indigo-500/30"
                                        :class="LEVELS[i].cell"
                                    >
                                        {{ descriptor }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Body: one criterion at a time on small screens -->
                    <div class="flex min-h-0 flex-1 flex-col lg:hidden">
                        <div class="flex shrink-0 gap-1.5 overflow-x-auto border-b px-4 py-3 dark:border-slate-800">
                            <button
                                v-for="criterion in CRITERIA"
                                :key="criterion.key"
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                                :class="
                                    activeCriterion === criterion.key
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                                "
                                @click="activeCriterion = criterion.key"
                            >
                                <component :is="criterion.icon" class="h-3.5 w-3.5" /> {{ criterion.label }}
                            </button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto p-4">
                            <template v-for="criterion in CRITERIA" :key="criterion.key">
                                <ul v-if="activeCriterion === criterion.key" class="flex flex-col gap-2.5">
                                    <li
                                        v-for="(descriptor, i) in criterion.descriptors"
                                        :key="LEVELS[i].score"
                                        class="flex items-start gap-3 rounded-2xl p-3 ring-1 ring-inset ring-black/[0.04] dark:ring-white/5"
                                        :class="LEVELS[i].cell"
                                    >
                                        <span
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-base font-extrabold shadow-sm"
                                            :class="LEVELS[i].chip"
                                        >
                                            {{ LEVELS[i].score }}
                                        </span>
                                        <span>
                                            <span class="block text-xs font-bold uppercase tracking-wide text-slate-900 dark:text-slate-50">
                                                {{ LEVELS[i].label }}
                                            </span>
                                            <span class="mt-0.5 block text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{
                                                descriptor
                                            }}</span>
                                        </span>
                                    </li>
                                </ul>
                            </template>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t bg-slate-50 px-6 py-3 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
                    >
                        <span>Each criterion is worth up to <strong>5 points</strong> — 6 criteria, <strong>30 points</strong> maximum.</span>
                        <button
                            type="button"
                            class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-indigo-700"
                            @click="open = false"
                        >
                            Got it
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
