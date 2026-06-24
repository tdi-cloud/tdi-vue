<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { X, Plus, Trash2, GripVertical, ExternalLink, Loader2, Settings, Link, FileText, Save } from 'lucide-vue-next';

interface Requirement {
    id: number | null;
    question: string;
    description: string;
    link: string;
    file_required: boolean;
    sort_order: number;
    _editing?: boolean;
}

interface Course {
    title: string;
    url: string;
}

interface Config {
    id: number | null;
    organizing_sponsor: string;
    slug: string;
    form_title: string;
    is_active: boolean;
    accomplished_form_note: string;
    available_courses: Course[];
    requirements: Requirement[];
}

const props = defineProps<{
    open: boolean;
    organizingSponsor: string; // pre-filled from parent
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', config: Config): void;
}>();

// ── State ─────────────────────────────────────────────────────────────────────

const tab       = ref<'general' | 'requirements' | 'courses'>('general');
const loading   = ref(false);
const saving    = ref(false);
const notFound  = ref(false);

const config = ref<Config>({
    id: null,
    organizing_sponsor: props.organizingSponsor,
    slug: '',
    form_title: '',
    is_active: true,
    accomplished_form_note: '',
    available_courses: [],
    requirements: [],
});

// ── Load config when modal opens ──────────────────────────────────────────────

watch(() => props.open, async (val) => {
    if (!val) return;
    tab.value    = 'general';
    notFound.value = false;
    loading.value  = true;

    try {
        // Try to find existing config for this sponsor
        const res = await axios.get('/foreign-sponsor-configs');
        const existing = res.data.find(
            (c: Config) => c.organizing_sponsor === props.organizingSponsor
        );

        if (existing) {
            const detail = await axios.get(`/foreign-sponsor-configs/${existing.id}`);
            config.value = {
                ...detail.data,
                available_courses:  detail.data.available_courses ?? [],
                requirements:       detail.data.requirements ?? [],
                accomplished_form_note: detail.data.accomplished_form_note ?? '',
            };
        } else {
            // New config for this sponsor
            config.value = {
                id: null,
                organizing_sponsor: props.organizingSponsor,
                slug: props.organizingSponsor.toLowerCase().replace(/\s+/g, '-'),
                form_title: `${props.organizingSponsor} Nomination Form`,
                is_active: true,
                accomplished_form_note: '',
                available_courses: [],
                requirements: [],
            };
        }
    } finally {
        loading.value = false;
    }
});

// ── Save general settings ─────────────────────────────────────────────────────

async function saveGeneral() {
    saving.value = true;
    try {
        if (config.value.id) {
            const res = await axios.put(`/foreign-sponsor-configs/${config.value.id}`, {
                form_title:             config.value.form_title,
                is_active:              config.value.is_active,
                accomplished_form_note: config.value.accomplished_form_note,
                available_courses:      config.value.available_courses,
            });
            config.value = { ...config.value, ...res.data };
        } else {
            const res = await axios.post('/foreign-sponsor-configs', {
                organizing_sponsor:     config.value.organizing_sponsor,
                form_title:             config.value.form_title,
                is_active:              config.value.is_active,
                accomplished_form_note: config.value.accomplished_form_note,
            });
            config.value = { ...config.value, ...res.data };
        }
        emit('saved', config.value);
    } finally {
        saving.value = false;
    }
}

// ── Requirements ──────────────────────────────────────────────────────────────

const newReq = ref<Omit<Requirement, 'id' | 'sort_order'>>({
    question: '', description: '', link: '', file_required: true, _editing: false,
});

const addingReq = ref(false);

async function addRequirement() {
    if (!config.value.id || !newReq.value.question) return;
    saving.value = true;
    try {
        const res = await axios.post(`/foreign-sponsor-configs/${config.value.id}/requirements`, newReq.value);
        config.value.requirements.push(res.data);
        newReq.value = { question: '', description: '', link: '', file_required: true };
        addingReq.value = false;
    } finally {
        saving.value = false;
    }
}

const editingReq = ref<Requirement | null>(null);

async function saveRequirement(req: Requirement) {
    saving.value = true;
    try {
        const res = await axios.put(`/foreign-nominee-requirements/${req.id}`, req);
        const idx = config.value.requirements.findIndex(r => r.id === req.id);
        if (idx >= 0) config.value.requirements[idx] = res.data;
        editingReq.value = null;
    } finally {
        saving.value = false;
    }
}

async function deleteRequirement(req: Requirement) {
    if (!confirm(`Delete "${req.question}"?`)) return;
    await axios.delete(`/foreign-nominee-requirements/${req.id}`);
    config.value.requirements = config.value.requirements.filter(r => r.id !== req.id);
}

// ── Courses ───────────────────────────────────────────────────────────────────

const newCourse = ref({ title: '', url: '' });

function addCourse() {
    if (!newCourse.value.title || !newCourse.value.url) return;
    config.value.available_courses.push({ ...newCourse.value });
    newCourse.value = { title: '', url: '' };
}

function removeCourse(idx: number) {
    config.value.available_courses.splice(idx, 1);
}

