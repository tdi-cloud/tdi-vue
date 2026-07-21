<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { X, Plus, Trash2, Users, LoaderCircle, Search, ChevronUp, ChevronDown } from 'lucide-vue-next';
import axios from 'axios';

interface Member {
    id: number;
    empcode: string;
    name: string | null;
    position: string | null;
    role: string;
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

const members     = ref<Member[]>([]);
const loading     = ref(false);
const deletingId  = ref<number | null>(null);
const movingId    = ref<number | null>(null);

const query       = ref('');
const results     = ref<EmployeeResult[]>([]);
const searching   = ref(false);
const open        = ref(false);
const adding      = ref(false);
const error       = ref('');
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
    if (!confirm(`Remove "${member.name}" from the NHRDC roster?`)) return;
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

onMounted(fetchMembers);
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
        <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md flex flex-col max-h-[80vh]">

            <!-- Header -->
            <div class="flex items-center gap-3 px-5 py-4 border-b shrink-0">
                <div class="flex items-center justify-center h-8 w-8 rounded-xl bg-indigo-600">
                    <Users class="h-4 w-4 text-white" />
                </div>
                <div>
                    <h3 class="text-sm font-extrabold leading-none">NHRDC Members</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Manage who can rate nominee interviews</p>
                </div>
                <button class="ml-auto text-muted-foreground hover:text-foreground transition-colors" @click="emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Add new -->
            <div class="px-5 py-3 border-b shrink-0">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                    <input
                        v-model="query"
                        type="text"
                        placeholder="Search employee name or empcode…"
                        class="w-full border rounded-lg pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @input="onQueryInput"
                        @focus="query.trim().length >= 2 && (open = true)"
                    />
                    <div
                        v-if="open"
                        class="absolute z-10 mt-1 w-full rounded-lg border bg-background shadow-lg max-h-48 overflow-y-auto"
                    >
                        <div v-if="searching" class="px-3 py-2 text-xs text-muted-foreground flex items-center gap-1.5">
                            <LoaderCircle class="h-3 w-3 animate-spin" /> Searching…
                        </div>
                        <template v-else>
                            <button
                                v-for="emp in results"
                                :key="emp.empcode"
                                type="button"
                                :disabled="isMember(emp.empcode) || adding"
                                class="w-full flex items-center justify-between gap-2 text-left px-3 py-2 text-xs hover:bg-muted/50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="addMember(emp)"
                            >
                                <span>
                                    <span class="font-semibold">{{ emp.name }}</span>
                                    <span class="text-muted-foreground"> · {{ emp.position }}</span>
                                </span>
                                <span v-if="isMember(emp.empcode)" class="text-[10px] font-bold text-emerald-600 shrink-0">Added</span>
                                <Plus v-else class="h-3.5 w-3.5 text-indigo-600 shrink-0" />
                            </button>
                            <p v-if="!results.length" class="px-3 py-2 text-xs text-muted-foreground">No matches.</p>
                        </template>
                    </div>
                </div>
                <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
            </div>

            <!-- Roster -->
            <div class="flex-1 overflow-y-auto px-5 py-3">
                <div v-if="loading" class="flex items-center justify-center py-10">
                    <LoaderCircle class="h-5 w-5 animate-spin text-indigo-500" />
                </div>
                <div v-else-if="!members.length" class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground gap-2">
                    <Users class="h-8 w-8 text-slate-300" />
                    <p class="text-xs font-semibold">No NHRDC members yet.</p>
                    <p class="text-xs">Search above to add one.</p>
                </div>
                <div v-else class="divide-y rounded-xl border overflow-hidden">
                    <div
                        v-for="(member, index) in members"
                        :key="member.id"
                        class="flex items-center justify-between gap-1 px-3 py-2.5 hover:bg-muted/40 transition-colors group"
                    >
                        <!-- Reorder -->
                        <div class="flex flex-col shrink-0">
                            <button
                                type="button"
                                :disabled="index === 0 || movingId !== null"
                                class="rounded p-0.5 text-muted-foreground hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 disabled:opacity-25 disabled:cursor-not-allowed transition-colors"
                                title="Move up"
                                @click="moveMember(member, 'up')"
                            >
                                <ChevronUp class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                :disabled="index === members.length - 1 || movingId !== null"
                                class="rounded p-0.5 text-muted-foreground hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 disabled:opacity-25 disabled:cursor-not-allowed transition-colors"
                                title="Move down"
                                @click="moveMember(member, 'down')"
                            >
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <button
                            type="button"
                            class="text-left flex-1 min-w-0 hover:text-indigo-600 transition-colors"
                            @click="selectMember(member)"
                        >
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <p class="text-sm font-semibold truncate">{{ member.name }}</p>
                                <span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full" :class="roleBadgeClass(member.role)">
                                    {{ member.role }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground truncate">{{ member.position }}</p>
                        </button>
                        <button
                            type="button"
                            :disabled="deletingId === member.id"
                            class="text-muted-foreground hover:text-red-500 transition-colors p-1 rounded-md opacity-0 group-hover:opacity-100 shrink-0"
                            @click="deleteMember(member)"
                        >
                            <LoaderCircle v-if="deletingId === member.id" class="h-3.5 w-3.5 animate-spin" />
                            <Trash2 v-else class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-5 py-3 border-t shrink-0 text-right">
                <p class="text-xs text-muted-foreground">Use the arrows to reorder — the top two set the Chairperson and Vice Chairperson. Click a name to assign them.</p>
            </div>

        </div>
    </div>
</template>
