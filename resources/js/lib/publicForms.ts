/**
 * Types and helpers for the public forms (pages/PublicForms and
 * components/public-forms). The shapes match App\Support\PublicForms\PublicFormDefinition.
 */

export type QuestionType = 'ranking' | 'multiple' | 'single' | 'text';

export interface SurveyOption {
    value: string;
    label: string;
    label_fil: string | null;
    /** Rating scales: the number shown before the label (e.g. 4 for "Excellent"). */
    score: number | null;
    /** Can't be combined with other answers (e.g. "No significant barriers"). */
    exclusive: boolean;
}

export interface SurveyQuestion {
    key: string;
    section: string;
    type: QuestionType;
    question: string;
    question_fil: string | null;
    instruction: string | null;
    instruction_fil: string | null;
    options: SurveyOption[];
    allow_other: boolean;
    other_label: string;
    other_label_fil: string | null;
    required: boolean;
    /** Ranking: how many choices to put in order. */
    rank: number | null;
    /** Multiple: the most choices allowed. */
    max: number | null;
    max_length: number;
}

export interface SurveySection {
    key: string;
    title: string;
    /** Short name for the progress steps (e.g. "Priorities"). */
    step_label: string;
    description: string | null;
    /** Who answers this section, when not everyone does (e.g. "For salary grade 18 and above"). */
    audience: string | null;
}

export type ClosedReason = 'not_open' | 'deadline';

export interface PublicFormSummary {
    slug: string;
    title: string;
    description: string | null;
    kicker: string | null;
    estimated_minutes: number;
    closed_reason: ClosedReason | null;
    closes_at_label: string | null;
}

export interface EmployeeMatch {
    empcode: string;
    name: string;
    position: string | null;
    office_division: string | null;
    /** Sections this employee answers. */
    sections: string[];
    already_answered: boolean;
}

export type AnswerValue = string | string[];

/** A saved answer in words, as App\Support\PublicForms\PublicFormDefinition::readableAnswers() returns it. */
export interface ReadableAnswer {
    /** Null for a question removed from the form since. */
    number: number | null;
    key: string;
    section: string;
    question: string;
    removed: boolean;
    lines: string[];
}

/* ── Question editor (admin) ─────────────────────────────────────────────── */

export interface EditorQuestion {
    id: number;
    section_id: number;
    type: QuestionType;
    question: string;
    question_fil: string | null;
    instruction: string | null;
    instruction_fil: string | null;
    options: SurveyOption[];
    allow_other: boolean;
    other_label: string | null;
    other_label_fil: string | null;
    required: boolean;
    rank: number | null;
    max: number | null;
}

export interface EditorSection {
    id: number;
    title: string;
    step_label: string | null;
    description: string | null;
    min_salary_grade: number | null;
    audience: string | null;
    questions: EditorQuestion[];
}

export interface EditorData {
    sections: EditorSection[];
    /** Questions removed from the form; their old answers still show in the responses. */
    removed_count: number;
    other_value: string;
}

/* ── Dashboard (admin), as App\Support\PublicForms\PublicFormDashboard::summary() returns it ── */

/** Dates are Y-m-d. */
export interface DashboardFilters {
    region: string | null;
    office: string | null;
    from: string | null;
    to: string | null;
}

export interface DashboardCountRow {
    label: string;
    count: number;
    /** Of all responses (that match the filters). */
    percent: number;
}

/** A one-line reading of a question; `emphasis` are the parts of `text` to show in bold. */
export interface DashboardTakeaway {
    text: string;
    emphasis: string[];
}

export interface DashboardOptionRow {
    value: string;
    label: string;
    score: number | null;
    exclusive: boolean;
    /** The "Other" choice (typed answers are in DashboardQuestion.other). */
    is_other: boolean;
    /** A choice removed from the question since, that still has answers. */
    removed: boolean;
    /** Ranking: the times it was ranked at all. */
    count: number;
    /** Of the people who answered the question. */
    percent: number;
    /** Ranking: how many put it at rank 1, 2, … */
    rank_counts: number[];
    first_count: number;
    /** Ranking: rank 1 earns as many points as there are ranks, the last rank 1 point. */
    weighted_score: number;
}

export interface DashboardScale {
    average: number | null;
    min: number;
    max: number;
    /** The choice nearest the average, e.g. "Very Satisfied". */
    reading: string | null;
    scored_count: number;
}

export interface DashboardTextAnswer {
    text: string;
    office: string | null;
    region: string | null;
    submitted_at: string | null;
}

export interface DashboardOtherAnswers {
    label: string;
    count: number;
    /** The typed answers, grouped without regard to capitals (top 10). */
    answers: { text: string; count: number }[];
}

export interface DashboardQuestion {
    key: string;
    /** Null for a question removed from the form since. */
    number: number | null;
    section: string;
    type: QuestionType;
    question: string;
    instruction: string | null;
    removed: boolean;
    answered: number;
    /** All responses (that match the filters), answered or not. */
    total: number;
    takeaway: DashboardTakeaway;
    /** Single: in the form's order. Multiple: most chosen first. Ranking: highest score first. */
    options: DashboardOptionRow[];
    /** Multiple: choices that can't be combined with others (e.g. "None"). */
    exclusive_options: DashboardOptionRow[];
    /** Single, when the choices have scores. */
    scale: DashboardScale | null;
    rank: number | null;
    max: number | null;
    latest_answers: DashboardTextAnswer[];
    other: DashboardOtherAnswers | null;
}

export interface DashboardSection {
    key: string;
    title: string;
    audience: string | null;
    questions: DashboardQuestion[];
}

export type DashboardStatusState = 'open' | 'closing_soon' | 'closed';

