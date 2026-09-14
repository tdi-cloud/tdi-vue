<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, ArrowRight, Check, ChevronDown, ExternalLink, Loader2, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
    sponsorLogos: Record<string, string>;
}>();

// ── Force light mode for public form ──────────────────────────────────────────
onMounted(() => {
    document.documentElement.classList.remove('dark');
});

// ── Form fields ───────────────────────────────────────────────────────────────

const fields = ref({
    foreign_program_id: '' as string | number,
    firstname: '',
    middle_name: '',
    surname: '',
    sex: '',
    age: '' as string | number,
    position: '',
    agency: '',
    contact_number: '',
    email: '',
});

const accomplishedFile = ref<File | null>(null);
const accomplishedFileName = ref('');
const requirementFiles = ref<Record<number, File | null>>({});
const requirementFileNames = ref<Record<number, string>>({});
const processing = ref(false);

// ── Errors ────────────────────────────────────────────────────────────────────
// Pinagsasama ang server errors (mula Inertia) at client-side per-step errors.

const page = usePage();
const serverErrors = computed(() => (page.props.errors as Record<string, string>) ?? {});
const stepErrors = ref<Record<string, string>>({});

function fieldError(key: string): string | undefined {
    return stepErrors.value[key] ?? serverErrors.value[key];
}

// ── Steps (dynamic — conditional sections excluded kung wala) ──────────────────

interface Step {
    key: string;
    label: string;
}

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
const currentKey = computed(() => steps.value[currentStep.value]?.key);
const isLastStep = computed(() => currentStep.value === steps.value.length - 1);
const progressPct = computed(() => ((currentStep.value + 1) / steps.value.length) * 100);

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
        if (!String(fields.value.surname).trim()) stepErrors.value.surname = 'Surname is required.';
        if (!fields.value.sex) stepErrors.value.sex = 'Please select.';
        if (!fields.value.age) {
            stepErrors.value.age = 'Age is required.';
        } else if (Number(fields.value.age) < 18 || Number(fields.value.age) > 100) {
            stepErrors.value.age = 'Age must be between 18 and 100.';
        }
        if (!String(fields.value.position).trim()) stepErrors.value.position = 'Position is required.';
        if (!String(fields.value.agency).trim()) stepErrors.value.agency = 'Agency is required.';
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

const checkingEmail = ref(false);

