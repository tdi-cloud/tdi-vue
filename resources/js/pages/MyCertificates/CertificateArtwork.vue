<script setup lang="ts">
import { accentStyle, formatDate, formatHours, type MyCertificate, typeMeta } from '@/lib/certificates';
import { computed } from 'vue';
import CertificateSeal from './CertificateSeal.vue';

/**
 * Landscape certificate, drawn entirely with CSS. Typography scales with the
 * container width, so the same artwork works in the spotlight and the details panel.
 */
const props = defineProps<{
    certificate: MyCertificate;
}>();

const meta = computed(() => typeMeta(props.certificate.type));
const status = computed(() => props.certificate.status.toLowerCase());
const details = computed(() =>
    [
        props.certificate.batch_label,
        props.certificate.date_range,
        props.certificate.hours > 0 ? `${formatHours(props.certificate.hours)} training hours` : null,
    ]
        .filter(Boolean)
        .join('  ·  '),
);
</script>

<template>
    <figure class="art" :class="`art--${status}`" :style="accentStyle(certificate.type)">
        <div class="art__sheet">
            <span class="art__corner art__corner--tl" aria-hidden="true"></span>
            <span class="art__corner art__corner--tr" aria-hidden="true"></span>
            <span class="art__corner art__corner--bl" aria-hidden="true"></span>
            <span class="art__corner art__corner--br" aria-hidden="true"></span>
            <span class="art__watermark" aria-hidden="true"></span>

            <div class="art__content">
                <p class="art__institution">TESDA Development Institute</p>
                <p class="art__heading">
                    Certificate <em>of</em> <span class="art__type">{{ certificate.type }}</span>
                </p>
                <p class="art__citation">{{ meta.citation }}</p>
                <p class="art__program">{{ certificate.program_title ?? 'Training Program' }}</p>
                <p v-if="details" class="art__details">{{ details }}</p>

                <div class="art__footer">
                    <div class="art__cell">
                        <span class="art__signature">{{ certificate.issued_by || 'TESDA Development Institute' }}</span>
                        <span class="art__label">Issued by</span>
                    </div>
                    <div class="art__seal">
                        <CertificateSeal :icon="meta.icon" :status="certificate.status" ribbons engraved />
                    </div>
                    <div class="art__cell">
                        <span class="art__signature art__signature--date">{{
                            certificate.issued_date ? formatDate(certificate.issued_date) : 'Not yet issued'
                        }}</span>
                        <span class="art__label">Date of Issue</span>
                    </div>
                </div>

                <p class="art__number"><span>Certificate No.</span> {{ certificate.certificate_number ?? 'To be assigned' }}</p>
            </div>

            <span v-if="certificate.status === 'Pending'" class="art__stamp" aria-hidden="true">Awaiting Issuance</span>
            <span v-if="certificate.status === 'Revoked'" class="art__clip" aria-hidden="true"><span class="art__revoked">Revoked</span></span>
        </div>
        <figcaption class="sr-only">
            {{ meta.label }} for {{ certificate.program_title }}, {{ certificate.status === 'Pending' ? 'awaiting issuance' : certificate.status }}
        </figcaption>
    </figure>
</template>

