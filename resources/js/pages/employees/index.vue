<script setup lang="ts">
import EmployeePictureDialog from '@/components/employee-progress/EmployeePictureDialog.vue';
import EmployeeProgressCard from '@/components/employee-progress/EmployeeProgressCard.vue';
import EmployeeProgressPager from '@/components/employee-progress/EmployeeProgressPager.vue';
import EmployeeProgressModal from '@/components/EmployeeProgressModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import DirectoryAmbience from '@/components/position-directory/DirectoryAmbience.vue';
import DirectoryCombobox from '@/components/position-directory/DirectoryCombobox.vue';
import DirectoryHero from '@/components/position-directory/DirectoryHero.vue';
import DirectorySearchField from '@/components/position-directory/DirectorySearchField.vue';
import FanCarousel from '@/components/position-directory/FanCarousel.vue';
import ResultsSkeleton from '@/components/position-directory/ResultsSkeleton.vue';
import ViewModeToggle from '@/components/position-directory/ViewModeToggle.vue';
import SearchEmptyState from '@/components/SearchEmptyState.vue';
import { useMotionLevel } from '@/composables/useMotionLevel';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedEmployees, ProgressEmployee } from '@/lib/employeeProgress';
import { pluralize, type ComboboxOption, type HeroStat, type PhotoRules } from '@/lib/positionDirectory';
import { Head, router } from '@inertiajs/vue3';
import { useIntersectionObserver, useResizeObserver } from '@vueuse/core';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, GraduationCap, Info, Layers, Users, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
// Theme colours (.pd-theme), shared with the Position Directory.
import '../../../css/position-directory.css';

const props = defineProps<{
    employees: PaginatedEmployees;
    regions: (string | null)[];
    offices: (string | null)[];
    plantillaStatuses: (string | null)[];
    filters: {
        search?: string;
        region?: string;
        office?: string;
        plantilla?: string;
        per_page?: string;
    };
    // "Did you mean …?" when a search finds nothing (server-side SearchSuggester).
    suggestion?: string | null;
    // Superadmins add, change or remove employee pictures from the cards.
    canManagePhotos: boolean;
    photoRules: PhotoRules;
}>();

// ─── How much motion to show ────────────────────────────────────────────────
const motionLevel = useMotionLevel();

// ─── Filters (kept in the query string) ────────────────────────────────────
const PER_PAGE_OPTIONS = ['10', '25', '50', '100'];

const search = ref(props.filters.search ?? '');
const region = ref(props.filters.region ?? 'all');
const office = ref(props.filters.office ?? 'all');
const plantilla = ref(props.filters.plantilla ?? 'all');
const perPage = ref(props.filters.per_page ?? '10');

// A new region changes the office choices, so the office selection starts over.
watch(region, () => {
    office.value = 'all';
});

const hasActiveFilters = computed(
    () => search.value !== '' || region.value !== 'all' || office.value !== 'all' || plantilla.value !== 'all' || perPage.value !== '10',
);

function clearFilters(): void {
    search.value = '';
    region.value = 'all';
    office.value = 'all';
    plantilla.value = 'all';
    perPage.value = '10';
}

const isFilled = (value: string | null | undefined): value is string => typeof value === 'string' && value.trim() !== '';

const regionOptions = computed<ComboboxOption[]>(() => [
    { value: 'all', label: 'All regions' },
    ...props.regions.filter(isFilled).map((value) => ({ value, label: value })),
]);

const officeOptions = computed<ComboboxOption[]>(() => [
    { value: 'all', label: 'All offices' },
    ...props.offices.filter(isFilled).map((value) => ({ value, label: value })),
]);

const plantillaOptions = computed<ComboboxOption[]>(() => [
    { value: 'all', label: 'All statuses' },
    ...props.plantillaStatuses.filter(isFilled).map((value) => ({ value, label: value.toUpperCase() })),
]);

