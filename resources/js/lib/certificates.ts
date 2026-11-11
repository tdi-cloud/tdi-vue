import { BadgeCheck, Ban, GraduationCap, HeartHandshake, Hourglass, Medal, Mic2, Trophy, Users, type LucideIcon } from 'lucide-vue-next';

export type CertificateType = 'Participation' | 'Completion' | 'Appearance' | 'Appreciation' | 'Recognition' | 'Achievement';
export type CertificateStatus = 'Pending' | 'Issued' | 'Revoked';

/** One certificate as sent by MyCertificateController::transform(). */
export interface MyCertificate {
    id: number;
    certificate_number: string | null;
    type: CertificateType;
    status: CertificateStatus;
    issued_date: string | null;
    year: number | null;
    hours: number;
    file_url: string | null;
    file_name: string | null;
    issued_by: string | null;
    remarks: string | null;
    revoked_at: string | null;
    revoked_reason: string | null;
    batch_id: number;
    program_title: string | null;
    program_code: string | null;
    program_type: string | null;
    batch_label: string | null;
    date_range: string | null;
    venue: string | null;
    modality: string | null;
    cover_image: string | null;
    program_url: string | null;
}

/** Collection statistics from MyCertificateController::statistics(). */
export interface CertificateStats {
    total: number;
    hours: number;
    programs: number;
    by_type: Record<CertificateType, number>;
    by_status: Record<CertificateStatus, number>;
    by_year: Record<string, number>;
    latest: MyCertificate | null;
}

export interface CertificateTypeMeta {
    label: string;
    /** Line printed under the heading of the certificate artwork. */
    citation: string;
    icon: LucideIcon;
    accent: string;
    /** Darker shade, readable on the ivory certificate paper. */
    deep: string;
    tint: string;
}

/** Same order as Certificate::TYPES. */
export const CERTIFICATE_TYPES: CertificateType[] = ['Participation', 'Completion', 'Appearance', 'Appreciation', 'Recognition', 'Achievement'];
export const CERTIFICATE_STATUSES: CertificateStatus[] = ['Issued', 'Pending', 'Revoked'];

export const CERTIFICATE_TYPE_META: Record<CertificateType, CertificateTypeMeta> = {
    Participation: {
        label: 'Certificate of Participation',
        citation: 'in recognition of active participation in',
        icon: Users,
        accent: '#2f5bd3',
        deep: '#1e3a8a',
        tint: '#e9eefb',
    },
    Completion: {
        label: 'Certificate of Completion',
        citation: 'for having successfully completed',
        icon: GraduationCap,
        accent: '#0f8a72',
        deep: '#0b5d4e',
        tint: '#e3f4ef',
    },
    Appearance: {
        label: 'Certificate of Appearance',
        citation: 'for the valued appearance and contribution to',
        icon: Mic2,
        accent: '#4f46e5',
        deep: '#3730a3',
        tint: '#ebeafd',
    },
    Appreciation: {
        label: 'Certificate of Appreciation',
        citation: 'in grateful appreciation of the contribution to',
        icon: HeartHandshake,
        accent: '#c27c1a',
        deep: '#7c4a0b',
        tint: '#fbf0dc',
    },
    Recognition: {
        label: 'Certificate of Recognition',
        citation: 'in recognition of outstanding involvement in',
        icon: Medal,
        accent: '#7c3aed',
        deep: '#5b21b6',
        tint: '#f1eafd',
    },
    Achievement: {
        label: 'Certificate of Achievement',
        citation: 'for distinguished achievement in',
        icon: Trophy,
        accent: '#b08d4f',
        deep: '#6f5426',
        tint: '#f6efe1',
    },
};

export const CERTIFICATE_STATUS_META: Record<CertificateStatus, { label: string; icon: LucideIcon }> = {
    Issued: { label: 'Issued', icon: BadgeCheck },
    Pending: { label: 'Awaiting Issuance', icon: Hourglass },
    Revoked: { label: 'Revoked', icon: Ban },
};

/** Soft per-type colors used on the admin Certificates tab (light and dark). */
export interface CertificateTypeColors {
    bg: string;
    text: string;
    border: string;
    dot: string;
}

