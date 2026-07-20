<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Search, ShieldCheck, X, SlidersHorizontal,
    Mail, Hash, Crown, ChevronLeft, ChevronRight,
} from 'lucide-vue-next';

interface UserRow {
    id: number;
    name: string;
    email: string;
    empcode: string | null;
    access: string;
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
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="u in users.data" :key="u.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 text-muted-foreground font-mono text-xs">
                                <span class="flex items-center gap-1.5"><Hash class="h-3 w-3" /> {{ u.empcode ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold text-sm leading-tight">{{ u.name }}</p>
                                <p class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5"><Mail class="h-3 w-3" /> {{ u.email }}</p>
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
    </AppLayout>
</template>
