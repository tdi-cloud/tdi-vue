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
