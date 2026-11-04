<script setup lang="ts">
import { accentStyle, canDownload, downloadName, formatDate, formatHours, type MyCertificate, statusMeta, typeMeta } from '@/lib/certificates';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Copy, Download, Eye, X } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CertificateArtwork from './CertificateArtwork.vue';

/**
 * Side sheet with everything about one certificate. Built on the radix-vue
 * dialog primitives (like components/ui/sheet) so it can sit above the fixed navbar.
 */
const props = defineProps<{
    open: boolean;
    certificate: MyCertificate | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'preview', certificate: MyCertificate): void;
}>();

const meta = computed(() => (props.certificate ? typeMeta(props.certificate.type) : null));
const status = computed(() => (props.certificate ? statusMeta(props.certificate.status) : null));
const isDownloadable = computed(() => !!props.certificate && canDownload(props.certificate));

const statusNote = computed(() => {
    const certificate = props.certificate;
    if (!certificate) return '';

    if (certificate.status === 'Issued') {
        return certificate.issued_date ? `Officially issued on ${formatDate(certificate.issued_date)}.` : 'Officially issued.';
    }
    if (certificate.status === 'Revoked') {
        return certificate.revoked_at
            ? `Revoked on ${formatDate(certificate.revoked_at)}. It is no longer a valid certificate.`
            : 'It is no longer a valid certificate.';
    }

    return 'Not yet officially issued. It becomes valid once the program team issues it.';
});

const fileHint = computed(() => {
    const certificate = props.certificate;
    if (!certificate) return '';
    if (!certificate.file_url) return 'The PDF will be available here once it has been uploaded.';
    if (certificate.status === 'Revoked') return 'Revoked certificates can be previewed for reference but not downloaded.';

    return '';
});

const details = computed(() => {
    const certificate = props.certificate;
    if (!certificate) return [];

    return [
        { label: 'Program', value: certificate.program_title, wide: true },
        { label: 'Program Code', value: certificate.program_code },
        { label: 'Program Type', value: certificate.program_type },
        { label: 'Batch', value: certificate.batch_label },
        { label: 'Schedule', value: certificate.date_range },
        { label: 'Date Issued', value: certificate.issued_date ? formatDate(certificate.issued_date) : 'Not yet issued' },
        { label: 'Training Hours', value: certificate.hours > 0 ? `${formatHours(certificate.hours)} hours` : null },
        { label: 'Venue', value: certificate.venue },
        { label: 'Modality', value: certificate.modality },
        { label: 'Issued By', value: certificate.issued_by, wide: true },
    ];
});

/* ---- Copy certificate number ---- */
const copyState = ref<'idle' | 'copied' | 'failed'>('idle');
const numberRef = ref<HTMLElement | null>(null);
let copyTimer: ReturnType<typeof setTimeout> | undefined;

const copyMessage = computed(() => {
    if (copyState.value === 'copied') return `Certificate number ${props.certificate?.certificate_number} copied to your clipboard.`;
    if (copyState.value === 'failed') return 'Copying is blocked in this browser. The number is selected; press Ctrl+C to copy it.';

    return '';
});

async function copyNumber() {
    const number = props.certificate?.certificate_number;
    if (!number) return;

    try {
        await navigator.clipboard.writeText(number);
        copyState.value = 'copied';
    } catch {
        // Clipboard blocked (e.g. plain http): select the number so it can be copied by hand.
        copyState.value = 'failed';
        if (numberRef.value) window.getSelection()?.selectAllChildren(numberRef.value);
    }

    clearTimeout(copyTimer);
    copyTimer = setTimeout(() => (copyState.value = 'idle'), 3200);
}

watch(
    () => props.certificate?.id,
    () => (copyState.value = 'idle'),
);
onBeforeUnmount(() => clearTimeout(copyTimer));
</script>

