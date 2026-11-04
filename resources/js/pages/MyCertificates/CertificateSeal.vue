<script setup lang="ts">
import type { CertificateStatus } from '@/lib/certificates';
import type { Component } from 'vue';
import { useId } from 'vue';

/**
 * Embossed gold seal. It fills its box, so the parent sets the size; the
 * centre colour comes from the --accent-deep custom property.
 */
withDefaults(
    defineProps<{
        icon?: Component;
        status?: CertificateStatus;
        /** Ribbon tails below the seal (the box should then be 1.3× taller than wide). */
        ribbons?: boolean;
        /** Institution lettering around the rim, for seals large enough to read it. */
        engraved?: boolean;
    }>(),
    { status: 'Issued', ribbons: false, engraved: false },
);

// useId() may contain characters that break url(#…) references.
const uid = (useId() ?? 'seal').replace(/[^a-zA-Z0-9_-]/g, '');
const gradientId = `seal-gold-${uid}`;
const rimId = `seal-rim-${uid}`;

// Scalloped rosette: 56 points alternating between two radii around (50, 50).
const rosettePoints = Array.from({ length: 56 }, (_, i) => {
    const radius = i % 2 === 0 ? 49 : 45.6;
    const angle = (Math.PI * 2 * i) / 56 - Math.PI / 2;

    return `${(50 + radius * Math.cos(angle)).toFixed(2)},${(50 + radius * Math.sin(angle)).toFixed(2)}`;
}).join(' ');
</script>

<template>
    <span class="seal" :class="[`seal--${status.toLowerCase()}`, { 'seal--ribbons': ribbons }]" aria-hidden="true">
        <svg :viewBox="ribbons ? '0 0 100 130' : '0 0 100 100'" class="seal__svg" focusable="false">
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" class="seal__stop-light" />
                    <stop offset="0.45" class="seal__stop-mid" />
                    <stop offset="1" class="seal__stop-dark" />
                </linearGradient>
                <path :id="rimId" d="M50,50 m-35.5,0 a35.5,35.5 0 1,1 71,0 a35.5,35.5 0 1,1 -71,0" />
            </defs>

            <g v-if="ribbons" class="seal__ribbons">
                <path d="M38 72 L27 126 L36.5 119 L44 129 L52 78 Z" />
                <path d="M62 72 L73 126 L63.5 119 L56 129 L48 78 Z" />
            </g>

            <polygon :points="rosettePoints" :fill="`url(#${gradientId})`" class="seal__rosette" />
            <circle cx="50" cy="50" r="41.5" class="seal__ring" />
            <circle cx="50" cy="50" r="29.5" class="seal__core" />
            <circle cx="50" cy="50" r="26.5" class="seal__core-ring" />

            <text v-if="engraved" class="seal__lettering">
                <textPath :href="`#${rimId}`" textLength="221" lengthAdjust="spacing">TESDA DEVELOPMENT INSTITUTE ✦ OFFICIAL SEAL ✦</textPath>
            </text>
        </svg>

        <span class="seal__icon-wrap" :class="{ 'seal__icon-wrap--ribbons': ribbons }">
            <component :is="icon" v-if="icon" class="seal__icon" :stroke-width="1.75" />
        </span>
    </span>
</template>

<style scoped>
.seal {
    --seal-light: #f8ebc4;
    --seal-mid: #d6b265;
    --seal-dark: #94702e;
    position: relative;
    display: inline-block;
    width: 100%;
    height: 100%;
    transition: transform 0.45s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.seal__svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
    filter: drop-shadow(0 1px 0.5px rgb(80 56 12 / 0.35)) drop-shadow(0 4px 6px rgb(60 40 8 / 0.18));
}
.seal__stop-light {
    stop-color: var(--seal-light);
}
.seal__stop-mid {
    stop-color: var(--seal-mid);
}
.seal__stop-dark {
    stop-color: var(--seal-dark);
}
.seal__ribbons path {
    fill: var(--accent-deep, #13275f);
    stroke: rgb(255 255 255 / 0.25);
    stroke-width: 0.6;
}
.seal__ring {
    fill: none;
    stroke: rgb(255 248 225 / 0.75);
    stroke-width: 1.1;
}
.seal__core {
    fill: var(--accent-deep, #13275f);
    stroke: var(--seal-dark);
    stroke-width: 1.2;
}
.seal__core-ring {
    fill: none;
    stroke: rgb(255 243 210 / 0.45);
    stroke-width: 0.6;
    stroke-dasharray: 1.4 1.6;
}
.seal__lettering {
    fill: #5f4618;
    font-family: 'Palatino Linotype', Palatino, 'Book Antiqua', Georgia, serif;
    font-size: 6.2px;
    font-weight: 700;
    letter-spacing: 0.4px;
}
.seal__icon-wrap {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
}
/* With ribbons the rosette occupies only the top 100/130 of the box. */
.seal__icon-wrap--ribbons {
    bottom: auto;
    height: calc(100% * 100 / 130);
}
.seal__icon {
    width: 27%;
    height: 27%;
    color: #fff4d6;
}

/* Not yet issued: an unsealed, outlined seal. */
.seal--pending .seal__svg {
    filter: none;
}
.seal--pending .seal__rosette {
    fill: rgb(214 178 101 / 0.12);
    stroke: #b8955a;
    stroke-width: 1;
    stroke-dasharray: 2.4 2.2;
}
.seal--pending .seal__ring,
.seal--pending .seal__core-ring {
    stroke: rgb(184 149 90 / 0.55);
}
.seal--pending .seal__core {
    fill: rgb(255 255 255 / 0.55);
    stroke: rgb(184 149 90 / 0.7);
    stroke-dasharray: 2 2;
}
.seal--pending .seal__ribbons path {
    fill: rgb(184 149 90 / 0.25);
}
.seal--pending .seal__icon {
    color: #9a7a43;
}
.seal--pending .seal__lettering {
    fill: #9a7a43;
}

/* Revoked: the seal loses its gold. */
.seal--revoked {
    --seal-light: #ececec;
    --seal-mid: #b9b9b9;
    --seal-dark: #7f7f7f;
}
.seal--revoked .seal__core,
.seal--revoked .seal__ribbons path {
    fill: #6b6b6b;
}
.seal--revoked .seal__lettering {
    fill: #555;
}
</style>
