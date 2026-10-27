<template>
    <Head :title="`${requirement.title} Report — Sign & Submit`" />
    <div class="min-h-screen bg-[#f7f9fd] font-[Inter,system-ui,sans-serif] text-[#1a2744] [color-scheme:light]">
        <TheNavbar />

        <!-- Hero -->
        <section class="relative overflow-hidden bg-[#0f1c48] pb-24 pt-32">
            <div class="absolute inset-0 bg-gradient-to-br from-[#0a153c] via-[#132a7a] to-[#1d3fc4] opacity-95"></div>
            <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-sky-400/10"></div>

            <div class="relative mx-auto flex w-[min(100%-2rem,1080px)] flex-col gap-6 sm:flex-row sm:items-center">
                <span
                    class="pop-in flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg"
                    :class="isSubmitted ? 'bg-emerald-500 shadow-emerald-900/40' : 'bg-amber-500 shadow-amber-900/40'"
                >
                    <CheckCircle2 v-if="isSubmitted" :size="34" />
                    <PenLine v-else :size="32" />
                </span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.15em]" :class="isSubmitted ? 'text-emerald-300' : 'text-amber-300'">
                        {{ requirement.title }} · {{ isSubmitted ? 'Signed copy submitted' : 'Not yet submitted' }}
                    </p>
                    <h1 class="mt-1 text-2xl font-extrabold text-white sm:text-3xl">
                        {{ isSubmitted ? 'Your signed report has been submitted' : 'Your report is ready — sign it to submit' }}
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ program.title }} <span v-if="program.batch_label">· {{ program.batch_label }}</span>
                    </p>
                </div>
            </div>
        </section>

        <main class="relative mx-auto -mt-14 grid w-[min(100%-2rem,1080px)] gap-5 pb-16 lg:grid-cols-[1fr_330px]">
            <!-- Unsigned PDF -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl shadow-blue-900/10">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <p class="flex min-w-0 items-center gap-2 text-sm font-semibold text-gray-700">
                        <FileText :size="16" class="shrink-0 text-[#1d3fc4]" />
                        <span class="truncate">Generated report (unsigned)</span>
                        <span v-if="generatedAt" class="hidden text-xs font-normal text-gray-400 sm:inline">· {{ formatDateTime(generatedAt) }}</span>
                    </p>
                    <div class="flex gap-2">
                        <a
                            :href="pdfUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#eef1fc] px-3 py-1.5 text-xs font-bold text-[#1d3fc4] no-underline hover:bg-[#dde4fb]"
                        >
                            <Printer :size="13" /> Open &amp; print
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

            <aside class="flex flex-col gap-4">
                <!-- Steps -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-extrabold uppercase tracking-wide text-gray-500">How to submit</h2>
                    <ol class="mt-3 space-y-3">
                        <li v-for="(step, index) in steps" :key="step.title" class="flex gap-3">
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                :class="step.done ? 'bg-emerald-100 text-emerald-700' : 'bg-[#eef1fc] text-[#1d3fc4]'"
                            >
                                <Check v-if="step.done" :size="14" />
                                <template v-else>{{ index + 1 }}</template>
                            </span>
                            <span>
                                <span class="block text-sm font-bold">{{ step.title }}</span>
                                <span class="block text-xs leading-relaxed text-gray-500">{{ step.text }}</span>
                            </span>
                        </li>
                    </ol>
                </div>

                <!-- Signed copy -->
                <div
                    class="rounded-2xl border-2 p-5 shadow-sm"
                    :class="isSubmitted ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-300 bg-amber-50/50'"
                >
                    <h2 class="flex items-center gap-2 text-sm font-extrabold"><Upload :size="16" /> Signed copy</h2>

                    <template v-if="signedCopy">
                        <p class="mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold" :class="statusStyle">
                            {{
                                signedCopy.status === 'Pending'
                                    ? 'Pending Review'
                                    : signedCopy.status === 'Rejected'
                                      ? 'Revision Needed'
                                      : signedCopy.status
                            }}
                        </p>
                        <p class="mt-2 text-xs text-gray-500">Uploaded {{ formatDateTime(signedCopy.submitted_at) }}</p>
                        <a
                            :href="signedCopy.file_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-[#1d3fc4]"
                        >
                            <FileText :size="13" /> View signed copy
                        </a>
                        <p
                            v-if="signedCopy.status === 'Rejected' && signedCopy.remarks"
                            class="mt-2 rounded-lg bg-orange-50 p-2 text-xs text-orange-800"
                        >
                            <strong>Reviewer note:</strong> {{ signedCopy.remarks }}
                        </p>
                        <p v-if="signedCopy.older_than_draft" class="mt-2 rounded-lg bg-amber-100 p-2 text-xs text-amber-900">
                            You regenerated the form after uploading this copy. Upload a newly signed copy so it matches your latest report.
                        </p>
                    </template>
                    <p v-else class="mt-2 text-sm text-amber-900">
                        <strong>Not submitted yet.</strong> Your report only counts once you upload the signed copy.
                    </p>

                    <form v-if="canUpload" class="mt-4 space-y-2" @submit.prevent="uploadSignedCopy">
                        <label
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-600 hover:border-[#1d3fc4]"
                        >
                            <Upload :size="15" class="shrink-0" />
                            <span class="truncate">{{ uploadForm.file?.name ?? 'Choose the scanned signed PDF' }}</span>
                            <input type="file" accept="application/pdf" class="hidden" @change="onFile" />
                        </label>
                        <p v-if="uploadForm.errors.file" class="text-xs text-red-600">{{ uploadForm.errors.file }}</p>
                        <button
                            type="submit"
                            :disabled="!uploadForm.file || uploadForm.processing"
                            class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <Loader2 v-if="uploadForm.processing" :size="15" class="animate-spin" />
                            <Send v-else :size="15" />
                            {{ signedCopy ? 'Replace signed copy' : 'Submit signed copy' }}
                        </button>
                    </form>
                </div>

                <div v-if="activitiesCount > 0" class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold text-violet-800"><Target :size="16" /> Your action plan</h2>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600">
                        You listed <strong>{{ activitiesCount }} {{ activitiesCount === 1 ? 'activity' : 'activities' }}</strong
                        >. After submitting, send an <strong>implementation report</strong> for each one on or before its target date.
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
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 no-underline hover:bg-gray-50"
                    >
                        <ArrowLeft :size="15" /> Back to program
                    </Link>
                    <Link
                        v-if="canEdit"
                        :href="route('programs.terminal-report.edit', { batch: batchId, requirement: requirement.id })"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 no-underline hover:bg-gray-50"
                    >
                        <PenLine :size="15" /> Edit form &amp; regenerate
                    </Link>
                </div>
            </aside>
        </main>

        <TheFooter />
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, CheckCircle2, Download, FileText, Loader2, PenLine, Printer, Send, Target, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

const props = defineProps<{
    batchId: number;
    requirement: { id: number; title: string; name: string };
    program: { title: string | null; batch_label: string | null };
    pdfUrl: string;
    pdfName: string;
    generatedAt: string | null;
    signedCopy: {
        status: 'Pending' | 'Approved' | 'Rejected';
        remarks: string | null;
        file_url: string;
        submitted_at: string | null;
        older_than_draft: boolean;
    } | null;
    activitiesCount: number;
    canEdit: boolean;
}>();

const isSubmitted = computed(() => !!props.signedCopy);
const canUpload = computed(() => props.signedCopy?.status !== 'Approved');

const statusStyle = computed(() => {
    switch (props.signedCopy?.status) {
        case 'Approved':
            return 'bg-emerald-100 text-emerald-700';
        case 'Rejected':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-amber-100 text-amber-800';
    }
});

const steps = computed(() => [
    { title: 'Download & print', text: 'Open the generated report and print it.', done: isSubmitted.value },
    {
        title: 'Sign it',
        text: 'Sign as "Prepared by", then have your approver sign "Discussed with and Approved by".',
        done: isSubmitted.value,
    },
    { title: 'Upload the signed copy', text: 'Scan the signed report and upload it here as a PDF.', done: isSubmitted.value },
]);

/* ---- Upload the signed copy as the requirement submission ---- */
const uploadForm = useForm({ file: null as File | null });

function onFile(event: Event) {
    uploadForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function uploadSignedCopy() {
    uploadForm.post(route('programs.my-progress.submit', { batch: props.batchId, requirement: props.requirement.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset(),
    });
}

function formatDateTime(value: string | null) {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

<style scoped>
.pop-in {
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
