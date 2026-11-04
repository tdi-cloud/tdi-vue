<template>
    <Head title="My Certificates" />
    <div class="mc-page">
        <TheNavbar />
        <CertificatesHero :stats="stats" />

        <main class="mc-main">
            <template v-if="certificates.length">
                <CertificateSpotlight
                    :certificate="stats.latest"
                    :pending-count="stats.by_status.Pending ?? 0"
                    @preview="openPreview"
                    @details="openDetails"
                    @show-pending="showPending"
                />

                <section ref="collectionRef" class="mc-collection" aria-labelledby="collection-title">
                    <div class="mc-collection__head">
                        <div>
                            <p class="mc-eyebrow"><span aria-hidden="true"></span>The Collection</p>
                            <h2 id="collection-title" class="mc-collection__title">Certificate Collection</h2>
                            <p class="mc-collection__sub">
                                {{ stats.total }} certificate{{ stats.total === 1 ? '' : 's' }} from {{ programCount }} program{{
                                    programCount === 1 ? '' : 's'
                                }}. Browse them by type, status or year.
                            </p>
                        </div>
                        <div class="mc-views" role="group" aria-label="Choose how to view your certificates">
                            <button type="button" class="mc-views__btn" :aria-pressed="viewMode === 'gallery'" @click="viewMode = 'gallery'">
                                <LayoutGrid :size="15" aria-hidden="true" /> Gallery
                            </button>
                            <button type="button" class="mc-views__btn" :aria-pressed="viewMode === 'timeline'" @click="viewMode = 'timeline'">
                                <History :size="15" aria-hidden="true" /> Timeline
                            </button>
                        </div>
                    </div>

                    <div class="mc-explorer">
                        <div class="mc-explorer__top">
                            <div class="mc-search">
                                <Search :size="18" class="mc-search__icon" aria-hidden="true" />
                                <input
                                    v-model="search"
                                    type="search"
                                    class="mc-search__input"
                                    placeholder="Search by program title or certificate number"
                                    aria-label="Search certificates by program title or certificate number"
                                />
                                <button v-if="search" type="button" class="mc-search__clear" aria-label="Clear search" @click="search = ''">
                                    <X :size="15" aria-hidden="true" />
                                </button>
                            </div>
                            <label class="mc-year">
                                <CalendarDays :size="16" aria-hidden="true" />
                                <span class="sr-only">Filter by year</span>
                                <select v-model="selectedYear">
                                    <option value="">All years</option>
                                    <option v-for="year in years" :key="year" :value="String(year)">{{ year }}</option>
                                </select>
                                <ChevronDown :size="15" class="mc-year__chevron" aria-hidden="true" />
                            </label>
                        </div>

                        <div class="mc-explorer__filters">
                            <div class="mc-chips" role="group" aria-label="Filter by certificate type">
                                <button type="button" class="mc-chip" :aria-pressed="selectedType === ''" @click="selectedType = ''">
                                    All Types <span class="mc-chip__count">{{ stats.total }}</span>
                                </button>
                                <button
                                    v-for="chip in typeChips"
                                    :key="chip.type"
                                    type="button"
                                    class="mc-chip"
                                    :style="accentStyle(chip.type)"
                                    :aria-pressed="selectedType === chip.type"
                                    :disabled="!chip.count"
                                    @click="selectedType = selectedType === chip.type ? '' : chip.type"
                                >
                                    <component :is="typeMeta(chip.type).icon" :size="13" class="mc-chip__icon" aria-hidden="true" />
                                    {{ chip.type }} <span class="mc-chip__count">{{ chip.count }}</span>
                                </button>
                            </div>

                            <div class="mc-status" role="group" aria-label="Filter by status">
                                <button
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    type="button"
                                    class="mc-status__btn"
                                    :aria-pressed="selectedStatus === option.value"
                                    @click="selectedStatus = option.value"
                                >
                                    <component :is="option.icon" v-if="option.icon" :size="13" aria-hidden="true" />
                                    {{ option.label }} <span class="mc-status__count">{{ option.count }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mc-results">
                        <p aria-live="polite">
                            Showing <strong>{{ filteredCertificates.length }}</strong> of {{ certificates.length }} certificate{{
                                certificates.length === 1 ? '' : 's'
                            }}
                        </p>
                        <button v-if="hasActiveFilters" type="button" class="mc-results__reset" @click="resetFilters">
                            <RotateCcw :size="13" aria-hidden="true" /> Reset filters
                        </button>
                    </div>

                    <div class="mc-stage">
                        <Transition name="mc-view" mode="out-in">
                            <EmptyState
                                v-if="!filteredCertificates.length"
                                key="empty"
                                variant="filtered"
                                class="mc-filtered-empty"
                                title="No certificates match these filters"
                                description="Try another search term or filter, or reset them to see your whole collection."
                                @clear-filters="resetFilters"
                            />
                            <div v-else-if="viewMode === 'gallery'" key="gallery" class="mc-gallery">
                                <CertificateCard
                                    v-for="(certificate, index) in filteredCertificates"
                                    :key="certificate.id"
                                    :certificate="certificate"
                                    :index="index"
                                    @open="openDetails"
                                />
                            </div>
                            <CertificateTimeline v-else key="timeline" :certificates="filteredCertificates" @open="openDetails" />
                        </Transition>
                    </div>
                </section>
            </template>

            <!-- No certificates yet -->
            <section v-else class="mc-empty" aria-labelledby="mc-empty-title">
                <div class="mc-empty__art" aria-hidden="true">
                    <span class="mc-empty__halo"></span>
                    <span class="mc-empty__sheet mc-empty__sheet--back"></span>
                    <span class="mc-empty__sheet"><i></i><i></i><i></i></span>
                    <span class="mc-empty__seal"><CertificateSeal :icon="Award" status="Pending" ribbons /></span>
                    <span class="mc-empty__spark mc-empty__spark--a"></span>
                    <span class="mc-empty__spark mc-empty__spark--b"></span>
                    <span class="mc-empty__spark mc-empty__spark--c"></span>
                </div>
                <p class="mc-eyebrow mc-eyebrow--center"><span aria-hidden="true"></span>Your Collection<span aria-hidden="true"></span></p>
                <h2 id="mc-empty-title" class="mc-empty__title">Your Achievements Start Here</h2>
                <p class="mc-empty__text">Your certificates will appear here as you complete training programs and receive official recognition.</p>
                <Link :href="route('programs.my-programs')" class="mc-empty__cta"
                    ><GraduationCap :size="17" aria-hidden="true" /> Explore My Programs</Link
                >
            </section>
        </main>

        <TheFooter />

        <CertificateDetailSheet v-model:open="detailsOpen" :certificate="selectedCertificate" @preview="openPreview($event, true)" />
        <FilePreviewModal
            v-model:open="previewOpen"
            :file-url="previewCertificate?.file_url ?? null"
            :title="previewTitle"
            :description="previewDescription"
            content-class="z-[120]"
        />
    </div>
</template>

<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import {
    accentStyle,
    CERTIFICATE_STATUS_META,
    CERTIFICATE_STATUSES,
    CERTIFICATE_TYPES,
    typeMeta,
    type CertificateStats,
    type CertificateStatus,
    type CertificateType,
    type MyCertificate,
} from '@/lib/certificates';
import { Head, Link } from '@inertiajs/vue3';
import { Award, CalendarDays, ChevronDown, GraduationCap, History, LayoutGrid, RotateCcw, Search, X, type LucideIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';
import CertificateCard from './CertificateCard.vue';
import CertificateDetailSheet from './CertificateDetailSheet.vue';
import CertificateSeal from './CertificateSeal.vue';
import CertificatesHero from './CertificatesHero.vue';
import CertificateSpotlight from './CertificateSpotlight.vue';
import CertificateTimeline from './CertificateTimeline.vue';

const props = defineProps<{
    certificates: MyCertificate[];
    stats: CertificateStats;
}>();

/* ---- Filters ---- */
const search = ref('');
const selectedType = ref<CertificateType | ''>('');
const selectedStatus = ref<CertificateStatus | ''>('');
const selectedYear = ref('');

const years = computed(() =>
    Object.keys(props.stats.by_year)
        .filter((year) => year !== '')
        .map(Number)
        .sort((a, b) => b - a),
);
const programCount = computed(() => new Set(props.certificates.map((c) => c.program_code).filter(Boolean)).size);
const typeChips = computed(() => CERTIFICATE_TYPES.map((type) => ({ type, count: props.stats.by_type[type] ?? 0 })));
const statusOptions = computed<{ value: CertificateStatus | ''; label: string; icon: LucideIcon | null; count: number }[]>(() => [
    { value: '', label: 'All', icon: null, count: props.stats.total },
    ...CERTIFICATE_STATUSES.map((status) => ({
        value: status,
        label: status,
        icon: CERTIFICATE_STATUS_META[status].icon,
        count: props.stats.by_status[status] ?? 0,
    })),
]);

const query = computed(() => search.value.trim().toLowerCase());
const filteredCertificates = computed(() =>
    props.certificates.filter((certificate) => {
        if (selectedType.value && certificate.type !== selectedType.value) return false;
        if (selectedStatus.value && certificate.status !== selectedStatus.value) return false;
        if (selectedYear.value && String(certificate.year) !== selectedYear.value) return false;
        if (query.value && ![certificate.program_title, certificate.certificate_number].some((value) => value?.toLowerCase().includes(query.value))) {
            return false;
        }

        return true;
    }),
);
const hasActiveFilters = computed(() => !!(query.value || selectedType.value || selectedStatus.value || selectedYear.value));

function resetFilters() {
    search.value = '';
    selectedType.value = '';
    selectedStatus.value = '';
    selectedYear.value = '';
}

/* ---- Gallery / timeline (remembered in this browser) ---- */
const VIEW_STORAGE_KEY = 'tdi:my-certificates:view';

function storedViewMode(): 'gallery' | 'timeline' {
    try {
        return window.localStorage.getItem(VIEW_STORAGE_KEY) === 'timeline' ? 'timeline' : 'gallery';
    } catch {
        // Storage blocked (private mode, disabled site data): start with the gallery.
        return 'gallery';
    }
}

const viewMode = ref<'gallery' | 'timeline'>(storedViewMode());
watch(viewMode, (mode) => {
    try {
        window.localStorage.setItem(VIEW_STORAGE_KEY, mode);
    } catch {
        // Not saved; the choice still applies until the page is reloaded.
    }
});

const collectionRef = ref<HTMLElement | null>(null);

function showPending() {
    resetFilters();
    selectedStatus.value = 'Pending';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    collectionRef.value?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
}

/* ---- Details sheet and PDF preview ---- */
const selectedCertificate = ref<MyCertificate | null>(null);
const detailsOpen = ref(false);
const previewCertificate = ref<MyCertificate | null>(null);
const previewOpen = ref(false);
// A preview opened from the details sheet hands back to it when closed.
let returnToDetails = false;

function openDetails(certificate: MyCertificate) {
    selectedCertificate.value = certificate;
    detailsOpen.value = true;
}

function openPreview(certificate: MyCertificate, fromDetails = false) {
    if (!certificate.file_url) {
        openDetails(certificate);
        return;
    }

    returnToDetails = fromDetails;
    detailsOpen.value = false;
    previewCertificate.value = certificate;
    previewOpen.value = true;
}

watch(previewOpen, (open) => {
    if (!open && returnToDetails) {
        returnToDetails = false;
        detailsOpen.value = true;
    }
});

const previewTitle = computed(() =>
    previewCertificate.value ? `${typeMeta(previewCertificate.value.type).label} — ${previewCertificate.value.program_title}` : 'Certificate',
);
const previewDescription = computed(() => {
    const certificate = previewCertificate.value;
    if (!certificate) return undefined;
    if (certificate.status === 'Revoked') return `${certificate.certificate_number} · Revoked — shown for reference only`;
    if (certificate.status === 'Pending') return `${certificate.certificate_number} · Awaiting issuance`;

    return certificate.certificate_number ?? undefined;
});
</script>

<style scoped>
.mc-page {
    --serif: 'Iowan Old Style', 'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', Georgia, serif;
    --gold: #b8955a;
    --ink: #17233f;
    --muted: #5d6882;
    --line: #e5dfd0;
    --surface: #fff;
    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    min-height: 100vh;
    overflow-x: clip;
    color: var(--ink);
    background:
        radial-gradient(60rem 30rem at 100% 40rem, rgb(212 175 106 / 0.07), transparent 70%),
        radial-gradient(50rem 30rem at 0% 70rem, rgb(29 63 196 / 0.05), transparent 70%), #f7f6f2;
}
/* Follows the app's light/dark mode (the `dark` class on <html>). */
.dark .mc-page {
    --ink: #e6ecf7;
    --muted: #a3afc7;
    --line: #26324b;
    --surface: #121a2e;
    background:
        radial-gradient(60rem 30rem at 100% 40rem, rgb(212 175 106 / 0.05), transparent 70%),
        radial-gradient(50rem 30rem at 0% 70rem, rgb(59 130 246 / 0.06), transparent 70%), #0a0f1e;
}
/* This page can be opened directly, so it can't rely on the homepage's global reset.
   Fully wrapped in :where() (zero specificity) so it never overrides the sections' own margins. */
:where(.mc-page) :where(*, *::before, *::after) {
    box-sizing: border-box;
}
:where(.mc-page) :where(h1, h2, h3, h4, p) {
    margin: 0;
    padding: 0;
}

.mc-main {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: -5.5rem auto 0;
    padding: 0 2rem 5.5rem;
}

.mc-eyebrow {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 0.6rem !important;
    color: #8a7650;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.26em;
    text-transform: uppercase;
}
.mc-eyebrow span {
    width: 26px;
    height: 1px;
    background: var(--gold);
}
.mc-eyebrow--center {
    justify-content: center;
}
.dark .mc-eyebrow {
    color: #d4b97f;
}

/* ---- Collection ---- */
.mc-collection {
    margin-top: 4.5rem;
    scroll-margin-top: 90px;
}
.mc-collection__head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.mc-collection__title {
    font-family: var(--serif);
    font-size: clamp(1.7rem, 3vw, 2.2rem);
    font-weight: 700;
    line-height: 1.15;
}
.mc-collection__sub {
    margin-top: 0.45rem !important;
    color: var(--muted);
    font-size: 0.92rem;
}

.mc-views {
    display: inline-flex;
    gap: 3px;
    padding: 3px;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 10px;
}
.mc-views__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.95rem;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 700;
    background: transparent;
    border: 0;
    border-radius: 7px;
    cursor: pointer;
    transition:
        background 0.25s ease,
        color 0.25s ease;
}
.mc-views__btn:hover {
    color: var(--ink);
}
.mc-views__btn[aria-pressed='true'] {
    color: #fff;
    background: #13275f;
    box-shadow: 0 4px 12px -4px rgb(19 39 95 / 0.6);
}
.dark .mc-views__btn[aria-pressed='true'] {
    color: #1a1405;
    background: linear-gradient(180deg, #f1d999, #d4af5f);
    box-shadow: none;
}

/* ---- Explorer ---- */
.mc-explorer {
    margin-top: 1.75rem;
}
.mc-explorer__top {
    display: flex;
    gap: 0.75rem;
}
.mc-search {
    position: relative;
    display: flex;
    min-width: 0;
    flex: 1;
    align-items: center;
}
.mc-search__icon {
    position: absolute;
    left: 1.05rem;
    color: #8a7650;
    pointer-events: none;
}
.mc-search__input {
    width: 100%;
    height: 52px;
    padding: 0 2.9rem 0 3rem;
    color: var(--ink);
    font: inherit;
    font-size: 0.95rem;
    background:
        linear-gradient(180deg, rgb(255 255 255 / 0.9), rgb(255 255 255 / 0.7)) padding-box,
        var(--surface);
    border: 1px solid var(--line);
    border-radius: 12px;
    box-shadow: 0 6px 20px -14px rgb(12 23 57 / 0.35);
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}
.mc-search__input::placeholder {
    color: #8f97aa;
    font-family: var(--serif);
    font-style: italic;
}
.mc-search__input::-webkit-search-cancel-button {
    -webkit-appearance: none;
    appearance: none;
}
.mc-search__input:focus {
    border-color: var(--gold);
    outline: none;
    box-shadow:
        0 0 0 4px rgb(184 149 90 / 0.18),
        0 6px 20px -14px rgb(12 23 57 / 0.35);
}
.mc-search__clear {
    position: absolute;
    right: 0.7rem;
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    color: var(--muted);
    background: #f1ede3;
    border: 0;
    border-radius: 50%;
    cursor: pointer;
}
.mc-search__clear:hover {
    color: var(--ink);
    background: #e7e1d3;
}
.mc-year {
    position: relative;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.5rem;
    height: 52px;
    padding: 0 0.9rem;
    color: #8a7650;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 12px;
}
.mc-year:focus-within {
    border-color: var(--gold);
    box-shadow: 0 0 0 4px rgb(184 149 90 / 0.18);
}
.mc-year select {
    min-width: 92px;
    padding-right: 1.4rem;
    color: var(--ink);
    font: inherit;
    font-size: 0.88rem;
    font-weight: 600;
    background: transparent;
    border: 0;
    outline: none;
    cursor: pointer;
    appearance: none;
}
.mc-year__chevron {
    position: absolute;
    right: 0.85rem;
    color: var(--muted);
    pointer-events: none;
}
.dark .mc-search__input {
    background: var(--surface);
    box-shadow: none;
}
.dark .mc-search__input::placeholder {
    color: #7f8aa3;
}
.dark .mc-search__clear {
    background: #1d2742;
}
.dark .mc-search__clear:hover {
    background: #26324b;
}
.dark .mc-search__icon,
.dark .mc-year {
    color: #d4b97f;
}
.dark .mc-year select option {
    color: #0f172a;
}

.mc-explorer__filters {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    margin-top: 1rem;
}
.mc-chips {
    display: flex;
    min-width: 0;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.mc-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.8rem;
    color: #3b4660;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 999px;
    cursor: pointer;
    transition:
        background 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease;
}
.mc-chip__icon {
    color: var(--accent, #8a7650);
}
.mc-chip__count {
    min-width: 1.4em;
    padding: 0.05rem 0.4rem;
    color: #6b7690;
    font-size: 0.7rem;
    font-variant-numeric: tabular-nums;
    text-align: center;
    background: #f3f0e8;
    border-radius: 999px;
}
.mc-chip:hover:not(:disabled) {
    border-color: rgb(var(--accent-rgb, 184 149 90) / 0.6);
}
.mc-chip[aria-pressed='true'] {
    color: #fff;
    background: #13275f;
    border-color: #13275f;
}
.mc-chip[aria-pressed='true'] .mc-chip__icon {
    color: #e9d29a;
}
.mc-chip[aria-pressed='true'] .mc-chip__count {
    color: #13275f;
    background: #e9d29a;
}
.mc-chip:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.dark .mc-chip {
    color: #c9d3e6;
}
.dark .mc-chip__icon {
    filter: brightness(1.5);
}
.dark .mc-chip__count {
    color: #a3afc7;
    background: #1d2742;
}
.dark .mc-chip[aria-pressed='true'] {
    color: #1a1405;
    background: linear-gradient(180deg, #f1d999, #d4af5f);
    border-color: #d4af5f;
}
.dark .mc-chip[aria-pressed='true'] .mc-chip__icon {
    color: #1a1405;
    filter: none;
}
.dark .mc-chip[aria-pressed='true'] .mc-chip__count {
    color: #f1d999;
    background: #13275f;
}

/* Status: understated tabs with a gold underline */
.mc-status {
    display: flex;
    gap: 0.25rem;
    border-bottom: 1px solid var(--line);
}
.mc-status__btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem 0.75rem 0.65rem;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
    background: none;
    border: 0;
    cursor: pointer;
    transition: color 0.2s ease;
}
.mc-status__btn::after {
    position: absolute;
    right: 0.6rem;
    bottom: -1px;
    left: 0.6rem;
    height: 2px;
    background: var(--gold);
    border-radius: 2px;
    transform: scaleX(0);
    transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
    content: '';
}
.mc-status__btn:hover {
    color: var(--ink);
}
.mc-status__btn[aria-pressed='true'] {
    color: var(--ink);
    font-weight: 800;
}
.mc-status__btn[aria-pressed='true']::after {
    transform: scaleX(1);
}
.mc-status__count {
    color: #8f97aa;
    font-size: 0.72rem;
    font-variant-numeric: tabular-nums;
}

.mc-results {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin: 1.5rem 0 1.25rem;
    color: var(--muted);
    font-size: 0.82rem;
}
.mc-results strong {
    color: var(--ink);
}
.mc-results__reset {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    color: #13275f;
    font-size: 0.8rem;
    font-weight: 700;
    background: none;
    border: 1px solid var(--line);
    border-radius: 8px;
    cursor: pointer;
}
.mc-results__reset:hover {
    border-color: var(--gold);
}
.dark .mc-results__reset {
    color: #c7d6f5;
}

.mc-views__btn:focus-visible,
.mc-chip:focus-visible,
.mc-status__btn:focus-visible,
.mc-search__clear:focus-visible,
.mc-results__reset:focus-visible,
.mc-empty__cta:focus-visible {
    outline: 2px solid #1d3fc4;
    outline-offset: 2px;
}
.dark .mc-views__btn:focus-visible,
.dark .mc-chip:focus-visible,
.dark .mc-status__btn:focus-visible,
.dark .mc-search__clear:focus-visible,
.dark .mc-results__reset:focus-visible,
.dark .mc-empty__cta:focus-visible {
    outline-color: #f5d76e;
}

/* ---- Views ---- */
.mc-stage {
    min-height: 320px;
}
.mc-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 2.25rem 1.85rem;
}
.mc-filtered-empty {
    max-width: 640px;
    margin: 1rem auto 0;
}
.mc-view-enter-active,
.mc-view-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease,
        filter 0.3s ease;
}
.mc-view-enter-from {
    opacity: 0;
    transform: translateY(10px);
    filter: blur(2px);
}
.mc-view-leave-to {
    opacity: 0;
    transform: translateY(-6px);
    filter: blur(2px);
}

/* ---- Empty state (no certificates yet) ---- */
.mc-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: clamp(2.5rem, 6vw, 4.5rem) 1.5rem;
    text-align: center;
    background: radial-gradient(ellipse 60% 55% at 50% 0%, rgb(212 175 106 / 0.12), transparent 70%), var(--surface);
    border: 1px solid rgb(184 149 90 / 0.28);
    border-radius: 18px;
    box-shadow: 0 40px 80px -50px rgb(7 14 36 / 0.45);
}
.mc-empty__art {
    position: relative;
    width: 230px;
    height: 170px;
    margin-bottom: 1.75rem;
}
.mc-empty__halo {
    position: absolute;
    inset: -10px 10px;
    background: radial-gradient(circle, rgb(29 63 196 / 0.12), transparent 65%);
    border-radius: 50%;
}
.mc-empty__sheet {
    position: absolute;
    top: 18px;
    left: 30px;
    display: flex;
    width: 170px;
    height: 120px;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding-top: 26px;
    background: #fbf8f0;
    border: 1.5px dashed rgb(184 149 90 / 0.7);
    border-radius: 3px;
    transform: rotate(-4deg);
}
.mc-empty__sheet i {
    display: block;
    width: 90px;
    height: 5px;
    background: #e7dfcc;
    border-radius: 3px;
}
.mc-empty__sheet i:first-child {
    width: 56px;
    height: 4px;
    background: rgb(184 149 90 / 0.55);
}
.mc-empty__sheet i:last-child {
    width: 70px;
}
.mc-empty__sheet--back {
    background: rgb(19 39 95 / 0.08);
    border: 1px solid rgb(19 39 95 / 0.12);
    transform: rotate(7deg) translate(10px, -4px);
}
.mc-empty__seal {
    position: absolute;
    right: 18px;
    bottom: -4px;
    width: 58px;
    aspect-ratio: 100 / 130;
    animation: mc-empty-bob 5s ease-in-out infinite;
}
@keyframes mc-empty-bob {
    0%,
    100% {
        transform: translateY(0) rotate(6deg);
    }
    50% {
        transform: translateY(-6px) rotate(2deg);
    }
}
.mc-empty__spark {
    position: absolute;
    width: 9px;
    height: 9px;
    background: #d4af5f;
    clip-path: polygon(50% 0, 62% 38%, 100% 50%, 62% 62%, 50% 100%, 38% 62%, 0 50%, 38% 38%);
}
.mc-empty__spark--a {
    top: 4px;
    left: 12px;
}
.mc-empty__spark--b {
    top: 0;
    right: 30px;
    width: 12px;
    height: 12px;
}
.mc-empty__spark--c {
    bottom: 22px;
    left: 2px;
    width: 7px;
    height: 7px;
    opacity: 0.7;
}
.mc-empty__title {
    font-family: var(--serif);
    font-size: clamp(1.7rem, 3.4vw, 2.3rem);
    font-weight: 700;
    line-height: 1.15;
}
.mc-empty__text {
    max-width: 480px;
    margin-top: 0.75rem !important;
    color: var(--muted);
    font-size: 0.98rem;
    line-height: 1.65;
}
.mc-empty__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.75rem;
    padding: 0.8rem 1.4rem;
    color: #fff;
    font-size: 0.92rem;
    font-weight: 700;
    text-decoration: none;
    background: linear-gradient(180deg, #1a3274, #13275f);
    border-radius: 10px;
    box-shadow: 0 12px 26px -12px rgb(19 39 95 / 0.7);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}