/** Removable chips for the floating bar on narrow pages. */
const activeFilterChips = computed(() => {
    const chips: { key: string; label: string; clear: () => void }[] = [];
    if (region.value !== 'all') {
        chips.push({ key: 'region', label: region.value, clear: () => (region.value = 'all') });
    }
    if (office.value !== 'all') {
        chips.push({ key: 'office', label: office.value, clear: () => (office.value = 'all') });
    }
    if (plantilla.value !== 'all') {
        chips.push({ key: 'plantilla', label: plantilla.value.toUpperCase(), clear: () => (plantilla.value = 'all') });
    }
    return chips;
});

// ─── Loading results (partial reloads: the region/status lists aren't re-queried) ─
const RELOADED_PROPS = ['employees', 'offices', 'filters', 'suggestion'];

const isLoading = ref(false);
const resultsRef = ref<HTMLElement | null>(null);
let latestVisit = 0;

/** Card to centre in the fan when the next page of results arrives. */
let pendingLanding: 'first' | 'last' = 'first';

function visitEmployees(url: string, data: Record<string, string | undefined>, options: { replace: boolean; landOn?: 'first' | 'last' }): void {
    const visit = ++latestVisit;
    pendingLanding = options.landOn ?? 'first';

    router.get(url, data, {
        only: RELOADED_PROPS,
        preserveState: true,
        preserveScroll: true,
        replace: options.replace,
        onStart: () => {
            isLoading.value = true;
        },
        onSuccess: () => {
            // Deep in the previous list (e.g. at the pager below the grid)? Bring the new list's top into view.
            const top = resultsRef.value?.getBoundingClientRect().top ?? 0;
            if (top < 0) {
                resultsRef.value?.scrollIntoView({ behavior: motionLevel.value === 'still' ? 'auto' : 'smooth', block: 'start' });
            }
        },
        onFinish: () => {
            if (visit === latestVisit) {
                isLoading.value = false;
            }
        },
    });
}

let debounce: ReturnType<typeof setTimeout>;
watch([search, region, office, plantilla, perPage], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        restoreFanFocus = false;
        visitEmployees(
            route('employees.index'),
            {
                search: search.value || undefined,
                region: region.value !== 'all' ? region.value : undefined,
                office: office.value !== 'all' ? office.value : undefined,
                plantilla: plantilla.value !== 'all' ? plantilla.value : undefined,
                per_page: perPage.value !== '10' ? perPage.value : undefined,
            },
            { replace: true },
        );
    }, 350);
});

function goToPage(url: string | null, landOn: 'first' | 'last' = 'first'): void {
    if (!url || isLoading.value) {
        return;
    }
    visitEmployees(url, {}, { replace: false, landOn });
}

// ─── Layout: 3D fan showcase (default) or a card grid ─────────────────────
type ViewMode = 'showcase' | 'grid';
const VIEW_STORAGE_KEY = 'tdi:employees:view';

function readViewMode(): ViewMode {
    try {
        return window.localStorage.getItem(VIEW_STORAGE_KEY) === 'grid' ? 'grid' : 'showcase';
    } catch {
        return 'showcase';
    }
}

const viewMode = ref<ViewMode>(readViewMode());

watch(viewMode, (mode) => {
    try {
        window.localStorage.setItem(VIEW_STORAGE_KEY, mode);
    } catch {
        // Not remembered; the choice still applies until the page is reloaded.
    }
});

/** Employee in the centre of the fan (index on the current page). */
const fanIndex = ref(0);
const fanRef = ref<{ element: HTMLElement | null } | null>(null);
let restoreFanFocus = false;

watch(
    () => props.employees,
    (page) => {
        fanIndex.value = pendingLanding === 'last' ? Math.max(0, page.data.length - 1) : 0;
        pendingLanding = 'first';
    },
);

/** Arrows, keys or a swipe past either end of the page: continue on the previous/next page. */
function onFanEdge(direction: -1 | 1): void {
    const url = direction === 1 ? props.employees.next_page_url : props.employees.prev_page_url;
    if (!url || isLoading.value) {
        return;
    }
    restoreFanFocus = fanRef.value?.element?.contains(document.activeElement) ?? false;
    goToPage(url, direction === 1 ? 'first' : 'last');
}

