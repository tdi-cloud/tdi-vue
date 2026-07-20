<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import {
    Search, AlertTriangle, Users, FileX,
    Mail, Building2, BadgeCheck, X, ChevronDown, ChevronUp,
    History, MailCheck, ListChecks, ChevronRight,
} from 'lucide-vue-next';
import EmailReminderModal from '@/pages/programs/EmailReminderModal.vue';

interface Employee {
    EMPCODE?: string; FIRSTNAME?: string; LASTNAME?: string; SURNAME?: string;
    MI?: string; FULLNAME?: string; fullname?: string; name?: string;
    POSITION?: string; 'OFFICE/DIVISION'?: string; OFFICE?: string;
    [key: string]: any;
}
interface ParticipantInBatch {
    id: number; empcode: string; attendance: string;
    employee?: Employee | null; user_email?: string | null;
}
interface Requirement { id: number; title: string; name: string; due_date: string; }
interface BatchWithDetails {
    id: number; batch: string;
    participants?: ParticipantInBatch[]; requirements?: Requirement[];
}
interface Submission {
    participant_id: number;
    requirement_id: number;
    batch_id: number;
    participant?: { empcode: string } | null;
    requirement?: { title: string } | null;
}
interface ReminderRecipient { empcode: string | null; name: string | null; email: string | null; }
interface ReminderLog {
    id: number;
    sent_by: string | null;
    sent_by_name: string | null;
    program_id: number | null;
    batch_id: number | null;
    requirement_id: number | null;
    subject: string;
    recipients: ReminderRecipient[];
    recipients_count: number;
    created_at: string;
}
interface Program { id?: number; title?: string; batches?: BatchWithDetails[]; email_reminder_logs?: ReminderLog[]; }
interface MissingEntry {
    participant_id: number; empcode: string; batch_id: number; batch_name: string;
    requirement_id: number; requirement_title: string; requirement_name: string;
    due_date: string | null; employee_name: string | null; employee_position: string | null;
    employee_office: string | null; employee_email: string | null;
}
interface Group {
    key: string; batch: string; requirement_title: string;
    requirement_name: string; due_date: string | null; entries: MissingEntry[];
}

const props = defineProps<{ open: boolean; program: Program; submissions: Submission[]; }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const allMissing = computed((): MissingEntry[] => {
    // Key: empcode + batch_id + requirement title
    // Hindi natin magagamit ang requirement_id dahil may 355 submissions na
    // ang requirement_id ay galing sa ibang batch/program (cross-program mismatch).
    // Ang tamang paraan: kung ang empcode ay may submission sa parehong batch
    // na may parehong requirement title, ibibilang na siyang nakasubmit.
    const submittedSet = new Set(
        props.submissions.map((s) => `${s.participant?.empcode ?? s.participant_id}__${s.batch_id}__${s.requirement?.title ?? ''}`)
    );
    const result: MissingEntry[] = [];
    for (const batch of props.program.batches ?? []) {
        for (const req of batch.requirements ?? []) {
            for (const p of batch.participants ?? []) {
                if (p.attendance?.toLowerCase() === 'absent') continue;
                if (submittedSet.has(`${p.empcode}__${batch.id}__${req.title}`)) continue;
                const emp = p.employee;
                const mi = (emp?.MI ?? '').trim();
                const miStr = mi ? ` ${mi.replace(/\.?$/, '.')}` : '';
                const fullName = emp
                    ? (emp.FULLNAME ?? emp.fullname ?? emp.name ??
                       ((`${emp.FIRSTNAME ?? ''}${miStr} ${emp.LASTNAME ?? emp.SURNAME ?? ''}`).trim() || null))
                    : null;
                result.push({
                    participant_id: p.id, empcode: p.empcode,
                    batch_id: batch.id, batch_name: batch.batch,
                    requirement_id: req.id, requirement_title: req.title,
                    requirement_name: req.name, due_date: req.due_date ?? null,
                    employee_name: fullName, employee_position: emp?.POSITION ?? null,
                    employee_office: emp?.['OFFICE/DIVISION'] ?? emp?.OFFICE ?? null,
                    employee_email: p.user_email ?? null,
                });
            }
        }
    }
    return result;
});

