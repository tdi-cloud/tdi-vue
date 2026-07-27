<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useConfirm } from '@/composables/useConfirm';
import { useInitials } from '@/composables/useInitials';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Search, ShieldCheck, X, SlidersHorizontal,
    Mail, Hash, Crown, ChevronLeft, ChevronRight, Pencil,
} from 'lucide-vue-next';

interface UserRow {
    id: number;
    name: string;
    email: string;
    empcode: string | null;
    access: string;
    avatar: string | null;
}

interface PaginatedUsers {
    data: UserRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    users: PaginatedUsers;
    accessLevels: string[];
    filters: {
        search?: string;
        access?: string;
    };
}>();

const page = usePage();
const currentUserId = computed(() => (page.props.auth as any)?.user?.id);

const search = ref(props.filters.search ?? '');
const access = ref(props.filters.access ?? 'all');

const hasActiveFilters = computed(() => search.value !== '' || access.value !== 'all');

const clearFilters = () => {
    search.value = '';
    access.value = 'all';
};

let debounce: ReturnType<typeof setTimeout>;
watch([search, access], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('user-management.index'), {
            search: search.value || undefined,
            access: access.value !== 'all' ? access.value : undefined,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 350);
});

const accessColor = (level: string) => {
    if (level === 'superadmin') return 'bg-rose-100 text-rose-700 border-rose-200';
    if (level === 'admin') return 'bg-violet-100 text-violet-700 border-violet-200';
    if (level === 'user') return 'bg-blue-100 text-blue-700 border-blue-200';
    return 'bg-gray-100 text-gray-600 border-gray-200';
};

const savingId = ref<number | null>(null);

const updateAccess = (user: UserRow, newAccess: string) => {
    if (newAccess === user.access) return;
    savingId.value = user.id;
    router.put(route('user-management.update', user.id), { access: newAccess }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { savingId.value = null; },
    });
};

/* ---- Edit modal: name + avatar ---- */
const { confirmDialog } = useConfirm();
const { getInitials } = useInitials();

const editingUser = ref<UserRow | null>(null);
const nameDraft = ref('');
const nameError = ref('');
const savingName = ref(false);

const avatarInput = ref<HTMLInputElement | null>(null);
const avatarProcessing = ref(false);
const avatarError = ref('');
const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

function openEditModal(user: UserRow) {
    editingUser.value = user;
    nameDraft.value = user.name;
    nameError.value = '';
    avatarError.value = '';
}

function closeEditModal() {
    editingUser.value = null;
}

// Kapag na-reload na ang page props pagkatapos ng update, hanapin ulit ang
// parehong user sa bagong `users.data` para ma-refresh ang laman ng modal
// (avatar/name) nang hindi ito nagsasara.
function refreshEditingUser(userId: number) {
    const updated = props.users.data.find(u => u.id === userId);
    if (updated) editingUser.value = updated;
}

function saveName() {
    if (!editingUser.value) return;
    if (!nameDraft.value.trim()) {
        nameError.value = 'Name is required.';
        return;
    }
    const userId = editingUser.value.id;
    savingName.value = true;
    nameError.value = '';
    router.put(route('user-management.update', userId), { name: nameDraft.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => refreshEditingUser(userId),
        onError: (errors) => { nameError.value = errors.name || 'Failed to update name.'; },
        onFinish: () => { savingName.value = false; },
    });
}

function triggerAvatarPick() {
    avatarError.value = '';
    avatarInput.value?.click();
}

function handleAvatarChange(e: Event) {
    if (!editingUser.value) return;
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        avatarError.value = 'Please choose a JPG, PNG, or WEBP image.';
        return;
    }
    if (file.size > MAX_AVATAR_BYTES) {
        avatarError.value = 'Image is too large — please choose one under 2MB.';
        return;
    }

    avatarError.value = '';
    avatarProcessing.value = true;
    const userId = editingUser.value.id;
    const data = new FormData();
    data.append('avatar', file);
    router.post(route('user-management.avatar.update', userId), data, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => refreshEditingUser(userId),
        onError: (errors) => { avatarError.value = errors.avatar || 'Upload failed. Please try again.'; },
        onFinish: () => { avatarProcessing.value = false; },
    });
}

async function removeAvatar() {
    if (!editingUser.value) return;
    if (!(await confirmDialog(`Remove ${editingUser.value.name}'s profile picture?`))) return;
    const userId = editingUser.value.id;
    avatarProcessing.value = true;
    router.delete(route('user-management.avatar.destroy', userId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => refreshEditingUser(userId),
        onFinish: () => { avatarProcessing.value = false; },
    });
}
</script>

