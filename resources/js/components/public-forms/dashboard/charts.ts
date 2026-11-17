/**
 * Colors, theme and shared ApexCharts settings for the public form dashboard.
 *
 * The categorical palette (navy first, gold second) passed the data-viz
 * palette checks in both modes against the card surfaces (#ffffff light,
 * #111827 dark): lightness band, chroma floor, color-blind separation of
 * neighbors (protan/deutan ΔE ≥ 12.5) and ≥ 3:1 contrast. Keep the order:
 * it is what keeps neighbors apart for color-blind readers.
 */
import { useLiteMode } from '@/composables/useLiteMode';
import { useMotionLevel } from '@/composables/useMotionLevel';
import type { ApexOptions } from 'apexcharts';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const CATEGORICAL = {
    light: ['#1d4ed8', '#b8860b', '#0d9488', '#ea580c', '#7c3aed', '#db2777'],
    dark: ['#3b82f6', '#b8860b', '#0f9b8e', '#ea580c', '#8b5cf6', '#e0457f'],
};

/** "Other", "Others" and "Not stated": a neutral gray, never a series color. */
const NEUTRAL = { light: '#94a3b8', dark: '#64748b' };

/** Ranks: one blue, rank 1 strongest (darkest on light, lightest on dark). Both ends checked as an ordinal ramp. */
const RANK_RAMP = { light: ['#1e3a8a', '#60a5fa'], dark: ['#bfdbfe', '#1d4ed8'] };

/**
 * Rating scales: gold below the middle, blue above, gray at the middle; the
 * far ends are the strongest. Each arm was checked as an ordinal ramp.
 */
const DIVERGING = {
    light: { low: ['#7c5300', '#d4a12a'], high: ['#1e3a8a', '#60a5fa'], middle: '#cbd5e1' },
    dark: { low: ['#fde68a', '#92650a'], high: ['#bfdbfe', '#1e40af'], middle: '#475569' },
};

function toRgb(hex: string): [number, number, number] {
    const value = parseInt(hex.slice(1), 16);
    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
}

function mix(from: string, to: string, amount: number): string {
    const [a, b] = [toRgb(from), toRgb(to)];
    return `#${a
        .map((channel, index) =>
            Math.round(channel + (b[index] - channel) * amount)
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`;
}

/** `count` evenly spaced steps from the first color to the second. */
function steps([start, end]: string[], count: number): string[] {
    if (count <= 0) return [];
    if (count === 1) return [start];
    return Array.from({ length: count }, (_, index) => mix(start, end, index / (count - 1)));
}

/** White or near-black text, whichever reads better on the fill. */
export function inkOn(fill: string): string {
    const [r, g, b] = toRgb(fill).map((channel) => {
        const value = channel / 255;
        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.4 ? '#0b0b0b' : '#ffffff';
}

export function formatPercent(value: number): string {
    return `${Number.isInteger(value) ? value : value.toFixed(1)}%`;
}

export function formatCount(value: number): string {
    return value.toLocaleString();
}

export function people(count: number): string {
    return `${formatCount(count)} ${count === 1 ? 'person' : 'people'}`;
}

/** Splits a long label into lines of about `width` characters (at most 3), for chart axes. */
export function wrapLabel(label: string, width = 26): string[] {
    const lines: string[] = [];
    let line = '';

    for (const word of label.split(/\s+/)) {
        if (line && `${line} ${word}`.length > width) {
            lines.push(line);
            line = word;
        } else {
            line = line ? `${line} ${word}` : word;
        }
    }
    if (line) lines.push(line);

    return lines.length > 3
        ? [
              ...lines.slice(0, 2),
              `${lines
                  .slice(2)
                  .join(' ')
                  .slice(0, width - 1)}…`,
          ]
        : lines;
}

// One observer for every chart on the page; ApexCharts doesn't follow the app's `dark` class by itself.
const isDark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
let observer: MutationObserver | null = null;
let observers = 0;

/**
 * The colors and motion every dashboard chart uses, following dark mode,
 * reduced motion and lite mode.
 */
export function useChartTheme() {
    const motionLevel = useMotionLevel();
    const { isLiteMode } = useLiteMode();

    onMounted(() => {
        observers++;
        if (!observer) {
            observer = new MutationObserver(() => {
                isDark.value = document.documentElement.classList.contains('dark');
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        }
        isDark.value = document.documentElement.classList.contains('dark');
    });

    onBeforeUnmount(() => {
        observers--;
        if (observers === 0) {
            observer?.disconnect();
            observer = null;
        }
    });

    const mode = computed(() => (isDark.value ? 'dark' : 'light'));

    const theme = computed(() =>
        isDark.value
            ? { text: '#cbd5e1', strong: '#f1f5f9', muted: '#94a3b8', grid: '#1f2a3d', surface: '#111827', tooltip: 'dark' as const }
            : { text: '#475569', strong: '#0f172a', muted: '#64748b', grid: '#e8edf3', surface: '#ffffff', tooltip: 'light' as const },
    );

    const categorical = computed(() => CATEGORICAL[mode.value]);
    const neutral = computed(() => NEUTRAL[mode.value]);
    const primary = computed(() => CATEGORICAL[mode.value][0]);

    /** Fixed slot per position, so a color always means the same choice; "Other"-like rows are gray. */
    function seriesColors(rows: { label: string; is_other?: boolean }[]): string[] {
        let slot = 0;
        return rows.map((row) =>
            row.is_other || ['Other', 'Others', 'Not stated'].includes(row.label) || slot >= categorical.value.length
                ? neutral.value
                : categorical.value[slot++],
        );
    }

    function rankColors(count: number): string[] {
        return steps(RANK_RAMP[mode.value], count);
    }

    /** One color per scale step (sorted low to high). */
    function scaleColors(count: number): string[] {
        const palette = DIVERGING[mode.value];
        const arm = Math.floor(count / 2);
        const low = steps(palette.low, arm);
        const high = steps(palette.high, arm).reverse();
        return count % 2 === 1 ? [...low, palette.middle, ...high] : [...low, ...high];
    }

    /** Gentle entry only: none for reduced motion, shorter in lite mode and on phones. */
    const animations = computed<NonNullable<ApexOptions['chart']>['animations']>(() =>
        motionLevel.value === 'still'
            ? { enabled: false }
            : {
                  enabled: true,
                  speed: motionLevel.value === 'full' ? 550 : 250,
                  animateGradually: { enabled: false },
                  dynamicAnimation: { enabled: true, speed: 250 },
              },
    );

    /** Settings shared by every chart: no toolbar, recessive grid, theme tooltip. */
    const baseOptions = computed<ApexOptions>(() => ({
        chart: {
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
            foreColor: theme.value.text,
            background: 'transparent',
            parentHeightOffset: 0,
            animations: animations.value,
        },
        grid: { borderColor: theme.value.grid, strokeDashArray: 0, xaxis: { lines: { show: false } }, yaxis: { lines: { show: false } } },
        legend: { show: false },
        tooltip: { theme: theme.value.tooltip },
        states: { active: { filter: { type: 'none' } } },
    }));

    return { isDark, isLiteMode, motionLevel, theme, categorical, neutral, primary, seriesColors, rankColors, scaleColors, baseOptions };
}
