<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import axios from 'axios';
import { ChevronDown, ChevronUp, LoaderCircle, Plus, Search, Trash2, UserCheck, UserPlus, Users, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const { confirmDialog } = useConfirm();

interface Member {
    id: number;
    empcode: string;
    name: string | null;
    position: string | null;
    role: string;
    representative: { empcode: string; name: string | null; position: string | null } | null;
}

function roleBadgeClass(role: string) {
    if (role === 'Chairperson, HRDC') return 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300';
    if (role === 'Vice Chairperson, HRDC') return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200';
    return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300';
}

interface EmployeeResult {
    empcode: string;
    name: string;
    position: string;
}

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', member: Member): void;
    (e: 'updated'): void;
}>();

const members = ref<Member[]>([]);
const loading = ref(false);
const deletingId = ref<number | null>(null);
const movingId = ref<number | null>(null);

const query = ref('');
const results = ref<EmployeeResult[]>([]);
const searching = ref(false);
const open = ref(false);
const adding = ref(false);
const error = ref('');
let searchTimer: ReturnType<typeof setTimeout> | undefined;

const fetchMembers = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('nhrdc-members.index'));
        members.value = data;
    } finally {
        loading.value = false;
    }
};

function onQueryInput() {
    clearTimeout(searchTimer);
    error.value = '';
    if (query.value.trim().length < 2) {
        results.value = [];
        open.value = false;
        return;
    }
    searchTimer = setTimeout(runSearch, 300);
}

async function runSearch() {
    searching.value = true;
    open.value = true;
    try {
        const { data } = await axios.get(route('foreign-nominee-assessments.search-employee'), {
            params: { q: query.value },
        });
        results.value = data;
    } catch {
        results.value = [];
    } finally {
        searching.value = false;
    }
}

const isMember = (empcode: string) => members.value.some((m) => m.empcode === empcode);

const addMember = async (emp: EmployeeResult) => {
    if (isMember(emp.empcode)) return;
    error.value = '';
    adding.value = true;
    try {
        const { data } = await axios.post(route('nhrdc-members.store'), { empcode: emp.empcode });
        members.value = [...members.value, data];
        query.value = '';
        results.value = [];
        open.value = false;
        emit('updated');
    } catch (err: any) {
        error.value = err.response?.data?.errors?.empcode?.[0] ?? 'Failed to add member.';
    } finally {
        adding.value = false;
    }
};

const deleteMember = async (member: Member) => {
    if (!(await confirmDialog(`Remove "${member.name}" from the NHRDC roster?`))) return;
    deletingId.value = member.id;
    try {
        await axios.delete(route('nhrdc-members.destroy', member.id));
        // Refetch rather than splice locally — removing a member can shift
        // who holds the Chairperson/Vice Chairperson role.
        await fetchMembers();
        emit('updated');
    } catch {
        alert('Failed to remove member.');
    } finally {
        deletingId.value = null;
    }
};

const selectMember = (member: Member) => {
    emit('select', member);
    emit('close');
};

const moveMember = async (member: Member, direction: 'up' | 'down') => {
    movingId.value = member.id;
    try {
        const routeName = direction === 'up' ? 'nhrdc-members.move-up' : 'nhrdc-members.move-down';
        const { data } = await axios.post(route(routeName, member.id));
        members.value = data;
        emit('updated');
    } finally {
        movingId.value = null;
    }
};

// ─── Representative (authorized to rate on the NHRDC member's behalf) ──────────

const repForId = ref<number | null>(null);
const repQuery = ref('');
const repResults = ref<EmployeeResult[]>([]);
const repSearching = ref(false);
const repSaving = ref(false);
const repError = ref('');
let repSearchTimer: ReturnType<typeof setTimeout> | undefined;

function openRepresentativePicker(member: Member) {
    repForId.value = repForId.value === member.id ? null : member.id;
    repQuery.value = '';
    repResults.value = [];
    repError.value = '';
}

function onRepQueryInput() {
    clearTimeout(repSearchTimer);
    repError.value = '';
    if (repQuery.value.trim().length < 2) {
        repResults.value = [];
        return;
    }
    repSearchTimer = setTimeout(async () => {
        repSearching.value = true;
        try {
            const { data } = await axios.get(route('foreign-nominee-assessments.search-employee'), { params: { q: repQuery.value } });
            repResults.value = data;
        } catch {
            repResults.value = [];
        } finally {
            repSearching.value = false;
        }
    }, 300);
}

