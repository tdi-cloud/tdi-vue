<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { registrationTheme } from '@/lib/registrationThemes';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarClock, Check, ClipboardPen, Copy, Eye, Hourglass, Settings2, UserPlus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

interface RegistrationFormSummary {
    id: number;
    batch_id: number;
    slug: string;
    title: string;
    theme: string | null;
    is_open: boolean;
    closes_at: string | null;
    slot_limit: number | null;
    updated_at: string | null;
    pending_registrations_count: number;
    slots_taken_count: number;
}

interface CardBatch {
    id: number;
    batch: string;
    date_start: string | null;
    date_end: string | null;
    registration_form?: RegistrationFormSummary | null;
}

interface FormRow {
    batch: CardBatch;
    form: RegistrationFormSummary;
    isAccepting: boolean;
}

const props = defineProps<{
    batches: CardBatch[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    (e: 'open-participants'): void;
}>();

const DAY_MS = 86_400_000;

// Same checks as RegistrationForm::closedReason(), so the card agrees with what the public link shows.
const isAccepting = (form: RegistrationFormSummary): boolean => {
    if (!form.is_open) return false;
    if (form.closes_at && new Date(form.closes_at).getTime() <= Date.now()) return false;
    return form.slot_limit === null || form.slots_taken_count < form.slot_limit;
};

const rows = computed<FormRow[]>(() =>
    props.batches.flatMap((batch) =>
        batch.registration_form ? [{ batch, form: batch.registration_form, isAccepting: isAccepting(batch.registration_form) }] : [],
    ),
);

const closesAtTime = (form: RegistrationFormSummary) => (form.closes_at ? new Date(form.closes_at).getTime() : Infinity);
const updatedAtTime = (form: RegistrationFormSummary) => (form.updated_at ? new Date(form.updated_at).getTime() : 0);

// The open form that closes soonest, otherwise the one changed most recently.
const featured = computed<FormRow | null>(() => {
    const accepting = rows.value.filter((row) => row.isAccepting);
    if (accepting.length) {
        return [...accepting].sort((a, b) => closesAtTime(a.form) - closesAtTime(b.form))[0];
    }
    return [...rows.value].sort((a, b) => updatedAtTime(b.form) - updatedAtTime(a.form))[0] ?? null;
});

const theme = computed(() => registrationTheme(featured.value?.form.theme));

const totalPending = computed(() => rows.value.reduce((sum, row) => sum + row.form.pending_registrations_count, 0));

const slotPercent = computed(() => {
    const form = featured.value?.form;
    if (!form?.slot_limit) return 0;
    return Math.min(100, Math.round((form.slots_taken_count / form.slot_limit) * 100));
});

const deadline = computed(() => {
    if (!featured.value?.form.closes_at) return null;

    const closes = new Date(featured.value.form.closes_at);
    const dateLabel = closes.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

    if (closes.getTime() <= Date.now()) {
        return { label: 'Deadline passed', dateLabel };
    }
    if (!featured.value.isAccepting) {
        return { label: 'Closing date', dateLabel };
    }

    // Count calendar days, so 11 PM tonight is "today" and 1 AM tomorrow is "in 1 day".
    const startOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const days = Math.round((startOfDay(closes) - startOfDay(new Date())) / DAY_MS);

    return { label: days === 0 ? 'Closes today' : `Closes in ${days} ${days === 1 ? 'day' : 'days'}`, dateLabel };
});

// Switched on but closed anyway because every slot is taken (a passed deadline already shows above).
const isFullWhileOpen = computed(() => {
    const form = featured.value?.form;
    return !!form?.is_open && !featured.value?.isAccepting && deadline.value?.label !== 'Deadline passed';
});

// Dates come as YYYY-MM-DD; build them as local dates so they don't shift a day in other time zones.
const parseDate = (value: string | null): Date | null => {
    const match = value?.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
};

const batchDates = (batch: CardBatch): string | null => {
    const start = parseDate(batch.date_start);
    const end = parseDate(batch.date_end);
    if (!start) return null;

    const format = (date: Date) => date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    return end && end.getTime() !== start.getTime() ? `${format(start)} – ${format(end)}` : format(start);
};

/* ===================== LINK ACTIONS ===================== */

const publicUrl = computed(() => (featured.value ? route('join.show', featured.value.form.slug) : ''));
const previewUrl = computed(() => (featured.value ? route('join.show', { slug: featured.value.form.slug, preview: 1 }) : ''));

const copyState = ref<'idle' | 'copied' | 'failed'>('idle');
let copyResetTimer: ReturnType<typeof setTimeout> | undefined;

async function copyLink() {
    if (!publicUrl.value) return;
    try {
        await navigator.clipboard.writeText(publicUrl.value);
        copyState.value = 'copied';
    } catch {
        copyState.value = 'failed';
    }
    clearTimeout(copyResetTimer);
    copyResetTimer = setTimeout(() => {
        copyState.value = 'idle';
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(copyResetTimer));

/* ===================== SHARED STYLES ===================== */

const solidButton =
    'h-auto min-h-9 w-full whitespace-normal bg-white py-2 font-semibold shadow-md hover:bg-white/90 focus-visible:ring-white/80 focus-visible:ring-offset-0';
const glassButton =
    'h-auto min-h-9 whitespace-normal border border-white/25 bg-white/15 py-2 text-white shadow-none hover:bg-white/25 hover:text-white focus-visible:ring-white/80 focus-visible:ring-offset-0';
</script>

<template>
    <section
        aria-labelledby="registration-form-card-title"
        class="group relative min-w-0 overflow-hidden rounded-xl p-4 text-white shadow-lg ring-1 ring-black/5 transition duration-300 hover:shadow-2xl motion-safe:hover:-translate-y-1 motion-reduce:transition-none dark:ring-white/10 sm:p-5"
        :class="[theme.header, theme.darkHeader, theme.shadow]"
    >
        <!-- Decorative layers -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/60 to-transparent"></div>
            <div
                class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.18)_1px,transparent_1px)] [background-size:14px_14px] [mask-image:radial-gradient(ellipse_at_top_right,black,transparent_60%)]"
            ></div>
            <div
                class="absolute -right-12 -top-12 h-40 w-40 rounded-full blur-3xl transition-transform duration-700 group-hover:scale-125 motion-reduce:transition-none"
                :class="theme.glow"
            ></div>
            <div class="absolute -bottom-16 -left-12 h-36 w-36 rounded-full bg-white/10 blur-3xl"></div>
            <ClipboardPen
                class="absolute -bottom-8 -right-6 h-36 w-36 rotate-12 text-white/[0.07] transition-transform duration-500 group-hover:rotate-6 motion-reduce:transition-none"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/25 via-transparent to-transparent"></div>
        </div>

        <!-- ===================== WITH FORMS ===================== -->
        <div v-if="featured" class="relative flex flex-col gap-4">
            <header class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 shadow-inner ring-1 ring-white/20 transition-transform duration-300 group-hover:-rotate-6 motion-reduce:transition-none"
                    >
                        <ClipboardPen class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <h2 id="registration-form-card-title" class="text-base font-semibold leading-tight">Registration Form</h2>
                        <p class="mt-0.5 truncate text-[11px] font-semibold uppercase tracking-wider" :class="theme.kicker">
                            {{ featured.batch.batch }}
                        </p>
                    </div>
                </div>

                <span
                    v-if="featured.isAccepting"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-400/20 px-2.5 py-1 text-[11px] font-semibold text-emerald-50 ring-1 ring-emerald-300/40"
                >
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75 motion-safe:animate-[ping_2s_cubic-bezier(0,0,0.2,1)_infinite]"
                        ></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                    </span>
                    Accepting registrations
                </span>
                <span
                    v-else
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white/80 ring-1 ring-white/20"
                >
                    <span class="h-2 w-2 rounded-full bg-slate-300/70"></span>
                    Closed
                </span>
            </header>

            <!-- Key numbers -->
            <div class="grid grid-cols-2 gap-2">
                <div
                    class="flex min-w-0 flex-col rounded-lg p-3 ring-1 transition-colors"
                    :class="[
                        totalPending > 0 ? 'bg-amber-300 text-amber-950 shadow-md ring-amber-100/70' : 'bg-white/15 ring-white/20 backdrop-blur-sm',
                        featured.form.slot_limit ? '' : 'col-span-2',
                    ]"
                >
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-3xl font-bold tabular-nums leading-none">{{ totalPending }}</span>
                        <Hourglass class="h-4 w-4 shrink-0" :class="totalPending > 0 ? 'text-amber-800' : 'text-white/60'" />
                    </span>
                    <span class="mt-1.5 text-xs font-medium leading-tight" :class="totalPending > 0 ? 'text-amber-900' : 'text-white/80'">
                        {{ totalPending > 0 ? 'Waiting for review' : 'None pending' }}{{ rows.length > 1 ? ' · all batches' : '' }}
                    </span>
                </div>

                <div v-if="featured.form.slot_limit" class="flex min-w-0 flex-col rounded-lg bg-white/15 p-3 ring-1 ring-white/20 backdrop-blur-sm">
                    <span class="text-3xl font-bold tabular-nums leading-none">
                        {{ featured.form.slots_taken_count
                        }}<span class="text-base font-semibold text-white/70">/{{ featured.form.slot_limit }}</span>
                    </span>
                    <span class="mt-1.5 text-xs font-medium leading-tight text-white/80">Slots taken</span>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/20"
                        role="progressbar"
                        :aria-valuenow="featured.form.slots_taken_count"
                        aria-valuemin="0"
                        :aria-valuemax="featured.form.slot_limit"
                        :aria-label="`${featured.form.slots_taken_count} of ${featured.form.slot_limit} slots taken`"
                    >
                        <div
                            class="h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none"
                            :class="slotPercent >= 100 ? 'bg-amber-300' : 'bg-white'"
                            :style="{ width: `${slotPercent}%` }"
                        ></div>
                    </div>
                </div>
            </div>

            <div v-if="deadline || isFullWhileOpen" class="-mt-1 flex flex-col gap-1">
                <p v-if="deadline" class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
                    <CalendarClock class="h-4 w-4 shrink-0 text-white/80" />
                    <span class="font-semibold">{{ deadline.label }}</span>
                    <span class="text-xs text-white/70">{{ deadline.dateLabel }}</span>
                </p>
                <p v-if="isFullWhileOpen" class="text-xs text-white/80">All slots are taken, so the link shows the form as full.</p>
            </div>

            <!-- One row per batch with a form -->
            <ul class="flex flex-col gap-1.5" aria-label="Registration forms by batch">
                <li v-for="row in rows" :key="row.form.id">
                    <component
                        :is="canManage ? Link : 'div'"
                        v-bind="
                            canManage
                                ? {
                                      href: route('batches.registration.edit', row.batch.id),
                                      'aria-label': `Manage registration form for ${row.batch.batch}`,
                                  }
                                : {}
                        "
                        class="flex min-w-0 items-center gap-2.5 rounded-lg px-2.5 py-2 ring-1 transition-colors"
                        :class="[
                            row.form.id === featured.form.id ? 'bg-white/20 ring-white/35' : 'bg-white/10 ring-white/10',
                            canManage ? 'hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80' : '',
                        ]"
                    >
                        <span
                            class="h-6 w-6 shrink-0 rounded-md shadow-sm ring-2 ring-white/40"
                            :class="registrationTheme(row.form.theme).swatch"
                            :title="`${registrationTheme(row.form.theme).label} theme`"
                        ></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium leading-tight">{{ row.batch.batch }}</span>
                            <span v-if="batchDates(row.batch)" class="block truncate text-[11px] leading-tight text-white/70">
                                {{ batchDates(row.batch) }}
                            </span>
                        </span>
                        <span
                            v-if="row.form.pending_registrations_count"
                            class="shrink-0 rounded-full bg-amber-300 px-1.5 text-[11px] font-bold leading-5 text-amber-950"
                            :title="`${row.form.pending_registrations_count} waiting for review`"
                        >
                            {{ row.form.pending_registrations_count }}
                            <span class="sr-only">pending</span>
                        </span>
                        <span
                            class="shrink-0 rounded-full px-2 text-[10px] font-semibold uppercase leading-5 tracking-wide"
                            :class="row.isAccepting ? 'bg-emerald-400/25 text-emerald-50' : 'bg-white/10 text-white/70'"
                        >
                            {{ row.isAccepting ? 'Open' : 'Closed' }}
                        </span>
                    </component>
                </li>
            </ul>

            <!-- Actions (for the featured form) -->
            <div class="flex flex-col gap-2">
                <Button v-if="canManage" as-child size="sm" :class="[solidButton, theme.accentText]">
                    <Link
                        :href="route('batches.registration.edit', featured.batch.id)"
                        class="group/manage"
                        :aria-label="`Manage registration form for ${featured.batch.batch}`"
                    >
                        <Settings2 class="h-4 w-4" /> Manage form
                        <ArrowRight class="h-4 w-4 transition-transform group-hover/manage:translate-x-0.5 motion-reduce:transition-none" />
                    </Link>
                </Button>
                <div class="grid grid-cols-2 gap-2">
                    <Button size="sm" :class="glassButton" :aria-label="`Copy the registration link for ${featured.batch.batch}`" @click="copyLink">
                        <Check v-if="copyState === 'copied'" class="h-4 w-4 text-emerald-300" />
                        <Copy v-else class="h-4 w-4" />
                        {{ copyState === 'copied' ? 'Copied!' : copyState === 'failed' ? 'Copy failed' : 'Copy link' }}
                    </Button>
                    <Button v-if="canManage" as-child size="sm" :class="glassButton">
                        <a
                            :href="previewUrl"
                            target="_blank"
                            rel="noopener"
                            :aria-label="`Preview the registration form for ${featured.batch.batch} (opens in a new tab)`"
                        >
                            <Eye class="h-4 w-4" /> Preview
                        </a>
                    </Button>
                    <Button v-else as-child size="sm" :class="glassButton">
                        <a
                            :href="publicUrl"
                            target="_blank"
                            rel="noopener"
                            :aria-label="`Open the registration form for ${featured.batch.batch} (opens in a new tab)`"
                        >
                            <Eye class="h-4 w-4" /> Open form
                        </a>
                    </Button>
                </div>
                <p class="sr-only" aria-live="polite">
                    {{ copyState === 'copied' ? 'Registration link copied.' : copyState === 'failed' ? 'Could not copy the link.' : '' }}
                </p>
            </div>
        </div>

        <!-- ===================== EMPTY STATE ===================== -->
        <div v-else class="relative flex flex-col gap-4">
            <header class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 shadow-inner ring-1 ring-white/20">
                    <UserPlus class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h2 id="registration-form-card-title" class="text-base font-semibold leading-tight">Registration Form</h2>
                    <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wider" :class="theme.kicker">Not set up yet</p>
                </div>
            </header>

            <p class="text-sm leading-relaxed text-white/85">
                <template v-if="batches.length">
                    Let employees sign up online for this program. Each batch gets its own link, slot limit and color theme.
                </template>
                <template v-else>Add a batch first. Then you can open an online registration form for it.</template>
            </p>

            <Button
                v-if="!batches.length"
                size="sm"
                :class="[solidButton, theme.accentText]"
                aria-label="Go to the Participants tab to add a batch"
                @click="emit('open-participants')"
            >
                Add a batch <ArrowRight class="h-4 w-4" />
            </Button>
            <template v-else-if="canManage">
                <Button
                    size="sm"
                    :class="[solidButton, theme.accentText]"
                    aria-label="Go to the Participants tab to set up a registration form"
                    @click="emit('open-participants')"
                >
                    Set up a form <ArrowRight class="h-4 w-4" />
                </Button>
                <p class="-mt-2 text-xs text-white/75">In Participants, open a batch's Generate menu and choose Registration Form.</p>
            </template>
            <p v-else class="text-xs text-white/75">Only the admin who created this program or a superadmin can set one up.</p>
        </div>
    </section>
</template>