export const CERTIFICATE_TYPE_COLORS: Record<string, CertificateTypeColors> = {
    Participation: {
        bg: 'bg-violet-50 dark:bg-violet-950/40',
        text: 'text-violet-700 dark:text-violet-300',
        border: 'border-violet-300 dark:border-violet-700',
        dot: 'bg-violet-500',
    },
    Completion: {
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
        text: 'text-emerald-700 dark:text-emerald-300',
        border: 'border-emerald-300 dark:border-emerald-700',
        dot: 'bg-emerald-500',
    },
    Appearance: {
        bg: 'bg-sky-50 dark:bg-sky-950/40',
        text: 'text-sky-700 dark:text-sky-300',
        border: 'border-sky-300 dark:border-sky-700',
        dot: 'bg-sky-500',
    },
    Appreciation: {
        bg: 'bg-amber-50 dark:bg-amber-950/40',
        text: 'text-amber-700 dark:text-amber-300',
        border: 'border-amber-300 dark:border-amber-700',
        dot: 'bg-amber-500',
    },
    Recognition: {
        bg: 'bg-rose-50 dark:bg-rose-950/40',
        text: 'text-rose-700 dark:text-rose-300',
        border: 'border-rose-300 dark:border-rose-700',
        dot: 'bg-rose-500',
    },
    Achievement: {
        bg: 'bg-orange-50 dark:bg-orange-950/40',
        text: 'text-orange-700 dark:text-orange-300',
        border: 'border-orange-300 dark:border-orange-700',
        dot: 'bg-orange-500',
    },
};

/** Stronger accent classes for the Email Certificates window, which the selected type tints as a whole. */
export interface CertificateTypeAccent {
    /** Soft header gradient. */
    header: string;
    /** Solid fill: icon tile, active step, selection check. */
    solid: string;
    /** Ring around a selected card. */
    ring: string;
    /** The primary button. */
    button: string;
    /** Soft badge. */
    soft: string;
    text: string;
}

export const CERTIFICATE_TYPE_ACCENTS: Record<string, CertificateTypeAccent> = {
    Participation: {
        header: 'from-violet-100/90 via-white to-white dark:from-violet-950/70 dark:via-slate-900 dark:to-slate-900',
        solid: 'bg-violet-600 text-white dark:bg-violet-500',
        ring: 'ring-2 ring-violet-500 dark:ring-violet-400',
        button: 'bg-violet-600 text-white hover:bg-violet-700 dark:bg-violet-600 dark:text-white dark:hover:bg-violet-500',
        soft: 'bg-violet-50 text-violet-700 ring-1 ring-violet-200 dark:bg-violet-950/50 dark:text-violet-300 dark:ring-violet-800',
        text: 'text-violet-700 dark:text-violet-300',
    },
    Completion: {
        header: 'from-emerald-100/90 via-white to-white dark:from-emerald-950/70 dark:via-slate-900 dark:to-slate-900',
        solid: 'bg-emerald-600 text-white dark:bg-emerald-500',
        ring: 'ring-2 ring-emerald-500 dark:ring-emerald-400',
        button: 'bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-600 dark:text-white dark:hover:bg-emerald-500',
        soft: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-800',
        text: 'text-emerald-700 dark:text-emerald-300',
    },
    Appearance: {
        header: 'from-sky-100/90 via-white to-white dark:from-sky-950/70 dark:via-slate-900 dark:to-slate-900',
        solid: 'bg-sky-600 text-white dark:bg-sky-500',
        ring: 'ring-2 ring-sky-500 dark:ring-sky-400',
        button: 'bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-600 dark:text-white dark:hover:bg-sky-500',
        soft: 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:ring-sky-800',
        text: 'text-sky-700 dark:text-sky-300',
    },
    Appreciation: {
        header: 'from-amber-100/90 via-white to-white dark:from-amber-950/60 dark:via-slate-900 dark:to-slate-900',
        solid: 'bg-amber-500 text-white dark:bg-amber-500',
        ring: 'ring-2 ring-amber-500 dark:ring-amber-400',
        button: 'bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-600 dark:text-white dark:hover:bg-amber-500',
        soft: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-800',
        text: 'text-amber-700 dark:text-amber-300',
    },
    Recognition: {
        header: 'from-rose-100/90 via-white to-white dark:from-rose-950/70 dark:via-slate-900 dark:to-slate-900',
        solid: 'bg-rose-600 text-white dark:bg-rose-500',
        ring: 'ring-2 ring-rose-500 dark:ring-rose-400',
        button: 'bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-600 dark:text-white dark:hover:bg-rose-500',
        soft: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:ring-rose-800',
        text: 'text-rose-700 dark:text-rose-300',
    },
    Achievement: {
        header: 'from-orange-100/90 via-white to-white dark:from-orange-950/60 dark:via-slate-900 dark:to-slate-900',
        solid: 'bg-orange-500 text-white dark:bg-orange-500',
        ring: 'ring-2 ring-orange-500 dark:ring-orange-400',
        button: 'bg-orange-600 text-white hover:bg-orange-700 dark:bg-orange-600 dark:text-white dark:hover:bg-orange-500',
        soft: 'bg-orange-50 text-orange-700 ring-1 ring-orange-200 dark:bg-orange-950/50 dark:text-orange-300 dark:ring-orange-800',
        text: 'text-orange-700 dark:text-orange-300',
    },
};