const assignRepresentative = async (member: Member, emp: EmployeeResult) => {
    repError.value = '';
    repSaving.value = true;
    try {
        const { data } = await axios.put(route('nhrdc-members.representative.assign', member.id), { empcode: emp.empcode });
        members.value = data;
        repForId.value = null;
        emit('updated');
    } catch (err: any) {
        repError.value = err.response?.data?.errors?.empcode?.[0] ?? 'Failed to assign representative.';
    } finally {
        repSaving.value = false;
    }
};

const removeRepresentative = async (member: Member) => {
    if (!(await confirmDialog(`Remove ${member.representative?.name ?? 'this employee'} as representative of ${member.name}?`))) return;
    repSaving.value = true;
    try {
        const { data } = await axios.delete(route('nhrdc-members.representative.remove', member.id));
        members.value = data;
        emit('updated');
    } finally {
        repSaving.value = false;
    }
};

onMounted(fetchMembers);
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
        <div class="flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl bg-background shadow-2xl">
            <!-- Header -->
            <div class="flex shrink-0 items-center gap-3 border-b px-5 py-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600">
                    <Users class="h-4 w-4 text-white" />
                </div>
                <div>
                    <h3 class="text-sm font-extrabold leading-none">NHRDC Members</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">Manage who can rate nominee interviews</p>
                </div>
                <button class="ml-auto text-muted-foreground transition-colors hover:text-foreground" @click="emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Add new -->
            <div class="shrink-0 border-b px-5 py-3">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="query"
                        type="text"
                        placeholder="Search employee name or empcode…"
                        class="w-full rounded-lg border py-2 pl-8 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @input="onQueryInput"
                        @focus="query.trim().length >= 2 && (open = true)"
                    />
                    <div v-if="open" class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border bg-background shadow-lg">
                        <div v-if="searching" class="flex items-center gap-1.5 px-3 py-2 text-xs text-muted-foreground">
                            <LoaderCircle class="h-3 w-3 animate-spin" /> Searching…
                        </div>
                        <template v-else>
                            <button
                                v-for="emp in results"
                                :key="emp.empcode"
                                type="button"
                                :disabled="isMember(emp.empcode) || adding"
                                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="addMember(emp)"
                            >
                                <span>
                                    <span class="font-semibold">{{ emp.name }}</span>
                                    <span class="text-muted-foreground"> · {{ emp.position }}</span>
                                </span>
                                <span v-if="isMember(emp.empcode)" class="shrink-0 text-[10px] font-bold text-emerald-600">Added</span>
                                <Plus v-else class="h-3.5 w-3.5 shrink-0 text-indigo-600" />
                            </button>
                            <p v-if="!results.length" class="px-3 py-2 text-xs text-muted-foreground">No matches.</p>
                        </template>
                    </div>
                </div>
                <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
            </div>

            <!-- Roster -->
            <div class="flex-1 overflow-y-auto px-5 py-3">
                <div v-if="loading" class="flex items-center justify-center py-10">
                    <LoaderCircle class="h-5 w-5 animate-spin text-indigo-500" />
                </div>
                <div v-else-if="!members.length" class="flex flex-col items-center justify-center gap-2 py-10 text-center text-muted-foreground">
                    <Users class="h-8 w-8 text-slate-300" />
                    <p class="text-xs font-semibold">No NHRDC members yet.</p>
                    <p class="text-xs">Search above to add one.</p>
                </div>
                <div v-else class="divide-y overflow-hidden rounded-xl border">
                    <div v-for="(member, index) in members" :key="member.id">
                        <div class="group flex items-center justify-between gap-1 px-3 py-2.5 transition-colors hover:bg-muted/40">
                            <!-- Reorder -->
                            <div class="flex shrink-0 flex-col">
                                <button
                                    type="button"
                                    :disabled="index === 0 || movingId !== null"
                                    class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-indigo-50 hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-25 dark:hover:bg-indigo-950/40"
                                    title="Move up"
                                    @click="moveMember(member, 'up')"
                                >
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    :disabled="index === members.length - 1 || movingId !== null"
                                    class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-indigo-50 hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-25 dark:hover:bg-indigo-950/40"
                                    title="Move down"
                                    @click="moveMember(member, 'down')"
                                >
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <button
                                type="button"
                                class="min-w-0 flex-1 text-left transition-colors hover:text-indigo-600"
                                @click="selectMember(member)"
                            >
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="truncate text-sm font-semibold">{{ member.name }}</p>
                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold" :class="roleBadgeClass(member.role)">
                                        {{ member.role }}
                                    </span>
                                </div>
                                <p class="truncate text-xs text-muted-foreground">{{ member.position }}</p>
                            </button>
                            <button
                                type="button"
                                :disabled="deletingId === member.id"
                                class="shrink-0 rounded-md p-1 text-muted-foreground opacity-0 transition-colors hover:text-red-500 group-hover:opacity-100"
                                @click="deleteMember(member)"
                            >
                                <LoaderCircle v-if="deletingId === member.id" class="h-3.5 w-3.5 animate-spin" />
                                <Trash2 v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Representative: authorized to rate on this member's behalf -->
                        <div class="border-t border-dashed bg-muted/20 py-2 pl-9 pr-3">
                            <div v-if="member.representative" class="flex items-center gap-2">
                                <UserCheck class="h-3.5 w-3.5 shrink-0 text-emerald-600" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs">
                                        <span class="text-muted-foreground">Representative:</span>
                                        <span class="font-semibold">{{ member.representative.name }}</span>
                                    </p>
                                    <p class="truncate text-[11px] text-muted-foreground">{{ member.representative.position }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-[11px] font-semibold text-indigo-600 hover:underline"
                                    @click="openRepresentativePicker(member)"
                                >
                                    Change
                                </button>
                                <button
                                    type="button"
                                    :disabled="repSaving"
                                    class="shrink-0 text-[11px] font-semibold text-red-500 hover:underline disabled:opacity-50"
                                    @click="removeRepresentative(member)"
                                >
                                    Remove
                                </button>
                            </div>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:underline"
                                @click="openRepresentativePicker(member)"
                            >
                                <UserPlus class="h-3.5 w-3.5" /> Assign representative
                            </button>

                            <div v-if="repForId === member.id" class="mt-2">
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        v-model="repQuery"
                                        type="text"
                                        placeholder="Search representative name or empcode…"
                                        class="w-full rounded-md border bg-background py-1.5 pl-7 pr-3 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        @input="onRepQueryInput"
                                    />
                                </div>
                                <div v-if="repQuery.trim().length >= 2" class="mt-1 max-h-40 overflow-y-auto rounded-md border bg-background">
                                    <div v-if="repSearching" class="flex items-center gap-1.5 px-3 py-2 text-xs text-muted-foreground">
                                        <LoaderCircle class="h-3 w-3 animate-spin" /> Searching…
                                    </div>
                                    <template v-else>
                                        <button
                                            v-for="emp in repResults"
                                            :key="emp.empcode"
                                            type="button"
                                            :disabled="repSaving || isMember(emp.empcode)"
                                            class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-xs transition-colors hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                                            @click="assignRepresentative(member, emp)"
                                        >
                                            <span>
                                                <span class="font-semibold">{{ emp.name }}</span>
                                                <span class="text-muted-foreground"> · {{ emp.position }}</span>
                                            </span>
                                            <span v-if="isMember(emp.empcode)" class="shrink-0 text-[10px] font-bold text-muted-foreground"
                                                >NHRDC member</span
                                            >
                                            <UserPlus v-else class="h-3.5 w-3.5 shrink-0 text-indigo-600" />
                                        </button>
                                        <p v-if="!repResults.length" class="px-3 py-2 text-xs text-muted-foreground">No matches.</p>
                                    </template>
                                </div>
                                <p v-if="repError" class="mt-1 text-xs text-red-500">{{ repError }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 border-t px-5 py-3 text-right">
                <p class="text-xs text-muted-foreground">
                    Use the arrows to reorder — the top two set the Chairperson and Vice Chairperson. Click a name to assign them. A representative
                    can rate nominees on the member's behalf; the NHRDC member remains the signatory.
                </p>
            </div>
        </div>
    </div>
</template>
