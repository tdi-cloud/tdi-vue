import { onBeforeUnmount, onMounted, type Ref } from 'vue';

/*
 * Things the sidebar remembers between Inertia visits. Every page wraps itself in
 * AppLayout, so the sidebar is destroyed and rebuilt on each visit; module-level
 * state survives that (it resets on a full page reload).
 */

let hasPlayedIntro = false;
let lastScrollTop = 0;

/**
 * True only the first time the sidebar mounts after a full page load, so the
 * load-in animation does not replay on every Inertia navigation.
 */
export function claimSidebarIntro(): boolean {
    const shouldPlay = !hasPlayedIntro;
    hasPlayedIntro = true;

    return shouldPlay;
}

/** Restores the sidebar's scroll position after a visit and keeps track of it. */
export function useSidebarScrollMemory(scrollElement: Readonly<Ref<HTMLElement | null>>): void {
    let element: HTMLElement | null = null;

    const remember = () => {
        lastScrollTop = element?.scrollTop ?? 0;
    };

    onMounted(() => {
        element = scrollElement.value;

        if (!element) {
            return;
        }

        element.scrollTop = lastScrollTop;
        element.addEventListener('scroll', remember, { passive: true });
    });

    onBeforeUnmount(() => element?.removeEventListener('scroll', remember));
}
