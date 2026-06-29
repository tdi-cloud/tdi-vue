<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import {
    Plus, LoaderCircle, BadgePlus, Save, Search, CircleHelp, Info, FileText,
    Heading, AlignLeft, MonitorSmartphone, Users, FolderTree, Layers3,
    Building2, UserCog, PhilippinePeso, Wallet, Globe, Sparkles,
} from 'lucide-vue-next';
import { ref, watch, onMounted, onUnmounted, nextTick, computed } from 'vue';
import ProgramList from '@/pages/programs/ProgramList.vue';
import GenerateTPMRModal from '@/pages/programs/GenerateTPMRModal.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Programs',
        href: '/programs',
    },
];

const showTPMR = ref(false);

interface ProgramListItem {
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

const INITIATED_OPTIONS = [
    { value: 'TDI', label: 'TESDA Development Institute (TDI)' },
    { value: 'NTTA', label: 'National TVET Trainors Academy (NTTA)' },
    { value: 'Other Executive Office', label: 'Other Executive Office' },
    { value: 'Other Training Provider', label: 'Other Training Provider' },
];

const BATCH_STATUS_OPTIONS = ['Active', 'Completed', 'Upcoming', 'Rescheduled'];
const search = ref('');
const filterInitiated = ref('all');
const filterBatchStatus = ref('all');
const filterMonth = ref('all');
const showModal = ref(false);
const showConfirm = ref(false);
const showInfo = ref(false);

/*
 * Measure exactly how much vertical space this page has.
 * Pass 1: viewport height minus where the wrapper starts.
 * Pass 2: if the document STILL overflows (layout margins/padding
 * below the content we can't see from here), shrink by exactly
 * that amount. Result: no page scrollbar, ever.
 */
const pageRef = ref<HTMLElement | null>(null);
const pageHeight = ref('auto');

const updateHeight = async () => {
    if (!pageRef.value) return;

    const doc = document.documentElement;
    const top = pageRef.value.getBoundingClientRect().top + (window.scrollY || 0);

    // Pass 1: fill the space from our top edge to the bottom of the viewport
    let h = doc.clientHeight - top;
    pageHeight.value = `${h}px`;

    // Pass 2: after the DOM updates, remove any leftover overflow
    await nextTick();
    const overflow = doc.scrollHeight - doc.clientHeight;
    if (overflow > 0) {
        h -= overflow;
        pageHeight.value = `${Math.max(h, 200)}px`;
    }
};

onMounted(() => {
    updateHeight();
    window.addEventListener('resize', updateHeight);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateHeight);
});

const form = useForm({
    title: '',
    description: '',
    modality: '',
    pax: '',
    category: '',
    type: '',
    initiated: '',
    provider: '',
    cost: '0',
    fund: '',
    origin: '',
});

watch(() => form.initiated, (val) => {
    if (val === 'TDI') {
        form.provider = 'TESDA Development Institute (TDI)';
    } else if (val === 'NTTA') {
        form.provider = 'National TVET Trainors Academy (NTTA)';
    } else {
        form.provider = '';
    }
});

const openConfirmation = () => {
    showConfirm.value = true;
};

const handleConfirmYes = () => {
    showConfirm.value = false;
    showInfo.value = true;
};

const handleConfirmNo = () => {
    showConfirm.value = false;
    showModal.value = true;
};

