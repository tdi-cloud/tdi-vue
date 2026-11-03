import { toast } from '@/components/ui/toast/use-toast';
import { router } from '@inertiajs/vue3';
import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { computed, readonly, ref } from 'vue';

// A request running longer than this is treated as a sign of a slow connection.
const SLOW_REQUEST_MS = 4000;
const RECONNECTED_NOTICE_MS = 3000;
const SLOW_CONNECTION_TYPES = ['slow-2g', '2g'];

interface NetworkInformationLike extends EventTarget {
    effectiveType?: string;
}

// Module-level state: one source of truth shared by the banner, forms and pages.
const isOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const justReconnected = ref(false);
const slowRequestCount = ref(0);
const isSlowConnectionType = ref(false);
const uploadPercentage = ref<number | null>(null);
// Number of inline <UploadProgress> bars on screen; the banner's own upload indicator hides while any are showing.
const inlineProgressCount = ref(0);

const isSlow = computed(() => isOnline.value && (slowRequestCount.value > 0 || isSlowConnectionType.value));

let reconnectedTimer: ReturnType<typeof setTimeout> | null = null;
let installed = false;

/** Starts a timer that flags "slow" if the request is still running after SLOW_REQUEST_MS. Returns a stop function. */
function trackRequest(): () => void {
    let counted = false;
    const timer = setTimeout(() => {
        counted = true;
        slowRequestCount.value++;
    }, SLOW_REQUEST_MS);

    return () => {
        clearTimeout(timer);
        if (counted) {
            slowRequestCount.value = Math.max(0, slowRequestCount.value - 1);
        }
    };
}

function setOnline(online: boolean): void {
    const wasOffline = !isOnline.value;
    isOnline.value = online;

    if (reconnectedTimer) clearTimeout(reconnectedTimer);
    justReconnected.value = online && wasOffline;

    if (justReconnected.value) {
        reconnectedTimer = setTimeout(() => (justReconnected.value = false), RECONNECTED_NOTICE_MS);
    }
}

function notifyOfflineBlocked(): void {
    toast({
        title: "You're offline",
        description: "Nothing was sent, and what you entered is still here. Try again once you're back online.",
        variant: 'destructive',
    });
}

function watchConnectionType(): void {
    // Network Information API isn't in every browser (e.g. Safari, Firefox), so feature-detect it.
    const connection = (navigator as Navigator & { connection?: NetworkInformationLike }).connection;
    if (!connection) return;

    const update = () => (isSlowConnectionType.value = SLOW_CONNECTION_TYPES.includes(connection.effectiveType ?? ''));
    update();
    connection.addEventListener('change', update);
}

function watchInertiaVisits(): void {
    const stoppers = new WeakMap<object, () => void>();

    // Block visits while offline. Cancelling keeps the form's data, so nothing the user typed is lost.
    router.on('before', (event) => {
        if (!isOnline.value) {
            event.preventDefault();
            notifyOfflineBlocked();
        }
    });

    router.on('start', (event) => {
        stoppers.set(event.detail.visit, trackRequest());
    });

    router.on('progress', (event) => {
        uploadPercentage.value = event.detail.progress?.percentage ?? null;
    });

    router.on('finish', (event) => {
        stoppers.get(event.detail.visit)?.();
        uploadPercentage.value = null;
    });

    // Fires on network interruptions (not on 4xx/5xx responses).
    router.on('exception', (event) => {
        event.preventDefault();
        if (!navigator.onLine) setOnline(false);

        toast({
            title: navigator.onLine ? "Can't reach the server" : "You're offline",
            description: 'The page could not be loaded. Check your connection and try again.',
            variant: 'destructive',
        });
    });
}

function watchAxiosRequests(): void {
    const stoppers = new WeakMap<InternalAxiosRequestConfig, () => void>();
    const stop = (config?: InternalAxiosRequestConfig) => config && stoppers.get(config)?.();

    axios.interceptors.request.use((config) => {
        const isRead = (config.method ?? 'get').toLowerCase() === 'get';

        // Don't send changes while offline; the caller's catch keeps the user's input as is.
        if (!isOnline.value && !isRead) {
            return Promise.reject(new AxiosError("You're offline", AxiosError.ERR_NETWORK, config));
        }

        stoppers.set(config, trackRequest());

        // Real upload progress for every file upload, keeping any handler the caller already set.
        if (config.data instanceof FormData) {
            const callerHandler = config.onUploadProgress;
            config.onUploadProgress = (event) => {
                if (event.total) {
                    uploadPercentage.value = Math.round((event.loaded / event.total) * 100);
                }
                callerHandler?.(event);
            };
        }

        return config;
    });

    const finish = (config?: InternalAxiosRequestConfig) => {
        stop(config);
        if (config?.data instanceof FormData) {
            uploadPercentage.value = null;
        }
    };

    axios.interceptors.response.use(
        (response) => {
            finish(response.config);
            return response;
        },
        (error: AxiosError) => {
            finish(error.config);
            return Promise.reject(error);
        },
    );
}

/** Registers the global listeners once (called from app.ts). */
export function installNetworkMonitoring(): void {
    if (installed || typeof window === 'undefined') return;
    installed = true;

    window.addEventListener('online', () => setOnline(true));
    window.addEventListener('offline', () => setOnline(false));

    watchConnectionType();
    watchInertiaVisits();
    watchAxiosRequests();
}

/** Called by UploadProgress while it is mounted, so the global indicator doesn't duplicate it. Returns the unregister function. */
export function registerInlineProgress(): () => void {
    inlineProgressCount.value++;
    return () => (inlineProgressCount.value = Math.max(0, inlineProgressCount.value - 1));
}

export function useNetworkStatus() {
    return {
        hasInlineProgress: computed(() => inlineProgressCount.value > 0),
        isOnline: readonly(isOnline),
        isSlow,
        justReconnected: readonly(justReconnected),
        // Real upload progress (0–100) of the current Inertia visit, or null when nothing is uploading.
        uploadPercentage: readonly(uploadPercentage),
    };
}