<template>
    <DialogRoot :open="open" @update:open="emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay class="cds-overlay" />
            <DialogContent v-if="certificate && meta && status" class="cds" :style="accentStyle(certificate.type)">
                <header class="cds__head">
                    <div class="cds__head-text">
                        <p class="cds__kicker"><i aria-hidden="true"></i>Certificate details</p>
                        <DialogTitle class="cds__title">{{ meta.label }}</DialogTitle>
                        <DialogDescription class="cds__desc">{{ certificate.program_title }}</DialogDescription>
                    </div>
                    <DialogClose class="cds__close" aria-label="Close certificate details">
                        <X :size="18" />
                    </DialogClose>
                </header>

                <div class="cds__body">
                    <div class="cds__display">
                        <CertificateArtwork :certificate="certificate" />
                    </div>

                    <div class="cds__status" :class="`cds__status--${certificate.status.toLowerCase()}`">
                        <component :is="status.icon" :size="20" class="cds__status-icon" aria-hidden="true" />
                        <div>
                            <p class="cds__status-label">{{ status.label }}</p>
                            <p class="cds__status-note">{{ statusNote }}</p>
                            <p v-if="certificate.status === 'Revoked'" class="cds__reason">
                                <strong>Reason:</strong> {{ certificate.revoked_reason || 'No reason was recorded.' }}
                            </p>
                        </div>
                    </div>

                    <div class="cds__number">
                        <div class="cds__number-text">
                            <span class="cds__label">Certificate No.</span>
                            <span ref="numberRef" class="cds__number-value">{{ certificate.certificate_number ?? 'To be assigned' }}</span>
                        </div>
                        <button
                            v-if="certificate.certificate_number"
                            type="button"
                            class="cds__copy"
                            :class="{ 'cds__copy--done': copyState === 'copied' }"
                            :aria-label="copyState === 'copied' ? 'Certificate number copied' : 'Copy certificate number'"
                            @click="copyNumber"
                        >
                            <Transition name="cds-swap" mode="out-in">
                                <Check v-if="copyState === 'copied'" :size="15" aria-hidden="true" />
                                <Copy v-else :size="15" aria-hidden="true" />
                            </Transition>
                            {{ copyState === 'copied' ? 'Copied' : 'Copy' }}
                        </button>
                        <Transition name="cds-notice">
                            <p
                                v-if="copyState !== 'idle'"
                                class="cds__notice"
                                :class="{ 'cds__notice--failed': copyState === 'failed' }"
                                aria-hidden="true"
                            >
                                <Check v-if="copyState === 'copied'" :size="14" />{{ copyMessage }}
                            </p>
                        </Transition>
                        <p class="sr-only" aria-live="polite">{{ copyMessage }}</p>
                    </div>

                    <dl class="cds__grid">
                        <div v-for="row in details" :key="row.label" class="cds__row" :class="{ 'cds__row--wide': row.wide }">
                            <dt class="cds__label">{{ row.label }}</dt>
                            <dd :class="{ cds__empty: !row.value }">{{ row.value || 'Not specified' }}</dd>
                        </div>
                    </dl>

                    <section v-if="certificate.remarks" class="cds__remarks" aria-label="Remarks">
                        <span class="cds__label">Remarks</span>
                        <p>{{ certificate.remarks }}</p>
                    </section>
                </div>

                <footer class="cds__actions">
                    <div class="cds__buttons">
                        <button
                            type="button"
                            class="cds__btn cds__btn--primary"
                            :disabled="!certificate.file_url"
                            @click="emit('preview', certificate)"
                        >
                            <Eye :size="16" aria-hidden="true" /> Preview PDF
                        </button>
                        <a
                            v-if="isDownloadable"
                            :href="certificate.file_url!"
                            :download="downloadName(certificate)"
                            class="cds__btn cds__btn--outline"
                        >
                            <Download :size="16" aria-hidden="true" /> Download
                        </a>
                        <button v-else type="button" class="cds__btn cds__btn--outline" disabled aria-describedby="cds-file-hint">
                            <Download :size="16" aria-hidden="true" /> Download
                        </button>
                        <Link v-if="certificate.program_url" :href="certificate.program_url" class="cds__btn cds__btn--link">
                            View Program <ArrowUpRight :size="16" aria-hidden="true" />
                        </Link>
                    </div>
                    <p v-if="fileHint" id="cds-file-hint" class="cds__hint">{{ fileHint }}</p>
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.cds-overlay {
    position: fixed;
    inset: 0;
    z-index: 110;
    background: rgb(5 10 25 / 0.6);
    -webkit-backdrop-filter: blur(3px);
    backdrop-filter: blur(3px);
}
.cds-overlay[data-state='open'] {
    animation: cds-fade-in 0.35s ease both;
}
.cds-overlay[data-state='closed'] {
    animation: cds-fade-out 0.25s ease both;
}