const submit = () => {
    form.post(route('programs.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const props = defineProps<{
    programs: ProgramListItem[];
}>();

// ✅ Lahat ng unique na "YYYY-MM" sa lahat ng programs, pinagsama-sama at pinaghanda
// para sa Month filter dropdown — sorted descending (pinakabago muna)
const availableMonths = computed(() => {
    const set = new Set<string>();
    props.programs.forEach((p) => p.months.forEach((m) => set.add(m)));
    return Array.from(set).sort().reverse();
});

const monthLabel = (ym: string) => {
    const [year, month] = ym.split('-');
    const date = new Date(Number(year), Number(month) - 1, 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};
</script>

<template>
    <Head title="Programs"/>

    <AppLayout >

     
        <div
            ref="pageRef"
            :style="{ height: pageHeight }"
            class="flex flex-col gap-4 p-4 w-full max-w-full overflow-hidden"
        >

            <div class="shrink-0 flex flex-wrap w-full items-center justify-between">
                <div class="">
                    <h1 class="text-lg font-extrabold leading-5">Training Programs</h1>
                    <p class="text-sm font-semibold text-slate-400">Manage all training activities and schedules</p>
                </div>

                <div class="flex gap-4">
                    <Button variant="outline" @click="showTPMR = true">
                        <FileText /> Generate TPMR
                    </Button>

                    <Button @click="openConfirmation" class="bg-blue-600 font-extrabold rounded-lg hover:bg-blue-500 dark:text-white self-end">
                        <Plus /> Create Program
                    </Button>

                    <GenerateTPMRModal v-model="showTPMR" />
                </div>


            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 items-end gap-2">

                <!-- Search (spans 2 columns) -->
                <div class="grid gap-1 md:col-span-2">
                    <Label class="text-[11px] font-semibold text-slate-400">Search</Label>
                    <div class="relative">
                        <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                        <Input
                            v-model="search"
                            class="text-xs h-8 w-full pl-7 outline-none shadow-md"
                            placeholder="Search programs..."
                        />
                    </div>
                </div>

                <!-- Office Initiated filter -->
                <div class="grid gap-1">
                    <Label class="text-[11px] font-semibold text-slate-400">Office Initiated</Label>
                    <Select v-model="filterInitiated">
                        <SelectTrigger class="text-xs h-8 w-full  shadow-md">
                            <SelectValue placeholder="All offices" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="all">All offices</SelectItem>
                            <SelectItem
                                v-for="opt in INITIATED_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                                class="text-xs"
                            >
                                {{ opt.value }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Batch Status filter -->
                <div class="grid gap-1">
                    <Label class="text-[11px] font-semibold text-slate-400">Batch Status</Label>
                    <Select v-model="filterBatchStatus">
                        <SelectTrigger class="text-xs h-8 w-full  shadow-md">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="all">All statuses</SelectItem>
                            <SelectItem
                                v-for="st in BATCH_STATUS_OPTIONS"
                                :key="st"
                                :value="st"
                                class="text-xs"
                            >
                                {{ st }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Month filter -->
                <div class="grid gap-1">
                    <Label class="text-[11px] font-semibold text-slate-400">Month</Label>
                    <Select v-model="filterMonth">
                        <SelectTrigger class="text-xs h-8 w-full shadow-md">
                            <SelectValue placeholder="All months" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="all">All months</SelectItem>
                            <SelectItem
                                v-for="m in availableMonths"
                                :key="m"
                                :value="m"
                                class="text-xs"
                            >
                                {{ monthLabel(m) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

            </div>

            <!-- Program List: fills the rest; only its inner list scrolls -->
            <ProgramList
                :programs="programs"
                :search="search"
                :filter-initiated="filterInitiated"
                :filter-batch-status="filterBatchStatus"
                :filter-month="filterMonth"
            />

            <!-- Confirmation Dialog -->
            <Dialog :open="showConfirm" @update:open="showConfirm = $event">
                <DialogContent class="max-w-md !rounded-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            <span class="flex gap-2 items-center">
                                <CircleHelp class="h-5 w-5 text-blue-600" /> Confirmation
                            </span>
                        </DialogTitle>
                        <DialogDescription class="text-sm pt-2">
                            Does the program you want to add already have a TESDA Order?
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" size="sm" @click="handleConfirmNo">No</Button>
                        <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" @click="handleConfirmYes">Yes</Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Information Dialog -->
            <Dialog :open="showInfo" @update:open="showInfo = $event">
                <DialogContent class="max-w-md !rounded-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            <span class="flex gap-2 items-center">
                                <Info class="h-5 w-5 text-blue-600" /> Information
                            </span>
                        </DialogTitle>
                        <DialogDescription class="text-sm pt-2 space-y-2">
                            <span class="block">
                                Programs with TESDA Orders are managed by the Central Office; therefore, you do not need to add this program.
                            </span>
                            <span class="block">
                                Only programs that are regionally initiated may be added to the system by the HRMOs of the respective region.
                            </span>
                            <span class="block">Thank you.</span>
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex justify-end pt-2">
                        <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" @click="showInfo = false">Okay</Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Create Program Modal -->
<Dialog :open="showModal" @update:open="showModal = $event">
    <DialogContent class="max-w-2xl flex flex-col max-h-[92vh] overflow-hidden border-0 p-0 !rounded-2xl shadow-2xl">

        <!-- Gradient header band with illustration -->
        <DialogHeader class="relative shrink-0 overflow-hidden bg-gradient-to-br from-blue-600 via-blue-500 to-indigo-500 px-6 pb-6 pt-6 text-left">
            <!-- dotted texture -->
            <div class="pointer-events-none absolute inset-0 opacity-20"
                 style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 18px 18px;"></div>
            <!-- floating glow circles -->
            <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute right-16 bottom-2 h-16 w-16 rounded-full bg-white/10 blur-xl"></div>

            <div class="relative z-10 flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                    <BadgePlus class="h-6 w-6 text-white" />
                </div>
                <div class="min-w-0">
                    <DialogTitle class="flex items-center gap-2 text-lg font-bold text-white">
                        Create New Program
                        <Sparkles class="h-4 w-4 text-blue-100" />
                    </DialogTitle>
                    <DialogDescription class="text-xs text-blue-100">
                        Fill in the details below to launch a new training program.
                    </DialogDescription>
                </div>
            </div>
        </DialogHeader>

        <!-- Scrollable Form -->
        <div class="overflow-y-auto flex-1 px-6">
            <form id="program-form" @submit.prevent="submit" class="space-y-5 py-4">

                <!-- SECTION: Program Details -->
                <div class="space-y-3">
                    <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-blue-600">
                        <Heading class="h-3.5 w-3.5" /> Program Details
                    </p>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Title -->
                        <div class="col-span-2 grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><Heading class="h-3.5 w-3.5 text-slate-400" /> Title <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.title" placeholder="Program title" />
                            <p class="text-xs text-red-500">{{ form.errors.title }}</p>
                        </div>

                        <!-- Description -->
                        <div class="col-span-2 grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><AlignLeft class="h-3.5 w-3.5 text-slate-400" /> Description</Label>
                            <Textarea class="text-xs min-h-[90px] resize-y" v-model="form.description" placeholder="Enter program description..." />
                            <p class="text-xs text-red-500">{{ form.errors.description }}</p>
                        </div>

                        <!-- Modality -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><MonitorSmartphone class="h-3.5 w-3.5 text-slate-400" /> Modality <span class="text-red-500">*</span></Label>
                            <Select v-model="form.modality">
                                <SelectTrigger class="text-xs h-8"><SelectValue placeholder="Select modality" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="In-person">In-person</SelectItem>
                                    <SelectItem class="text-xs" value="Online/Virtual">Online/Virtual</SelectItem>
                                    <SelectItem class="text-xs" value="Hybrid">Hybrid</SelectItem>
                                    <SelectItem class="text-xs" value="Self-Paced">Self-Paced</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.modality }}</p>
                        </div>

                        <!-- Pax -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><Users class="h-3.5 w-3.5 text-slate-400" /> Target Pax <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.pax" placeholder="Number of participants" />
                            <p class="text-xs text-red-500">{{ form.errors.pax }}</p>
                        </div>

                        <!-- Category -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><FolderTree class="h-3.5 w-3.5 text-slate-400" /> Category <span class="text-red-500">*</span></Label>
                            <Select v-model="form.category">
                                <SelectTrigger class="text-xs h-8"><SelectValue placeholder="Select category" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="Benchmarking">Benchmarking</SelectItem>
                                    <SelectItem class="text-xs" value="Capability Building">Capability Building</SelectItem>
                                    <SelectItem class="text-xs" value="Executive-Office">Executive-Office</SelectItem>
                                    <SelectItem class="text-xs" value="Foreign-Bilateral">Foreign-Bilateral</SelectItem>
                                    <SelectItem class="text-xs" value="Foreign-FSTP">Foreign-FSTP</SelectItem>
                                    <SelectItem class="text-xs" value="Local-In-House">Local-In-House</SelectItem>
                                    <SelectItem class="text-xs" value="Local-Public">Local-Public</SelectItem>
                                    <SelectItem class="text-xs" value="Other-Foreign">Other Foreign Program</SelectItem>
                                    <SelectItem class="text-xs" value="Regional">Regional</SelectItem>
                                    <SelectItem class="text-xs" value="Team-Building">Team-Building</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.category }}</p>
                        </div>

                        <!-- Program Type -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><Layers3 class="h-3.5 w-3.5 text-slate-400" /> Program Type <span class="text-red-500">*</span></Label>
                            <Select v-model="form.type">
                                <SelectTrigger class="text-xs h-8"><SelectValue placeholder="Select type" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="ADMIN">ADMIN</SelectItem>
                                    <SelectItem class="text-xs" value="TECHNICAL">TECHNICAL</SelectItem>
                                    <SelectItem class="text-xs" value="SUPERVISORY/MANAGERIAL">SUPERVISORY/MANAGERIAL</SelectItem>
                                    <SelectItem class="text-xs" value="TEAM-BUILDING">TEAM-BUILDING</SelectItem>
                                    <SelectItem class="text-xs" value="OTHER">OTHER</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.type }}</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION: Office & Provider -->
                <div class="space-y-3 border-t pt-4">
                    <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-blue-600">
                        <Building2 class="h-3.5 w-3.5" /> Office &amp; Provider
                    </p>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Office Initiated -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><Building2 class="h-3.5 w-3.5 text-slate-400" /> Office Initiated <span class="text-red-500">*</span></Label>
                            <Select v-model="form.initiated">
                                <SelectTrigger class="text-xs h-8"><SelectValue placeholder="Select office" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="TDI">TESDA Development Institute (TDI)</SelectItem>
                                    <SelectItem class="text-xs" value="NTTA">National TVET Trainors Academy (NTTA)</SelectItem>
                                    <SelectItem class="text-xs" value="Other Executive Office">Other Executive Office</SelectItem>
                                    <SelectItem class="text-xs" value="Other Training Provider">Other Training Provider</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.initiated }}</p>
                        </div>

                        <!-- Provider -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><UserCog class="h-3.5 w-3.5 text-slate-400" /> Provider</Label>
                            <Input class="text-xs h-8" v-model="form.provider" placeholder="Training provider" />
                            <p class="text-xs text-red-500">{{ form.errors.provider }}</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION: Funding & Origin -->
                <div class="space-y-3 border-t pt-4">
                    <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-blue-600">
                        <Wallet class="h-3.5 w-3.5" /> Funding &amp; Origin
                    </p>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Cost -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><PhilippinePeso class="h-3.5 w-3.5 text-slate-400" /> Cost <span class="text-red-500">*</span></Label>
                            <div class="relative">
                                <PhilippinePeso class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input class="text-xs h-8 pl-7" v-model="form.cost" placeholder="e.g. 5000" />
                            </div>
                            <p class="text-xs text-red-500">{{ form.errors.cost }}</p>
                        </div>

                        <!-- Fund Source -->
                        <div class="grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><Wallet class="h-3.5 w-3.5 text-slate-400" /> Fund Source <span class="text-red-500">*</span></Label>
                            <Select v-model="form.fund">
                                <SelectTrigger class="text-xs h-8"><SelectValue placeholder="Select fund source" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="Central Office - SDP">Central Office - SDP</SelectItem>
                                    <SelectItem class="text-xs" value="Regional Office - SDP">Regional Office - SDP</SelectItem>
                                    <SelectItem class="text-xs" value="Other Office">Other Office</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.fund }}</p>
                        </div>

                        <!-- Origin -->
                        <div class="col-span-2 grid gap-1">
                            <Label class="flex items-center gap-1.5 text-xs"><Globe class="h-3.5 w-3.5 text-slate-400" /> Origin <span class="text-red-500">*</span></Label>
                            <Select v-model="form.origin">
                                <SelectTrigger class="text-xs h-8"><SelectValue placeholder="Select origin" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="Local">Local</SelectItem>
                                    <SelectItem class="text-xs" value="Foreign">Foreign</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.origin }}</p>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <!-- Fixed Footer -->
        <div class="shrink-0 flex items-center justify-between gap-2 border-t bg-muted/30 px-6 py-3">
            <p class="flex items-center gap-1 text-[11px] text-muted-foreground">
                <Info class="h-3 w-3" /> Fields marked <span class="text-red-500">*</span> are required.
            </p>
            <div class="flex gap-2">
                <Button type="button" variant="outline" size="sm" @click="showModal = false">Cancel</Button>
                <Button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-md shadow-blue-500/25 hover:from-blue-700 hover:to-blue-600" form="program-form" size="sm" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-3 w-3 animate-spin mr-1" />
                    <Save class="h-3.5 w-3.5" /> Save Program
                </Button>
            </div>
        </div>

    </DialogContent>
</Dialog>

        </div>

    </AppLayout>
</template>