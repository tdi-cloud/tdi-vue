import { toast } from '@/components/ui/toast/use-toast';
import axios, { type AxiosError } from 'axios';
import { ref } from 'vue';

/** True once a request comes back 401/419; SessionExpiredDialog (mounted in app.ts) shows while it's set. */
export const sessionExpired = ref(false);

declare module 'axios' {
    interface AxiosRequestConfig {
        // Skip the global error toast when the caller shows the error inline itself.
        silentErrors?: boolean;
    }
}

interface FriendlyError {
    title: string;
    message: string;
}

/**
 * Turns any axios error into plain words the user can act on. Never exposes
 * status codes, exception classes or stack traces.
 */
export function describeHttpError(error: unknown): FriendlyError {
    const err = error as AxiosError<{ message?: unknown }>;

    if (!err?.response) {
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            return { title: "You're offline", message: "Nothing was sent, and what you entered is still here. Try again once you're back online." };
        }
        if (err?.code === 'ECONNABORTED' || err?.code === 'ETIMEDOUT') {
            return { title: 'This is taking too long', message: 'The server took too long to respond. Check your connection and try again.' };
        }

        return { title: "Can't reach the server", message: 'Check your internet connection and try again.' };
    }

    const status = err.response.status;

    if (status === 401 || status === 419) {
        return { title: 'Your session has expired', message: 'Refresh the page and sign in again to continue.' };
    }
    if (status === 403) {
        return { title: "You can't do that", message: "Your account doesn't have permission for this action." };
    }
    if (status === 404) {
        return { title: 'Not found', message: "We couldn't find what you were looking for. It may have been removed." };
    }
    if (status === 413) {
        return { title: 'File is too large', message: 'Choose a smaller file and try again.' };
    }
    if (status === 429) {
        return { title: 'Too many requests', message: 'Wait a moment, then try again.' };
    }
    if (status >= 500) {
        return { title: 'Something went wrong on our side', message: 'Your saved work is safe. Please try again in a moment.' };
    }

    // Other 4xx: the server's own message is written for users (e.g. abort(400, '...')).
    const serverMessage = err.response.data?.message;

    return {
        title: "That didn't work",
        message: typeof serverMessage === 'string' && serverMessage !== '' ? serverMessage : 'Please check your input and try again.',
    };
}

/** Friendly one-line message for showing an error inline. */
export function httpErrorMessage(error: unknown): string {
    const { title, message } = describeHttpError(error);

    return `${title}. ${message}`;
}

/**
 * Registers one response interceptor on the default axios instance, so every
 * existing `import axios from 'axios'` call gets friendly error toasts.
 * Validation errors (422) are left alone: forms show them inline per field.
 */
export function installHttpErrorHandling(): void {
    axios.interceptors.response.use(undefined, (error: AxiosError) => {
        // Cast so the isCancel type guard doesn't narrow `error` to never below.
        const isCancelled = axios.isCancel(error as unknown);
        const isValidationError = error.response?.status === 422;
        const status = error.response?.status;

        // Signed out or expired CSRF token: one dialog (not a toast per request), even for silent requests.
        if (status === 401 || status === 419) {
            sessionExpired.value = true;
        } else if (!isCancelled && !isValidationError && !error.config?.silentErrors) {
            const { title, message } = describeHttpError(error);

            toast({ title, description: message, variant: 'destructive' });
        }

        return Promise.reject(error);
    });
}

/**
 * "Did you mean …?" sent by type-ahead lookups as an X-Search-Suggestion header
 * when nothing matched (AddSearchSuggestionHeader middleware). Null when absent.
 */
export function suggestionFrom(response: { headers?: Record<string, unknown> } | null | undefined): string | null {
    const value = response?.headers?.['x-search-suggestion'];

    return typeof value === 'string' && value !== '' ? value : null;
}
