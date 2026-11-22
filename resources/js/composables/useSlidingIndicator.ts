import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type ComputedRef, type Ref } from 'vue';

interface IndicatorRect {
    top: number;
    left: number;
    width: number;
    height: number;
}

const ACTIVE_ITEM_SELECTOR = '[data-sidebar="menu-button"][data-active="true"]';

/**
 * Where the indicator was last shown. It lives outside the component because every
 * page wraps itself in AppLayout, so the sidebar remounts on each Inertia visit; the
 * new sidebar starts the pill here and slides it to the newly active item.
 */
let lastRect: IndicatorRect | null = null;

function isSameRect(first: IndicatorRect | null, second: IndicatorRect | null): boolean {
    if (first === null || second === null) {
        return first === second;
    }

    return first.top === second.top && first.left === second.left && first.width === second.width && first.height === second.height;
}

/**
 * Offset of `element` inside `container`. Uses the offsetParent chain (not
 * getBoundingClientRect) so hover nudges, the press scale and the load-in slide
 * never throw the measurement off.
 */
function measureWithin(element: HTMLElement, container: HTMLElement): IndicatorRect | null {
    let top = 0;
    let left = 0;
    let node: HTMLElement | null = element;

    while (node && node !== container) {
        top += node.offsetTop;
        left += node.offsetLeft;
        node = node.offsetParent as HTMLElement | null;
    }

    if (node !== container) {
        return null;
    }

    return { top, left, width: element.offsetWidth, height: element.offsetHeight };
}

/**
 * Positions a "pill" behind the active sidebar menu button. It slides (CSS transition on
 * transform) when the active item changes and follows layout changes such as collapsing
 * the sidebar instantly, so it never lags behind the width transition.
 *
 * @param container positioned element that holds the pill and the menu buttons
 * @param activeKey changes whenever a different item becomes active
 * @param canAnimate false when the user prefers reduced motion; the pill then jumps
 */
export function useSlidingIndicator(
    container: Readonly<Ref<HTMLElement | null>>,
    activeKey: Readonly<Ref<string | null>>,
    canAnimate: Readonly<Ref<boolean>>,
) {
    const rect = ref<IndicatorRect | null>(null);
    const isAnimating = ref(false);

    let target: IndicatorRect | null = null;
    let observer: ResizeObserver | null = null;

    function findActiveElement(): HTMLElement | null {
        return container.value?.querySelector<HTMLElement>(ACTIVE_ITEM_SELECTOR) ?? null;
    }

    function measure(): IndicatorRect | null {
        const activeElement = findActiveElement();

        return activeElement && container.value ? measureWithin(activeElement, container.value) : null;
    }

    function placeInstantly(next: IndicatorRect | null): void {
        target = next;
        isAnimating.value = false;
        rect.value = next;

        if (next) {
            lastRect = next;
        }
    }

    /** Follows resizes (collapse/expand, window resize) without animating. */
    function follow(): void {
        const next = measure();

        if (!isSameRect(next, target)) {
            placeInstantly(next);
        }
    }

    function slideFrom(from: IndicatorRect | null): void {
        const next = measure();

        if (!next || !from || isSameRect(from, next) || !canAnimate.value) {
            placeInstantly(next);
            return;
        }

        placeInstantly(from);
        target = next;
        lastRect = next;

        // Let one frame paint at the old spot, then enable the transition and move.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (target !== next) {
                    return;
                }

                isAnimating.value = true;
                rect.value = next;
            });
        });
    }

    function observe(): void {
        observer?.disconnect();

        if (!container.value) {
            return;
        }

        observer = new ResizeObserver(() => follow());
        observer.observe(container.value);

        const activeElement = findActiveElement();
        if (activeElement) {
            observer.observe(activeElement);
        }
    }

    onMounted(() => {
        slideFrom(lastRect);
        observe();
    });

    watch(activeKey, async () => {
        await nextTick();
        slideFrom(rect.value ?? lastRect);
        observe();
    });

    onBeforeUnmount(() => observer?.disconnect());

    const style: ComputedRef<Record<string, string>> = computed(() => {
        const current = rect.value;

        if (!current) {
            return {};
        }

        return {
            width: `${current.width}px`,
            height: `${current.height}px`,
            transform: `translate3d(${current.left}px, ${current.top}px, 0)`,
        };
    });

    return {
        style,
        isVisible: computed(() => rect.value !== null),
        isAnimating,
    };
}