// The fan is rebuilt after loading; keyboard users keep browsing where they were.
watch(
    fanRef,
    (fan) => {
        if (fan?.element && restoreFanFocus) {
            restoreFanFocus = false;
            fan.element.focus({ preventScroll: true });
        }
    },
    { flush: 'post' },
);

/** Taller text block under a shorter picture than the directory card (see EmployeeProgressCard). */
const fanGeometry = { '--fan-card-ratio': '0.8', '--fan-card-text': '185px' };

// ─── One-screen layout on wide pages ───────────────────────────────────────
// Left: title, 3D emblem, search, filters and counts (pinned). Right: the cards.
// Measured on the page itself (not the window) because the sidebar may be open
// or collapsed; the gap between thresholds stops a scrollbar appearing or
// disappearing from flipping the layout back and forth.
const pageRef = ref<HTMLElement | null>(null);
const isWide = ref(false);

// Applied on the next frame: changing the layout inside the observer callback
// makes the browser report a "ResizeObserver loop" error.
let layoutFrame = 0;
useResizeObserver(pageRef, ([entry]) => {
    const width = entry.contentRect.width;
    cancelAnimationFrame(layoutFrame);
    layoutFrame = requestAnimationFrame(() => {
        isWide.value = isWide.value ? width >= 980 : width >= 1000;
    });
});

/** The fan stretches to fill the right column, so everything fits one screen. */
const fillsScreen = computed(() => isWide.value && viewMode.value === 'showcase');

// On narrow pages the banner scrolls away, and a floating bar takes over.
const heroRef = ref<HTMLElement | null>(null);
const isHeroVisible = ref(true);
useIntersectionObserver(heroRef, ([entry]) => {
    isHeroVisible.value = entry?.isIntersecting ?? true;
});

// Kept open while it has focus, so it doesn't vanish mid-typing when shorter results bring the banner back.
const toolbarRef = ref<HTMLElement | null>(null);
const toolbarHasFocus = ref(false);

function onToolbarFocusOut(event: FocusEvent): void {
    toolbarHasFocus.value = toolbarRef.value?.contains(event.relatedTarget as Node | null) ?? false;
}

const isToolbarShown = computed(() => !isWide.value && (!isHeroVisible.value || toolbarHasFocus.value));

// ─── Hero counts and wording ───────────────────────────────────────────────
const inTrainingCount = computed(() => props.employees.data.filter((employee) => employee.progress_stats.total_programs > 0).length);

const heroStats = computed<HeroStat[]>(() => [
    { label: 'Employees', value: props.employees.total, icon: Users },
    { label: 'Page', value: props.employees.current_page, suffix: `/ ${props.employees.last_page.toLocaleString()}`, icon: Layers },
    { label: 'In training', value: inTrainingCount.value, suffix: `/ ${props.employees.data.length}`, icon: GraduationCap },
]);

/** Changes with every new set of results, to play the hero's light sweep. */
const resultsKey = computed(() => `${props.employees.current_page}|${JSON.stringify(props.filters)}`);

/** What the shown results are filtered by (the server's filters, not the fields being typed in). */
const resultsTitle = computed(() => {
    if (props.filters.search) {
        return `Results for “${props.filters.search}”`;
    }
    const parts = [props.filters.region, props.filters.office, props.filters.plantilla?.toUpperCase()].filter(isFilled);
    return parts.length > 0 ? parts.join(' · ') : 'All employees';
});

const announcement = computed(() => {
    if (isLoading.value) {
        return '';
    }
    const { total, from, to } = props.employees;
    return total === 0 ? 'No employees found.' : `Showing ${from ?? 0}–${to ?? 0} of ${pluralize(total, 'employee')}.`;
});

// ─── Progress modal ────────────────────────────────────────────────────────
const selectedEmpcode = ref<string | null>(null);

function openDetails(employee: ProgressEmployee): void {
    selectedEmpcode.value = employee.EMPCODE;
}

function closeModal(): void {
    selectedEmpcode.value = null;
}

// ─── Employee pictures (superadmins) ───────────────────────────────────────
// Pictures changed in this session, by EMPCODE: shown at once, without reloading the list.
const photoOverrides = reactive(new Map<string, string | null>());

