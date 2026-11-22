import type { Ref } from 'vue';

/**
 * Soft ripple that grows from the click point. The ripple is added to the element marked
 * with `data-ripple-layer` inside the clicked element (falls back to the element itself),
 * takes that layer's `currentColor`, and removes itself when its animation ends.
 * Styles live in resources/css/sidebar-motion.css (`.sb-ripple`).
 */
export function useRipple(isEnabled: Readonly<Ref<boolean>>) {
    function spawnRipple(event: PointerEvent): void {
        if (!isEnabled.value || event.button !== 0) {
            return;
        }

        const host = event.currentTarget as HTMLElement;
        const layer = host.querySelector<HTMLElement>('[data-ripple-layer]') ?? host;
        const bounds = host.getBoundingClientRect();
        const diameter = Math.max(bounds.width, bounds.height) * 2;

        const ripple = document.createElement('span');
        ripple.className = 'sb-ripple';
        ripple.style.width = `${diameter}px`;
        ripple.style.height = `${diameter}px`;
        ripple.style.left = `${event.clientX - bounds.left - diameter / 2}px`;
        ripple.style.top = `${event.clientY - bounds.top - diameter / 2}px`;
        ripple.addEventListener('animationend', () => ripple.remove(), { once: true });

        layer.appendChild(ripple);
    }

    return { spawnRipple };
}
