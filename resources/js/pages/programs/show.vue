<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import BatchList from '@/pages/programs/BatchList.vue';
import CertificatesPanel from '@/pages/programs/CertificatesPanel.vue';
import CompetencyModal from '@/pages/programs/CompetencyModal.vue';
import CoverPagePanel from '@/pages/programs/CoverPagePanel.vue';
import EvaluationDashboard from '@/pages/programs/EvaluationDashboard.vue';
import RequirementList from '@/pages/programs/RequirementList.vue';
import ResourceSpeakers from '@/pages/programs/ResourceSpeakers.vue';
import SubmissionList from '@/pages/programs/SubmissionList.vue';
import SupportingDocuments from '@/pages/programs/SupportingDocuments.vue';
import TesdaOrderPanel from '@/pages/programs/TesdaOrderPanel.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Award,
    Building2,
    CalendarDays,
    ChevronRight,
    ClipboardCheck,
    Coins,
    FilePenLine,
    FileSignature,
    FileText,
    Flag,
    HandCoins,
    Handshake,
    Hash,
    House,
    IdCard,
    Info,
    Layers,
    Lightbulb,
    Megaphone,
    Pencil,
    Play,
    Plus,
    Presentation,
    ScrollText,
    Settings2,
    Trash2,
    Truck,
    Users,
    Wallet,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';

const { confirmDialog } = useConfirm();

interface Requirement {
    id: number;
    batch_id: number;
    title: string;
    name: string;
    due_date: string;
    is_required: boolean;
    note: string | null;
}

interface Batch {
    id: number;
    sort_order: number;
    program_code: string;
    batch: string;
    status: string;
    modality: string;
    venue: string | null;
    date_start: string;
    date_end: string;
    time_start: string;
    time_end: string;
    days: string;
    hours: string;
    requirements?: Requirement[];
    evaluation_form?: { id: number; batch_id: number; is_active: boolean } | null;
    participants?: { id: number; certificates?: unknown[] }[];
}

interface Competency {
    id: number;
    domain: string;
    competency: string;
}

interface SupportingDocument {
    id: number;
    program_id: number;
    program_code: string | null;
    document_type: string;
    subject: string;
    document_series: number;
    origin: string | null;
    document_number: string;
    date_issued: string | null;
    link: string | null;
}

interface ResourceSpeaker {
    id: number;
    program_id: number;
    program_code: string | null;
    name: string;
    designation: string | null;
    affiliation: string | null;
    topic: string | null;
    expertise: string | null;
    email: string | null;
    contact_number: string | null;
    date_engaged: string | null;
    remarks: string | null;
}

interface Program {
    id: number;
    program_code: string;
    title: string;
    description: string;
    modality: string;
    pax: string;
    category: string;
    type: string;
    initiated: string;
    provider: string;
    cost: string;
    fund: string;
    origin: string;
    created_at: string;
    batches?: Batch[];
    competencies?: Competency[];
    supporting_documents?: SupportingDocument[];
    resource_speakers?: ResourceSpeaker[];
    cover_page?: { id: number; image: string; image_url: string | null } | null;
    email_reminder_logs?: any[];
}

const props = defineProps<{
    program: Program;
    submissions: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Programs', href: '/programs' },
    { title: props.program.program_code, href: `/programs/${props.program.id}` },
];

const deleteProgram = async () => {
    if (await confirmDialog('Are you sure you want to delete this program?')) {
        router.delete(route('programs.destroy', props.program.id), {
            onSuccess: () => router.visit(route('programs.index')),
        });
    }
};

/* ===================== TABS ===================== */

const activeTab = ref('details');
const tabsSection = ref<HTMLElement | null>(null);
const competenciesSection = ref<HTMLElement | null>(null);

