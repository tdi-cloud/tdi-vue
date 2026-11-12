/**
 * Elevated card styles for the form settings pages (Registration Form, Evaluation Form).
 * A layered soft shadow plus a light tint of the page's accent at the top of each card,
 * so every section stands out from the page in light and dark mode. Class names are
 * written out in full so Tailwind can find them; pass one to <Card :class="…">.
 */
const ELEVATION =
    'border-slate-200/90 shadow-[0_1px_2px_rgba(15,23,42,0.06),0_12px_32px_-14px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:shadow-[0_1px_2px_rgba(0,0,0,0.5),0_12px_32px_-14px_rgba(0,0,0,0.8)]';

export const SETTINGS_CARD = {
    /** Shadow only, for cards that already have a tinted header band. */
    plain: ELEVATION,
    blue: `${ELEVATION} bg-[linear-gradient(to_bottom,#eff6ff,transparent_110px)] dark:bg-[linear-gradient(to_bottom,rgba(23,37,84,0.5),transparent_110px)]`,
    rose: `${ELEVATION} bg-[linear-gradient(to_bottom,#fff1f2,transparent_110px)] dark:bg-[linear-gradient(to_bottom,rgba(76,5,25,0.45),transparent_110px)]`,
    /** Destructive actions: same elevation, red border and tint. */
    danger: `${ELEVATION} border-red-200 bg-[linear-gradient(to_bottom,#fef2f2,transparent_110px)] dark:border-red-900/60 dark:bg-[linear-gradient(to_bottom,rgba(69,10,10,0.5),transparent_110px)]`,
} as const;
