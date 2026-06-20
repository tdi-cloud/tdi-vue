<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import {
    Megaphone,
    Plus,
    Pencil,
    Trash2,
    LoaderCircle,
    Save,
    Mail,
    Phone,
    CalendarDays,
    Briefcase,
    Building2,
    BookOpen,
    Sparkles,
    StickyNote,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

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
    resource_speakers?: ResourceSpeaker[];
}

const props = defineProps<{
    program: Program;
}>();

const speakers = computed(() => props.program.resource_speakers ?? []);

/* ---------- Modal state ---------- */
const showModal = ref(false);
const editingSpeaker = ref<ResourceSpeaker | null>(null);
const processing = ref(false);

const form = ref({
    name: '',
    designation: '',
    affiliation: '',
    topic: '',
    expertise: '',
    email: '',
    contact_number: '',
    date_engaged: '',
    remarks: '',
});

const errors = ref<Record<string, string>>({});

const resetForm = () => {
    form.value = {
        name: '',
        designation: '',
        affiliation: '',
        topic: '',
        expertise: '',
        email: '',
        contact_number: '',
        date_engaged: '',
        remarks: '',
    };
};

const openCreate = () => {
    editingSpeaker.value = null;
    resetForm();
    errors.value = {};
    showModal.value = true;
};

const openEdit = (speaker: ResourceSpeaker) => {
    editingSpeaker.value = speaker;
    form.value = {
        name: speaker.name,
        designation: speaker.designation ?? '',
        affiliation: speaker.affiliation ?? '',
        topic: speaker.topic ?? '',
        expertise: speaker.expertise ?? '',
        email: speaker.email ?? '',
        contact_number: speaker.contact_number ?? '',
        date_engaged: speaker.date_engaged ?? '',
        remarks: speaker.remarks ?? '',
    };
    errors.value = {};
    showModal.value = true;
};

const submit = () => {
    errors.value = {};
    processing.value = true;

    const payload = {
        name: form.value.name,
        designation: form.value.designation || null,
        affiliation: form.value.affiliation || null,
        topic: form.value.topic || null,
        expertise: form.value.expertise || null,
        email: form.value.email || null,
        contact_number: form.value.contact_number || null,
        date_engaged: form.value.date_engaged || null,
        remarks: form.value.remarks || null,
    };

    if (editingSpeaker.value) {
        router.put(
            route('programs.resource-speakers.update', [props.program.id, editingSpeaker.value.id]),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showModal.value = false;
                },
                onError: (e) => {
                    errors.value = e as any;
                },
                onFinish: () => {
                    processing.value = false;
                },
            }
        );
    } else {
        router.post(
            route('programs.resource-speakers.store', props.program.id),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showModal.value = false;
                },
                onError: (e) => {
                    errors.value = e as any;
                },
                onFinish: () => {
                    processing.value = false;
                },
            }
        );
    }
};

const destroy = (speaker: ResourceSpeaker) => {
    if (!confirm(`Remove "${speaker.name}" from the resource speakers list?`)) return;

    router.delete(route('programs.resource-speakers.destroy', [props.program.id, speaker.id]), {
        preserveScroll: true,
    });
};