function photoUrlFor(employee: ProgressEmployee): string | null {
    return photoOverrides.has(employee.EMPCODE) ? (photoOverrides.get(employee.EMPCODE) ?? null) : employee.photo_url;
}

const isPictureDialogOpen = ref(false);
const pictureEmployee = ref<ProgressEmployee | null>(null);

function openPictureDialog(employee: ProgressEmployee): void {
    pictureEmployee.value = employee;
    isPictureDialogOpen.value = true;
}

function onPhotoChanged(empcode: string, url: string | null): void {
    photoOverrides.set(empcode, url);
}
</script>

<template>
    <Head title="Employee Progress" />

    <AppLayout>
        <div ref="pageRef" class="pd-theme pd-page" :data-motion="motionLevel">
            <DirectoryAmbience :motion-level="motionLevel" :tone="(employees.current_page - 1) % 4" />

            <p class="sr-only" aria-live="polite">{{ announcement }}</p>

            <div class="pd-page__inner" :class="{ 'is-wide': isWide }">
                <aside ref="heroRef" class="pd-page__side">
                    <DirectoryHero
                        title-accent="Employees"
                        subtitle="TESDA Workforce Training Progress"
                        :stats="heroStats"
                        :node-count="employees.data.length"
                        :pulse-key="resultsKey"
                        visual-label="Each orbiting light is an employee on this page"
                        emblem="gear"
                        :motion-level="motionLevel"
                        :loading="isLoading"
                        :panel="isWide"
                    >
                        <template #selector>
                            <div class="ep-filters">
                                <DirectorySearchField
                                    v-model="search"
                                    label="Search employees"
                                    placeholder="Name, employee code or office…"
                                    :loading="isLoading"
                                />
                                <div class="ep-filters__grid">
                                    <DirectoryCombobox
                                        :options="regionOptions"
                                        :selected="region"
                                        label="Region"
                                        placeholder="All regions"
                                        item-name="region"
                                        size="small"
                                        @select="(value) => (region = value)"
                                    />
                                    <DirectoryCombobox
                                        :options="plantillaOptions"
                                        :selected="plantilla"
                                        label="Plantilla status"
                                        placeholder="All statuses"
                                        item-name="status"
                                        item-name-plural="statuses"
                                        size="small"
                                        align="end"
                                        :searchable="false"
                                        @select="(value) => (plantilla = value)"
                                    />
                                    <DirectoryCombobox
                                        class="ep-filters__wide"
                                        :options="officeOptions"
                                        :selected="office"
                                        label="Office"
                                        placeholder="All offices"
                                        item-name="office"
                                        size="small"
                                        @select="(value) => (office = value)"
                                    />
                                </div>
                            </div>
                        </template>
                        <template #footer>
                            <div class="ep-hero-footer">
                                <p class="ep-order-note">
                                    <Info class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                    Employees with training programs are listed first, then by last name.
                                </p>
                                <button v-if="hasActiveFilters" type="button" class="ep-clear" @click="clearFilters">
                                    <X class="h-3.5 w-3.5" aria-hidden="true" />
                                    Clear filters
                                </button>
                            </div>
                        </template>
                    </DirectoryHero>
                </aside>

                <section
                    ref="resultsRef"
                    class="pd-results"
                    :class="{ 'is-fill': fillsScreen }"
                    aria-labelledby="ep-results-title"
                    :aria-busy="isLoading"
                >
                    <!-- Narrow pages: floats from a zero-height sticky anchor once the banner has scrolled away -->
                    <div v-if="!isWide" class="pd-toolbar-anchor">
                        <Transition name="pd-fade">
                            <div
                                v-show="isToolbarShown"
                                ref="toolbarRef"
                                class="pd-toolbar"
                                @focusin="toolbarHasFocus = true"
                                @focusout="onToolbarFocusOut"
                            >
                                <div class="pd-toolbar__search">
                                    <DirectorySearchField
                                        v-model="search"
                                        variant="compact"
                                        label="Search employees"
                                        placeholder="Search employees…"
                                        :loading="isLoading"
                                    />
                                </div>

                                <ul v-if="activeFilterChips.length > 0" class="pd-toolbar__chips" aria-label="Active filters">
                                    <li v-for="chip in activeFilterChips" :key="chip.key">
                                        <button
                                            type="button"
                                            class="pd-toolbar__chip"
                                            :aria-label="`Remove filter ${chip.label}`"
                                            @click="chip.clear"
                                        >
                                            <span class="truncate">{{ chip.label }}</span>
                                            <X class="h-3 w-3 shrink-0" aria-hidden="true" />
                                        </button>
                                    </li>
                                </ul>

                                <div v-if="employees.last_page > 1" class="pd-toolbar__pages">
                                    <button
                                        type="button"
                                        class="pd-toolbar__page-button"
                                        :disabled="!employees.prev_page_url || isLoading"
                                        aria-label="Previous page"
                                        @click="goToPage(employees.prev_page_url)"
                                    >
                                        <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                    <span class="pd-toolbar__page-count">
                                        {{ employees.current_page.toLocaleString() }} / {{ employees.last_page.toLocaleString() }}
                                    </span>
                                    <button
                                        type="button"
                                        class="pd-toolbar__page-button"
                                        :disabled="!employees.next_page_url || isLoading"
                                        aria-label="Next page"
                                        @click="goToPage(employees.next_page_url)"
                                    >
                                        <ChevronRight class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <Transition name="pd-stage" mode="out-in">
                        <!-- Loading -->
                        <ResultsSkeleton v-if="isLoading" key="loading" label="Loading employees…" :motion-level="motionLevel" />

                        <!-- Results -->
                        <div v-else-if="employees.data.length > 0" key="results" class="pd-results__list">
                            <header class="pd-results__header">
                                <div class="min-w-0">
                                    <p class="pd-results__eyebrow">Training progress</p>
                                    <h2 id="ep-results-title" class="pd-results__title">{{ resultsTitle }}</h2>
                                </div>
                                <div class="pd-results__aside">
                                    <ViewModeToggle v-model="viewMode" />
                                    <div class="ep-per-page" role="group" aria-label="Employees per page">
                                        <span class="ep-per-page__label" aria-hidden="true">Per page</span>
                                        <button
                                            v-for="option in PER_PAGE_OPTIONS"
                                            :key="option"
                                            type="button"
                                            class="ep-per-page__button"
                                            :aria-pressed="perPage === option"
                                            :aria-label="`${option} per page`"
                                            @click="perPage = option"
                                        >
                                            {{ option }}
                                        </button>
                                    </div>
                                </div>
                            </header>

                            <FanCarousel
                                v-if="viewMode === 'showcase'"
                                ref="fanRef"
                                v-model:active-index="fanIndex"
                                :items="employees.data"
                                :item-key="(employee) => employee.EMPCODE"
                                :item-name="(employee) => employee.name"
                                label="Employees and their training progress"
                                :motion-level="motionLevel"
                                :fill="fillsScreen"
                                :has-previous="Boolean(employees.prev_page_url)"
                                :has-next="Boolean(employees.next_page_url)"
                                :counter-offset="(employees.from ?? 1) - 1"
                                :counter-total="employees.total"
                                :style="fanGeometry"
                                @open="openDetails"
                                @edge="onFanEdge"
                            >
                                <!-- Page of results in the fan -->
                                <template #header>
                                    <header class="ep-fan-header">
                                        <p class="ep-fan-header__index" aria-hidden="true">
                                            {{ String(employees.current_page).padStart(2, '0') }}<span>/ {{ employees.last_page }}</span>
                                        </p>
                                        <div class="ep-fan-header__copy">
                                            <p class="ep-fan-header__eyebrow">Page {{ employees.current_page }} of {{ employees.last_page }}</p>
                                            <h3 class="ep-fan-header__title">
                                                Employees {{ (employees.from ?? 0).toLocaleString() }}–{{ (employees.to ?? 0).toLocaleString() }}
                                                <span>of {{ employees.total.toLocaleString() }}</span>
                                            </h3>
                                        </div>
                                        <div v-if="employees.last_page > 1" class="ep-fan-header__nav">
                                            <button
                                                type="button"
                                                class="ep-fan-header__button"
                                                :disabled="!employees.prev_page_url"
                                                @click="goToPage(employees.prev_page_url)"
                                            >
                                                <ChevronsLeft class="h-4 w-4" aria-hidden="true" />
                                                <span>Previous page</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="ep-fan-header__button"
                                                :disabled="!employees.next_page_url"
                                                @click="goToPage(employees.next_page_url)"
                                            >
                                                <span>Next page</span>
                                                <ChevronsRight class="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </div>
                                    </header>
                                </template>

                                <template #card="{ item, featured }">
                                    <EmployeeProgressCard
                                        :employee="item"
                                        :photo-url="photoUrlFor(item)"
                                        :index="0"
                                        :motion-level="motionLevel"
                                        :featured="featured"
                                        :can-manage-photos="canManagePhotos"
                                        @open="openDetails"
                                        @manage-photo="openPictureDialog"
                                    />
                                </template>
                            </FanCarousel>

                            <div v-else class="ep-grid">
                                <EmployeeProgressCard
                                    v-for="(employee, index) in employees.data"
                                    :key="employee.EMPCODE"
                                    :employee="employee"
                                    :photo-url="photoUrlFor(employee)"
                                    :index="index"
                                    :motion-level="motionLevel"
                                    :can-manage-photos="canManagePhotos"
                                    @open="openDetails"
                                    @manage-photo="openPictureDialog"
                                />
                            </div>

                            <!-- The one-screen showcase pages through its header and arrows instead -->
                            <EmployeeProgressPager
                                v-if="!fillsScreen"
                                class="ep-pager"
                                :links="employees.links"
                                :from="employees.from"
                                :to="employees.to"
                                :total="employees.total"
                                :loading="isLoading"
                                @visit="(url) => goToPage(url)"
                            />
                        </div>

                        <!-- Nothing to show -->
                        <div v-else key="empty" class="pd-results__empty">
                            <h2 id="ep-results-title" class="sr-only">Results</h2>
                            <SearchEmptyState
                                v-if="filters.search"
                                :query="filters.search"
                                :suggestion="suggestion"
                                @search="search = $event"
                                @clear-filters="clearFilters"
                            />
                            <EmptyState
                                v-else
                                variant="filtered"
                                :icon="Users"
                                title="No employees found."
                                description="Try adjusting your filters."
                                @clear-filters="clearFilters"
                            />
                        </div>
                    </Transition>
                </section>
            </div>
        </div>

        <!-- ===== Employee Progress Modal ===== -->
        <EmployeeProgressModal :empcode="selectedEmpcode" @close="closeModal" />

        <!-- ===== Employee picture (superadmins) ===== -->
        <EmployeePictureDialog
            v-if="canManagePhotos"
            v-model:open="isPictureDialogOpen"
            :employee="pictureEmployee"
            :photo-url="pictureEmployee ? photoUrlFor(pictureEmployee) : null"
            :photo-rules="photoRules"
            :motion-level="motionLevel"
            @photo-changed="onPhotoChanged"
        />
    </AppLayout>
