import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const STORAGE_PREFIX = 'tdi:lite-mode:';
const COOKIE_NAME = 'tdi_lite';

let cookieSyncInstalled = false;

function writeCookie(enabled: boolean): void {
    try {
        document.cookie = enabled ? `${COOKIE_NAME}=1; path=/; max-age=31536000; samesite=lax` : `${COOKIE_NAME}=; path=/; max-age=0; samesite=lax`;
    } catch {
        // Cookies blocked: lists just keep their normal page size.
    }
}

// Choices made in this session, per storage key, so every component updates together.
const cache = ref<Record<string, boolean>>({});

function read(key: string): boolean {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch {
        // Storage blocked (private mode, disabled site data) — lite mode simply starts off.
        return false;
    }
}

function write(key: string, enabled: boolean): void {
    try {
        if (enabled) {
            window.localStorage.setItem(key, '1');
        } else {
            window.localStorage.removeItem(key);
        }
    } catch {
        // Not saved; the choice still applies until the page is reloaded.
    }
}

/**
 * "Lite mode" for slow connections: pages skip heavy charts, 3D scenes and
 * images. The choice is remembered per user in this browser.
 */
export function useLiteMode() {
    const page = usePage<{ auth?: { user?: { id: number } | null } }>();
    const storageKey = computed(() => STORAGE_PREFIX + (page.props?.auth?.user?.id ?? 'guest'));

    const isLiteMode = computed(() => cache.value[storageKey.value] ?? read(storageKey.value));

    const setLiteMode = (enabled: boolean) => {
        cache.value[storageKey.value] = enabled;
        write(storageKey.value, enabled);
    };

    // Mirror the choice into a cookie so the server sends shorter lists (App\Support\LiteMode).
    if (!cookieSyncInstalled) {
        cookieSyncInstalled = true;
        watch(isLiteMode, (enabled) => writeCookie(enabled), { immediate: true });
    }

    return { isLiteMode, setLiteMode };
}
