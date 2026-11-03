import { toast } from '@/components/ui/toast/use-toast';
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

const STORAGE_PREFIX = 'tdi:draft:';
const SAVE_DELAY_MS = 800;

// Pending saves of every mounted form, so they can be written right away (e.g. before a re-login reload).
const pendingSaves = new Set<() => void>();

/** Writes every form draft that is waiting for its debounce. */
export function flushFormDrafts(): void {
    pendingSaves.forEach((save) => save());
}

type DraftSource = Record<string, unknown> & { data?: () => Record<string, unknown> };

/** Files, signatures as Blobs, etc. can't be stored as JSON, so they're left out of the draft. */
function serializable(values: Record<string, unknown>): Record<string, unknown> {
    return JSON.parse(
        JSON.stringify(values, (_key, value) =>
            value instanceof Blob || (typeof FileList !== 'undefined' && value instanceof FileList) ? undefined : value,
        ),
    );
}

function readDraft(key: string): { savedAt: number; values: Record<string, unknown> } | null {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeDraft(key: string, values: Record<string, unknown>): void {
    try {
        window.localStorage.setItem(key, JSON.stringify({ savedAt: Date.now(), values: serializable(values) }));
    } catch {
        // Storage full or blocked: the form still works, it just isn't backed up.
    }
}

function removeDraft(key: string): void {
    try {
        window.localStorage.removeItem(key);
    } catch {
        // Nothing to clean up if storage is blocked.
    }
}

/**
 * Autosaves a long form to this browser so the user's work survives an
 * expired session, a lost connection or an accidental reload.
 *
 * `form` is an Inertia useForm() object or any reactive object of field values.
 * The draft is restored on load (with a toast), and should be cleared with
 * `clearDraft()` in the form's onSuccess.
 *
 * Draft keys include the signed-in user's id, so drafts never cross accounts.
 */
export function useFormDraft(name: string, source: object, options: { notify?: boolean } = {}) {
    // Inertia forms have no index signature, so accept any object and treat it as a field map here.
    const form = source as DraftSource;
    const page = usePage<{ auth?: { user?: { id: number } | null } }>();
    const key = `${STORAGE_PREFIX}${page.props?.auth?.user?.id ?? 'guest'}:${name}`;

    const values = () => (typeof form.data === 'function' ? form.data() : { ...form });

    const restoredAt = ref<Date | null>(null);
    const draft = readDraft(key);

    if (draft) {
        Object.entries(draft.values).forEach(([field, value]) => {
            // Only restore fields the form still has (the form may have changed since).
            if (field in form && typeof form[field] !== 'function') {
                form[field] = value;
            }
        });
        restoredAt.value = new Date(draft.savedAt);
        // A page with more than one draft (e.g. form fields + ratings) shows only one notice.
        if (options.notify !== false)
            toast({
                title: 'Your unsaved work was restored',
                description: `We kept what you entered on ${restoredAt.value.toLocaleString()}. Review it, then submit when ready.`,
            });
    }

    let timer: ReturnType<typeof setTimeout> | null = null;
    const save = () => {
        if (timer) clearTimeout(timer);
        timer = null;
        pendingSaves.delete(save);
        writeDraft(key, values());
    };

    watch(
        values,
        () => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(save, SAVE_DELAY_MS);
            pendingSaves.add(save);
        },
        { deep: true },
    );

    const clearDraft = () => {
        if (timer) clearTimeout(timer);
        timer = null;
        pendingSaves.delete(save);
        removeDraft(key);
        restoredAt.value = null;
    };

    onBeforeUnmount(() => {
        // Keep the latest edits when the user navigates away mid-form.
        if (pendingSaves.has(save)) save();
    });

    return { restoredAt, clearDraft };
}