/* ---------- Helpers ---------- */
const formatDate = (d: string | null) => {
    if (!d) return null;
    return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const initials = (name: string) => {
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
};

const AVATAR_COLORS = [
    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
];

const avatarColor = (id: number) => AVATAR_COLORS[id % AVATAR_COLORS.length];
</script>

<template>
    <div class="flex flex-col gap-4">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-extrabold flex items-center gap-1.5">
                    <Megaphone class="h-4 w-4 text-blue-600" /> Resource Speakers
                </h2>
                <p class="text-xs font-semibold text-slate-400">
                    {{ speakers.length }} speaker(s) engaged for {{ program.program_code }}
                </p>
            </div>

            <Button size="sm" class="bg-blue-600 font-extrabold rounded-lg hover:bg-blue-500 dark:text-white" @click="openCreate">
                <Plus class="h-4 w-4" /> Add Resource Speaker
            </Button>
        </div>

        <!-- Empty state with illustration -->
        <div v-if="!speakers.length" class="flex flex-col items-center justify-center rounded-2xl border border-dashed py-16 px-6 text-center gap-3">
            <!-- Illustration: speaker at podium / microphone -->
            <svg viewBox="0 0 200 160" class="h-40 w-auto" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />

                <!-- podium -->
                <path d="M70 130 L80 75 H120 L130 130 Z" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                <rect x="75" y="68" width="50" height="10" rx="2" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />

                <!-- person -->
                <circle cx="100" cy="40" r="16" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <path d="M76 75 c0 -16 11 -24 24 -24 s24 8 24 24 Z" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />

                <!-- microphone -->
                <rect x="142" y="55" width="10" height="22" rx="5" fill="currentColor" class="text-amber-400 dark:text-amber-500" />
                <path d="M134 68 a13 13 0 0 0 26 0" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" class="text-amber-400 dark:text-amber-500" />
                <line x1="147" y1="81" x2="147" y2="92" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="text-amber-400 dark:text-amber-500" />

                <!-- sparkle accents -->
                <circle cx="48" cy="50" r="3" fill="currentColor" class="text-amber-300 dark:text-amber-600" />
                <circle cx="40" cy="65" r="2" fill="currentColor" class="text-blue-300 dark:text-blue-700" />
                <circle cx="158" cy="38" r="2.5" fill="currentColor" class="text-blue-300 dark:text-blue-700" />
            </svg>
            <p class="text-sm font-bold text-slate-500">No resource speakers yet</p>
            <p class="text-xs text-slate-400 max-w-xs">
                Keep a record of the speakers, trainers, or experts who were engaged for this program — their topics, affiliations, and contact details.
            </p>
            <Button size="sm" variant="outline" class="mt-1" @click="openCreate">
                <Plus class="h-3.5 w-3.5 mr-1" /> Add your first speaker
            </Button>
        </div>

        <!-- Speaker list -->
        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="speaker in speakers"
                :key="speaker.id"
                class="rounded-xl border bg-card p-4 shadow-sm transition hover:shadow-md flex flex-col gap-2"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full text-sm font-extrabold"
                        :class="avatarColor(speaker.id)"
                    >
                        {{ initials(speaker.name) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-extrabold leading-5 truncate">{{ speaker.name }}</h3>
                        <p v-if="speaker.designation" class="text-[11px] text-muted-foreground flex items-center gap-1 truncate">
                            <Briefcase class="h-3 w-3 shrink-0" /> {{ speaker.designation }}
                        </p>
                        <p v-if="speaker.affiliation" class="text-[11px] text-muted-foreground flex items-center gap-1 truncate">
                            <Building2 class="h-3 w-3 shrink-0" /> {{ speaker.affiliation }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1 mt-1">
                    <Badge v-if="speaker.topic" class="bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-0 text-[10px] font-bold gap-1">
                        <BookOpen class="h-3 w-3" /> {{ speaker.topic }}
                    </Badge>
                    <Badge v-if="speaker.expertise" class="bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border-0 text-[10px] font-bold gap-1">
                        <Sparkles class="h-3 w-3" /> {{ speaker.expertise }}
                    </Badge>
                </div>

                <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400 mt-1">
                    <p v-if="speaker.email" class="flex items-center gap-1.5">
                        <Mail class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ speaker.email }}</span>
                    </p>
                    <p v-if="speaker.contact_number" class="flex items-center gap-1.5">
                        <Phone class="h-3.5 w-3.5 shrink-0" />
                        {{ speaker.contact_number }}
                    </p>
                    <p v-if="formatDate(speaker.date_engaged)" class="flex items-center gap-1.5">
                        <CalendarDays class="h-3.5 w-3.5 shrink-0" />
                        {{ formatDate(speaker.date_engaged) }}
                    </p>
                    <p v-if="speaker.remarks" class="flex items-start gap-1.5 mt-0.5">
                        <StickyNote class="h-3.5 w-3.5 shrink-0 mt-0.5" />
                        <span class="leading-snug">{{ speaker.remarks }}</span>
                    </p>
                </div>

                <div class="mt-2 flex justify-end gap-1 border-t pt-2">
                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openEdit(speaker)">
                        <Pencil class="h-3 w-3" /> Edit
                    </Button>
                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-red-500 hover:text-red-600" @click="destroy(speaker)">
                        <Trash2 class="h-3 w-3" /> Delete
                    </Button>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-w-xl flex flex-col max-h-[90vh] overflow-hidden !rounded-2xl">
                <DialogHeader class="shrink-0">
                    <DialogTitle>
                        <span class="flex gap-2 items-center">
                            <Megaphone class="h-5 w-5 text-blue-600" />
                            {{ editingSpeaker ? 'Edit Resource Speaker' : 'Add Resource Speaker' }}
                        </span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ editingSpeaker
                            ? 'Update the details of this resource speaker.'
                            : `Record a speaker, trainer, or expert engaged for ${program.program_code}.` }}
                    </DialogDescription>
                </DialogHeader>

                <div class="overflow-y-auto flex-1 px-1">
                    <form id="resource-speaker-form" @submit.prevent="submit" class="grid grid-cols-2 gap-4 py-2">

                        <!-- Name (full width) -->
                        <div class="grid gap-1 col-span-2">
                            <Label class="text-xs">Full Name <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.name" placeholder="e.g. Engr. Juan Dela Cruz" />
                            <p class="text-xs text-red-500">{{ errors.name }}</p>
                        </div>

                        <!-- Designation -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Designation / Position</Label>
                            <Input class="text-xs h-8" v-model="form.designation" placeholder="e.g. Training Officer III" />
                            <p class="text-xs text-red-500">{{ errors.designation }}</p>
                        </div>

                        <!-- Affiliation -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Affiliation / Organization</Label>
                            <Input class="text-xs h-8" v-model="form.affiliation" placeholder="e.g. TESDA Region IV-A" />
                            <p class="text-xs text-red-500">{{ errors.affiliation }}</p>
                        </div>

                        <!-- Topic -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Topic / Subject Handled</Label>
                            <Input class="text-xs h-8" v-model="form.topic" placeholder="e.g. ISO 9001:2015 Awareness" />
                            <p class="text-xs text-red-500">{{ errors.topic }}</p>
                        </div>

                        <!-- Expertise -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Field of Expertise</Label>
                            <Input class="text-xs h-8" v-model="form.expertise" placeholder="e.g. Quality Management Systems" />
                            <p class="text-xs text-red-500">{{ errors.expertise }}</p>
                        </div>

                        <!-- Email -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Email</Label>
                            <Input type="email" class="text-xs h-8" v-model="form.email" placeholder="e.g. juan.delacruz@tesda.gov.ph" />
                            <p class="text-xs text-red-500">{{ errors.email }}</p>
                        </div>

                        <!-- Contact Number -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Contact Number</Label>
                            <Input class="text-xs h-8" v-model="form.contact_number" placeholder="e.g. 09171234567" />
                            <p class="text-xs text-red-500">{{ errors.contact_number }}</p>
                        </div>

                        <!-- Date Engaged -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Date Engaged</Label>
                            <Input type="date" class="text-xs h-8" v-model="form.date_engaged" />
                            <p class="text-xs text-red-500">{{ errors.date_engaged }}</p>
                        </div>

                        <!-- Remarks (full width) -->
                        <div class="grid gap-1 col-span-2">
                            <Label class="text-xs">Remarks</Label>
                            <Textarea
                                class="text-xs min-h-[70px] resize-y"
                                v-model="form.remarks"
                                placeholder="Additional notes about this speaker's session..."
                            />
                            <p class="text-xs text-red-500">{{ errors.remarks }}</p>
                        </div>

                    </form>
                </div>

                <div class="shrink-0 flex justify-end gap-2 pt-3 border-t mt-2">
                    <Button type="button" variant="outline" size="sm" @click="showModal = false">Cancel</Button>
                    <Button type="submit" class="bg-blue-600 hover:bg-blue-700 dark:text-white" form="resource-speaker-form" size="sm" :disabled="processing">
                        <LoaderCircle v-if="processing" class="h-3 w-3 animate-spin mr-1" />
                        <Save v-else class="h-3.5 w-3.5" />
                        {{ editingSpeaker ? 'Update Speaker' : 'Save Speaker' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

    </div>
</template>