</template>

<style scoped>
.pd-page {
    position: relative;
    flex: 1;
    overflow: clip;
    background: var(--pd-page-bg);
    color: var(--pd-ink);
}

@media (min-width: 768px) {
    .pd-page {
        border-bottom-left-radius: 0.75rem;
        border-bottom-right-radius: 0.75rem;
    }
}

/*
 * minmax(0, 1fr): the column is never wider than the screen, so wide content
 * (e.g. the filter chips in the floating bar) can't stretch the page sideways.
 */
.pd-page__inner {
    position: relative;
    z-index: 1;
    margin: 0 auto;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    max-width: 90rem;
    padding: 1rem;
}

@media (min-width: 640px) {
    .pd-page__inner {
        padding: 1.5rem;
    }
}

/*
 * Wide pages: two columns that together fit one screen. --pd-screen-h is the
 * viewport minus the app header (4rem), the inset layout margins (2 × 0.5rem)
 * and this page's padding (2 × 1.5rem).
 */
.pd-page__inner.is-wide {
    --pd-screen-h: max(32rem, calc(100svh - 8rem));
    grid-template-columns: minmax(20rem, 25rem) minmax(0, 1fr);
    align-items: start;
    gap: 1.25rem;
}

/* The panel holds search and filters too, so its 3D emblem may shrink further than the directory's. */
.pd-page__side {
    --pd-hero-visual-min: 3.5rem;
}