const search      = ref('');
const filterBatch = ref('all');
const filterReq   = ref('all');

const batches = computed(() => (props.program.batches ?? []).map((b) => ({ id: b.id, batch: b.batch })));
const requirementTitles = computed(() => [...new Set(allMissing.value.map((m) => m.requirement_title))].sort());

const filtered = computed((): MissingEntry[] => {
    const q = search.value.toLowerCase().trim();
    return allMissing.value.filter((m) => {
        if (filterBatch.value !== 'all' && String(m.batch_id) !== filterBatch.value) return false;
        if (filterReq.value !== 'all' && m.requirement_title !== filterReq.value) return false;
        if (q) {
            const hay = [m.employee_name ?? '', m.empcode, m.requirement_title, m.requirement_name, m.employee_office ?? '']
                .join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });
});

const grouped = computed((): Group[] => {
    const map = new Map<string, Group>();
    for (const m of filtered.value) {
        const key = `${m.batch_id}__${m.requirement_id}`;
        if (!map.has(key)) {
            map.set(key, { key, batch: m.batch_name, requirement_title: m.requirement_title,
                requirement_name: m.requirement_name, due_date: m.due_date, entries: [] });
        }
        map.get(key)!.entries.push(m);
    }
    return [...map.values()];
});

const collapsed = ref<Record<string, boolean>>({});

// Default: lahat ng groups ay collapsed para makita agad ang lahat ng group headers.
// Kapag lumabas ang bagong group (dahil sa filter), i-collapse din siya by default.
watch(grouped, (newGroups, _oldGroups, onCleanup) => {
    newGroups.forEach((g, index) => {
        if (collapsed.value[g.key] === undefined) {
            // Unang group ay naka-expand by default, lahat ng iba ay collapsed
            collapsed.value[g.key] = index !== 0;
        }
    });
}, { immediate: true });

const toggle = (key: string) => { collapsed.value[key] = !collapsed.value[key]; };

const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const isOverdue  = (d: string | null) => !!d && new Date(d) < new Date();
const initials   = (name: string | null) => {
    if (!name) return '??';
    return name.split(' ').filter(Boolean).slice(0, 2).map((n) => n[0].toUpperCase()).join('');
};
const avatarColor = (empcode: string) => {
    const colors = ['bg-violet-500', 'bg-blue-500', 'bg-emerald-500', 'bg-rose-500',
                    'bg-amber-500', 'bg-indigo-500', 'bg-teal-500', 'bg-pink-500'];
    return colors[empcode.charCodeAt(0) % colors.length];
};

defineExpose({ missingCount: computed(() => allMissing.value.length) });

/* ===================== REMINDER HISTORY / ALREADY-REMINDED LOOKUP ===================== */

const reminderLogs = computed(() => props.program.email_reminder_logs ?? []);

// Key: empcode + batch_id + requirement_id -> pinaka-huling reminder log na
// nagpadala sa kaniya para dito. Ang `reminderLogs` ay latest-first na mula
// sa backend, kaya ang unang match sa bawat key ang pinaka-bago.
const lastReminderFor = computed(() => {
    const map = new Map<string, ReminderLog>();
    for (const log of reminderLogs.value) {
        for (const r of log.recipients ?? []) {
            if (!r.empcode) continue;
            const key = `${r.empcode}__${log.batch_id}__${log.requirement_id}`;
            if (!map.has(key)) map.set(key, log);
        }
    }
    return map;
});

const reminderFor = (entry: MissingEntry): ReminderLog | undefined =>
    lastReminderFor.value.get(`${entry.empcode}__${entry.batch_id}__${entry.requirement_id}`);

const showHistory = ref(false);

const formatDateTime = (d: string) =>
    new Date(d).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

const expandedLog = ref<number | null>(null);
const toggleLogRecipients = (id: number) => { expandedLog.value = expandedLog.value === id ? null : id; };

/* ===================== EMAIL REMINDER ===================== */

interface EmailTarget {
    batchId: number | null;
    requirementId: number | null;
    batchName: string;
    requirementTitle: string;
    requirementName: string;
    dueDate: string | null;
    participants: Array<{ empcode: string; employee_name: string | null; employee_email: string | null }>;
}

const showEmail = ref(false);
const emailTarget = ref<EmailTarget>({
    batchId: null,
    requirementId: null,
    batchName: '',
    requirementTitle: '',
    requirementName: '',
    dueDate: null,
    participants: [],
});

const openEmailReminder = (group: Group) => {
    emailTarget.value = {
        batchId: group.entries[0]?.batch_id ?? null,
        requirementId: group.entries[0]?.requirement_id ?? null,
        batchName: group.batch,
        requirementTitle: group.requirement_title,
        requirementName: group.requirement_name,
        dueDate: group.due_date,
        participants: group.entries.map((e) => ({
            empcode: e.empcode,
            employee_name: e.employee_name,
            employee_email: e.employee_email,
        })),
    };
    showEmail.value = true;
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">

                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/80"
                    @click="emit('update:open', false)"
                />

                <!-- Modal panel -->
                <div
                    class="relative z-10 w-full max-w-3xl mx-4 rounded-2xl border bg-background shadow-lg"
                    style="display:flex; flex-direction:column; height:88vh; max-height:88vh;"
                >

                    <!-- ─── HEADER ─── -->
                    <div class="px-6 pt-5 pb-4 border-b shrink-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="flex items-center justify-center w-8 h-8 rounded-xl bg-red-100 dark:bg-red-900/40 shrink-0">
                                    <FileX class="h-4 w-4 text-red-600 dark:text-red-400" />
                                </span>
                                <div>
                                    <h2 class="text-base font-semibold leading-none">Incomplete Submissions</h2>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        Participants who have not yet completed one or more required documents.
                                        Absent participants are excluded.
                                    </p>
                                </div>
                            </div>
                            <!-- Close X button -->
                            <button
                                type="button"
                                class="shrink-0 rounded-sm opacity-70 hover:opacity-100 transition-opacity"
                                @click="emit('update:open', false)"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Stats pills -->
                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold">
                                <Users class="h-3.5 w-3.5 text-blue-500" />
                                {{ new Set(allMissing.map(m => m.participant_id)).size }} participant(s)
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold">
                                <FileX class="h-3.5 w-3.5 text-red-500" />
                                {{ allMissing.length }} incomplete submission{{ allMissing.length !== 1 ? 's' : '' }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold"
                                :class="allMissing.filter(m => isOverdue(m.due_date)).length
                                    ? 'text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20'
                                    : 'text-muted-foreground'"
                            >
                                <AlertTriangle class="h-3.5 w-3.5" />
                                {{ allMissing.filter(m => isOverdue(m.due_date)).length }} overdue
                            </span>

                            <button
                                type="button"
                                class="ml-auto inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold transition-colors"
                                :class="showHistory
                                    ? 'bg-blue-600 border-blue-600 text-white'
                                    : 'hover:bg-muted/50 text-muted-foreground'"
                                @click="showHistory = !showHistory"
                            >
                                <History class="h-3.5 w-3.5" />
                                Reminder History
                                <span
                                    v-if="reminderLogs.length"
                                    class="rounded-full px-1.5"
                                    :class="showHistory ? 'bg-white/20' : 'bg-muted'"
                                >{{ reminderLogs.length }}</span>
                            </button>
                        </div>

                        <!-- Filters -->
                        <div v-if="!showHistory" class="flex flex-wrap items-center gap-2 mt-3">
                            <div class="relative flex-1 min-w-[160px]">
                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                <Input v-model="search" class="text-xs h-8 pl-8" placeholder="Search by name, code, office…" />
                            </div>
                            <Select v-model="filterBatch">
                                <SelectTrigger class="text-xs h-8 w-36 shrink-0">
                                    <SelectValue placeholder="All batches" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="all">All batches</SelectItem>
                                    <SelectItem v-for="b in batches" :key="b.id" :value="String(b.id)" class="text-xs">{{ b.batch }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select v-model="filterReq">
                                <SelectTrigger class="text-xs h-8 w-40 shrink-0">
                                    <SelectValue placeholder="All requirements" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="all">All requirements</SelectItem>
                                    <SelectItem v-for="t in requirementTitles" :key="t" :value="t" class="text-xs">{{ t }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- ─── BODY (only this scrolls) ─── -->
                    <div class="px-6 py-4 flex flex-col gap-3" style="flex:1; min-height:0; overflow-y:auto;">

                      <template v-if="!showHistory">

                        <!-- All clear -->
                        <div v-if="allMissing.length === 0"
                            class="flex flex-col items-center justify-center py-16 text-center gap-3">
                            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30">
                                <BadgeCheck class="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <p class="text-sm font-bold">All submissions are in!</p>
                            <p class="text-xs text-muted-foreground max-w-xs">Every participant has completed all required documents.</p>
                        </div>

                        <!-- No filter match -->
                        <div v-else-if="filtered.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-center gap-2">
                            <Search class="h-8 w-8 text-muted-foreground/50" />
                            <p class="text-sm font-bold text-muted-foreground">No results match your filters</p>
                            <p class="text-xs text-muted-foreground">Try adjusting the search or filter options.</p>
                        </div>

                        <!-- Grouped list -->
                        <template v-else>
                            <p class="text-[11px] text-muted-foreground">
                                Showing <span class="font-semibold">{{ filtered.length }}</span> incomplete submission(s)
                                across <span class="font-semibold">{{ grouped.length }}</span> group(s)
                            </p>

                            <div v-for="group in grouped" :key="group.key" class="rounded-2xl border shadow-sm overflow-hidden">

                                <!-- Group toggle header -->
                                <!-- Div instead of button — hindi pwede mag-nest ng button sa loob ng button -->
                                <div
                                    class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-muted/40 hover:bg-muted/70 transition-colors cursor-pointer select-none"
                                    @click="toggle(group.key)"
                                >
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 px-2.5 py-0.5 text-[11px] font-bold">
                                            <FileX class="h-3 w-3" /> {{ group.requirement_title }}
                                        </span>
                                        <span class="text-xs text-muted-foreground">{{ group.requirement_name }}</span>
                                        <span class="text-[11px] text-muted-foreground">·</span>
                                        <span class="text-[11px] font-semibold">{{ group.batch }}</span>
                                        <span
                                            v-if="group.due_date"
                                            class="text-[11px] px-2 py-0.5 rounded-full font-semibold"
                                            :class="isOverdue(group.due_date)
                                                ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
                                                : 'bg-slate-100 dark:bg-slate-800 text-muted-foreground'"
                                        >
                                            {{ isOverdue(group.due_date) ? '⚠ Overdue' : 'Due' }}: {{ formatDate(group.due_date) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[11px] font-bold text-muted-foreground">{{ group.entries.length }} incomplete</span>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 text-[11px] font-semibold transition-colors"
                                            @click.stop="openEmailReminder(group)"
                                        >
                                            <Mail class="h-3 w-3" /> Email Reminder
                                        </button>
                                        <ChevronDown v-if="collapsed[group.key]" class="h-4 w-4 text-muted-foreground" />
                                        <ChevronUp v-else class="h-4 w-4 text-muted-foreground" />
                                    </div>
                                </div>

                                <!-- Participant rows -->
                                <div v-if="!collapsed[group.key]" class="divide-y" style="max-height:320px; overflow-y:auto;">
                                    <div
                                        v-for="m in group.entries"
                                        :key="`${m.participant_id}-${m.requirement_id}`"
                                        class="flex items-center gap-3 px-4 py-3"
                                    >
                                        <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold text-white"
                                            :class="avatarColor(m.empcode)">
                                            {{ initials(m.employee_name) }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold leading-tight truncate">
                                                {{ m.employee_name ?? m.empcode }}
                                                <span class="font-normal text-xs text-muted-foreground ml-1">({{ m.empcode }})</span>
                                            </p>
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                                                <span v-if="m.employee_position" class="text-[11px] text-muted-foreground flex items-center gap-1">
                                                    <BadgeCheck class="h-3 w-3" /> {{ m.employee_position }}
                                                </span>
                                                <span v-if="m.employee_office" class="text-[11px] text-muted-foreground flex items-center gap-1">
                                                    <Building2 class="h-3 w-3" /> {{ m.employee_office }}
                                                </span>
                                                <a v-if="m.employee_email" :href="`mailto:${m.employee_email}`"
                                                    class="text-[11px] text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                                                    <Mail class="h-3 w-3" /> {{ m.employee_email }}
                                                </a>
                                                <span v-else class="text-[11px] text-muted-foreground/60 flex items-center gap-1 italic">
                                                    <Mail class="h-3 w-3" /> No email on file
                                                </span>
                                                <span
                                                    v-if="reminderFor(m)"
                                                    class="text-[11px] font-semibold flex items-center gap-1 text-emerald-600 dark:text-emerald-400"
                                                    :title="`Sent by ${reminderFor(m)!.sent_by_name ?? reminderFor(m)!.sent_by ?? 'system'}`"
                                                >
                                                    <MailCheck class="h-3 w-3" /> Reminded {{ formatDateTime(reminderFor(m)!.created_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </template>

                      </template>

                      <!-- ─── REMINDER HISTORY ─── -->
                      <template v-else>
                        <div v-if="reminderLogs.length === 0"
                            class="flex flex-col items-center justify-center py-16 text-center gap-3">
                            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-muted">
                                <History class="h-8 w-8 text-muted-foreground/50" />
                            </div>
                            <p class="text-sm font-bold">No reminders sent yet</p>
                            <p class="text-xs text-muted-foreground max-w-xs">Once you send an email reminder, it will show up here with the date and recipients.</p>
                        </div>

                        <template v-else>
                            <p class="text-[11px] text-muted-foreground">
                                {{ reminderLogs.length }} reminder{{ reminderLogs.length !== 1 ? 's' : '' }} sent for this program
                            </p>

                            <div v-for="log in reminderLogs" :key="log.id" class="rounded-2xl border shadow-sm overflow-hidden">
                                <div
                                    class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-muted/40 hover:bg-muted/70 transition-colors cursor-pointer select-none"
                                    @click="toggleLogRecipients(log.id)"
                                >
                                    <div class="flex items-center gap-2 flex-wrap min-w-0">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2.5 py-0.5 text-[11px] font-bold shrink-0">
                                            <MailCheck class="h-3 w-3" /> {{ log.recipients_count }} recipient{{ log.recipients_count !== 1 ? 's' : '' }}
                                        </span>
                                        <span class="text-xs font-semibold truncate">{{ log.subject }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[11px] text-muted-foreground">{{ formatDateTime(log.created_at) }}</span>
                                        <ChevronRight v-if="expandedLog !== log.id" class="h-4 w-4 text-muted-foreground" />
                                        <ChevronDown v-else class="h-4 w-4 text-muted-foreground" />
                                    </div>
                                </div>

                                <div class="px-4 py-2 text-[11px] text-muted-foreground border-t bg-background flex items-center gap-1.5">
                                    <ListChecks class="h-3 w-3" />
                                    Sent by <span class="font-semibold text-foreground">{{ log.sent_by_name ?? log.sent_by ?? 'system' }}</span>
                                </div>

                                <div v-if="expandedLog === log.id" class="divide-y" style="max-height:260px; overflow-y:auto;">
                                    <div v-for="(r, idx) in log.recipients" :key="idx" class="flex items-center gap-3 px-4 py-2.5">
                                        <div class="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white"
                                            :class="avatarColor(r.empcode ?? r.email ?? String(idx))">
                                            {{ initials(r.name ?? r.email) }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold truncate">{{ r.name ?? r.email ?? 'Unknown' }}</p>
                                            <p class="text-[11px] text-muted-foreground truncate">{{ r.email }}<span v-if="r.empcode"> · {{ r.empcode }}</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                      </template>

                    </div>

                    <!-- ─── FOOTER ─── -->
                    <div class="px-6 py-3 border-t shrink-0 flex justify-end">
                        <Button variant="outline" size="sm" @click="emit('update:open', false)">
                            <X class="h-3.5 w-3.5 mr-1" /> Close
                        </Button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Email Reminder Compose Modal -->
    <EmailReminderModal
        v-show="showEmail"
        :open="showEmail"
        :program-id="program.id ?? null"
        :batch-id="emailTarget.batchId"
        :requirement-id="emailTarget.requirementId"
        :program-title="program.title ?? ''"
        :batch-name="emailTarget.batchName"
        :requirement-title="emailTarget.requirementTitle"
        :requirement-name="emailTarget.requirementName"
        :due-date="emailTarget.dueDate"
        :participants="emailTarget.participants"
        @update:open="showEmail = $event"
    />
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>