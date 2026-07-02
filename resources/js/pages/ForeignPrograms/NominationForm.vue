<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router, usePage, Head } from '@inertiajs/vue3';
import { Upload, ExternalLink, ChevronDown, AlertCircle, Loader2, Check, ArrowLeft, ArrowRight } from 'lucide-vue-next';

interface Program {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    modality: string;
}

interface Requirement {
    id: number;
    question: string;
    description: string | null;
    link: string | null;
    file_required: boolean;
    sort_order: number;
}

interface Course {
    title: string;
    url: string;
}

interface Config {
    id: number;
    organizing_sponsor: string;
    slug: string;
    form_title: string;
    available_courses: Course[] | null;
    accomplished_form_note: string | null;
    requirements: Requirement[];
}

const props = defineProps<{
    config: Config;
    programs: Program[];
}>();

// ── Force light mode for public form ──────────────────────────────────────────
onMounted(() => {
    document.documentElement.classList.remove('dark');
});

// ── Form fields ───────────────────────────────────────────────────────────────

const fields = ref({
    foreign_program_id: '' as string | number,
    firstname:          '',
    middle_name:        '',
    surname:            '',
    sex:                '',
    age:                '' as string | number,
    position:           '',
    agency:             '',
    contact_number:     '',
    email:              '',
});

const accomplishedFile     = ref<File | null>(null);
const accomplishedFileName = ref('');
const requirementFiles     = ref<Record<number, File | null>>({});
const requirementFileNames = ref<Record<number, string>>({});
const processing           = ref(false);

// ── Errors ────────────────────────────────────────────────────────────────────
// Pinagsasama ang server errors (mula Inertia) at client-side per-step errors.

const page        = usePage();
const serverErrors = computed(() => (page.props.errors as Record<string, string>) ?? {});
const stepErrors   = ref<Record<string, string>>({});

function fieldError(key: string): string | undefined {
    return stepErrors.value[key] ?? serverErrors.value[key];
}

// ── Steps (dynamic — conditional sections excluded kung wala) ──────────────────

interface Step { key: string; label: string; }

const steps = computed<Step[]>(() => {
    const s: Step[] = [
        { key: 'program', label: 'Program' },
        { key: 'profile', label: 'Profile' },
    ];
    if (props.config.requirements.length > 0) {
        s.push({ key: 'requirements', label: 'Requirements' });
    }
    if (props.config.available_courses && props.config.available_courses.length > 0) {
        s.push({ key: 'courses', label: 'Courses' });
    }
    s.push({ key: 'accomplished', label: 'Application Form' });
    return s;
});

const currentStep = ref(0);
const currentKey   = computed(() => steps.value[currentStep.value]?.key);
const isLastStep   = computed(() => currentStep.value === steps.value.length - 1);
const progressPct  = computed(() => ((currentStep.value + 1) / steps.value.length) * 100);

// ── Per-step validation ───────────────────────────────────────────────────────

function validateCurrentStep(): boolean {
    stepErrors.value = {};
    const key = currentKey.value;

    if (key === 'program') {
        if (!fields.value.foreign_program_id) {
            stepErrors.value.foreign_program_id = 'Please select a program.';
        }
    } else if (key === 'profile') {
        if (!String(fields.value.firstname).trim()) stepErrors.value.firstname = 'First name is required.';
        if (!String(fields.value.surname).trim())   stepErrors.value.surname   = 'Surname is required.';
        if (!fields.value.sex)                       stepErrors.value.sex       = 'Please select.';
        if (!fields.value.age) {
            stepErrors.value.age = 'Age is required.';
        } else if (Number(fields.value.age) < 18 || Number(fields.value.age) > 100) {
            stepErrors.value.age = 'Age must be between 18 and 100.';
        }
        if (!String(fields.value.position).trim()) stepErrors.value.position = 'Position is required.';
        if (!String(fields.value.agency).trim())   stepErrors.value.agency   = 'Agency is required.';
        if (!String(fields.value.contact_number).trim()) {
            stepErrors.value.contact_number = 'Contact number is required.';
        }
        if (!String(fields.value.email).trim()) {
            stepErrors.value.email = 'Email is required.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.value.email)) {
            stepErrors.value.email = 'Please enter a valid email address.';
        }
    } else if (key === 'requirements') {
        for (const req of props.config.requirements) {
            if (req.file_required && !requirementFiles.value[req.id]) {
                stepErrors.value[`requirement_${req.id}`] = 'This file is required.';
            }
        }
    }
    // 'courses' at 'accomplished' — walang required na validation

    return Object.keys(stepErrors.value).length === 0;
}

