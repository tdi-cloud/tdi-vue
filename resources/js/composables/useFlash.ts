import { ToastAction } from '@/components/ui/toast';
import { useToast } from '@/components/ui/toast/use-toast';
import { router, usePage } from '@inertiajs/vue3';
import { h, watch } from 'vue';

/** Structured flash sent by App\Support\FlashMessage. */
export interface StructuredFlash {
    title: string;
    message: string;
    next?: string | null;
    action?: { label: string; href: string } | null;
}

/** Older controllers still flash a plain string; both shapes are supported. */
export type FlashValue = string | StructuredFlash | null | undefined;

function isStructured(value: FlashValue): value is StructuredFlash {
    return typeof value === 'object' && value !== null && 'title' in value;
}

/** One-line text for pages that render the flash inline instead of as a toast. */
export function flashText(value: FlashValue): string {
    if (!value) return '';
    if (!isStructured(value)) return value;

    return [value.title, value.message, value.next].filter(Boolean).join('. ').replace(/\.\./g, '.');
}

export function useFlash() {
    const page = usePage();
    const { toast } = useToast();

    const show = (value: FlashValue, kind: 'success' | 'error') => {
        if (!value) return;

        const fallbackTitle = kind === 'success' ? 'Done' : 'Something went wrong';
        const structured = isStructured(value) ? value : null;
        const description = structured ? [structured.message, structured.next].filter(Boolean).join(' ') : (value as string);
        const action = structured?.action;

        toast({
            title: structured?.title ?? fallbackTitle,
            description,
            variant: kind === 'success' ? 'success' : 'destructive',
            action: action ? h(ToastAction, { altText: action.label, onClick: () => router.visit(action.href) }, () => action.label) : undefined,
        });
    };

    watch(
        // props is undefined until Inertia's <App> loads the first page (this runs in the root component, before it).
        () => page.props?.flash as { success?: FlashValue; error?: FlashValue } | undefined,
        (flash) => {
            show(flash?.success, 'success');
            show(flash?.error, 'error');
        },
        // Immediate so a flash on a full page load (e.g. login after an expired session) shows too.
        { deep: true, immediate: true },
    );
}
