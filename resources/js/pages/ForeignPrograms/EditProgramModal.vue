<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Calendar, Users, Building2, Globe, MapPin, Banknote,
    Tag, FileText, CalendarDays, Building, Hash, AlignLeft,
    CheckCircle2, Pencil, X,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import OrganizingSponsorModal from '@/components/OrganizingSponsorModal.vue';

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    modality: 'in-person' | 'online' | 'hybrid';
    organizing_sponsor: string;
    status: string;
    submission_date?: string | null;
    embassy_deadline?: string | null;
    interview_date?: string | null;
    invited_agencies?: string | null;
    attached_agency?: string | null;
    category?: string | null;
    description?: string | null;
    online_start?: string | null;
    online_end?: string | null;
    program_cost?: string | null;
    fund_source?: string | null;
}

const props = defineProps<{
    open: boolean;
    program: ForeignProgram | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const statusLabels: Record<string, string> = {
    for_dissemination: 'For Dissemination',
    waiting_for_nominees: 'Waiting for Nominees',
    for_interview: 'For Interview',
    for_endorsement: 'For Endorsement',
    no_nominee: 'No Nominee',
    waiting_for_result: 'Waiting for Result',
    ongoing: 'Ongoing',
    concluded: 'Concluded',
    not_nfp_concern: 'Not NFP Concern',
};

// Hindi safe ang basta pag-slice ng ISO string dito — kapag naka-timestamp ang
// value (may 'T'), UTC ang naka-encode nun, at kapag ibang timezone ang app
// (hal. Asia/Manila, +8), isang araw na bago ang UTC midnight kaysa sa totoong
// lokal na petsa. Kaya nagpa-parse muna tayo bilang isang instant, tapos kunin
// ang taon/buwan/araw gamit ang LOCAL time ng browser.
const toDateInput = (date?: string | null): string => {
    if (!date) return '';
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    if (isNaN(d.getTime())) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const editForm = useForm({
    program_title: '',
    description: '',
    program_start: '',
    program_end: '',
    slots: 1,
    modality: 'in-person' as 'in-person' | 'online' | 'hybrid',
    online_start: '',
    online_end: '',
    program_cost: '',
    fund_source: '',
    category: 'Foreign',
    organizing_sponsor: '',
    status: 'for_dissemination',
    submission_date: '',
    embassy_deadline: '',
    interview_date: '',
    invited_agencies: '',
    attached_agency: '',
});

const showEditOnlineDates = computed(() => editForm.modality === 'online' || editForm.modality === 'hybrid');

watch(() => editForm.modality, (val) => {
    if (val === 'in-person') { editForm.online_start = ''; editForm.online_end = ''; }
});

// I-populate ang form tuwing binubuksan ang modal para sa isang bagong program.
watch(() => [props.open, props.program], ([open]) => {
    if (open && props.program) {
        const p = props.program;
        editForm.program_title = p.program_title;
        editForm.description = p.description ?? '';
        editForm.program_start = toDateInput(p.program_start);
        editForm.program_end = toDateInput(p.program_end);
        editForm.slots = p.slots;
        editForm.modality = p.modality;
        editForm.online_start = toDateInput(p.online_start);
        editForm.online_end = toDateInput(p.online_end);
        editForm.program_cost = p.program_cost ?? '';
        editForm.fund_source = p.fund_source ?? '';
        editForm.category = p.category ?? 'Foreign';
        editForm.organizing_sponsor = p.organizing_sponsor;
        editForm.status = p.status;
        editForm.submission_date = toDateInput(p.submission_date);
        editForm.embassy_deadline = toDateInput(p.embassy_deadline);
        editForm.interview_date = toDateInput(p.interview_date);
        editForm.invited_agencies = p.invited_agencies ?? '';
        editForm.attached_agency = p.attached_agency ?? '';
        editForm.clearErrors();
    }
}, { immediate: true });

const close = () => {
    emit('update:open', false);
};

const submit = () => {
    if (!props.program) return;
    editForm.put(route('foreign-programs.update', props.program.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    });
};

// ── Organizing sponsor list + "manage sponsors" modal ──────────────────────────
const showSponsorModal = ref(false);
const sponsors = ref<string[]>([]);

const fetchSponsors = async () => {
    const res = await fetch(route('organizing-sponsors.index'), {
        headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    sponsors.value = data.map((s: { id: number; name: string }) => s.name);
};

fetchSponsors();

const onSponsorSelected = (name: string) => {
    editForm.organizing_sponsor = name;
};
</script>

<template>
    <OrganizingSponsorModal
        v-if="showSponsorModal"
        @close="showSponsorModal = false"
        @select="onSponsorSelected"
        @updated="fetchSponsors"
    />

    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="close">
        <div class="bg-background rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

            <!-- Header -->
            <div class="sticky top-0 z-10 bg-background border-b px-6 py-4 rounded-t-2xl flex items-center gap-3">
                <div class="flex items-center justify-center h-9 w-9 rounded-xl bg-amber-500 shadow">
                    <Pencil class="h-4 w-4 text-white" />
                </div>
                <div>
                    <h2 class="text-base font-bold leading-none">Edit Program</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Update the details of this program</p>
                </div>
                <button @click="close" class="ml-auto text-muted-foreground hover:text-foreground transition-colors">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="p-6 flex flex-col gap-6">

                <!-- Basic Info -->
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                        <FileText class="h-3.5 w-3.5" /> <span>Basic Information</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2 flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <AlignLeft class="h-3.5 w-3.5 text-muted-foreground" /> Program Title <span class="text-red-500">*</span>
                            </label>
                            <input v-model="editForm.program_title" type="text" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                            <span v-if="editForm.errors.program_title" class="text-xs text-red-500">{{ editForm.errors.program_title }}</span>
                        </div>
                        <div class="md:col-span-2 flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Description
                            </label>
                            <textarea v-model="editForm.description" rows="3" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none" placeholder="Optional"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                        <CalendarDays class="h-3.5 w-3.5" /> <span>Schedule</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program Start <span class="text-red-500">*</span>
                            </label>
                            <input v-model="editForm.program_start" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program End <span class="text-red-500">*</span>
                            </label>
                            <input v-model="editForm.program_end" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Hash class="h-3.5 w-3.5 text-muted-foreground" /> Slots <span class="text-red-500">*</span>
                            </label>
                            <input v-model="editForm.slots" type="number" min="1" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Globe class="h-3.5 w-3.5 text-muted-foreground" /> Modality <span class="text-red-500">*</span>
                            </label>
                            <select v-model="editForm.modality" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option value="in-person">🏢 In-person</option>
                                <option value="online">💻 Online</option>
                                <option value="hybrid">🔀 Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <template v-if="showEditOnlineDates">
                        <div class="rounded-xl bg-purple-50 border border-purple-200 p-4 dark:bg-purple-950/30 dark:border-purple-900">
                            <p class="text-xs font-extrabold uppercase tracking-wide text-purple-600 dark:text-purple-400 mb-3">💻 Online Schedule</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold">Online Start</label>
                                    <input v-model="editForm.online_start" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-purple-400" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold">Online End</label>
                                    <input v-model="editForm.online_end" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-purple-400" />
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Classification & Funding -->
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                        <Banknote class="h-3.5 w-3.5" /> <span>Classification & Funding</span>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 dark:bg-amber-950/30 dark:border-amber-900">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Tag class="h-3.5 w-3.5 text-muted-foreground" /> Category <span class="text-red-500">*</span>
                                </label>
                                <select v-model="editForm.category" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <option value="Foreign">🌐 Foreign</option>
                                    <option value="Bilateral">🤝 Bilateral</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Banknote class="h-3.5 w-3.5 text-muted-foreground" /> Program Cost
                                </label>
                                <input v-model="editForm.program_cost" type="text" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-amber-500" placeholder="e.g. 50,000" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Fund Source
                                </label>
                                <select v-model="editForm.fund_source" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <option value="">— Select —</option>
                                    <option value="SDP">SDP</option>
                                    <option value="Other Office">Other Office</option>
                                    <option value="Sponsoring Organization">Sponsoring Organization</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Organizer & Status -->
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                        <Building2 class="h-3.5 w-3.5" /> <span>Organizer & Status</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Building2 class="h-3.5 w-3.5 text-muted-foreground" /> Organizing Sponsor <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <select
                                    v-model="editForm.organizing_sponsor"
                                    class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                                >
                                    <option value="">— Select sponsor —</option>
                                    <option v-for="s in sponsors" :key="s" :value="s">{{ s }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="px-3 py-2 rounded-lg border text-xs font-semibold text-amber-600 hover:bg-amber-50 transition-colors whitespace-nowrap"
                                    @click="showSponsorModal = true"
                                >
                                    + Manage
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Status <span class="text-red-500">*</span>
                            </label>
                            <select v-model="editForm.status" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Key Dates -->
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                        <CalendarDays class="h-3.5 w-3.5" /> <span>Key Dates</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Submission Date
                            </label>
                            <input v-model="editForm.submission_date" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Users class="h-3.5 w-3.5 text-muted-foreground" /> Interview Date
                            </label>
                            <input v-model="editForm.interview_date" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5 text-muted-foreground" /> Embassy Deadline
                            </label>
                            <input v-model="editForm.embassy_deadline" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>
                    </div>
                </div>

                <!-- Agencies -->
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                        <Building class="h-3.5 w-3.5" /> <span>Invited Agencies</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold flex items-center gap-1.5">
                            <Building class="h-3.5 w-3.5 text-muted-foreground" /> Agencies
                        </label>
                        <textarea v-model="editForm.invited_agencies" rows="2" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none" placeholder="Comma-separated, e.g. DILG, DBM, CSC"></textarea>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 bg-background border-t px-6 py-4 rounded-b-2xl flex justify-end gap-2">
                <Button variant="outline" @click="close">Cancel</Button>
                <Button class="bg-amber-500 hover:bg-amber-600 text-white" :disabled="editForm.processing" @click="submit">
                    <Pencil v-if="!editForm.processing" class="h-4 w-4 mr-1" />
                    {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </div>
    </div>
</template>