export interface FormDashboardData {
    generated_at: string;
    filters: DashboardFilters;
    filter_options: { regions: string[]; offices: string[] };
    status: { state: DashboardStatusState; label: string; detail: string; closes_at: string | null; days_left: number | null };
    overview: {
        total: number;
        /** Without the filters. */
        all_responses: number;
        today: number;
        last_7_days: number;
        previous_7_days: number;
        first_response_at: string | null;
        latest_response_at: string | null;
        latest_response_ago: string | null;
        regions: number;
        offices: number;
        emails_pending: number;
    };
    timeline: { date: string; label: string; count: number; total: number }[];
    profile: {
        regions: DashboardCountRow[];
        offices: DashboardCountRow[];
        sex: DashboardCountRow[];
        plantilla_status: DashboardCountRow[];
        salary_grade: DashboardCountRow[];
    };
    sections: DashboardSection[];
    removed_questions: DashboardQuestion[];
}

export function hasDashboardFilters(filters: DashboardFilters): boolean {
    return Boolean(filters.region || filters.office || filters.from || filters.to);
}

function shortDate(day: string): string {
    return new Date(`${day}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/** The active filters in words, e.g. [{ key: 'region', label: 'Region: NCR' }, { key: 'dates', label: 'Nov 1 – Nov 14, 2026' }]. */
export function dashboardFilterLabels(filters: DashboardFilters): { key: 'region' | 'office' | 'dates'; label: string }[] {
    const labels: { key: 'region' | 'office' | 'dates'; label: string }[] = [];

    if (filters.region) labels.push({ key: 'region', label: `Region: ${filters.region}` });
    if (filters.office) labels.push({ key: 'office', label: `Office: ${filters.office}` });

    if (filters.from && filters.to) {
        labels.push({
            key: 'dates',
            label: filters.from === filters.to ? shortDate(filters.from) : `${shortDate(filters.from)} – ${shortDate(filters.to)}`,
        });
    } else if (filters.from) {
        labels.push({ key: 'dates', label: `From ${shortDate(filters.from)}` });
    } else if (filters.to) {
        labels.push({ key: 'dates', label: `Until ${shortDate(filters.to)}` });
    }

    return labels;
}

/** The takeaway split into plain and bold parts, in order. */
export function takeawayParts(takeaway: DashboardTakeaway): { text: string; strong: boolean }[] {
    const parts: { text: string; strong: boolean }[] = [];
    let rest = takeaway.text;

    for (const phrase of takeaway.emphasis) {
        const at = phrase ? rest.indexOf(phrase) : -1;
        if (at < 0) continue;
        if (at > 0) parts.push({ text: rest.slice(0, at), strong: false });
        parts.push({ text: phrase, strong: true });
        rest = rest.slice(at + phrase.length);
    }

    if (rest) parts.push({ text: rest, strong: false });

    return parts;
}

/** How each question type is named in the editor. */
export const QUESTION_TYPES: { value: QuestionType; label: string; hint: string }[] = [
    { value: 'single', label: 'Pick one', hint: 'One answer. Give choices a rating number to show them as a rating scale.' },
    { value: 'multiple', label: 'Pick several', hint: 'Tick boxes, with an optional limit (e.g. "Select up to 2").' },
    { value: 'ranking', label: 'Rank the top choices', hint: 'People tap their choices in order of priority (e.g. "Rank your top 3").' },
    { value: 'text', label: 'Written answer', hint: 'A text box for a longer answer.' },
];

export function questionTypeLabel(type: QuestionType): string {
    return QUESTION_TYPES.find((candidate) => candidate.value === type)?.label ?? type;
}

export function emptyAnswer(question: SurveyQuestion): AnswerValue {
    return question.type === 'ranking' || question.type === 'multiple' ? [] : '';
}

export function isAnswered(value: AnswerValue | undefined): boolean {
    if (Array.isArray(value)) {
        return value.length > 0;
    }
    return typeof value === 'string' && value.trim() !== '';
}

export function pickedOther(value: AnswerValue | undefined, otherValue: string): boolean {
    return Array.isArray(value) ? value.includes(otherValue) : value === otherValue;
}

/** "4 — Excellent" for rating scales, otherwise just the label. */
export function optionLabel(option: SurveyOption): string {
    return option.score !== null ? `${option.score} — ${option.label}` : option.label;
}

/** The answer in words, one line per choice (ranked choices are numbered). */
export function answerLines(question: SurveyQuestion, value: AnswerValue | undefined, otherText: string, otherValue: string): string[] {
    const describe = (choice: string): string => {
        if (choice === otherValue) {
            return `Other: ${otherText.trim()}`;
        }
        const option = question.options.find((candidate) => candidate.value === choice);
        return option ? optionLabel(option) : choice;
    };

    if (Array.isArray(value)) {
        const lines = value.map(describe);
        return question.type === 'ranking' ? lines.map((line, index) => `${index + 1}. ${line}`) : lines;
    }

    return isAnswered(value) ? [describe(String(value).trim())] : [];
}

/**
 * What's missing or wrong in one answer, in the same words the server uses,
 * or null when it's fine.
 */
export function answerProblem(question: SurveyQuestion, value: AnswerValue | undefined): string | null {
    if (!isAnswered(value)) {
        return question.required ? 'Please answer this question.' : null;
    }

    if (question.type === 'ranking' && Array.isArray(value) && question.rank !== null && value.length !== question.rank) {
        return `Please rank exactly ${question.rank} choices.`;
    }

    if (question.type === 'multiple' && Array.isArray(value) && question.max !== null && value.length > question.max) {
        return `Please choose up to ${question.max} answers.`;
    }

    return null;
}