<template>
    <Head title="User Management" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center h-10 w-10 rounded-xl bg-rose-600 shadow-md">
                    <ShieldCheck class="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold leading-none">User Management</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Manage user access levels — superadmin only</p>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name, email, or empcode..."
                            class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 bg-background shadow-sm"
                        />
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground px-2 py-1.5 rounded-lg border bg-muted/30">
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        <span>Filters</span>
                    </div>
                    <button v-if="hasActiveFilters" class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors px-2 py-1.5" @click="clearFilters">
                        <X class="h-3.5 w-3.5" /> Clear all
                    </button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 p-3 rounded-xl border bg-muted/30">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <Crown class="h-3 w-3" /> Access Level
                        </label>
                        <select v-model="access" class="border rounded-lg px-2 py-1.5 text-xs bg-background shadow-sm">
                            <option value="all">All</option>
                            <option v-for="lvl in accessLevels" :key="lvl" :value="lvl">{{ lvl.toUpperCase() }}</option>
                        </select>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of {{ users.total }} user(s)
                </p>
            </div>

            <!-- Table -->
            <div class="rounded-2xl border overflow-hidden shadow-sm bg-background">
                <table v-if="users.data.length" class="w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-rose-50 via-red-50 to-orange-50 dark:from-rose-950/40 dark:via-red-950/40 dark:to-orange-950/40 border-b-2 border-rose-200 dark:border-rose-900">
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-rose-700 dark:text-rose-300">Empcode</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-rose-700 dark:text-rose-300">User</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide text-rose-700 dark:text-rose-300">Current Access</th>
                            <th class="text-right font-bold px-4 py-3 text-xs uppercase tracking-wide text-rose-700 dark:text-rose-300">Change Access</th>
                            <th class="text-center font-bold px-4 py-3 text-xs uppercase tracking-wide text-rose-700 dark:text-rose-300">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="u in users.data" :key="u.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 text-muted-foreground font-mono text-xs">
                                <span class="flex items-center gap-1.5"><Hash class="h-3 w-3" /> {{ u.empcode ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <Avatar class="h-8 w-8 overflow-hidden rounded-full bg-rose-600 shrink-0">
                                        <AvatarImage v-if="u.avatar" :src="u.avatar" :alt="u.name" />
                                        <AvatarFallback class="rounded-full text-xs font-extrabold text-white">
                                            {{ getInitials(u.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0">
                                        <p class="font-bold text-sm leading-tight truncate">{{ u.name }}</p>
                                        <p class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5"><Mail class="h-3 w-3" /> {{ u.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border" :class="accessColor(u.access)">
                                    <Crown v-if="u.access === 'superadmin'" class="h-3 w-3" />
                                    {{ u.access.toUpperCase() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <select
                                    :value="u.access"
                                    :disabled="u.id === currentUserId || savingId === u.id"
                                    :title="u.id === currentUserId ? 'You cannot change your own access level' : ''"
                                    class="border rounded-lg px-2 py-1.5 text-xs bg-background shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                    @change="updateAccess(u, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option v-for="lvl in accessLevels" :key="lvl" :value="lvl">{{ lvl.toUpperCase() }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-muted-foreground hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors"
                                    title="Edit name / profile picture"
                                    @click="openEditModal(u)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground gap-2">
                    <ShieldCheck class="h-10 w-10 opacity-30" />
                    <p class="text-sm font-semibold">No users found.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-muted-foreground text-xs">Page {{ users.current_page }} of {{ users.last_page }}</p>
                <div class="flex items-center gap-1">
                    <template v-for="link in users.links" :key="link.label">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-rose-600 text-white border-rose-600' : 'hover:bg-muted text-muted-foreground'"
                        >
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </a>
                        <span v-else class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-xs text-muted-foreground opacity-40">
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </span>
                    </template>
                </div>
            </div>

        </div>

        <!-- ===== Edit User Modal (name + avatar) ===== -->
        <Teleport to="body">
            <div
                v-if="editingUser"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                @click.self="closeEditModal"
            >
                <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md">
                    <div class="flex items-center gap-3 px-5 py-4 border-b">
                        <div class="flex items-center justify-center h-9 w-9 rounded-xl bg-rose-600 shrink-0">
                            <Pencil class="h-4 w-4 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-extrabold leading-none">Edit User</h3>
                            <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ editingUser.email }}</p>
                        </div>
                        <button class="ml-auto text-muted-foreground hover:text-foreground transition-colors shrink-0" @click="closeEditModal">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-5 flex flex-col gap-6">
                        <!-- Avatar -->
                        <div class="flex items-center gap-4">
                            <Avatar class="h-16 w-16 overflow-hidden rounded-full bg-rose-600 shrink-0">
                                <AvatarImage v-if="editingUser.avatar" :src="editingUser.avatar" :alt="editingUser.name" />
                                <AvatarFallback class="rounded-full text-lg font-extrabold text-white">
                                    {{ getInitials(editingUser.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="flex flex-col gap-2 min-w-0">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold hover:bg-muted transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="avatarProcessing"
                                        @click="triggerAvatarPick"
                                    >
                                        {{ avatarProcessing ? 'Uploading…' : editingUser.avatar ? 'Change photo' : 'Upload photo' }}
                                    </button>
                                    <button
                                        v-if="editingUser.avatar"
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="avatarProcessing"
                                        @click="removeAvatar"
                                    >
                                        Remove
                                    </button>
                                </div>
                                <p class="text-[11px] text-muted-foreground">JPG, PNG, or WEBP. Max 2MB.</p>
                                <p v-if="avatarError" class="text-[11px] text-red-600">{{ avatarError }}</p>
                            </div>

                            <input
                                ref="avatarInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="handleAvatarChange"
                            />
                        </div>

                        <!-- Name -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-semibold">Name</label>
                            <input
                                v-model="nameDraft"
                                type="text"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 bg-background"
                            />
                            <p v-if="nameError" class="text-xs text-red-600">{{ nameError }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-4 border-t">
                        <button
                            type="button"
                            class="rounded-lg border px-3.5 py-2 text-sm font-semibold hover:bg-muted transition-colors"
                            @click="closeEditModal"
                        >
                            Close
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm px-4 py-2 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                            :disabled="savingName || nameDraft === editingUser.name"
                            @click="saveName"
                        >
                            {{ savingName ? 'Saving…' : 'Save Name' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