<style scoped>
.art {
    --serif: 'Iowan Old Style', 'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', Georgia, serif;
    --mono: ui-monospace, 'SFMono-Regular', 'Cascadia Mono', 'Segoe UI Mono', Consolas, monospace;
    --gold: #b8955a;
    --ink: #1b2440;
    container: certificate / inline-size;
    margin: 0;
    width: 100%;
}
.art__sheet {
    position: relative;
    display: flex;
    /* No overflow clipping here: it would let long titles overflow the aspect ratio instead of growing the sheet. */
    aspect-ratio: 1.414;
    color: var(--ink);
    background:
        radial-gradient(120% 90% at 50% 0%, rgb(255 255 255 / 0.75), transparent 60%),
        radial-gradient(70% 60% at 100% 100%, rgb(201 174 120 / 0.18), transparent 70%),
        radial-gradient(60% 50% at 0% 100%, rgb(201 174 120 / 0.12), transparent 70%),
        repeating-linear-gradient(0deg, rgb(110 90 50 / 0.018) 0 1px, transparent 1px 3px),
        repeating-linear-gradient(90deg, rgb(110 90 50 / 0.014) 0 1px, transparent 1px 4px), #fbf8f0;
    border-radius: 3px;
    box-shadow:
        0 1px 0 rgb(255 255 255 / 0.9) inset,
        0 0 0 1px rgb(184 149 90 / 0.35);
}
/* Double gold frame */
.art__sheet::before,
.art__sheet::after {
    position: absolute;
    pointer-events: none;
    content: '';
}
.art__sheet::before {
    inset: clamp(8px, 2.2cqi, 18px);
    border: clamp(1px, 0.32cqi, 2.5px) solid var(--gold);
}
.art__sheet::after {
    inset: clamp(12px, 3.1cqi, 25px);
    border: 1px solid rgb(184 149 90 / 0.5);
}
.art__corner {
    position: absolute;
    z-index: 1;
    width: clamp(18px, 5cqi, 40px);
    height: clamp(18px, 5cqi, 40px);
    border: 0 solid var(--gold);
}
.art__corner::after {
    position: absolute;
    width: 28%;
    height: 28%;
    background: var(--gold);
    transform: rotate(45deg);
    content: '';
}
.art__corner--tl {
    top: clamp(14px, 3.9cqi, 31px);
    left: clamp(14px, 3.9cqi, 31px);
    border-top-width: 2px;
    border-left-width: 2px;
}
.art__corner--tr {
    top: clamp(14px, 3.9cqi, 31px);
    right: clamp(14px, 3.9cqi, 31px);
    border-top-width: 2px;
    border-right-width: 2px;
}
.art__corner--bl {
    bottom: clamp(14px, 3.9cqi, 31px);
    left: clamp(14px, 3.9cqi, 31px);
    border-bottom-width: 2px;
    border-left-width: 2px;
}
.art__corner--br {
    right: clamp(14px, 3.9cqi, 31px);
    bottom: clamp(14px, 3.9cqi, 31px);
    border-right-width: 2px;
    border-bottom-width: 2px;
}
.art__corner--tl::after,
.art__corner--tr::after {
    top: 22%;
}
.art__corner--bl::after,
.art__corner--br::after {
    bottom: 22%;
}
.art__corner--tl::after,
.art__corner--bl::after {
    left: 22%;
}
.art__corner--tr::after,
.art__corner--br::after {
    right: 22%;
}
/* Guilloché-style security rings behind the text */
.art__watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 62%;
    aspect-ratio: 1;
    background: repeating-radial-gradient(circle, rgb(var(--accent-rgb) / 0.07) 0 1px, transparent 1px 7px);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    -webkit-mask-image: radial-gradient(circle, #000 30%, transparent 70%);
    mask-image: radial-gradient(circle, #000 30%, transparent 70%);
}
.art__content {
    position: relative;
    z-index: 2;
    display: flex;
    width: 100%;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: clamp(30px, 7.5cqi, 64px) clamp(28px, 9cqi, 80px);
    text-align: center;
}
.art__institution {
    margin: 0;
    color: #7d6a45;
    font-size: clamp(0.5rem, 1.35cqi, 0.78rem);
    font-weight: 700;
    letter-spacing: 0.42em;
    text-transform: uppercase;
}
.art__heading {
    margin: clamp(4px, 1.4cqi, 12px) 0 0;
    color: var(--ink);
    font-family: var(--serif);
    font-size: clamp(1.15rem, 5cqi, 2.6rem);
    font-weight: 600;
    letter-spacing: 0.01em;
    line-height: 1.1;
}
.art__heading em {
    color: #8a7650;
    font-size: 0.7em;
    font-weight: 400;
}
.art__type {
    color: var(--accent-deep);
}
.art__citation {
    margin: clamp(6px, 2cqi, 16px) 0 clamp(2px, 0.6cqi, 6px);
    color: #6b5d43;
    font-family: var(--serif);
    font-size: clamp(0.66rem, 1.7cqi, 1rem);
    font-style: italic;
}
.art__program {
    display: -webkit-box;
    max-width: 92%;
    margin: 0;
    overflow: hidden;
    color: var(--ink);
    font-family: var(--serif);
    font-size: clamp(1rem, 3.5cqi, 1.95rem);
    font-weight: 700;
    line-height: 1.22;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}
.art__details {
    margin: clamp(4px, 1.2cqi, 10px) 0 0;
    color: #6b5d43;
    font-size: clamp(0.6rem, 1.4cqi, 0.82rem);
    font-weight: 600;
    letter-spacing: 0.04em;
    white-space: pre-wrap;
}
.art__footer {
    display: grid;
    width: 100%;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: end;
    gap: clamp(8px, 3cqi, 28px);
    margin-top: auto;
    padding-top: clamp(8px, 2.4cqi, 20px);
}
.art__cell {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}
.art__signature {
    width: 100%;
    overflow: hidden;
    padding-bottom: clamp(3px, 0.8cqi, 7px);
    color: var(--ink);
    font-family: var(--serif);
    font-size: clamp(0.66rem, 1.7cqi, 1rem);
    font-style: italic;
    text-overflow: ellipsis;
    white-space: nowrap;
    border-bottom: 1px solid rgb(27 36 64 / 0.35);
}
.art__signature--date {
    font-style: normal;
    font-weight: 600;
}
.art__label {
    color: #8a7650;
    font-size: clamp(0.5rem, 1.15cqi, 0.68rem);
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
}
.art__seal {
    width: clamp(56px, 12.5cqi, 104px);
    aspect-ratio: 100 / 130;
}
.art__number {
    margin: clamp(14px, 4.4cqi, 40px) 0 0;
    color: #5c4f36;
    font-family: var(--mono);
    font-size: clamp(0.58rem, 1.35cqi, 0.8rem);
    font-weight: 600;
    letter-spacing: 0.14em;
    /* Engraved: a light edge under dark ink */
    text-shadow: 0 1px 0 rgb(255 255 255 / 0.9);
}
.art__number span {
    color: #8a7650;
    font-family: inherit;
    letter-spacing: 0.08em;
}

/* Status treatments */
.art--pending .art__content,
.art--pending .art__watermark {
    filter: saturate(0.35);
    opacity: 0.86;
}
.art--revoked .art__content,
.art--revoked .art__watermark,
.art--revoked .art__corner {
    filter: grayscale(1);
    opacity: 0.62;
}
.art__stamp {
    position: absolute;
    top: clamp(26px, 7cqi, 56px);
    right: clamp(26px, 7cqi, 56px);
    z-index: 3;
    padding: 0.3em 0.75em;
    color: #8a6a2c;
    font-size: clamp(0.5rem, 1.25cqi, 0.72rem);
    font-weight: 800;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    border: 1.5px dashed rgb(138 106 44 / 0.6);
    border-radius: 3px;
    transform: rotate(6deg);
}
.art__clip {
    position: absolute;
    inset: 0;
    z-index: 3;
    overflow: hidden;
    pointer-events: none;
}
.art__revoked {
    position: absolute;
    top: 50%;
    left: -10%;
    width: 120%;
    padding: clamp(4px, 1.1cqi, 10px) 0;
    color: #fff;
    font-size: clamp(0.7rem, 2.2cqi, 1.3rem);
    font-weight: 800;
    letter-spacing: 0.6em;
    text-align: center;
    text-transform: uppercase;
    background: linear-gradient(90deg, rgb(127 29 29 / 0.88), rgb(153 27 27 / 0.92), rgb(127 29 29 / 0.88));
    box-shadow: 0 6px 18px rgb(127 29 29 / 0.3);
    transform: translateY(-50%) rotate(-16deg);
}

/* The paper keeps its identity in dark mode, just without the glare. */
.dark .art__sheet {
    filter: brightness(0.94);
}

/* Narrow containers: let the sheet grow taller instead of shrinking the text further. */
@container certificate (max-width: 520px) {
    .art__sheet {
        aspect-ratio: auto;
        min-height: 300px;
    }
    .art__content {
        padding: 40px 30px 34px;
    }
    .art__institution {
        letter-spacing: 0.28em;
    }
    .art__details {
        white-space: normal;
    }
    .art__footer {
        gap: 10px;
        padding-top: 18px;
    }
}
</style>
