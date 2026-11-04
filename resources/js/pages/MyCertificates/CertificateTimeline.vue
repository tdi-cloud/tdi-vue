<script setup lang="ts">
import { accentStyle, formatDate, formatHours, type MyCertificate, statusMeta, typeMeta } from '@/lib/certificates';
import { ChevronRight, Clock, Layers3 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    certificates: MyCertificate[];
}>();

const emit = defineEmits<{
    (e: 'open', certificate: MyCertificate): void;
}>();

interface YearGroup {
    year: number | null;
    certificates: MyCertificate[];
    hours: number;
}

// Certificates arrive newest first, so each group keeps that order.
const groups = computed<YearGroup[]>(() => {
    const byYear = new Map<number | null, MyCertificate[]>();

    props.certificates.forEach((certificate) => {
        const list = byYear.get(certificate.year) ?? [];
        list.push(certificate);
        byYear.set(certificate.year, list);
    });

    return [...byYear.entries()]
        .sort(([a], [b]) => (b ?? 0) - (a ?? 0))
        .map(([year, certificates]) => ({
            year,
            certificates,
            hours: certificates.filter((c) => c.status !== 'Revoked').reduce((sum, c) => sum + c.hours, 0),
        }));
});
</script>

<template>
    <ol class="tl" aria-label="Certificates by year">
        <li v-for="group in groups" :key="group.year ?? 'undated'" class="tl__year">
            <div class="tl__year-head">
                <span class="tl__year-marker" aria-hidden="true"></span>
                <h3 class="tl__year-num">{{ group.year ?? 'Undated' }}</h3>
                <p class="tl__year-sum">
                    {{ group.certificates.length }} certificate{{ group.certificates.length === 1 ? '' : 's' }}
                    <template v-if="group.hours > 0"> · {{ formatHours(group.hours) }} hrs</template>
                </p>
            </div>

            <ol class="tl__entries">
                <li
                    v-for="(certificate, index) in group.certificates"
                    :key="certificate.id"
                    class="tl__entry"
                    :class="`tl__entry--${certificate.status.toLowerCase()}`"
                    :style="{ ...accentStyle(certificate.type), '--i': String(Math.min(index, 10)) }"
                >
                    <time class="tl__date" :datetime="certificate.issued_date ?? undefined">
                        <template v-if="certificate.issued_date">
                            <span class="tl__day">{{ formatDate(certificate.issued_date, { day: 'numeric' }) }}</span>
                            <span class="tl__month">{{ formatDate(certificate.issued_date, { month: 'short' }) }}</span>
                        </template>
                        <span v-else class="tl__month">Not yet<br />issued</span>
                    </time>

                    <span class="tl__marker" aria-hidden="true">
                        <component :is="typeMeta(certificate.type).icon" :size="15" :stroke-width="2" />
                    </span>

                    <article class="tl__card" @click="emit('open', certificate)">
                        <div class="tl__main">
                            <p class="tl__type">{{ typeMeta(certificate.type).label }}</p>
                            <h4 class="tl__title">
                                <button
                                    type="button"
                                    class="tl__open"
                                    :aria-label="`${typeMeta(certificate.type).label}: ${certificate.program_title}. ${statusMeta(certificate.status).label}. View details`"
                                    @click.stop="emit('open', certificate)"
                                >
                                    {{ certificate.program_title ?? 'Training Program' }}
                                </button>
                            </h4>
                            <p class="tl__meta">
                                <span v-if="certificate.issued_date" class="tl__meta-date">{{ formatDate(certificate.issued_date) }}</span>
                                <span v-if="certificate.hours > 0"
                                    ><Clock :size="12" aria-hidden="true" /> {{ formatHours(certificate.hours) }} hrs</span
                                >
                                <span v-if="certificate.batch_label"><Layers3 :size="12" aria-hidden="true" /> {{ certificate.batch_label }}</span>
                            </p>
                        </div>
                        <span class="tl__status">
                            <component :is="statusMeta(certificate.status).icon" :size="13" aria-hidden="true" />
                            {{ statusMeta(certificate.status).label }}
                        </span>
                        <ChevronRight class="tl__chevron" :size="18" aria-hidden="true" />
                    </article>
                </li>
            </ol>
        </li>
    </ol>
</template>

