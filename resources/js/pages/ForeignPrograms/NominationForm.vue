<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Upload, ExternalLink, ChevronDown, AlertCircle, Loader2 } from 'lucide-vue-next';

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
// Tinatanggal ang .dark class para hindi maapektuhan ng system/browser dark mode
// ang text color ng mga input at select (na umaasa sa --foreground variable).
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

// ── Errors from Inertia page props ────────────────────────────────────────────

const page   = usePage();
const errors = computed(() => (page.props.errors as Record<string, string>) ?? {});

// ── File handlers ─────────────────────────────────────────────────────────────

function handleRequirementFile(reqId: number, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (file) {
        requirementFiles.value[reqId]     = file;
        requirementFileNames.value[reqId] = file.name;
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
</script>

<template>
    <div class="min-h-screen bg-gray-100 py-8 px-4 [color-scheme:light]">
        <div class="mx-auto max-w-2xl space-y-4">

            <!-- ── Banner ── -->
            <div class="rounded-2xl overflow-hidden shadow-md">
                <div class="bg-blue-700 px-6 pt-6 pb-4">
                    <p class="text-blue-200 text-xs font-semibold uppercase tracking-widest mb-1">
                        {{ config.organizing_sponsor }}
                    </p>
                    <h1 class="text-white text-2xl font-extrabold leading-tight">
                        {{ config.form_title }}
                    </h1>
                </div>
                <div class="bg-white px-6 py-3 border-t-4 border-blue-500 text-xs text-gray-500">
                    All fields marked with <span class="text-red-500 font-bold">*</span> are required.
                    Maximum file size: <strong>10 MB</strong>.
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">

                <!-- ── Section 1: Program Selection ── -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">Section 1</p>
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
                                required
                            >
                                <option value="">— Select a program —</option>
                                <option v-for="p in programs" :key="p.id" :value="p.id">
                                    {{ p.program_title }}
                                </option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                        </div>
                        <p v-if="errors.foreign_program_id" class="mt-1 text-xs text-red-500">
                            {{ errors.foreign_program_id }}
                        </p>

                        <!-- Selected program details -->
                        <div v-if="selectedProgram" class="mt-3 rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-xs space-y-1">
                            <p><span class="font-semibold text-gray-600">Dates:</span>
                                {{ formatDate(selectedProgram.program_start) }} — {{ formatDate(selectedProgram.program_end) }}
                            </p>
                            <p><span class="font-semibold text-gray-600">Modality:</span> {{ modalityLabel(selectedProgram.modality) }}</p>
                            <p><span class="font-semibold text-gray-600">Slots:</span> {{ selectedProgram.slots }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Section 2: Nominee's Profile ── -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">Section 2</p>
                        <h2 class="text-base font-extrabold text-gray-800">Nominee's Profile</h2>
                    </div>
                    <div class="px-5 py-5 space-y-4">

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.firstname" type="text" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="errors.firstname" class="mt-1 text-xs text-red-500">{{ errors.firstname }}</p>
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
                                <input v-model="fields.surname" type="text" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="errors.surname" class="mt-1 text-xs text-red-500">{{ errors.surname }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Sex <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select v-model="fields.sex" required
                                        class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 pr-8 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                        <option value="">— Select —</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <ChevronDown class="absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400 pointer-events-none" />
                                </div>
                                <p v-if="errors.sex" class="mt-1 text-xs text-red-500">{{ errors.sex }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Age <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.age" type="number" min="18" max="100" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="errors.age" class="mt-1 text-xs text-red-500">{{ errors.age }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Position / Designation <span class="text-red-500">*</span>
                            </label>
                            <input v-model="fields.position" type="text" required
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                            <p v-if="errors.position" class="mt-1 text-xs text-red-500">{{ errors.position }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Agency <span class="text-red-500">*</span>
                                <span class="ml-1 font-normal text-gray-400">(Abbreviation only, e.g. TESDA, DOLE)</span>
                            </label>
                            <input v-model="fields.agency" type="text" required
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                            <p v-if="errors.agency" class="mt-1 text-xs text-red-500">{{ errors.agency }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.contact_number" type="tel" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="errors.contact_number" class="mt-1 text-xs text-red-500">{{ errors.contact_number }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input v-model="fields.email" type="email" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" />
                                <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ── Section 3: Documentary Requirements ── -->
                <div v-if="config.requirements.length > 0" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">Section 3</p>
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
                                <p v-if="errors[`requirement_${req.id}`]" class="mt-1 text-xs text-red-500">
                                    {{ errors[`requirement_${req.id}`] }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Section 4: Available Courses ── -->
                <div v-if="config.available_courses && config.available_courses.length > 0" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">Section 4</p>
                        <h2 class="text-base font-extrabold text-gray-800">Available Courses</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            View the available courses offered by {{ config.organizing_sponsor }}.
                        </p>
                    </div>
                    <div class="px-5 py-5">
                        <ul class="space-y-2">
                            <li v-for="(course, idx) in config.available_courses" :key="idx">
                                <a :href="course.url" target="_blank"
                                    class="flex items-center gap-2 text-sm text-blue-700 hover:text-blue-900 hover:underline font-medium">
                                    <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                                    {{ course.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ── Section 5: Accomplished Application Form ── -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-blue-50 border-l-4 border-blue-500 px-5 py-3">
                        <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">
                            Section {{ config.available_courses?.length ? 5 : 4 }}
                        </p>
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
                        <p v-if="errors.accomplished_form" class="mt-1 text-xs text-red-500">
                            {{ errors.accomplished_form }}
                        </p>
                    </div>
                </div>

                <!-- ── Submit ── -->
                <div class="bg-white rounded-2xl shadow-sm px-5 py-5">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-blue-700 hover:bg-blue-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-extrabold py-3 text-sm transition"
                    >
                        <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                        {{ processing ? 'Submitting…' : 'Submit Nomination' }}
                    </button>
                    <p class="text-center text-xs text-gray-400 mt-3">
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