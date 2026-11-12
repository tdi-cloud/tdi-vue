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