<style scoped>
.tl {
    --serif: 'Iowan Old Style', 'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', Georgia, serif;
    --gold: #b8955a;
    --line-x: 118px;
    position: relative;
    max-width: 980px;
    margin: 0 auto;
    padding: 0;
    list-style: none;
}
/* The vertical spine */
.tl::before {
    position: absolute;
    top: 14px;
    bottom: 14px;
    left: var(--line-x);
    width: 1px;
    background: linear-gradient(to bottom, transparent, var(--gold) 4%, rgb(184 149 90 / 0.45) 96%, transparent);
    content: '';
}
.tl__year + .tl__year {
    margin-top: 2.75rem;
}
.tl__year-head {
    position: relative;
    display: flex;
    align-items: baseline;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-left: calc(var(--line-x) + 2rem);
}
.tl__year-marker {
    position: absolute;
    top: 50%;
    left: calc(var(--line-x) - 7px);
    width: 15px;
    height: 15px;
    background: #f7f6f2;
    border: 1.5px solid var(--gold);
    transform: translateY(-50%) rotate(45deg);
}
.tl__year-marker::after {
    position: absolute;
    inset: 3px;
    background: var(--gold);
    content: '';
}
.tl__year-num {
    margin: 0;
    color: #13275f;
    font-family: var(--serif);
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1;
}
.tl__year-sum {
    margin: 0;
    color: #6b7690;
    font-size: 0.78rem;
    font-weight: 600;
}

