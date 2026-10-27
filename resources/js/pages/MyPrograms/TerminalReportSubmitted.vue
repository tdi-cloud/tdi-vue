<template>
    <Head :title="`${requirement.title} Report Submitted`" />
    <div class="min-h-screen bg-[#f7f9fd] font-[Inter,system-ui,sans-serif] text-[#1a2744] [color-scheme:light]">
        <TheNavbar />

        <!-- Success hero -->
        <section class="relative overflow-hidden bg-[#0f1c48] pb-24 pt-32">
            <div class="absolute inset-0 bg-gradient-to-br from-[#0a153c] via-[#132a7a] to-[#1d3fc4] opacity-95"></div>
            <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-emerald-400/10"></div>

            <div class="relative mx-auto flex w-[min(100%-2rem,1080px)] flex-col gap-6 sm:flex-row sm:items-center">
                <span
                    class="success-badge flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-900/40"
                >
                    <CheckCircle2 :size="34" />
                </span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-emerald-300">{{ requirement.title }} · Submitted for review</p>
                    <h1 class="mt-1 text-2xl font-extrabold text-white sm:text-3xl">Your report has been generated and submitted</h1>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ program.title }} <span v-if="program.batch_label">· {{ program.batch_label }}</span>
                        <span v-if="submittedAt"> · Submitted {{ formatDateTime(submittedAt) }}</span>
                    </p>
                </div>
            </div>
        </section>

        <main class="relative mx-auto -mt-14 grid w-[min(100%-2rem,1080px)] gap-5 pb-16 lg:grid-cols-[1fr_300px]">
            <!-- PDF preview -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl shadow-blue-900/10">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <p class="flex min-w-0 items-center gap-2 text-sm font-semibold text-gray-700">
                        <FileText :size="16" class="shrink-0 text-[#1d3fc4]" />
                        <span class="truncate">{{ pdfName }}</span>
                    </p>
                    <div class="flex gap-2">
                        <a
                            :href="pdfUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#eef1fc] px-3 py-1.5 text-xs font-bold text-[#1d3fc4] no-underline hover:bg-[#dde4fb]"
                        >
                            <ExternalLink :size="13" /> Open
                        </a>
                        <a
                            :href="pdfUrl"
                            :download="pdfName"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#1d3fc4] px-3 py-1.5 text-xs font-bold text-white no-underline hover:bg-[#1535a8]"
                        >
                            <Download :size="13" /> Download
                        </a>
                    </div>
                </header>
                <iframe :src="pdfUrl" :title="`${requirement.title} report PDF`" class="block h-[78vh] min-h-[520px] w-full bg-gray-100"></iframe>
            </section>

            <!-- Next steps -->
            <aside class="flex flex-col gap-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-extrabold uppercase tracking-wide text-gray-500">Status</h2>
                    <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">
                        <Clock :size="14" /> {{ status === 'Pending' ? 'Pending Review' : status }}
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-gray-600">
                        An admin will review your report. You'll be notified if anything needs to be revised.
                    </p>
                </div>

                <div v-if="activitiesCount > 0" class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold text-violet-800"><Target :size="16" /> Next: your action plan</h2>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600">
                        You listed <strong>{{ activitiesCount }} {{ activitiesCount === 1 ? 'activity' : 'activities' }}</strong
                        >. Submit an <strong>implementation report</strong> for each one on or before its target date.
                    </p>
                    <Link
                        :href="route('programs.my-progress', { batch: batchId, tab: 'action-plan' })"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white no-underline hover:bg-violet-700"
                    >
                        Go to My Action Plan <ArrowRight :size="13" />
                    </Link>
                </div>

                <div class="flex flex-col gap-2">
                    <Link
                        :href="route('programs.my-progress', batchId)"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#1d3fc4] px-4 py-2.5 text-sm font-bold text-white no-underline shadow-md shadow-blue-200 hover:bg-[#1535a8]"
                    >
                        <ArrowLeft :size="15" /> Back to program
                    </Link>
                    <Link
                        v-if="canEdit"
                        :href="route('programs.terminal-report.edit', { batch: batchId, requirement: requirement.id })"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 no-underline hover:bg-gray-50"
                    >
                        <PenLine :size="15" /> Update form
                    </Link>
                </div>
            </aside>
        </main>

        <TheFooter />
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, Clock, Download, ExternalLink, FileText, PenLine, Target } from 'lucide-vue-next';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

defineProps<{
    batchId: number;
    requirement: { id: number; title: string; name: string };
    program: { title: string | null; batch_label: string | null };
    pdfUrl: string;
    pdfName: string;
    status: string;
    submittedAt: string | null;
    activitiesCount: number;
    canEdit: boolean;
}>();

function formatDateTime(value: string) {
    return new Date(value).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

<style scoped>
.success-badge {
    animation: pop-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes pop-in {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
