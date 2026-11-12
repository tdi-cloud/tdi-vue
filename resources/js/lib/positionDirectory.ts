/**
 * Shared types and helpers for the navy/gold "pd" visuals in
 * components/position-directory (first built for the former Position
 * Directory; now used by the Employees page and the dashboard background).
 */
import type { Component } from 'vue';

export interface PhotoRules {
    maxKilobytes: number;
    types: string[];
}

/** One count in the hero (e.g. Employees 1,234). */
export interface HeroStat {
    label: string;
    value: number;
    icon: Component;
    /** Small text after the number, e.g. "/ 14". */
    suffix?: string;
}

/** A run of consecutive items in the fan carousel (e.g. one office/division). */
export interface FanGroup {
    name: string;
    size: number;
}

/** One choice in a DirectoryCombobox. */
export interface ComboboxOption {
    value: string;
    label: string;
    /** Shown as a small count chip beside the label. */
    count?: number | null;
}

/** Shape of the hero's 3D emblem. */
export type EmblemShape = 'crystal' | 'gear';

export interface GearSpec {
    teeth: number;
    /** Tooth tips. */
    outerRadius: number;
    /** Bottom of the gaps between teeth. */
    rootRadius: number;
    /** Inner edge of the rim (outer edge of the spoke cut-outs). */
    rimRadius: number;
    /** Inner edge of the spoke cut-outs. */
    hubRadius: number;
    spokes: number;
    /** Width of each spoke, in the same units as the radii. */
    spokeWidth: number;
}

/** The emblem gear, sized like the crystal (radius 1.3) so the scene framing stays the same. */
export const EMBLEM_GEAR: GearSpec = {
    teeth: 12,
    outerRadius: 1.3,
    rootRadius: 1.08,
    rimRadius: 0.84,
    hubRadius: 0.44,
    spokes: 5,
    spokeWidth: 0.2,
};

export interface GearProfile {
    /** Outer edge with the teeth, counter-clockwise. */
    outline: [number, number][];
    /** Cut-outs between the spokes. */
    windows: [number, number][][];
}

/**
 * 2D outline of a spoked gear (tapered teeth), shared by the WebGL emblem
 * (extruded into 3D) and the CSS fallback (an SVG path).
 */
export function gearProfile(spec: GearSpec): GearProfile {
    const point = (radius: number, angle: number): [number, number] => [radius * Math.cos(angle), radius * Math.sin(angle)];
    const arc = (radius: number, from: number, to: number, steps: number): [number, number][] =>
        Array.from({ length: steps + 1 }, (_, index) => point(radius, from + ((to - from) * index) / steps));

    const toothStep = (Math.PI * 2) / spec.teeth;
    const rootHalf = toothStep * 0.27;
    const tipHalf = toothStep * 0.16;

    const outline: [number, number][] = [];
    for (let tooth = 0; tooth < spec.teeth; tooth++) {
        const center = tooth * toothStep;
        outline.push(point(spec.rootRadius, center - rootHalf));
        outline.push(...arc(spec.outerRadius, center - tipHalf, center + tipHalf, 3));
        outline.push(point(spec.rootRadius, center + rootHalf));
        // Gap to the next tooth (its end point is the next tooth's first point).
        outline.push(...arc(spec.rootRadius, center + rootHalf, center + toothStep - rootHalf, 4).slice(1, -1));
    }

    const spokeStep = (Math.PI * 2) / spec.spokes;
    const windows = Array.from({ length: spec.spokes }, (_, index) => {
        const center = (index + 0.5) * spokeStep;
        // Spokes keep a constant width, so the cut-out is angularly narrower near the hub.
        const rimHalf = spokeStep / 2 - spec.spokeWidth / spec.rimRadius / 2;
        const hubHalf = spokeStep / 2 - spec.spokeWidth / spec.hubRadius / 2;
        return [...arc(spec.rimRadius, center - rimHalf, center + rimHalf, 10), ...arc(spec.hubRadius, center + hubHalf, center - hubHalf, 6)];
    });

    return { outline, windows };
}

/** Stable number for a string (e.g. an EMPCODE), to seed per-card motion with seededUnit(). */
export function hashSeed(value: string): number {
    let hash = 0;
    for (let index = 0; index < value.length; index++) {
        hash = (Math.imul(hash, 31) + value.charCodeAt(index)) | 0;
    }

    return Math.abs(hash % 100000);
}

/**
 * How much movement the directory shows:
 * - full:  3D hero scene, floating cards, light sweeps
 * - calm:  no WebGL and no idle card movement (lite mode, phones, low-end devices)
 * - still: no animation at all (the user asked for reduced motion)
 */
export type MotionLevel = 'full' | 'calm' | 'still';

/** True on devices that report very few CPU cores or very little memory. */
export function isLowPowerDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const nav = navigator as Navigator & { deviceMemory?: number };

    return (nav.hardwareConcurrency ?? 8) <= 2 || (nav.deviceMemory ?? 8) <= 2;
}

/** Small, stable pseudo-random number in [0, 1) for a given seed, so each card keeps its own motion. */
export function seededUnit(seed: number, salt = 0): number {
    const x = Math.sin(seed * 12.9898 + salt * 78.233) * 43758.5453;

    return x - Math.floor(x);
}

export function pluralize(count: number, singular: string, plural = `${singular}s`): string {
    return `${count.toLocaleString()} ${count === 1 ? singular : plural}`;
}

type VisibilityCallback = (isVisible: boolean) => void;

const visibilityCallbacks = new WeakMap<Element, VisibilityCallback>();
let sharedObserver: IntersectionObserver | null = null;

/**
 * One IntersectionObserver for every card on the page (instead of one each),
 * used to pause card animations while they are off screen. Returns a cleanup function.
 */
export function observeVisibility(element: Element, callback: VisibilityCallback): () => void {
    if (typeof IntersectionObserver === 'undefined') {
        callback(true);

        return () => {};
    }

    sharedObserver ??= new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                visibilityCallbacks.get(entry.target)?.(entry.isIntersecting);
            }
        },
        { rootMargin: '160px 0px' },
    );

    visibilityCallbacks.set(element, callback);
    sharedObserver.observe(element);

    return () => {
        sharedObserver?.unobserve(element);
        visibilityCallbacks.delete(element);
    };
}