async function emailAlreadyUsed(): Promise<boolean> {
    checkingEmail.value = true;
    try {
        const res = await fetch(
            route('nominate.check-email', props.config.slug) +
                `?email=${encodeURIComponent(fields.value.email)}&foreign_program_id=${encodeURIComponent(String(fields.value.foreign_program_id))}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!res.ok) return false;
        const data = await res.json();
        return !!data.already_submitted;
    } catch {
        return false;
    } finally {
        checkingEmail.value = false;
    }
}

async function next() {
    if (!validateCurrentStep()) return;

    // Bago umalis sa 'profile' step, i-check muna kung nagamit na ang email
    // na ito para sa napiling program — para hindi umasa ang user na
    // tatanggapin ang submission niya, sa halip agad na mabigyan ng feedback.
    if (currentKey.value === 'profile') {
        if (await emailAlreadyUsed()) {
            stepErrors.value.email = 'This email has already been used to submit a nomination for this program.';
            return;
        }
    }

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
        requirementFiles.value[reqId] = file;
        requirementFileNames.value[reqId] = file.name;
        delete stepErrors.value[`requirement_${reqId}`];
    }
}

function handleAccomplishedFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (file) {
        accomplishedFile.value = file;
        accomplishedFileName.value = file.name;
    }
}

// ── Submit via FormData ───────────────────────────────────────────────────────

function submit() {
    // Kung hindi pa huling step, Enter key = Next imbes na submit.
    if (!isLastStep.value) {
        next();
        return;
    }
    if (!validateCurrentStep()) return;

    const data = new FormData();

    data.append('foreign_program_id', String(fields.value.foreign_program_id));
    data.append('firstname', fields.value.firstname);
    data.append('middle_name', fields.value.middle_name ?? '');
    data.append('surname', fields.value.surname);
    data.append('sex', fields.value.sex);
    data.append('age', String(fields.value.age));
    data.append('position', fields.value.position);
    data.append('agency', fields.value.agency);
    data.append('contact_number', fields.value.contact_number ?? '');
    data.append('email', fields.value.email ?? '');

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
        onFinish: () => {
            processing.value = false;
        },
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const selectedProgram = computed(() => props.programs.find((p) => p.id === Number(fields.value.foreign_program_id)) ?? null);

function formatDate(d: string) {
    if (!d) return '—';
    const date = d.includes('T') ? new Date(d) : new Date(d + 'T00:00:00');
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function modalityLabel(m: string) {
    return { 'in-person': 'In-Person', online: 'Online', hybrid: 'Hybrid' }[m] ?? m;
}

const logoLoadFailed = ref(false);

// Kinukuha mula sa mga larawang na-customize ng superadmin sa /site-images
// (Homepage Images), kaya pareho ang logo dito at sa FSTP section ng homepage.
const sponsorLogo = computed(() => {
    const key = props.config.slug?.toLowerCase() ?? '';
    return props.sponsorLogos[key] ?? null;
});

function handleSponsorLogoError() {
    logoLoadFailed.value = true;
}
</script>

<template>
    <Head :title="config.form_title" />

    <div class="min-h-screen bg-gray-100 px-4 py-8 [color-scheme:light]">
        <div class="mx-auto max-w-2xl space-y-4">
            <!-- ── Banner ── -->
            <div class="overflow-hidden rounded-2xl shadow-md">
                <div class="bg-blue-700 px-6 pb-4 pt-6">
                    <div class="flex items-center gap-4">
                        <div
                            v-if="sponsorLogo && !logoLoadFailed"
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-white p-2 shadow-sm"
                        >
                            <img
                                :src="sponsorLogo"
                                :alt="`${config.organizing_sponsor} logo`"
                                class="h-full w-full object-contain"
                                @error="handleSponsorLogoError"
                            />
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-blue-200">
                                {{ config.organizing_sponsor }}
                            </p>
                            <h1 class="text-2xl font-extrabold leading-tight text-white">
                                {{ config.form_title }}
                            </h1>
                        </div>
                    </div>
                </div>
                <div class="border-t-4 border-blue-500 bg-white px-6 py-3 text-xs text-gray-500">
                    All fields marked with <span class="font-bold text-red-500">*</span> are required. Maximum file size: <strong>10 MB</strong>.
                </div>
            </div>

            <!-- ── Progress (step circles + fill bar) ── -->
            <div class="rounded-2xl bg-white px-5 py-4 shadow-sm">
                <div class="mb-3 flex items-start justify-between gap-1">
                    <div v-for="(s, i) in steps" :key="s.key" class="flex min-w-0 flex-1 flex-col items-center">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all"
                            :class="
                                i < currentStep
                                    ? 'bg-blue-600 text-white'
                                    : i === currentStep
                                      ? 'bg-blue-600 text-white ring-4 ring-blue-100'
                                      : 'bg-gray-200 text-gray-500'
                            "
                        >
                            <Check v-if="i < currentStep" class="h-4 w-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="mt-1.5 w-full px-0.5 text-center text-[10px] leading-tight"
                            :class="i === currentStep ? 'font-semibold text-blue-600' : 'text-gray-400'"
                        >
                            {{ s.label }}
                        </span>
                    </div>
                </div>

                <div class="h-1.5 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full bg-blue-600 transition-all duration-300" :style="{ width: progressPct + '%' }"></div>
                </div>
                <p class="mt-2 text-center text-xs text-gray-500">Step {{ currentStep + 1 }} of {{ steps.length }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- ── Step: Program Selection ── -->
                <div v-show="currentKey === 'program'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 border-blue-500 bg-blue-50 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Program Selection</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Select the program you wish to apply for.</p>
                    </div>
                    <div class="px-5 py-5">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700"> List of Programs <span class="text-red-500">*</span> </label>
                        <div class="relative">
                            <select
                                v-model="fields.foreign_program_id"
                                class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-10 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">— Select a program —</option>
                                <option v-for="p in programs" :key="p.id" :value="p.id">
                                    {{ p.program_title }}
                                </option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        </div>
                        <p v-if="fieldError('foreign_program_id')" class="mt-1 text-xs text-red-500">
                            {{ fieldError('foreign_program_id') }}
                        </p>

                        <div v-if="selectedProgram" class="mt-3 space-y-1 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs">
                            <p>
                                <span class="font-semibold text-gray-600">Dates:</span> {{ formatDate(selectedProgram.program_start) }} —
                                {{ formatDate(selectedProgram.program_end) }}
                            </p>
                            <p><span class="font-semibold text-gray-600">Modality:</span> {{ modalityLabel(selectedProgram.modality) }}</p>
                            <p><span class="font-semibold text-gray-600">Slots:</span> {{ selectedProgram.slots }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Step: Nominee's Profile ── -->
                <div v-show="currentKey === 'profile'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 border-blue-500 bg-blue-50 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Nominee's Profile</h2>
                    </div>
                    <div class="space-y-4 px-5 py-5">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600"> First Name <span class="text-red-500">*</span> </label>
                                <input
                                    v-model="fields.firstname"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p v-if="fieldError('firstname')" class="mt-1 text-xs text-red-500">{{ fieldError('firstname') }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Middle Name</label>
                                <input
                                    v-model="fields.middle_name"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600"> Surname <span class="text-red-500">*</span> </label>
                                <input
                                    v-model="fields.surname"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p v-if="fieldError('surname')" class="mt-1 text-xs text-red-500">{{ fieldError('surname') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600"> Sex <span class="text-red-500">*</span> </label>
                                <div class="relative">
                                    <select
                                        v-model="fields.sex"
                                        class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 pr-8 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option value="">— Select —</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                </div>
                                <p v-if="fieldError('sex')" class="mt-1 text-xs text-red-500">{{ fieldError('sex') }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600"> Age <span class="text-red-500">*</span> </label>
                                <input
                                    v-model="fields.age"
                                    type="number"
                                    min="18"
                                    max="100"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p v-if="fieldError('age')" class="mt-1 text-xs text-red-500">{{ fieldError('age') }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                Position / Designation <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="fields.position"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                            <p v-if="fieldError('position')" class="mt-1 text-xs text-red-500">{{ fieldError('position') }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                Agency <span class="text-red-500">*</span>
                                <span class="ml-1 font-normal text-gray-400">(Abbreviation only, e.g. TESDA, DOLE)</span>
                            </label>
                            <input
                                v-model="fields.agency"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                            <p v-if="fieldError('agency')" class="mt-1 text-xs text-red-500">{{ fieldError('agency') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="fields.contact_number"
                                    type="tel"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p v-if="fieldError('contact_number')" class="mt-1 text-xs text-red-500">{{ fieldError('contact_number') }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="fields.email"
                                    type="email"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p v-if="fieldError('email')" class="mt-1 text-xs text-red-500">{{ fieldError('email') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Step: Documentary Requirements ── -->
                <div v-show="currentKey === 'requirements'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 border-blue-500 bg-blue-50 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Documentary Requirements</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Upload the required documents. Maximum 10MB per file.</p>
                    </div>
                    <div class="space-y-5 px-5 py-5">
                        <div v-for="(req, idx) in config.requirements" :key="req.id" class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-semibold text-gray-800">
                                {{ idx + 1 }}. {{ req.question }}
                                <span v-if="req.file_required" class="ml-0.5 text-red-500">*</span>
                            </p>
                            <p v-if="req.description" class="mt-1 text-xs text-gray-500">{{ req.description }}</p>
                            <a
                                v-if="req.link"
                                :href="req.link"
                                target="_blank"
                                class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                            >
                                <ExternalLink class="h-3 w-3" /> Open Form / Link
                            </a>

                            <div v-if="req.file_required" class="mt-3">
                                <label
                                    :for="`req-file-${req.id}`"
                                    class="flex w-fit cursor-pointer items-center gap-2 rounded-lg border border-dashed border-blue-300 bg-blue-50 px-4 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100"
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
                <div v-show="currentKey === 'courses'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 border-blue-500 bg-blue-50 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Available Courses</h2>
                        <p class="mt-0.5 text-xs text-gray-500">View the available courses offered by {{ config.organizing_sponsor }}.</p>
                    </div>
                    <div class="px-5 py-5">
                        <ul class="space-y-2">
                            <li v-for="(course, idx) in config.available_courses ?? []" :key="idx">
                                <a
                                    :href="course.url"
                                    target="_blank"
                                    class="flex items-center gap-2 text-sm font-medium text-blue-700 hover:text-blue-900 hover:underline"
                                >
                                    <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                                    {{ course.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ── Step: Accomplished Application Form ── -->
                <div v-show="currentKey === 'accomplished'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 border-blue-500 bg-blue-50 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Accomplished {{ config.organizing_sponsor }} Application Form</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Upload your completed application form (PDF only, max 10MB).</p>
                    </div>
                    <div class="px-5 py-5">
                        <div
                            v-if="config.accomplished_form_note"
                            class="mb-3 flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-800"
                        >
                            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                            <span>{{ config.accomplished_form_note }}</span>
                        </div>
                        <label
                            for="accomplished-form"
                            class="flex w-fit cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 px-5 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                        >
                            <Upload class="h-4 w-4" />
                            {{ accomplishedFileName || 'Upload PDF' }}
                        </label>
                        <input id="accomplished-form" type="file" accept=".pdf" class="hidden" @change="handleAccomplishedFile" />
                        <p v-if="fieldError('accomplished_form')" class="mt-1 text-xs text-red-500">
                            {{ fieldError('accomplished_form') }}
                        </p>
                    </div>
                </div>

                <!-- ── Navigation ── -->
                <div class="rounded-2xl bg-white px-5 py-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <button
                            v-if="currentStep > 0"
                            type="button"
                            @click="back"
                            class="flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        >
                            <ArrowLeft class="h-4 w-4" /> Back
                        </button>

                        <button
                            v-if="!isLastStep"
                            type="button"
                            :disabled="checkingEmail"
                            @click="next"
                            class="ml-auto flex items-center justify-center gap-1.5 rounded-xl bg-blue-700 px-6 py-3 text-sm font-extrabold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Loader2 v-if="checkingEmail" class="h-4 w-4 animate-spin" />
                            {{ checkingEmail ? 'Checking…' : 'Next' }}
                            <ArrowRight v-if="!checkingEmail" class="h-4 w-4" />
                        </button>

                        <button
                            v-else
                            type="submit"
                            :disabled="processing"
                            class="ml-auto flex items-center justify-center gap-2 rounded-xl bg-blue-700 px-6 py-3 text-sm font-extrabold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                            {{ processing ? 'Submitting…' : 'Submit Nomination' }}
                        </button>
                    </div>
                    <p v-if="isLastStep" class="mt-3 text-center text-xs text-gray-400">Please review all information carefully before submitting.</p>
                </div>
            </form>

            <!-- ── Footer ── -->
            <footer class="flex items-center justify-center gap-2.5 pb-4 pt-2">
                <!-- TODO: Palitan ang src ng totoong link ng logo -->
                <img
                    src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png"
                    alt="TESDA Development Institute"
                    class="h-7 w-7 object-contain"
                />
                <span class="text-sm font-semibold text-gray-600"> TESDA Development Institute </span>
            </footer>
        </div>
    </div>
</template>