function next() {
    if (!validateCurrentStep()) return;
    if (currentStep.value < steps.value.length - 1) currentStep.value++;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function back() {
    stepErrors.value = {};
    if (currentStep.value > 0) currentStep.value--;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── File handlers ─────────────────────────────────────────────────────────────

function handleRequirementFile(reqId: number, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (file) {
        requirementFiles.value[reqId]     = file;
        requirementFileNames.value[reqId] = file.name;
        delete stepErrors.value[`requirement_${reqId}`];
    }
}

function handleAccomplishedFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (file) {
        accomplishedFile.value     = file;
        accomplishedFileName.value = file.name;
    }
}

// ── Submit via FormData ───────────────────────────────────────────────────────

function submit() {
    // Kung hindi pa huling step, Enter key = Next imbes na submit.
    if (!isLastStep.value) { next(); return; }
    if (!validateCurrentStep()) return;

    const data = new FormData();

    data.append('foreign_program_id', String(fields.value.foreign_program_id));
    data.append('firstname',          fields.value.firstname);
    data.append('middle_name',        fields.value.middle_name ?? '');
    data.append('surname',            fields.value.surname);
    data.append('sex',                fields.value.sex);
    data.append('age',                String(fields.value.age));
    data.append('position',           fields.value.position);
    data.append('agency',             fields.value.agency);
    data.append('contact_number',     fields.value.contact_number ?? '');
    data.append('email',              fields.value.email ?? '');

    if (accomplishedFile.value) {
        data.append('accomplished_form', accomplishedFile.value);
    }

    for (const [reqId, file] of Object.entries(requirementFiles.value)) {
        if (file) {
            data.append(`requirement_${reqId}`, file);
        }
    }

    processing.value = true;
    router.post(route('nominate.submit', props.config.slug), data, {
        onFinish: () => { processing.value = false; },
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const selectedProgram = computed(() =>
    props.programs.find(p => p.id === Number(fields.value.foreign_program_id)) ?? null
);

function formatDate(d: string) {
    if (!d) return '—';
    const date = d.includes('T') ? new Date(d) : new Date(d + 'T00:00:00');
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

function modalityLabel(m: string) {
    return { 'in-person': 'In-Person', online: 'Online', hybrid: 'Hybrid' }[m] ?? m;
}

const sponsorLogos: Record<string, string> = {
    'jica':  '/storage/sponsors/jica.png',
    'koica': '/storage/sponsors/koica.png',
    'mtcp':  '/storage/sponsors/mtcp.png',
    'scp':   '/storage/sponsors/scp.png',
    'tica':  '/storage/sponsors/tica.png',
    'itec':  '/storage/sponsors/itec.png',
};

const logoLoadFailed = ref(false);

const sponsorLogo = computed(() => {
    const key = props.config.slug?.toLowerCase() ?? '';
    return sponsorLogos[key] ?? null;
});

function handleSponsorLogoError() {
    logoLoadFailed.value = true;
}


</script>

<template>
    <Head :title="config.form_title" />

    <div class="min-h-screen bg-gray-100 py-8 px-4 [color-scheme:light]">
        <div class="mx-auto max-w-2xl space-y-4">

            <!-- ── Banner ── -->
            <div class="rounded-2xl overflow-hidden shadow-md">
                <div class="bg-blue-700 px-6 pt-6 pb-4">
                    <div class="flex items-center gap-4">
                        <div
                            v-if="sponsorLogo && !logoLoadFailed"
                            class="h-16 w-16 rounded-xl bg-white flex items-center justify-center shrink-0 p-2 shadow-sm"
                        >
                            <img
                                :src="sponsorLogo"
                                :alt="`${config.organizing_sponsor} logo`"
                                class="h-full w-full object-contain"
                                @error="handleSponsorLogoError"
                            />
                        </div>
                        <div>
                            <p class="text-blue-200 text-xs font-semibold uppercase tracking-widest mb-1">
                                {{ config.organizing_sponsor }}
                            </p>
                            <h1 class="text-white text-2xl font-extrabold leading-tight">
                                {{ config.form_title }}
                            </h1>
                        </div>
                    </div>
                </div>
                <div class="bg-white px-6 py-3 border-t-4 border-blue-500 text-xs text-gray-500">
                    All fields marked with <span class="text-red-500 font-bold">*</span> are required.
                    Maximum file size: <strong>10 MB</strong>.
                </div>
            </div>

            <!-- ── Progress (step circles + fill bar) ── -->
            <div class="bg-white rounded-2xl shadow-sm px-5 py-4">
                <div class="flex items-start justify-between mb-3 gap-1">
                    <div
                        v-for="(s, i) in steps"
                        :key="s.key"
                        class="flex flex-col items-center flex-1 min-w-0"
                    >
                        <div
                            class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-all"
                            :class="i < currentStep ? 'bg-blue-600 text-white'
                                : i === currentStep ? 'bg-blue-600 text-white ring-4 ring-blue-100'
                                : 'bg-gray-200 text-gray-500'"
                        >
                            <Check v-if="i < currentStep" class="h-4 w-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="text-[10px] mt-1.5 text-center leading-tight w-full px-0.5"
                            :class="i === currentStep ? 'text-blue-600 font-semibold' : 'text-gray-400'"
                        >
                            {{ s.label }}
                        </span>
                    </div>
                </div>

                <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-blue-600 rounded-full transition-all duration-300"
                        :style="{ width: progressPct + '%' }"
                    ></div>
                </div>
                <p class="text-center text-xs text-gray-500 mt-2">
                    Step {{ currentStep + 1 }} of {{ steps.length }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">

                <!-- ── Step: Program Selection ── -->
                <div v-show="currentKey === 'program'" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Program Selection</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Select the program you wish to apply for.</p>
                    </div>
                    <div class="px-5 py-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            List of Programs <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select
                                v-model="fields.foreign_program_id"
                                class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-4 py-2.5 pr-10 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition"
                            >
                                <option value="">— Select a program —</option>
                                <option v-for="p in programs" :key="p.id" :value="p.id">
                                    {{ p.program_title }}
                                </option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                        </div>
                        <p v-if="fieldError('foreign_program_id')" class="mt-1 text-xs text-red-500">
                            {{ fieldError('foreign_program_id') }}
                        </p>

                        <div v-if="selectedProgram" class="mt-3 rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-xs space-y-1">
                            <p><span class="font-semibold text-gray-600">Dates:</span>
                                {{ formatDate(selectedProgram.program_start) }} — {{ formatDate(selectedProgram.program_end) }}
                            </p>
                            <p><span class="font-semibold text-gray-600">Modality:</span> {{ modalityLabel(selectedProgram.modality) }}</p>
                            <p><span class="font-semibold text-gray-600">Slots:</span> {{ selectedProgram.slots }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Step: Nominee's Profile ── -->
                <div v-show="currentKey === 'profile'" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Nominee's Profile</h2>
                    </div>
                    <div class="px-5 py-5 space-y-4">

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.firstname" type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="fieldError('firstname')" class="mt-1 text-xs text-red-500">{{ fieldError('firstname') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Middle Name</label>
                                <input v-model="fields.middle_name" type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Surname <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.surname" type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="fieldError('surname')" class="mt-1 text-xs text-red-500">{{ fieldError('surname') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Sex <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select v-model="fields.sex"
                                        class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 pr-8 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                        <option value="">— Select —</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <ChevronDown class="absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400 pointer-events-none" />
                                </div>
                                <p v-if="fieldError('sex')" class="mt-1 text-xs text-red-500">{{ fieldError('sex') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Age <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.age" type="number" min="18" max="100"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="fieldError('age')" class="mt-1 text-xs text-red-500">{{ fieldError('age') }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Position / Designation <span class="text-red-500">*</span>
                            </label>
                            <input v-model="fields.position" type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                            <p v-if="fieldError('position')" class="mt-1 text-xs text-red-500">{{ fieldError('position') }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Agency <span class="text-red-500">*</span>
                                <span class="ml-1 font-normal text-gray-400">(Abbreviation only, e.g. TESDA, DOLE)</span>
                            </label>
                            <input v-model="fields.agency" type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                            <p v-if="fieldError('agency')" class="mt-1 text-xs text-red-500">{{ fieldError('agency') }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.contact_number" type="tel"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="fieldError('contact_number')" class="mt-1 text-xs text-red-500">{{ fieldError('contact_number') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.email" type="email"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="fieldError('email')" class="mt-1 text-xs text-red-500">{{ fieldError('email') }}</p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ── Step: Documentary Requirements ── -->
                <div v-show="currentKey === 'requirements'" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Documentary Requirements</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Upload the required documents. Maximum 10MB per file.</p>
                    </div>
                    <div class="px-5 py-5 space-y-5">
                        <div
                            v-for="(req, idx) in config.requirements"
                            :key="req.id"
                            class="border border-gray-100 rounded-xl p-4 bg-gray-50"
                        >
                            <p class="text-sm font-semibold text-gray-800">
                                {{ idx + 1 }}. {{ req.question }}
                                <span v-if="req.file_required" class="text-red-500 ml-0.5">*</span>
                            </p>
                            <p v-if="req.description" class="text-xs text-gray-500 mt-1">{{ req.description }}</p>
                            <a v-if="req.link" :href="req.link" target="_blank"
                                class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline mt-1">
                                <ExternalLink class="h-3 w-3" /> Open Form / Link
                            </a>

                            <div v-if="req.file_required" class="mt-3">
                                <label
                                    :for="`req-file-${req.id}`"
                                    class="flex items-center gap-2 cursor-pointer w-fit rounded-lg border border-dashed border-blue-300 bg-blue-50 px-4 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition"
                                >
                                    <Upload class="h-3.5 w-3.5" />
                                    {{ requirementFileNames[req.id] ?? 'Choose File' }}
                                </label>
                                <input
                                    :id="`req-file-${req.id}`"
                                    type="file"
                                    class="hidden"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                    @change="handleRequirementFile(req.id, $event)"
                                />
                                <p v-if="fieldError(`requirement_${req.id}`)" class="mt-1 text-xs text-red-500">
                                    {{ fieldError(`requirement_${req.id}`) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Step: Available Courses ── -->
                <div v-show="currentKey === 'courses'" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Available Courses</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            View the available courses offered by {{ config.organizing_sponsor }}.
                        </p>
                    </div>
                    <div class="px-5 py-5">
                        <ul class="space-y-2">
                            <li v-for="(course, idx) in (config.available_courses ?? [])" :key="idx">
                                <a :href="course.url" target="_blank"
                                    class="flex items-center gap-2 text-sm text-blue-700 hover:text-blue-900 hover:underline font-medium">
                                    <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                                    {{ course.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ── Step: Accomplished Application Form ── -->
                <div v-show="currentKey === 'accomplished'" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">
                            Accomplished {{ config.organizing_sponsor }} Application Form
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Upload your completed application form (PDF only, max 10MB).
                        </p>
                    </div>
                    <div class="px-5 py-5">
                        <div v-if="config.accomplished_form_note" class="mb-3 flex items-start gap-2 rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-800">
                            <AlertCircle class="h-4 w-4 shrink-0 mt-0.5 text-amber-500" />
                            <span>{{ config.accomplished_form_note }}</span>
                        </div>
                        <label for="accomplished-form"
                            class="flex items-center gap-2 cursor-pointer w-fit rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 px-5 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-100 transition">
                            <Upload class="h-4 w-4" />
                            {{ accomplishedFileName || 'Upload PDF' }}
                        </label>
                        <input id="accomplished-form" type="file" accept=".pdf" class="hidden"
                            @change="handleAccomplishedFile" />
                        <p v-if="fieldError('accomplished_form')" class="mt-1 text-xs text-red-500">
                            {{ fieldError('accomplished_form') }}
                        </p>
                    </div>
                </div>

                <!-- ── Navigation ── -->
                <div class="bg-white rounded-2xl shadow-sm px-5 py-5">
                    <div class="flex items-center gap-3">
                        <button
                            v-if="currentStep > 0"
                            type="button"
                            @click="back"
                            class="flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold py-3 px-5 text-sm transition"
                        >
                            <ArrowLeft class="h-4 w-4" /> Back
                        </button>

                        <button
                            v-if="!isLastStep"
                            type="button"
                            @click="next"
                            class="ml-auto flex items-center justify-center gap-1.5 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-extrabold py-3 px-6 text-sm transition"
                        >
                            Next <ArrowRight class="h-4 w-4" />
                        </button>

                        <button
                            v-else
                            type="submit"
                            :disabled="processing"
                            class="ml-auto flex items-center justify-center gap-2 rounded-xl bg-blue-700 hover:bg-blue-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-extrabold py-3 px-6 text-sm transition"
                        >
                            <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                            {{ processing ? 'Submitting…' : 'Submit Nomination' }}
                        </button>
                    </div>
                    <p v-if="isLastStep" class="text-center text-xs text-gray-400 mt-3">
                        Please review all information carefully before submitting.
                    </p>
                </div>

            </form>

            <!-- ── Footer ── -->
            <footer class="flex items-center justify-center gap-2.5 pt-2 pb-4">
                <!-- TODO: Palitan ang src ng totoong link ng logo -->
                <img
                    src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png"
                    alt="TESDA Development Institute"
                    class="h-7 w-7 object-contain"
                />
                <span class="text-sm font-semibold text-gray-600">
                    TESDA Development Institute
                </span>
            </footer>
        </div>
    </div>
</template>