async function saveCourses() {
    saving.value = true;
    try {
        const res = await axios.put(`/foreign-sponsor-configs/${config.value.id}`, {
            form_title:             config.value.form_title,
            is_active:              config.value.is_active,
            accomplished_form_note: config.value.accomplished_form_note,
            available_courses:      config.value.available_courses,
        });
        config.value = { ...config.value, ...res.data };
    } finally {
        saving.value = false;
    }
}

// ── Computed ──────────────────────────────────────────────────────────────────

const nominationUrl = computed(() =>
    config.value.slug ? `/nominate/${config.value.slug}` : ''
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            @click.self="$emit('close')"
        >
            <div class="bg-background rounded-2xl shadow-xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden">

                <!-- Header -->
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center">
                            <Settings class="h-4 w-4 text-blue-600" />
                        </div>
                        <div>
                            <p class="font-bold text-sm">Sponsor Config — {{ organizingSponsor }}</p>
                            <p v-if="nominationUrl" class="text-xs text-muted-foreground">
                                Form URL:
                                <a :href="nominationUrl" target="_blank" class="text-blue-600 hover:underline font-mono">
                                    {{ nominationUrl }}
                                </a>
                            </p>
                        </div>
                    </div>
                    <button @click="$emit('close')" class="text-muted-foreground hover:text-foreground p-1 rounded-lg">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="flex justify-center py-16">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>

                <template v-else>
                    <!-- Tabs -->
                    <div class="flex border-b shrink-0 px-5">
                        <button
                            v-for="t in [
                                { key: 'general', label: 'General' },
                                { key: 'requirements', label: 'Requirements' },
                                { key: 'courses', label: 'Available Courses' },
                            ]"
                            :key="t.key"
                            class="px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors"
                            :class="tab === t.key
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-muted-foreground hover:text-foreground'"
                            @click="tab = t.key as any"
                        >
                            {{ t.label }}
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="overflow-y-auto flex-1 p-5">

                        <!-- ── General Tab ── -->
                        <div v-if="tab === 'general'" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-muted-foreground mb-1">Form Title <span class="text-red-500">*</span></label>
                                <input
                                    v-model="config.form_title"
                                    type="text"
                                    class="w-full rounded-xl border border-border bg-muted/30 px-3 py-2 text-sm outline-none focus:border-blue-500 transition"
                                />
                            </div>

                            <div class="flex items-center gap-3">
                                <input
                                    id="is-active"
                                    v-model="config.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-border text-blue-600"
                                />
                                <label for="is-active" class="text-sm font-semibold">Form Active</label>
                                <span class="text-xs text-muted-foreground">(inactive = nominees cannot access the form)</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-muted-foreground mb-1">
                                    <div class="flex items-center gap-1"><FileText class="h-3 w-3" /> Note for Accomplished Form</div>
                                </label>
                                <textarea
                                    v-model="config.accomplished_form_note"
                                    rows="3"
                                    placeholder="Instructions for downloading/filling the application form…"
                                    class="w-full rounded-xl border border-border bg-muted/30 px-3 py-2 text-sm outline-none focus:border-blue-500 transition resize-none"
                                />
                            </div>

                            <div v-if="!config.id" class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
                                ⚠️ Save General Settings first before adding requirements and courses.
                            </div>

                            <button
                                :disabled="saving"
                                class="w-full flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-bold py-2.5 text-sm transition"
                                @click="saveGeneral"
                            >
                                <Loader2 v-if="saving" class="h-3.5 w-3.5 animate-spin" />
                                <Save v-else class="h-3.5 w-3.5" />
                                {{ saving ? 'Saving…' : 'Save General Settings' }}
                            </button>
                        </div>

                        <!-- ── Requirements Tab ── -->
                        <div v-else-if="tab === 'requirements'" class="space-y-3">
                            <div v-if="!config.id" class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
                                ⚠️ Save General Settings first before adding requirements.
                            </div>

                            <template v-else>
                                <!-- Existing requirements -->
                                <div
                                    v-for="(req, idx) in config.requirements"
                                    :key="req.id"
                                    class="rounded-xl border border-border bg-muted/20 p-3"
                                >
                                    <template v-if="editingReq?.id === req.id">
                                        <div class="space-y-2">
                                            <input
                                                v-model="editingReq.question"
                                                type="text"
                                                placeholder="Question / Label"
                                                class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none"
                                            />
                                            <textarea
                                                v-model="editingReq.description"
                                                rows="2"
                                                placeholder="Description (optional)"
                                                class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none resize-none"
                                            />
                                            <input
                                                v-model="editingReq.link"
                                                type="url"
                                                placeholder="Link (optional)"
                                                class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none"
                                            />
                                            <div class="flex items-center gap-2">
                                                <input v-model="editingReq.file_required" type="checkbox" class="h-4 w-4 rounded" id="fr-edit" />
                                                <label for="fr-edit" class="text-xs font-semibold">File upload required</label>
                                            </div>
                                            <div class="flex gap-2 justify-end">
                                                <button class="text-xs text-muted-foreground px-3 py-1 rounded-lg border hover:bg-muted transition" @click="editingReq = null">Cancel</button>
                                                <button class="text-xs bg-blue-600 text-white px-3 py-1 rounded-lg font-bold hover:bg-blue-700 transition" @click="saveRequirement(editingReq)">Save</button>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-start gap-2 flex-1 min-w-0">
                                                <GripVertical class="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold truncate">{{ idx + 1 }}. {{ req.question }}</p>
                                                    <p v-if="req.description" class="text-xs text-muted-foreground mt-0.5">{{ req.description }}</p>
                                                    <a v-if="req.link" :href="req.link" target="_blank" class="text-xs text-blue-600 hover:underline flex items-center gap-1 mt-0.5">
                                                        <ExternalLink class="h-3 w-3" /> {{ req.link }}
                                                    </a>
                                                    <span class="inline-block mt-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                                        :class="req.file_required ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'">
                                                        {{ req.file_required ? 'File Required' : 'No File Upload' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex gap-1 shrink-0">
                                                <button class="p-1.5 rounded-lg hover:bg-muted transition text-muted-foreground hover:text-foreground" @click="editingReq = { ...req }">
                                                    <Settings class="h-3.5 w-3.5" />
                                                </button>
                                                <button class="p-1.5 rounded-lg hover:bg-red-50 transition text-muted-foreground hover:text-red-500" @click="deleteRequirement(req)">
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Add new requirement -->
                                <div v-if="addingReq" class="rounded-xl border border-blue-200 bg-blue-50/50 p-3 space-y-2">
                                    <input
                                        v-model="newReq.question"
                                        type="text"
                                        placeholder="Question / Label *"
                                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none"
                                        autofocus
                                    />
                                    <textarea
                                        v-model="newReq.description"
                                        rows="2"
                                        placeholder="Description (optional)"
                                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none resize-none"
                                    />
                                    <input
                                        v-model="newReq.link"
                                        type="url"
                                        placeholder="Link to form/document (optional)"
                                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none"
                                    />
                                    <div class="flex items-center gap-2">
                                        <input v-model="newReq.file_required" type="checkbox" class="h-4 w-4 rounded" id="fr-new" />
                                        <label for="fr-new" class="text-xs font-semibold">File upload required</label>
                                    </div>
                                    <div class="flex gap-2 justify-end">
                                        <button class="text-xs text-muted-foreground px-3 py-1 rounded-lg border hover:bg-muted transition" @click="addingReq = false">Cancel</button>
                                        <button class="text-xs bg-blue-600 text-white px-3 py-1 rounded-lg font-bold hover:bg-blue-700 transition" :disabled="!newReq.question" @click="addRequirement">Add</button>
                                    </div>
                                </div>

                                <button
                                    v-else
                                    class="flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800 transition"
                                    @click="addingReq = true"
                                >
                                    <Plus class="h-4 w-4" /> Add Requirement
                                </button>
                            </template>
                        </div>

                        <!-- ── Courses Tab ── -->
                        <div v-else-if="tab === 'courses'" class="space-y-3">
                            <div v-if="!config.id" class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
                                ⚠️ Save General Settings first before adding courses.
                            </div>

                            <template v-else>
                                <div v-for="(course, idx) in config.available_courses" :key="idx"
                                    class="flex items-center gap-2 rounded-xl border border-border bg-muted/20 px-3 py-2.5"
                                >
                                    <ExternalLink class="h-3.5 w-3.5 text-blue-500 shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold truncate">{{ course.title }}</p>
                                        <a :href="course.url" target="_blank" class="text-xs text-blue-500 hover:underline truncate block">{{ course.url }}</a>
                                    </div>
                                    <button class="p-1.5 rounded-lg hover:bg-red-50 text-muted-foreground hover:text-red-500 transition" @click="removeCourse(idx)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <!-- Add course -->
                                <div class="rounded-xl border border-dashed border-blue-200 p-3 space-y-2">
                                    <p class="text-xs font-semibold text-muted-foreground">Add Course Link</p>
                                    <input
                                        v-model="newCourse.title"
                                        type="text"
                                        placeholder="Course title"
                                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none"
                                    />
                                    <input
                                        v-model="newCourse.url"
                                        type="url"
                                        placeholder="https://..."
                                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none"
                                    />
                                    <button
                                        :disabled="!newCourse.title || !newCourse.url"
                                        class="flex items-center gap-1 text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg font-bold hover:bg-blue-700 disabled:opacity-50 transition"
                                        @click="addCourse"
                                    >
                                        <Plus class="h-3.5 w-3.5" /> Add Course
                                    </button>
                                </div>

                                <button
                                    :disabled="saving"
                                    class="w-full flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-bold py-2.5 text-sm transition"
                                    @click="saveCourses"
                                >
                                    <Loader2 v-if="saving" class="h-3.5 w-3.5 animate-spin" />
                                    <Save v-else class="h-3.5 w-3.5" />
                                    {{ saving ? 'Saving…' : 'Save Courses' }}
                                </button>
                            </template>
                        </div>

                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>