/**
 * Shared types and helpers for the Employees page (pages/employees and
 * components/employee-progress).
 */

export interface ProgressEmployee {
    id: number;
    EMPCODE: string;
    FIRSTNAME: string;
    LASTNAME: string;
    MI: string;
    POSITION: string;
    'OFFICE/DIVISION': string;
    'PLANTILLA STATUS': string;
    REGION: string;
    OFFICE: string;
    SEX: string;
    SG: string;
    name: string;
    initials: string;
    avatar_color: string;
    /** Linked user account's avatar. */
    avatar: string | null;
    /** Employee picture added by a superadmin (preferred over the avatar). */
    photo_url: string | null;
    progress_stats: {
        total_programs: number;
        completed_programs: number;
        total_hours: number;
        hours_completed: number;
    };
    submission_stats: {
        total_requirements: number;
        approved_submissions: number;
    };
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedEmployees {
    data: ProgressEmployee[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
}

export interface ProgressMetric {
    key: 'programs' | 'hours' | 'submissions';
    label: string;
    value: string;
    percent: number;
    /** CSS colour (a .pd-theme token) for the ring or bar. */
    color: string;
}

/**
 * Employee picture first, then the user account avatar; null shows the designed
 * placeholder. `photoUrl` overrides the loaded picture after a change in this session.
 */
export function employeePicture(employee: ProgressEmployee, photoUrl: string | null = employee.photo_url): string | null {
    return photoUrl ?? employee.avatar ?? null;
}

/** Chip colours per plantilla status (same as the previous table). */
export function plantillaColor(status: string | null | undefined): string {
    const normalized = status?.toUpperCase();
    if (normalized === 'PERMANENT') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200/80 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20';
    }
    if (normalized === 'JOB ORDER') {
        return 'bg-amber-50 text-amber-700 border-amber-200/80 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20';
    }
    if (normalized === 'CTI') {
        return 'bg-blue-50 text-blue-700 border-blue-200/80 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20';
    }
    return 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-500/10 dark:text-slate-300 dark:border-slate-500/20';
}

export function progressPercent(done: number, total: number): number {
    return total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
}

export function formatHours(hours: number): number {
    return Number.isInteger(hours) ? hours : Number(hours.toFixed(1));
}

/** Programs completed, training hours and approved submissions for one employee. */
export function progressMetrics(employee: ProgressEmployee): ProgressMetric[] {
    const { progress_stats: progress, submission_stats: submissions } = employee;

    return [
        {
            key: 'programs',
            label: 'Programs',
            value: `${progress.completed_programs} / ${progress.total_programs}`,
            percent: progressPercent(progress.completed_programs, progress.total_programs),
            color: 'var(--pd-metric-programs)',
        },
        {
            key: 'hours',
            label: 'Hours',
            value: `${formatHours(progress.hours_completed)} / ${formatHours(progress.total_hours)} hrs`,
            percent: progressPercent(progress.hours_completed, progress.total_hours),
            color: 'var(--pd-metric-hours)',
        },
        {
            key: 'submissions',
            label: 'Submissions',
            value: `${submissions.approved_submissions} / ${submissions.total_requirements}`,
            percent: progressPercent(submissions.approved_submissions, submissions.total_requirements),
            color: 'var(--pd-metric-submissions)',
        },
    ];
}