const goToTab = (tab: string) => {
    activeTab.value = tab;
    tabsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const scrollToCompetencies = () => {
    competenciesSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

/* ===================== SUMMARY (derived from loaded data) ===================== */

const batches = computed(() => props.program.batches ?? []);

const totalParticipants = computed(() => batches.value.reduce((sum, b) => sum + (b.participants?.length ?? 0), 0));

const totalRequirements = computed(() => batches.value.reduce((sum, b) => sum + (b.requirements?.length ?? 0), 0));

const totalCertificates = computed(() =>
    batches.value.reduce((sum, b) => sum + (b.participants ?? []).reduce((pSum, p) => pSum + (p.certificates?.length ?? 0), 0), 0),
);

interface TabItem {
    value: string;
    label: string;
    icon: Component;
    count?: number;
}

const tabs = computed<TabItem[]>(() => [
    { value: 'details', label: 'Details', icon: Info },
    { value: 'participants', label: 'Participants', icon: Users, count: totalParticipants.value },
    { value: 'submissions', label: 'Submissions', icon: FileText, count: props.submissions.length },
    { value: 'Supporting', label: 'Supporting Docs', icon: ScrollText, count: props.program.supporting_documents?.length ?? 0 },
    { value: 'requirements', label: 'Requirements', icon: FilePenLine, count: totalRequirements.value },
    { value: 'certificates', label: 'Certificates', icon: Award, count: totalCertificates.value },
    { value: 'tesda-order', label: 'TESDA Order', icon: FileSignature },
    { value: 'resource', label: 'Resource Speaker', icon: Megaphone, count: props.program.resource_speakers?.length ?? 0 },
]);

interface Metric {
    label: string;
    value: number;
    icon: Component;
    tone: string;
    action: () => void;
}

const metrics = computed<Metric[]>(() => [
    {
        label: 'Batches',
        value: batches.value.length,
        icon: Layers,
        tone: 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',
        action: () => goToTab('participants'),
    },
    {
        label: 'Participants',
        value: totalParticipants.value,
        icon: Users,
        tone: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300',
        action: () => goToTab('participants'),
    },
    {
        label: 'Requirements',
        value: totalRequirements.value,
        icon: FilePenLine,
        tone: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-300',
        action: () => goToTab('requirements'),
    },
    {
        label: 'Competencies',
        value: props.program.competencies?.length ?? 0,
        icon: Lightbulb,
        tone: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
        action: scrollToCompetencies,
    },
    {
        label: 'Resource Speakers',
        value: props.program.resource_speakers?.length ?? 0,
        icon: Megaphone,
        tone: 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-300',
        action: () => goToTab('resource'),
    },
    {
        label: 'Certificates',
        value: totalCertificates.value,
        icon: Award,
        tone: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
        action: () => goToTab('certificates'),
    },
]);

const quickActions: { label: string; icon: Component; tab: string }[] = [
    { label: 'Manage Batches & Participants', icon: Layers, tab: 'participants' },
    { label: 'Manage Requirements', icon: FilePenLine, tab: 'requirements' },
    { label: 'Manage Resource Speakers', icon: Megaphone, tab: 'resource' },
    { label: 'Manage Supporting Docs', icon: ScrollText, tab: 'Supporting' },
];

/* ===================== DISPLAY HELPERS ===================== */

const displayValue = (value: string | null | undefined): string => (value && String(value).trim() !== '' ? value : '—');

const formattedCost = computed(() => {
    const cost = props.program.cost;
    if (cost === null || cost === undefined || String(cost).trim() === '') {
        return '—';
    }
    const numericCost = Number(cost);
    return Number.isNaN(numericCost) ? cost : numericCost.toLocaleString();
});

const createdOn = computed(() => {
    if (!props.program.created_at) {
        return null;
    }
    const date = new Date(props.program.created_at);
    return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
});

const heroBadges = computed(() => [props.program.category, props.program.type, props.program.modality].filter((b) => b && String(b).trim() !== ''));

interface InfoField {
    label: string;
    value: string;
    icon: Component;
    iconClass: string;
}

const deliveryFields = computed<InfoField[]>(() => [
    { label: 'Modality', value: displayValue(props.program.modality), icon: Presentation, iconClass: 'text-blue-600 dark:text-blue-300' },
    { label: 'Category', value: displayValue(props.program.category), icon: House, iconClass: 'text-indigo-600 dark:text-indigo-300' },
    { label: 'Program Type', value: displayValue(props.program.type), icon: Settings2, iconClass: 'text-slate-600 dark:text-slate-300' },
    { label: 'Target Pax', value: displayValue(props.program.pax), icon: Users, iconClass: 'text-purple-600 dark:text-purple-300' },
]);

const financialFields = computed<InfoField[]>(() => [
    { label: 'Cost', value: formattedCost.value, icon: Coins, iconClass: 'text-amber-600 dark:text-amber-300' },
    { label: 'Fund Source', value: displayValue(props.program.fund), icon: HandCoins, iconClass: 'text-green-600 dark:text-green-300' },
]);

const organizationFields = computed<InfoField[]>(() => [
    { label: 'Office Initiated', value: displayValue(props.program.initiated), icon: Play, iconClass: 'text-orange-600 dark:text-orange-300' },
    { label: 'Provider', value: displayValue(props.program.provider), icon: Handshake, iconClass: 'text-blue-600 dark:text-blue-300' },
    { label: 'Origin', value: displayValue(props.program.origin), icon: Flag, iconClass: 'text-emerald-600 dark:text-emerald-300' },
]);

/* ===================== COMPETENCIES ===================== */

const showCompetencyModal = ref(false);
const showEvaluationDashboard = ref(false);

const DOMAIN_ORDER = ['Leadership', 'Core', 'Organizational', 'Technical', 'TTI'];

const DOMAIN_COLORS: Record<string, string> = {
    Leadership: 'text-purple-500',
    Core: 'text-blue-500',
    Organizational: 'text-emerald-500',
    Technical: 'text-orange-500',
    TTI: 'text-rose-500',
};

// Accent (dot + header rule) per domain, kapareho ng DOMAIN_COLORS
const DOMAIN_ACCENTS: Record<string, { dot: string; rule: string; header: string }> = {
    Leadership: { dot: 'bg-purple-500', rule: 'border-purple-200 dark:border-purple-500/30', header: 'bg-purple-50/70 dark:bg-purple-500/5' },
    Core: { dot: 'bg-blue-500', rule: 'border-blue-200 dark:border-blue-500/30', header: 'bg-blue-50/70 dark:bg-blue-500/5' },
    Organizational: {
        dot: 'bg-emerald-500',
        rule: 'border-emerald-200 dark:border-emerald-500/30',
        header: 'bg-emerald-50/70 dark:bg-emerald-500/5',
    },
    Technical: { dot: 'bg-orange-500', rule: 'border-orange-200 dark:border-orange-500/30', header: 'bg-orange-50/70 dark:bg-orange-500/5' },
    TTI: { dot: 'bg-rose-500', rule: 'border-rose-200 dark:border-rose-500/30', header: 'bg-rose-50/70 dark:bg-rose-500/5' },
};

// Grouped per domain para sa sidebar display
const groupedCompetencies = computed(() => {
    const list = props.program.competencies ?? [];
    return DOMAIN_ORDER.map((domain) => ({
        domain,
        items: list.filter((c) => c.domain === domain),
    })).filter((g) => g.items.length > 0);
});

// Names ng naka-add na, para hindi na lumabas sa choices ng modal
const existingCompetencyNames = computed(() => (props.program.competencies ?? []).map((c) => c.competency));

const removeCompetency = async (competency: Competency) => {
    if (await confirmDialog('Remove this competency from the program?', { confirmText: 'Remove' })) {
        router.delete(route('programs.competencies.destroy', [props.program.id, competency.id]), {
            preserveScroll: true,
        });
    }
};

/* ===================== SHARED STYLES ===================== */

const cardClass = 'rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/70';
</script>

<template>
    <Head :title="program.program_code" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 px-4 pb-10 pt-4 sm:px-6">
            <!-- ===================== HERO ===================== -->
            <section
                class="relative overflow-hidden rounded-2xl border border-blue-900/20 bg-gradient-to-br from-sky-900 via-blue-900 to-indigo-950 text-white shadow-xl dark:border-white/10 dark:from-slate-900 dark:via-blue-950 dark:to-indigo-950"
            >
                <!-- Subtle decorative layers -->
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-400 via-amber-300 to-sky-400"
                ></div>
                <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-sky-400/10 blur-3xl"></div>
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-indigo-400/10 blur-3xl"
                ></div>

                <div class="relative flex flex-col gap-6 p-5 sm:p-7">
                    <!-- Top bar: back + actions -->
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <Link
                            :href="route('programs.index')"
                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-blue-100 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                        >
                            <ArrowLeft class="h-4 w-4" /> Back to Programs
                        </Link>
                        <div class="flex items-center gap-2">
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="border-white/25 bg-white/10 text-white shadow-none hover:bg-white/20 hover:text-white"
                            >
                                <Link :href="route('programs.edit', program.id)"> <Pencil class="h-4 w-4" /> Edit </Link>
                            </Button>
                            <Button variant="destructive" size="sm" class="shadow-none" @click="deleteProgram">
                                <Trash2 class="h-4 w-4" /> Delete
                            </Button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
                        <!-- Identity -->
                        <div class="flex min-w-0 flex-col gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border border-amber-300/40 bg-amber-400/15 px-2.5 py-1 font-mono text-xs font-semibold tracking-wide text-amber-200"
                                >
                                    <Hash class="h-3.5 w-3.5" /> {{ program.program_code }}
                                </span>
                                <span v-if="createdOn" class="inline-flex items-center gap-1.5 text-xs text-blue-200/80">
                                    <CalendarDays class="h-3.5 w-3.5" /> Created {{ createdOn }}
                                </span>
                            </div>

                            <h1 class="break-words text-2xl font-bold leading-tight tracking-tight sm:text-3xl">
                                {{ program.title }}
                            </h1>

                            <div v-if="heroBadges.length" class="flex flex-wrap gap-2">
                                <span
                                    v-for="badge in heroBadges"
                                    :key="badge"
                                    class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-blue-50"
                                >
                                    {{ badge }}
                                </span>
                            </div>

                            <p class="line-clamp-3 max-w-3xl text-sm leading-relaxed text-blue-100/90">
                                {{ program.description || 'No description provided.' }}
                            </p>
                        </div>

                        <!-- Cover -->
                        <div
                            class="rounded-2xl bg-white/95 p-1.5 shadow-2xl ring-1 ring-white/20 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] dark:bg-slate-900"
                        >
                            <CoverPagePanel :program-id="program.id" :cover-page="program.cover_page" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===================== TABS ===================== -->
            <Tabs v-model="activeTab" class="flex min-w-0 flex-1 flex-col gap-6">
                <div ref="tabsSection" class="scroll-mt-4" :class="cardClass">
                    <div class="overflow-x-auto [scrollbar-width:thin]">
                        <TabsList class="flex h-auto w-max min-w-full justify-start gap-1 rounded-none bg-transparent px-2 py-0">
                            <TabsTrigger
                                v-for="tab in tabs"
                                :key="tab.value"
                                :value="tab.value"
                                class="relative shrink-0 rounded-none border-b-2 border-transparent px-3 py-3.5 text-sm font-medium text-slate-500 transition-colors hover:text-slate-900 data-[state=active]:border-blue-600 data-[state=active]:bg-transparent data-[state=active]:text-blue-700 data-[state=active]:shadow-none dark:text-slate-400 dark:hover:text-slate-100 dark:data-[state=active]:border-sky-400 dark:data-[state=active]:text-sky-300"
                            >
                                <span class="flex items-center gap-2">
                                    <component :is="tab.icon" class="h-4 w-4" />
                                    {{ tab.label }}
                                    <span
                                        v-if="tab.count"
                                        class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ tab.count }}
                                    </span>
                                </span>
                            </TabsTrigger>
                        </TabsList>
                    </div>
                </div>

                <!-- ===================== DETAILS TAB ===================== -->
                <TabsContent value="details" class="mt-0 flex flex-col gap-6">
                    <!-- Summary metrics -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                        <button
                            v-for="metric in metrics"
                            :key="metric.label"
                            type="button"
                            class="group flex items-center gap-3 p-3.5 text-left transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:hover:border-blue-500/50"
                            :class="cardClass"
                            :aria-label="`${metric.value} ${metric.label} — view`"
                            @click="metric.action"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="metric.tone">
                                <component :is="metric.icon" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xl font-bold leading-none text-slate-900 dark:text-white">{{ metric.value }}</span>
                                <span class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400">{{ metric.label }}</span>
                            </span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,340px)]">
                        <!-- MAIN: Program information -->
                        <div class="flex min-w-0 flex-col gap-4">
                            <!-- Program Identity -->
                            <section :class="cardClass" class="p-5">
                                <header class="mb-4 flex items-center gap-2">
                                    <span
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                                    >
                                        <IdCard class="h-4 w-4" />
                                    </span>
                                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Program Identity</h2>
                                </header>
                                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,160px)_minmax(0,1fr)]">
                                    <div>
                                        <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Program Code
                                        </dt>
                                        <dd class="mt-1 font-mono text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ program.program_code }}
                                        </dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Title</dt>
                                        <dd class="mt-1 break-words text-sm font-semibold text-slate-900 dark:text-white">{{ program.title }}</dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Description
                                        </dt>
                                        <dd class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                                            {{ program.description || 'No description provided.' }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <!-- Delivery -->
                            <section :class="cardClass" class="p-5">
                                <header class="mb-4 flex items-center gap-2">
                                    <span
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
                                    >
                                        <Truck class="h-4 w-4" />
                                    </span>
                                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Delivery</h2>
                                </header>
                                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div
                                        v-for="field in deliveryFields"
                                        :key="field.label"
                                        class="min-w-0 rounded-lg border border-slate-100 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-800/40"
                                    >
                                        <dt class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            {{ field.label }}
                                        </dt>
                                        <dd class="mt-1.5 flex items-start gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                                            <component :is="field.icon" class="mt-0.5 h-4 w-4 shrink-0" :class="field.iconClass" />
                                            <span class="min-w-0 break-words">{{ field.value }}</span>
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Financial -->
                                <section :class="cardClass" class="p-5">
                                    <header class="mb-4 flex items-center gap-2">
                                        <span
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                                        >
                                            <Wallet class="h-4 w-4" />
                                        </span>
                                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Financial</h2>
                                    </header>
                                    <dl class="flex flex-col divide-y divide-slate-100 dark:divide-slate-800">
                                        <div
                                            v-for="field in financialFields"
                                            :key="field.label"
                                            class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <dt class="flex shrink-0 items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                                                <component :is="field.icon" class="h-4 w-4" :class="field.iconClass" /> {{ field.label }}
                                            </dt>
                                            <dd class="min-w-0 break-words text-right text-sm font-semibold text-slate-900 dark:text-white">
                                                {{ field.value }}
                                            </dd>
                                        </div>
                                    </dl>
                                </section>

                                <!-- Organization -->
                                <section :class="cardClass" class="p-5">
                                    <header class="mb-4 flex items-center gap-2">
                                        <span
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                        >
                                            <Building2 class="h-4 w-4" />
                                        </span>
                                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Organization</h2>
                                    </header>
                                    <dl class="flex flex-col divide-y divide-slate-100 dark:divide-slate-800">
                                        <div
                                            v-for="field in organizationFields"
                                            :key="field.label"
                                            class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <dt class="flex shrink-0 items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                                                <component :is="field.icon" class="h-4 w-4" :class="field.iconClass" /> {{ field.label }}
                                            </dt>
                                            <dd class="min-w-0 break-words text-right text-sm font-semibold text-slate-900 dark:text-white">
                                                {{ field.value }}
                                            </dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>
                        </div>

                        <!-- SIDEBAR: Quick actions + evaluation -->
                        <aside class="flex min-w-0 flex-col gap-4">
                            <!-- Quick Actions -->
                            <section :class="cardClass" class="p-5">
                                <header class="mb-4 flex items-center gap-2">
                                    <Zap class="h-4 w-4 text-amber-500" />
                                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Quick Actions</h2>
                                </header>
                                <div class="flex flex-col gap-2">
                                    <Button
                                        class="w-full justify-start bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500"
                                        @click="showCompetencyModal = true"
                                    >
                                        <Plus class="h-4 w-4" /> Add Competency
                                    </Button>
                                    <button
                                        v-for="action in quickActions"
                                        :key="action.tab"
                                        type="button"
                                        class="group flex w-full items-center gap-2.5 rounded-md border border-slate-200 px-3 py-2 text-left text-sm font-medium text-slate-700 transition-colors hover:border-blue-300 hover:bg-blue-50/60 hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:text-slate-200 dark:hover:border-blue-500/50 dark:hover:bg-blue-500/10 dark:hover:text-sky-300"
                                        @click="goToTab(action.tab)"
                                    >
                                        <component
                                            :is="action.icon"
                                            class="h-4 w-4 shrink-0 text-slate-400 group-hover:text-blue-600 dark:group-hover:text-sky-300"
                                        />
                                        <span class="min-w-0 flex-1 truncate">{{ action.label }}</span>
                                        <ChevronRight
                                            class="h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 dark:text-slate-600"
                                        />
                                    </button>
                                </div>
                            </section>

                            <!-- Evaluation Results -->
                            <section
                                class="relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-700 via-blue-800 to-indigo-900 p-5 text-white shadow-lg ring-1 ring-blue-900/10 transition-shadow hover:shadow-xl dark:from-blue-900 dark:via-indigo-950 dark:to-slate-950 dark:ring-white/10"
                            >
                                <div
                                    aria-hidden="true"
                                    class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-sky-400/15 blur-2xl"
                                ></div>
                                <div class="relative flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                                        <ClipboardCheck class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-semibold">Evaluation Results</h2>
                                        <p class="mt-1 text-sm leading-relaxed text-blue-100">
                                            View aggregated participant feedback and program performance insights.
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    size="sm"
                                    class="relative mt-5 w-full border border-white/25 bg-white/15 text-white hover:bg-white/25"
                                    @click="showEvaluationDashboard = true"
                                >
                                    View Evaluation Dashboard <ArrowRight class="h-4 w-4" />
                                </Button>
                            </section>
                        </aside>
                    </div>

                    <!-- Competencies -->
                    <section ref="competenciesSection" class="scroll-mt-4 p-5" :class="cardClass">
                        <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"
                                >
                                    <Lightbulb class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Competencies</h2>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Grouped by competency domain</p>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                class="bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500"
                                @click="showCompetencyModal = true"
                            >
                                <Plus class="h-4 w-4" /> Add Competency
                            </Button>
                        </header>

                        <!-- Grouped list -->
                        <div v-if="groupedCompetencies.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="group in groupedCompetencies"
                                :key="group.domain"
                                class="min-w-0 overflow-hidden rounded-lg border border-slate-200/80 dark:border-slate-800"
                            >
                                <div
                                    class="flex items-center justify-between gap-2 border-b px-3 py-2"
                                    :class="[DOMAIN_ACCENTS[group.domain]?.rule, DOMAIN_ACCENTS[group.domain]?.header]"
                                >
                                    <p
                                        class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider"
                                        :class="DOMAIN_COLORS[group.domain]"
                                    >
                                        <span class="h-2 w-2 rounded-full" :class="DOMAIN_ACCENTS[group.domain]?.dot"></span>
                                        {{ group.domain }}
                                    </p>
                                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ group.items.length }}</span>
                                </div>
                                <ul class="flex flex-col divide-y divide-slate-100 dark:divide-slate-800">
                                    <li
                                        v-for="c in group.items"
                                        :key="c.id"
                                        class="group flex items-start justify-between gap-2 px-3 py-2 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                    >
                                        <p class="min-w-0 break-words text-sm leading-snug text-slate-700 dark:text-slate-200">{{ c.competency }}</p>
                                        <button
                                            type="button"
                                            class="mt-0.5 shrink-0 rounded text-slate-400 transition-all hover:text-red-500 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 sm:opacity-0 sm:group-hover:opacity-100"
                                            :aria-label="`Remove ${c.competency}`"
                                            :title="`Remove ${c.competency}`"
                                            @click="removeCompetency(c)"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 py-10 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400"
                        >
                            <Lightbulb class="mb-2 h-6 w-6 text-slate-300 dark:text-slate-600" />
                            <p class="text-sm font-semibold">No competencies yet.</p>
                            <p class="mt-1 text-xs">Click "Add Competency" to attach competencies to this program.</p>
                        </div>
                    </section>

                    <!-- Competency picker modal -->
                    <CompetencyModal
                        :open="showCompetencyModal"
                        :program-id="program.id"
                        :existing="existingCompetencyNames"
                        @update:open="showCompetencyModal = $event"
                    />
                </TabsContent>

                <!-- Participants Tab -->
                <TabsContent value="participants" class="mt-0 flex min-w-0 flex-col gap-4">
                    <!-- BATCH SECTION: list ng batches + Add Batch modal -->
                    <BatchList :program="program" :batches="program.batches ?? []" />
                </TabsContent>

                <!-- Submissions Tab -->
                <TabsContent value="submissions" class="mt-0 flex min-w-0 flex-col gap-4">
                    <SubmissionList :program="program" :submissions="submissions" />
                </TabsContent>

                <!-- Certificates Tab -->
                <TabsContent value="certificates" class="mt-0 flex min-w-0 flex-col gap-4">
                    <CertificatesPanel :program="program" />
                </TabsContent>

                <!-- Requirements Tab -->
                <TabsContent value="requirements" class="mt-0 flex min-w-0 flex-col gap-4">
                    <RequirementList :program="program" />
                </TabsContent>

                <!-- Supporting Documents Tab -->
                <TabsContent value="Supporting" class="mt-0 flex min-w-0 flex-col gap-4">
                    <SupportingDocuments :program="program" />
                </TabsContent>

                <!-- Resource Speakers Tab -->
                <TabsContent value="resource" class="mt-0 flex min-w-0 flex-col gap-4">
                    <ResourceSpeakers :program="program" />
                </TabsContent>

                <TabsContent value="tesda-order" class="mt-0 flex min-w-0 flex-col gap-4">
                    <TesdaOrderPanel :program="program" />
                </TabsContent>
            </Tabs>
        </div>

        <!-- Evaluation Results modal -->
        <Transition name="backdrop" appear>
            <div
                v-if="showEvaluationDashboard"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
                @click.self="showEvaluationDashboard = false"
            >
                <div
                    class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-background shadow-2xl ring-1 ring-black/5 dark:ring-white/10"
                >
                    <div
                        class="sticky top-0 z-10 flex items-center gap-3 rounded-t-2xl border-b bg-gradient-to-r from-blue-800 via-blue-700 to-indigo-800 px-6 py-4 text-white"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20 shadow backdrop-blur">
                            <ClipboardCheck class="h-4 w-4 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold leading-none">Evaluation Results</h2>
                            <p class="mt-0.5 truncate text-xs text-white/75">Aggregated feedback for {{ program.title }}</p>
                        </div>
                        <button
                            type="button"
                            class="ml-auto shrink-0 rounded text-white/80 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                            aria-label="Close evaluation results"
                            @click="showEvaluationDashboard = false"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="p-4 sm:p-6">
                        <EvaluationDashboard :program="program" />
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
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
</style>