/** One certificate type's pre-made email, from CertificateEmailTemplates::all(). */
export interface CertificateEmailTemplate {
    subject: string;
    body: string;
    emoji: string;
}

/** One past send, from ProgramController::certificateEmailHistory(). */
export interface CertificateEmailHistoryEntry {
    id: number;
    sent_at: string | null;
    sent_by: string | null;
    batch_id: number | null;
    batch: string | null;
    certificate_type: string | null;
    subject: string;
    sent_count: number;
    failed_count: number;
}

/** What the Email Certificates window opens on when started from a certificate row. */
export interface CertificateEmailPreset {
    batchId: number;
    type: string;
    certificateId: number;
}

/** A certificate can be emailed once it is Issued, its PDF is on disk and its participant has an email. */
export function isReadyToEmail(certificate: { status: string; file_path: string | null; has_file?: boolean }, email?: string | null): boolean {
    return certificate.status === 'Issued' && !!certificate.file_path && certificate.has_file !== false && !!email?.trim();
}

/** Older rows may carry a type outside Certificate::TYPES; they fall back to Participation. */
export function typeMeta(type: string): CertificateTypeMeta {
    return CERTIFICATE_TYPE_META[type as CertificateType] ?? CERTIFICATE_TYPE_META.Participation;
}

export function statusMeta(status: string) {
    return CERTIFICATE_STATUS_META[status as CertificateStatus] ?? CERTIFICATE_STATUS_META.Pending;
}

function hexToRgbChannels(hex: string): string {
    const value = parseInt(hex.replace('#', ''), 16);

    return `${(value >> 16) & 255} ${(value >> 8) & 255} ${value & 255}`;
}

/** CSS custom properties that give a certificate its type-specific accent. */
export function accentStyle(type: string): Record<string, string> {
    const meta = typeMeta(type);

    return {
        '--accent': meta.accent,
        '--accent-deep': meta.deep,
        '--accent-tint': meta.tint,
        '--accent-rgb': hexToRgbChannels(meta.accent),
    };
}

/** Formats a "YYYY-MM-DD" date as local time, so it never shifts a day across time zones. */
export function formatDate(date: string | null, options: Intl.DateTimeFormatOptions = { month: 'long', day: 'numeric', year: 'numeric' }): string {
    if (!date) return '';

    const [year, month, day] = date.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('en-US', options);
}

export function formatHours(hours: number): string {
    return Number.isInteger(hours) ? hours.toLocaleString('en-US') : hours.toLocaleString('en-US', { maximumFractionDigits: 1 });
}

/** Revoked certificates can still be previewed for reference, but never downloaded as a valid copy. */
export function canDownload(certificate: MyCertificate): boolean {
    return !!certificate.file_url && certificate.status !== 'Revoked';
}

export function downloadName(certificate: MyCertificate): string {
    return certificate.file_name || `${certificate.certificate_number ?? 'certificate'}.pdf`;
}