.tl__entries {
    display: grid;
    gap: 0.85rem;
    margin: 0;
    padding: 0;
    list-style: none;
}
.tl__entry {
    display: grid;
    grid-template-columns: calc(var(--line-x) - 22px) 44px minmax(0, 1fr);
    align-items: center;
    animation: tl-in 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
    animation-delay: calc(var(--i) * 55ms);
}
@keyframes tl-in {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
.tl__date {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding-right: 0.85rem;
    line-height: 1;
    text-align: right;
}
.tl__day {
    color: #13275f;
    font-family: var(--serif);
    font-size: 1.55rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.tl__month {
    margin-top: 0.25rem;
    color: #8a7650;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    line-height: 1.35;
    text-transform: uppercase;
}
.tl__marker {
    position: relative;
    z-index: 1;
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    justify-self: center;
    color: #fff;
    background: var(--accent);
    border-radius: 50%;
    box-shadow:
        0 0 0 3px #f7f6f2,
        0 0 0 4.5px var(--gold),
        0 6px 14px -4px rgb(var(--accent-rgb) / 0.55);
}

.tl__card {
    position: relative;
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 1rem;
    margin-left: 0.5rem;
    padding: 1rem 1.15rem 1rem 1.25rem;
    cursor: pointer;
    background: #fff;
    border: 1px solid #e6e1d4;
    border-radius: 6px;
    transition:
        transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1),
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}
.tl__card:hover {
    border-color: rgb(var(--accent-rgb) / 0.45);
    box-shadow: 0 14px 30px -18px rgb(12 23 57 / 0.4);
    transform: translateX(4px);
}
.tl__card:has(.tl__open:focus-visible) {
    box-shadow:
        0 0 0 2px #fff,
        0 0 0 5px rgb(var(--accent-rgb) / 0.7);
}
.tl__main {
    min-width: 0;
    flex: 1;
}
.tl__type {
    margin: 0;
    color: var(--accent-deep);
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.tl__title {
    margin: 0.2rem 0 0.35rem;
}
.tl__open {
    padding: 0;
    color: #17233f;
    font-family: var(--serif);
    font-size: 1.08rem;
    font-weight: 700;
    line-height: 1.3;
    text-align: left;
    background: none;
    border: 0;
    cursor: pointer;
}
.tl__open:focus-visible {
    outline: none;
}
.tl__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem 1rem;
    margin: 0;
    color: #5b6782;
    font-size: 0.76rem;
    font-weight: 600;
}
.tl__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.tl__meta-date {
    display: none !important;
}
.tl__status {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.65rem;
    font-size: 0.7rem;
    font-weight: 800;
    border-radius: 999px;
}
.tl__chevron {
    flex-shrink: 0;
    color: #9aa3b8;
    transition:
        transform 0.3s ease,
        color 0.3s ease;
}
.tl__card:hover .tl__chevron {
    color: var(--accent);
    transform: translateX(3px);
}

/* Issued: an ivory card with a gold edge stands out from the rest. */
.tl__entry--issued .tl__card {
    background:
        radial-gradient(90% 120% at 0% 0%, rgb(255 255 255 / 0.9), transparent 60%),
        repeating-linear-gradient(0deg, rgb(110 90 50 / 0.016) 0 1px, transparent 1px 3px), #fbf8f0;
    border-color: #e3d6b8;
    box-shadow: 0 10px 26px -20px rgb(12 23 57 / 0.45);
}
.tl__entry--issued .tl__card::before {
    position: absolute;
    top: 10px;
    bottom: 10px;
    left: -1px;
    width: 3px;
    background: linear-gradient(to bottom, #e6cd8f, var(--gold));
    border-radius: 0 3px 3px 0;
    content: '';
}
.tl__entry--issued .tl__status {
    color: #0f7a4f;
    background: #e3f4ec;
}
.tl__entry--pending .tl__card {
    background: transparent;
    border-style: dashed;
    border-color: #d8cdb4;
}
.tl__entry--pending .tl__marker {
    color: var(--accent-deep);
    background: #fbf8f0;
    border: 1.5px dashed var(--gold);
    box-shadow: 0 0 0 3px #f7f6f2;
}
.tl__entry--pending .tl__status {
    color: #8a5d00;
    background: #fdf1d8;
}
.tl__entry--revoked .tl__card {
    background: #f4f4f3;
    border-color: #e2e2e0;
}
.tl__entry--revoked .tl__main,
.tl__entry--revoked .tl__date {
    opacity: 0.6;
}
.tl__entry--revoked .tl__marker {
    color: #f4f4f3;
    background: #8a8a8a;
    box-shadow: 0 0 0 3px #f7f6f2;
}
.tl__entry--revoked .tl__open {
    text-decoration: line-through;
    text-decoration-color: rgb(155 28 28 / 0.55);
    text-decoration-thickness: 1.5px;
}
.tl__entry--revoked .tl__status {
    color: #9b1c1c;
    background: #fbe6e6;
}

/* Dark mode */
.dark .tl__year-num,
.dark .tl__day {
    color: #e9edf7;
}
.dark .tl__year-sum {
    color: #8f9bb5;
}
.dark .tl__month {
    color: #d4b97f;
}
.dark .tl__year-marker {
    background: #0a0f1e;
}
.dark .tl__marker {
    box-shadow:
        0 0 0 3px #0a0f1e,
        0 0 0 4.5px var(--gold),
        0 6px 14px -4px rgb(var(--accent-rgb) / 0.55);
}
.dark .tl__entry--pending .tl__marker,
.dark .tl__entry--revoked .tl__marker {
    box-shadow: 0 0 0 3px #0a0f1e;
}
.dark .tl__card {
    background: #121a2e;
    border-color: #26324b;
}
.dark .tl__open {
    color: #e6ecf7;
}
.dark .tl__type {
    color: rgb(var(--accent-rgb));
    filter: brightness(1.45);
}
.dark .tl__meta {
    color: #a3afc7;
}
/* Issued entries keep their ivory paper in dark mode. */
.dark .tl__entry--issued .tl__card {
    border-color: #4a4130;
    filter: brightness(0.94);
}
.dark .tl__entry--issued .tl__open {
    color: #17233f;
}
.dark .tl__entry--issued .tl__type {
    color: var(--accent-deep);
    filter: none;
}
.dark .tl__entry--issued .tl__meta {
    color: #5b6782;
}
.dark .tl__entry--pending .tl__card {
    background: transparent;
    border-color: #3a4560;
}
.dark .tl__entry--pending .tl__status {
    color: #fcd34d;
    background: #d9770626;
}
.dark .tl__entry--revoked .tl__card {
    background: #151b29;
    border-color: #262e40;
}
.dark .tl__entry--revoked .tl__status {
    color: #fca5a5;
    background: #dc262626;
}
.dark .tl__card:has(.tl__open:focus-visible) {
    box-shadow:
        0 0 0 2px #0a0f1e,
        0 0 0 5px rgb(var(--accent-rgb) / 0.8);
}

@media (max-width: 640px) {
    .tl {
        --line-x: 17px;
    }
    .tl__year-head {
        padding-left: 3rem;
    }
    .tl__year-num {
        font-size: 1.6rem;
    }
    .tl__entry {
        grid-template-columns: 34px minmax(0, 1fr);
    }
    .tl__date {
        display: none;
    }
    .tl__meta-date {
        display: inline-flex !important;
    }
    .tl__card {
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-left: 0.6rem;
        padding: 0.9rem 1rem;
    }
    .tl__chevron {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .tl__entry {
        animation: none;
    }
    .tl__card,
    .tl__chevron {
        transition: none;
    }
    .tl__card:hover,
    .tl__card:hover .tl__chevron {
        transform: none;
    }
}
</style>