.pd-page__inner.is-wide .pd-page__side {
    position: sticky;
    top: 1.5rem;
    z-index: 30;
    height: var(--pd-screen-h);
}

.pd-results.is-fill {
    display: flex;
    flex-direction: column;
    height: var(--pd-screen-h);
}

.pd-results.is-fill > .pd-results__list,
.pd-results.is-fill > .pd-results__loading {
    display: flex;
    flex: 1 1 0;
    flex-direction: column;
    min-height: 0;
}

/* ─── Hero: search and filters ──────────────────────────────────────────── */
.ep-filters {
    display: grid;
    gap: 0.7rem;
}

.ep-filters__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
}

.ep-filters__wide {
    grid-column: 1 / -1;
}

.ep-hero-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 0.75rem;
}

.ep-order-note {
    display: flex;
    flex: 1 1 14rem;
    align-items: flex-start;
    gap: 0.4rem;
}

.ep-order-note svg {
    margin-top: 0.1rem;
}

.ep-clear {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.3rem;
    border-radius: 9999px;
    border: 1px solid rgba(226, 194, 122, 0.45);
    background: rgba(255, 255, 255, 0.06);
    padding: 0.3rem 0.7rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #f6e7bd;
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease;
}

.ep-clear:hover {
    border-color: rgba(226, 194, 122, 0.85);
    background: rgba(255, 255, 255, 0.12);
}