.cds {
    --serif: 'Iowan Old Style', 'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', Georgia, serif;
    --mono: ui-monospace, 'SFMono-Regular', 'Cascadia Mono', 'Segoe UI Mono', Consolas, monospace;
    --gold: #b8955a;
    --surface: #fbfaf6;
    --ink: #17233f;
    --muted: #5d6882;
    --line: #e7e1d3;
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 120;
    display: flex;
    width: min(620px, 100vw);
    flex-direction: column;
    color: var(--ink);
    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    background: var(--surface);
    border-left: 1px solid rgb(184 149 90 / 0.35);
    box-shadow: -30px 0 80px -20px rgb(5 10 25 / 0.45);
}
.cds:focus {
    outline: none;
}
.cds[data-state='open'] {
    animation: cds-slide-in 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
.cds[data-state='closed'] {
    animation: cds-slide-out 0.28s ease-in both;
}
@keyframes cds-slide-in {
    from {
        opacity: 0.4;
        transform: translateX(48px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
@keyframes cds-slide-out {
    to {
        opacity: 0;
        transform: translateX(48px);
    }
}
@keyframes cds-fade-in {
    from {
        opacity: 0;
    }
}
@keyframes cds-fade-out {
    to {
        opacity: 0;
    }
}

.cds__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.35rem 1.5rem 1.1rem;
    border-bottom: 1px solid var(--line);
}
.cds__head-text {
    min-width: 0;
}
.cds__kicker {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.35rem;
    color: #8a7650;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.22em;
    text-transform: uppercase;
}
.cds__kicker i {
    width: 7px;
    height: 7px;
    background: var(--accent);
    transform: rotate(45deg);
}
.cds__title {
    margin: 0;
    font-family: var(--serif);
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1.2;
}
.cds__desc {
    display: -webkit-box;
    margin: 0.3rem 0 0;
    overflow: hidden;
    color: var(--muted);
    font-size: 0.86rem;
    line-height: 1.45;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
.cds__close {
    display: grid;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    place-items: center;
    color: var(--muted);
    background: transparent;
    border: 1px solid var(--line);
    border-radius: 8px;
    cursor: pointer;
    transition:
        color 0.2s,
        border-color 0.2s,
        background 0.2s;
}
.cds__close:hover {
    color: var(--ink);
    background: #fff;
    border-color: #d6ccb6;
}

.cds__body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    overscroll-behavior: contain;
}
/* The certificate mounted on a dark display board */
.cds__display {
    padding: clamp(0.9rem, 3vw, 1.4rem);
    background: radial-gradient(ellipse 60% 70% at 50% 0%, rgb(255 236 200 / 0.16), transparent 70%), linear-gradient(180deg, #13214a, #0b1433);
    border-radius: 10px;
    box-shadow: inset 0 0 0 1px rgb(212 175 106 / 0.18);
}

.cds__status {
    display: flex;
    gap: 0.85rem;
    margin-top: 1.25rem;
    padding: 0.95rem 1.05rem;
    border: 1px solid;
    border-radius: 8px;
}
.cds__status-icon {
    flex-shrink: 0;
    margin-top: 0.1rem;
}
.cds__status-label {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 800;
}
.cds__status-note,
.cds__reason {
    margin: 0.2rem 0 0;
    font-size: 0.82rem;
    line-height: 1.5;
}
.cds__reason {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px dashed currentColor;
}
.cds__status--issued {
    color: #0d6b45;
    background: #ecf8f2;
    border-color: #bfe5d1;
}
.cds__status--pending {
    color: #7a5200;
    background: #fdf5e3;
    border-color: #f0dba8;
}
.cds__status--revoked {
    color: #8f1b1b;
    background: #fcefef;
    border-color: #f1c7c7;
}

.cds__number {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1rem;
    padding: 0.9rem 1rem;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
}
.cds__number-text {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.2rem;
}
.cds__number-value {
    overflow-wrap: anywhere;
    color: var(--ink);
    font-family: var(--mono);
    font-size: 1.02rem;
    font-weight: 700;
    letter-spacing: 0.1em;
}
.cds__copy {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.85rem;
    color: #13275f;
    font-size: 0.8rem;
    font-weight: 700;
    background: #f3f1ea;
    border: 1px solid #e2dccd;
    border-radius: 7px;
    cursor: pointer;
    transition:
        background 0.2s,
        color 0.2s,
        border-color 0.2s;
}
.cds__copy:hover {
    background: #ebe7dc;
}
.cds__copy--done {
    color: #0d6b45;
    background: #e6f6ee;
    border-color: #bfe5d1;
}
.cds__notice {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    color: #0d6b45;
    font-size: 0.78rem;
    font-weight: 600;
}
.cds__notice--failed {
    color: #8a5d00;
}

.cds__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin: 1rem 0 0;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
}
.cds__row {
    min-width: 0;
    padding: 0.8rem 1rem;
    border-bottom: 1px solid #f0ece2;
}
.cds__row:nth-child(odd):not(.cds__row--wide) {
    border-left: 1px solid #f0ece2;
}
.cds__row--wide {
    grid-column: 1 / -1;
}
.cds__row:last-child {
    border-bottom: 0;
}
.cds__row dd {
    margin: 0.2rem 0 0;
    overflow-wrap: anywhere;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.4;
}
.cds__label {
    color: #8a7650;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.cds__empty {
    color: #9aa3b8;
    font-weight: 500 !important;
    font-style: italic;
}
.cds__remarks {
    margin-top: 1rem;
    padding: 0.9rem 1rem;
    background: #fff;
    border: 1px solid var(--line);
    border-left: 3px solid var(--gold);
    border-radius: 8px;
}
.cds__remarks p {
    margin: 0.3rem 0 0;
    font-size: 0.86rem;
    line-height: 1.55;
    white-space: pre-line;
}

.cds__actions {
    padding: 1rem 1.5rem 1.15rem;
    background: rgb(251 250 246 / 0.92);
    border-top: 1px solid var(--line);
}
.cds__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.cds__btn {
    display: inline-flex;
    min-height: 42px;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.6rem 1.05rem;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition:
        background 0.2s,
        border-color 0.2s,
        color 0.2s,
        transform 0.2s;
}
.cds__btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.cds__btn--primary {
    color: #fff;
    background: linear-gradient(180deg, #1a3274, #13275f);
    box-shadow: 0 6px 16px -8px rgb(19 39 95 / 0.7);
}
.cds__btn--primary:hover:not(:disabled) {
    background: linear-gradient(180deg, #213d8a, #172f72);
    transform: translateY(-1px);
}
.cds__btn--outline {
    color: var(--ink);
    background: #fff;
    border-color: #d8d0bd;
}
.cds__btn--outline:hover:not(:disabled) {
    border-color: var(--gold);
}
.cds__btn--link {
    margin-left: auto;
    color: #13275f;
    background: transparent;
}
.cds__btn--link:hover {
    color: #1d3fc4;
}
.cds__hint {
    margin: 0.6rem 0 0;
    color: var(--muted);
    font-size: 0.76rem;
}
.cds__close:focus-visible,
.cds__copy:focus-visible,
.cds__btn:focus-visible {
    outline: 2px solid #1d3fc4;
    outline-offset: 2px;
}

/* Copy button icon swap and the inline confirmation */
.cds-swap-enter-active,
.cds-swap-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.cds-swap-enter-from,
.cds-swap-leave-to {
    opacity: 0;
    transform: scale(0.6);
}
.cds-notice-enter-active,
.cds-notice-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}
.cds-notice-enter-from,
.cds-notice-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Dark mode: the panel adapts; the certificate artwork keeps its paper. */
.dark .cds {
    --surface: #0e1528;
    --ink: #e6ecf7;
    --muted: #a3afc7;
    --line: #26324b;
    border-left-color: #2a3654;
}
.dark .cds__kicker,
.dark .cds__label {
    color: #d4b97f;
}
.dark .cds__close:hover {
    background: #16203a;
    border-color: #3a4766;
}
.dark .cds__number,
.dark .cds__grid,
.dark .cds__remarks {
    background: #121a2e;
}
.dark .cds__row {
    border-color: #1f2a42;
}
.dark .cds__copy {
    color: #c7d6f5;
    background: #18233d;
    border-color: #2c3958;
}
.dark .cds__copy:hover {
    background: #1e2b4a;
}
.dark .cds__copy--done,
.dark .cds__notice {
    color: #6ee7b7;
}
.dark .cds__copy--done {
    background: #05966926;
    border-color: #05966966;
}
.dark .cds__notice--failed {
    color: #fcd34d;
}
.dark .cds__status--issued {
    color: #86efac;
    background: #05966920;
    border-color: #05966955;
}
.dark .cds__status--pending {
    color: #fcd34d;
    background: #d9770620;
    border-color: #d9770655;
}
.dark .cds__status--revoked {
    color: #fca5a5;
    background: #dc262620;
    border-color: #dc262655;
}
.dark .cds__actions {
    background: rgb(14 21 40 / 0.94);
}
.dark .cds__btn--outline {
    color: #e6ecf7;
    background: #121a2e;
    border-color: #33405e;
}
.dark .cds__btn--primary {
    color: #1a1405;
    background: linear-gradient(180deg, #f1d999, #d4af5f);
}
.dark .cds__btn--primary:hover:not(:disabled) {
    background: linear-gradient(180deg, #f6e2ad, #dcb96c);
}
.dark .cds__btn--link {
    color: #c7d6f5;
}
.dark .cds__btn--link:hover {
    color: #fff;
}
.dark .cds__close:focus-visible,
.dark .cds__copy:focus-visible,
.dark .cds__btn:focus-visible {
    outline-color: #f5d76e;
}

@media (max-width: 520px) {
    .cds__head,
    .cds__body,
    .cds__actions {
        padding-inline: 1rem;
    }
    .cds__grid {
        grid-template-columns: 1fr;
    }
    .cds__row:nth-child(odd):not(.cds__row--wide) {
        border-left: 0;
    }
    .cds__btn--link {
        margin-left: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cds[data-state='open'],
    .cds[data-state='closed'],
    .cds-overlay[data-state='open'],
    .cds-overlay[data-state='closed'] {
        animation-duration: 0.01ms;
    }
    .cds__btn,
    .cds-swap-enter-active,
    .cds-swap-leave-active,
    .cds-notice-enter-active,
    .cds-notice-leave-active {
        transition: none;
    }
    .cds__btn--primary:hover:not(:disabled) {
        transform: none;
    }
}
</style>