.mc-empty__cta:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 30px -12px rgb(19 39 95 / 0.8);
}
.dark .mc-empty {
    background: radial-gradient(ellipse 60% 55% at 50% 0%, rgb(212 175 106 / 0.1), transparent 70%), #10182c;
    border-color: rgb(212 175 106 / 0.18);
}
.dark .mc-empty__sheet--back {
    background: rgb(148 163 209 / 0.08);
    border-color: rgb(148 163 209 / 0.16);
}
.dark .mc-empty__halo {
    background: radial-gradient(circle, rgb(212 175 106 / 0.12), transparent 65%);
}
.dark .mc-empty__cta {
    color: #1a1405;
    background: linear-gradient(180deg, #f3dda2, #d4af5f);
    box-shadow: 0 12px 26px -12px rgb(212 175 95 / 0.6);
}

@media (max-width: 1024px) {
    .mc-gallery {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .mc-main {
        margin-top: -5rem;
        padding: 0 1rem 4rem;
    }
    .mc-collection {
        margin-top: 3.25rem;
    }
    .mc-collection__head {
        align-items: stretch;
        flex-direction: column;
    }
    .mc-views {
        align-self: flex-start;
    }
    .mc-explorer__top {
        flex-direction: column;
    }
    .mc-year {
        align-self: stretch;
    }
    .mc-year select {
        flex: 1;
    }
    /* Type chips scroll sideways inside the page gutter instead of wrapping into many rows. */
    .mc-chips {
        width: calc(100% + 2rem);
        flex-wrap: nowrap;
        margin: 0 -1rem;
        padding: 0.15rem 1rem;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-mask-image: linear-gradient(90deg, transparent, #000 1rem, #000 calc(100% - 1rem), transparent);
        mask-image: linear-gradient(90deg, transparent, #000 1rem, #000 calc(100% - 1rem), transparent);
    }
    .mc-chips::-webkit-scrollbar {
        display: none;
    }
    .mc-status {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .mc-status__btn {
        flex: 1;
        justify-content: center;
        padding-inline: 0.4rem;
    }
    .mc-results {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.6rem;
    }
    .mc-gallery {
        grid-template-columns: 1fr;
        gap: 1.75rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .mc-view-enter-active,
    .mc-view-leave-active,
    .mc-views__btn,
    .mc-chip,
    .mc-status__btn::after,
    .mc-empty__cta {
        transition: none;
    }
    .mc-empty__seal {
        animation: none;
    }
    .mc-empty__cta:hover {
        transform: none;
    }
}
</style>