.ep-clear:focus-visible {
    outline: 2px solid #e2c27a;
    outline-offset: 2px;
}

/* ─── Floating bar (narrow pages) ───────────────────────────────────────── */
/* Zero-height sticky anchor: the bar floats over the results instead of taking space above them. */
.pd-toolbar-anchor {
    position: sticky;
    top: 0.5rem;
    z-index: 20;
    height: 0;
}

.pd-toolbar {
    position: relative;
    min-width: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    border-radius: 1.1rem;
    border: 1px solid var(--pd-border);
    background: color-mix(in srgb, var(--pd-page-bg) 84%, transparent);
    padding: 0.5rem;
    box-shadow: 0 12px 30px -24px rgba(10, 36, 99, 0.6);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.pd-toolbar__search {
    flex: 1 1 14rem;
    min-width: 0;
}

.pd-toolbar__chips {
    display: flex;
    flex: 999 1 auto;
    min-width: 0;
    gap: 0.35rem;
    overflow-x: auto;
    scrollbar-width: thin;
    padding: 0.15rem;
}

.pd-toolbar__chip {
    display: inline-flex;
    max-width: 12rem;
    align-items: center;
    gap: 0.35rem;
    border-radius: 9999px;
    border: 1px solid transparent;
    background: linear-gradient(120deg, var(--pd-navy), var(--pd-royal));
    padding: 0.35rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #ffffff;
    box-shadow: 0 0 0 2px var(--pd-gold-halo);
}

.pd-toolbar__chip:focus-visible,
.pd-toolbar__page-button:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

.pd-toolbar__pages {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.25rem;
    margin-left: auto;
}

.pd-toolbar__page-button {
    display: inline-flex;
    width: 2.25rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    color: var(--pd-ink);
}

.pd-toolbar__page-button:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

.pd-toolbar__page-count {
    min-width: 3.5rem;
    text-align: center;
    font-size: 0.78rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--pd-ink-soft);
}

/* ─── Results ───────────────────────────────────────────────────────────── */
.pd-results {
    scroll-margin-top: 5.5rem;
}

.pd-results__header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.5rem 1.5rem;
    margin-bottom: 0.75rem;
    padding: 0 0.25rem;
}

.pd-results__eyebrow {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--pd-gold-ink);
}

