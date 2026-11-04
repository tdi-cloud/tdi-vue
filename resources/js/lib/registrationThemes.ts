/**
 * Color presets for the public registration form (keys match
 * App\Models\RegistrationForm::THEMES). Every value is a complete Tailwind
 * class string: Tailwind only keeps classes it can find written out in the
 * source, so never build these names dynamically.
 */
export interface RegistrationTheme {
    label: string;
    // Small preview chip in the theme picker
    swatch: string;
    // Banner gradient and the uppercase label above the program title
    header: string;
    kicker: string;
    // Thick top border of the white strip under the banner
    strip: string;
    // Buttons, finished steps and the progress bar
    accentBg: string;
    accentHover: string;
    accentText: string;
    activeRing: string;
    // Left border and tint of each section card's header
    sectionHeader: string;
    // Inputs on focus
    focus: string;
    // Highlighted choice (selected name in the search results)
    selected: string;
    // Registration card on the program page: dark-mode gradient stops (used
    // with header), the blurred glow blob and the colored drop shadow
    darkHeader: string;
    glow: string;
    shadow: string;
}

export const REGISTRATION_THEMES: Record<string, RegistrationTheme> = {
    blue: {
        label: 'Blue',
        swatch: 'bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600',
        header: 'bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600',
        kicker: 'text-blue-200',
        strip: 'border-blue-500',
        accentBg: 'bg-blue-600',
        accentHover: 'hover:bg-blue-700',
        accentText: 'text-blue-600',
        activeRing: 'ring-blue-100',
        sectionHeader: 'border-blue-500 bg-blue-50',
        focus: 'focus:border-blue-500 focus:ring-blue-100',
        selected: 'border-blue-500 bg-blue-50',
        darkHeader: 'dark:from-blue-950 dark:via-blue-900 dark:to-sky-900',
        glow: 'bg-sky-300/30',
        shadow: 'shadow-blue-900/25',
    },
    navy: {
        label: 'Navy',
        swatch: 'bg-gradient-to-br from-slate-900 via-blue-900 to-blue-700',
        header: 'bg-gradient-to-br from-slate-900 via-blue-900 to-blue-700',
        kicker: 'text-blue-200',
        strip: 'border-blue-800',
        accentBg: 'bg-blue-900',
        accentHover: 'hover:bg-blue-950',
        accentText: 'text-blue-900',
        activeRing: 'ring-blue-200',
        sectionHeader: 'border-blue-800 bg-slate-50',
        focus: 'focus:border-blue-800 focus:ring-blue-200',
        selected: 'border-blue-800 bg-slate-50',
        darkHeader: 'dark:from-slate-950 dark:via-blue-950 dark:to-blue-900',
        glow: 'bg-blue-400/25',
        shadow: 'shadow-slate-900/30',
    },
    sky: {
        label: 'Sky',
        swatch: 'bg-gradient-to-br from-sky-700 via-sky-600 to-cyan-500',
        header: 'bg-gradient-to-br from-sky-700 via-sky-600 to-cyan-500',
        kicker: 'text-sky-100',
        strip: 'border-sky-500',
        accentBg: 'bg-sky-600',
        accentHover: 'hover:bg-sky-700',
        accentText: 'text-sky-700',
        activeRing: 'ring-sky-100',
        sectionHeader: 'border-sky-500 bg-sky-50',
        focus: 'focus:border-sky-500 focus:ring-sky-100',
        selected: 'border-sky-500 bg-sky-50',
        darkHeader: 'dark:from-sky-950 dark:via-sky-900 dark:to-cyan-900',
        glow: 'bg-cyan-200/30',
        shadow: 'shadow-sky-900/25',
    },
    indigo: {
        label: 'Indigo',
        swatch: 'bg-gradient-to-br from-indigo-800 via-indigo-700 to-blue-600',
        header: 'bg-gradient-to-br from-indigo-800 via-indigo-700 to-blue-600',
        kicker: 'text-indigo-200',
        strip: 'border-indigo-500',
        accentBg: 'bg-indigo-600',
        accentHover: 'hover:bg-indigo-700',
        accentText: 'text-indigo-600',
        activeRing: 'ring-indigo-100',
        sectionHeader: 'border-indigo-500 bg-indigo-50',
        focus: 'focus:border-indigo-500 focus:ring-indigo-100',
        selected: 'border-indigo-500 bg-indigo-50',
        darkHeader: 'dark:from-indigo-950 dark:via-indigo-900 dark:to-blue-900',
        glow: 'bg-violet-300/25',
        shadow: 'shadow-indigo-900/25',
    },
    teal: {
        label: 'Teal',
        swatch: 'bg-gradient-to-br from-teal-800 via-teal-700 to-cyan-600',
        header: 'bg-gradient-to-br from-teal-800 via-teal-700 to-cyan-600',
        kicker: 'text-teal-100',
        strip: 'border-teal-500',
        accentBg: 'bg-teal-600',
        accentHover: 'hover:bg-teal-700',
        accentText: 'text-teal-700',
        activeRing: 'ring-teal-100',
        sectionHeader: 'border-teal-500 bg-teal-50',
        focus: 'focus:border-teal-500 focus:ring-teal-100',
        selected: 'border-teal-500 bg-teal-50',
        darkHeader: 'dark:from-teal-950 dark:via-teal-900 dark:to-cyan-900',
        glow: 'bg-emerald-300/25',
        shadow: 'shadow-teal-900/25',
    },
};

export function registrationTheme(key: string | null | undefined): RegistrationTheme {
    return REGISTRATION_THEMES[key ?? ''] ?? REGISTRATION_THEMES.blue;
}
