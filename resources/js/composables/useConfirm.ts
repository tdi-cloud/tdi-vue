import { reactive } from 'vue';

export interface ConfirmOptions {
    title?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'default';
}

interface ConfirmDialogState extends Required<ConfirmOptions> {
    open: boolean;
    message: string;
    resolve: ((value: boolean) => void) | null;
}

const state = reactive<ConfirmDialogState>({
    open: false,
    title: 'Are you sure?',
    message: '',
    confirmText: 'Delete',
    cancelText: 'Cancel',
    variant: 'danger',
    resolve: null,
});

function confirmDialog(message: string, options: ConfirmOptions = {}): Promise<boolean> {
    state.title = options.title ?? 'Are you sure?';
    state.message = message;
    state.confirmText = options.confirmText ?? 'Delete';
    state.cancelText = options.cancelText ?? 'Cancel';
    state.variant = options.variant ?? 'danger';
    state.open = true;

    return new Promise<boolean>((resolve) => {
        state.resolve = resolve;
    });
}

function respond(value: boolean) {
    state.open = false;
    state.resolve?.(value);
    state.resolve = null;
}

export function useConfirm() {
    return { confirmDialog };
}

// Used only by ConfirmDialogHost — the single global instance that renders `state`.
export function useConfirmDialogState() {
    return { state, respond };
}