.pd-results__title {
    margin-top: 0.1rem;
    font-size: clamp(1.1rem, 1rem + 0.5vw, 1.4rem);
    font-weight: 800;
    letter-spacing: -0.01em;
    overflow-wrap: anywhere;
    color: var(--pd-ink);
}

/* Per page and layout toggle on one line (toggle last), wrapping on narrow screens */
.pd-results__aside {
    display: flex;
    flex-direction: row-reverse;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
    gap: 0.5rem 0.75rem;
}

.ep-per-page {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    border-radius: 9999px;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    padding: 0.25rem;
}

.ep-per-page__label {
    padding: 0 0.4rem 0 0.55rem;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--pd-ink-soft);
}

.ep-per-page__button {
    min-width: 2.1rem;
    border-radius: 9999px;
    padding: 0.3rem 0.5rem;
    font-size: 0.78rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--pd-ink-soft);
    transition:
        background-color 0.25s ease,
        color 0.25s ease;
}

.ep-per-page__button:hover {
    color: var(--pd-ink);
}

.ep-per-page__button[aria-pressed='true'] {
    background: linear-gradient(120deg, var(--pd-navy), var(--pd-royal));
    color: #ffffff;
}

.ep-per-page__button:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

/* ─── Fan header: the page of results ───────────────────────────────────── */
.ep-fan-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.6rem 1.25rem;
    padding: 0 1.25rem;
}

@media (min-width: 640px) {
    .ep-fan-header {
        padding: 0 1.75rem;
    }
}

.ep-fan-header__index {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.03em;
    background: linear-gradient(160deg, var(--pd-royal), var(--pd-gold));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.ep-fan-header__index span {
    margin-left: 0.25rem;
    font-size: 0.85rem;
    letter-spacing: 0;
    color: var(--pd-ink-soft);
    -webkit-text-fill-color: var(--pd-ink-soft);
}

.ep-fan-header__copy {
    min-width: 0;
    flex: 1 1 12rem;
}

.ep-fan-header__eyebrow {
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--pd-ink-soft);
}

.ep-fan-header__title {
    margin-top: 0.1rem;
    font-size: clamp(1rem, 0.95rem + 0.35vw, 1.2rem);
    font-weight: 800;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
    color: var(--pd-ink);
}

.ep-fan-header__title span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--pd-ink-soft);
}

.ep-fan-header__nav {
    display: flex;
    gap: 0.4rem;
}

.ep-fan-header__button {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 9999px;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    padding: 0.35rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--pd-ink);
    transition:
        border-color 0.2s ease,
        opacity 0.2s ease;
}

.ep-fan-header__button:hover:not(:disabled) {
    border-color: var(--pd-royal);
}

.ep-fan-header__button:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

.ep-fan-header__button:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

/* ─── Grid view ─────────────────────────────────────────────────────────── */
/* Room above the cards for their float, and below for the floor shadows. */
.ep-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 13.5rem), 1fr));
    gap: 1.75rem 1.25rem;
    margin-bottom: 1rem;
    padding: 1rem 0.25rem 1.25rem;
}

.ep-pager {
    margin-top: 0.75rem;
}

.ep-grid + .ep-pager {
    margin-top: 0;
}

.pd-results__empty {
    border-radius: 1.5rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface);
}

/* ─── Transitions ───────────────────────────────────────────────────────── */
.pd-stage-enter-active {
    transition:
        opacity 0.45s ease,
        transform 0.55s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.pd-stage-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.pd-stage-enter-from {
    opacity: 0;
    transform: perspective(1200px) translateY(24px) rotateX(6deg);
}

.pd-stage-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.99);
}

.pd-fade-enter-active,
.pd-fade-leave-active {
    transition: opacity 0.25s ease;
}

.pd-fade-enter-from,
.pd-fade-leave-to {
    opacity: 0;
}

.pd-page[data-motion='still'] .pd-stage-enter-active,
.pd-page[data-motion='still'] .pd-stage-leave-active {
    transition: opacity 0.1s linear;
}

.pd-page[data-motion='still'] .pd-stage-enter-from,
.pd-page[data-motion='still'] .pd-stage-leave-to {
    transform: none;
}
</style>
