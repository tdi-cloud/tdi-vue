import {
    Building2,
    CheckCircle2,
    Clock,
    FileCheck2,
    Hourglass,
    Megaphone,
    MinusCircle,
    Monitor,
    PlayCircle,
    Shuffle,
    UserX,
    Video,
    type LucideIcon,
} from 'lucide-vue-next';

export interface StatusMeta {
    label: string;
    badgeClass: string;
    dotClass: string;
    icon: LucideIcon;
}

/**
 * Single source of truth for Foreign Program status labels/colors/icons.
 * Values must match the plain-string `status` column (no DB enum — see
 * migration 2026_07_15_103400) — do not rename these keys.
 */
export const PROGRAM_STATUS_META: Record<string, StatusMeta> = {
    for_dissemination: {
        label: 'For Dissemination',
        badgeClass: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        dotClass: 'bg-slate-500',
        icon: Megaphone,
    },
    waiting_for_nominees: {
        label: 'Waiting for Nominees',
        badgeClass: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
        dotClass: 'bg-amber-500',
        icon: Clock,
    },
    for_interview: {
        label: 'For Interview',
        badgeClass: 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900',
        dotClass: 'bg-blue-500',
        icon: Video,
    },
    for_endorsement: {
        label: 'For Endorsement',
        badgeClass: 'bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-900',
        dotClass: 'bg-violet-500',
        icon: FileCheck2,
    },
    no_nominee: {
        label: 'No Nominee',
        badgeClass: 'bg-red-100 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
        dotClass: 'bg-red-500',
        icon: UserX,
    },
    waiting_for_result: {
        label: 'Waiting for Result',
        badgeClass: 'bg-cyan-100 text-cyan-700 border-cyan-200 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-900',
        dotClass: 'bg-cyan-500',
        icon: Hourglass,
    },
    ongoing: {
        label: 'Ongoing',
        badgeClass: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
        dotClass: 'bg-emerald-500',
        icon: PlayCircle,
    },
    concluded: {
        label: 'Concluded',
        badgeClass: 'bg-gray-200 text-gray-600 border-gray-300 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
        dotClass: 'bg-gray-500',
        icon: CheckCircle2,
    },
    not_nfp_concern: {
        label: 'Not NFP Concern',
        badgeClass: 'bg-neutral-200 text-neutral-500 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700',
        dotClass: 'bg-neutral-400',
        icon: MinusCircle,
    },
};

export const PROGRAM_STATUS_OPTIONS = Object.entries(PROGRAM_STATUS_META).map(([value, meta]) => ({
    value,
    label: meta.label,
}));

const FALLBACK_STATUS_META: StatusMeta = {
    label: 'Unknown',
    badgeClass: 'bg-muted text-muted-foreground border-transparent',
    dotClass: 'bg-muted-foreground',
    icon: MinusCircle,
};

export function programStatusMeta(status: string | undefined | null): StatusMeta {
    if (!status) return FALLBACK_STATUS_META;
    return PROGRAM_STATUS_META[status] ?? { ...FALLBACK_STATUS_META, label: status };
}

export interface ModalityMeta {
    label: string;
    badgeClass: string;
    icon: LucideIcon;
}

export const MODALITY_META: Record<string, ModalityMeta> = {
    'in-person': {
        label: 'In-person',
        badgeClass: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
        icon: Building2,
    },
    online: {
        label: 'Online',
        badgeClass: 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-900',
        icon: Monitor,
    },
    hybrid: {
        label: 'Hybrid',
        badgeClass: 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900',
        icon: Shuffle,
    },
};

export function modalityMeta(modality: string | undefined | null): ModalityMeta {
    if (!modality) return { label: '—', badgeClass: 'bg-muted text-muted-foreground border-transparent', icon: Monitor };
    return MODALITY_META[modality] ?? { label: modality, badgeClass: 'bg-muted text-muted-foreground border-transparent', icon: Monitor };
}

/**
 * Parses a date that may be a plain `YYYY-MM-DD` (from a `date` cast) or a
 * full ISO timestamp. Appending T00:00:00 for plain dates keeps the value in
 * local time so it doesn't shift a day back in timezones behind UTC.
 */
function parseProgramDate(date?: string | null): Date | null {
    if (!date) return null;
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    return isNaN(d.getTime()) ? null : d;
}

export function formatProgramDate(date?: string | null): string {
    const d = parseProgramDate(date);
    if (!d) return '—';
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

export function toDateInput(date?: string | null): string {
    const d = parseProgramDate(date);
    if (!d) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

export type DeadlineUrgency = 'overdue' | 'soon' | 'normal';

/** Highlights an upcoming/overdue deadline. `soonDays` controls the "approaching" window. */
export function deadlineUrgency(date?: string | null, soonDays = 7): DeadlineUrgency | null {
    const d = parseProgramDate(date);
    if (!d) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.round((d.getTime() - today.getTime()) / 86400000);
    if (diffDays < 0) return 'overdue';
    if (diffDays <= soonDays) return 'soon';
    return 'normal';
}

export const DEADLINE_URGENCY_CLASS: Record<DeadlineUrgency, string> = {
    overdue: 'text-red-600 dark:text-red-400 font-semibold',
    soon: 'text-amber-600 dark:text-amber-400 font-semibold',
    normal: 'text-muted-foreground',
};
