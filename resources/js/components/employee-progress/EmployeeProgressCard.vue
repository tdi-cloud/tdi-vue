<script setup lang="ts">
import EmployeePortrait from '@/components/position-directory/EmployeePortrait.vue';
import FloatingCard from '@/components/position-directory/FloatingCard.vue';
import { employeePicture, plantillaColor, progressMetrics, type ProgressEmployee } from '@/lib/employeeProgress';
import { hashSeed, type MotionLevel } from '@/lib/positionDirectory';
import { Building2, ImageUp, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Employees page card on the shared 3D shell. Front: picture, name, position,
 * office/division, plantilla status and three progress rings. Back: the full
 * metrics and employee details (and, for superadmins, the picture button).
 * Opening it shows the progress modal.
 */
const props = defineProps<{
    employee: ProgressEmployee;
    /** The stored employee picture, including changes made in this session. */
    photoUrl: string | null;
    /** Position in the rendered list; only used to stagger the entrance. */
    index: number;
    motionLevel: MotionLevel;
    /** The card in the centre of the fan carousel. */
    featured?: boolean;
    canManagePhotos?: boolean;
}>();

const emit = defineEmits<{
    open: [employee: ProgressEmployee];
    managePhoto: [employee: ProgressEmployee];
}>();

const metrics = computed(() => progressMetrics(props.employee));
const picture = computed(() => employeePicture(props.employee, props.photoUrl));
const position = computed(() => props.employee.POSITION?.trim() || null);
const officeDivision = computed(() => props.employee['OFFICE/DIVISION']?.trim() || null);
const plantilla = computed(() => props.employee['PLANTILLA STATUS']?.trim() || null);

/** Seeded by EMPCODE (not the database id, which changes with HR's monthly re-import). */
const seed = computed(() => hashSeed(props.employee.EMPCODE));

const details = computed(() =>
    [
        { label: 'Employee code', value: props.employee.EMPCODE },
        { label: 'Position', value: position.value },
        { label: 'Office / division', value: officeDivision.value },
        { label: 'Plantilla status', value: plantilla.value },
        { label: 'Region', value: props.employee.REGION?.trim() },
        { label: 'Office', value: props.employee.OFFICE?.trim() },
    ].filter((detail): detail is { label: string; value: string } => Boolean(detail.value)),
);

/** Circumference-free ring: the circle uses pathLength="100", so the dash is the percent. */
function ringStyle(percent: number, color: string): Record<string, string> {
    return { stroke: color, strokeDasharray: `${percent} 100` };
}

function open(): void {
    emit('open', props.employee);
}
</script>

<template>
    <FloatingCard
        :seed="seed"
        :index="index"
        :motion-level="motionLevel"
        :featured="featured"
        :open-label="`View the training progress of ${employee.name}`"
        :flip-label="`Show progress details for ${employee.name}`"
        back-eyebrow="Training progress"
        :back-title="employee.name"
        @open="open"
    >
        <template #front>
            <div class="pd-progress-card" :data-motion="motionLevel">
                <div class="pd-card__media pd-progress-card__media">
                    <EmployeePortrait :src="picture" :name="employee.name" />
                    <div class="pd-card__media-shade" aria-hidden="true" />
                    <span class="pd-card__order" :title="`Employee code ${employee.EMPCODE}`">
                        <span class="sr-only">Employee code</span>{{ employee.EMPCODE }}
                    </span>
                </div>

                <div class="pd-card__body pd-progress-card__body">
                    <h3 class="pd-card__name pd-progress-card__name">{{ employee.name }}</h3>
                    <p v-if="position" class="pd-card__position pd-progress-card__position" :title="position">{{ position }}</p>
                    <p v-if="officeDivision" class="pd-card__office pd-progress-card__office" :title="officeDivision">
                        <Building2 class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                        <span>{{ officeDivision }}</span>
                    </p>
                    <span v-if="plantilla" class="pd-progress-card__chip" :class="plantillaColor(plantilla)">{{ plantilla }}</span>

                    <ul class="pd-progress-card__rings" aria-label="Training progress">
                        <li v-for="metric in metrics" :key="metric.key" class="pd-progress-card__ring">
                            <span class="pd-progress-card__ring-figure">
                                <svg viewBox="0 0 36 36" aria-hidden="true">
                                    <circle cx="18" cy="18" r="15" class="pd-progress-card__ring-track" />
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="15"
                                        pathLength="100"
                                        class="pd-progress-card__ring-value"
                                        :style="ringStyle(metric.percent, metric.color)"
                                    />
                                </svg>
                                <span class="pd-progress-card__ring-percent" aria-hidden="true">{{ metric.percent }}%</span>
                            </span>
                            <span class="pd-progress-card__ring-label">
                                {{ metric.label }}<span class="sr-only">: {{ metric.value }} ({{ metric.percent }}%)</span>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </template>

        <template #back>
            <div class="pd-card__details">
                <ul class="pd-progress-card__metrics">
                    <li v-for="metric in metrics" :key="metric.key" class="pd-progress-card__metric">
                        <div class="pd-progress-card__metric-row">
                            <span class="pd-progress-card__metric-label">{{ metric.label }}</span>
                            <span class="pd-progress-card__metric-value">
                                {{ metric.value }}
                                <strong>{{ metric.percent }}%</strong>
                            </span>
                        </div>
                        <div class="pd-progress-card__bar" aria-hidden="true">
                            <span :style="{ width: `${metric.percent}%`, background: metric.color }" />
                        </div>
                    </li>
                </ul>

                <dl>
                    <div v-for="detail in details" :key="detail.label" class="pd-card__detail">
                        <dt>{{ detail.label }}</dt>
                        <dd>{{ detail.value }}</dd>
                    </div>
                </dl>
            </div>
        </template>

        <template #actions>
            <button type="button" class="pd-card__action pd-card__action--primary" @click="open">
                <TrendingUp class="h-3.5 w-3.5" aria-hidden="true" />
                View progress
            </button>
            <button v-if="canManagePhotos" type="button" class="pd-card__action" @click="emit('managePhoto', employee)">
                <ImageUp class="h-3.5 w-3.5" aria-hidden="true" />
                {{ photoUrl ? 'Change picture' : 'Add picture' }}
            </button>
        </template>
    </FloatingCard>
</template>

<style scoped>
/* Sized by its own width, so the text and rings fit both the fan and the grid. */
.pd-progress-card {
    container: progress-card / inline-size;
}

/* A shorter picture (10:8) leaves room for the progress rings below it. */
.pd-card__media.pd-progress-card__media {
    aspect-ratio: 10 / 8;
}

.pd-card__body.pd-progress-card__body {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding-bottom: 1rem;
}

.pd-card__name.pd-progress-card__name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pd-card__position.pd-progress-card__position {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pd-card__office.pd-progress-card__office {
    max-width: 100%;
    margin-top: 0.4rem;
}

.pd-card__office.pd-progress-card__office span {
    -webkit-line-clamp: 1;
}

.pd-progress-card__chip {
    margin-top: 0.5rem;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border-radius: 9999px;
    border-width: 1px;
    padding: 0.05rem 0.55rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}

/* ─── Progress rings ────────────────────────────────────────────────────── */
.pd-progress-card__rings {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem;
    width: 100%;
    margin-top: 0.75rem;
    border-top: 1px solid var(--pd-border);
    padding-top: 0.7rem;
}

.pd-progress-card__ring {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.pd-progress-card__ring-figure {
    position: relative;
    display: grid;
    width: clamp(2.4rem, 22cqi, 3rem);
    aspect-ratio: 1;
    place-items: center;
}

.pd-progress-card__ring-figure svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.pd-progress-card__ring-track,
.pd-progress-card__ring-value {
    fill: none;
    stroke-width: 3.5;
}

.pd-progress-card__ring-track {
    stroke: var(--pd-metric-track);
}

.pd-progress-card__ring-value {
    stroke-linecap: round;
    transition: stroke-dasharray 0.9s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.pd-progress-card__ring-percent {
    font-size: clamp(0.58rem, 5cqi, 0.7rem);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--pd-ink);
}

.pd-progress-card__ring-label {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: clamp(0.55rem, 4.6cqi, 0.64rem);
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--pd-ink-soft);
}

/* ─── Back: full metrics ────────────────────────────────────────────────── */
.pd-progress-card__metrics {
    display: grid;
    gap: 0.65rem;
    margin-bottom: 0.6rem;
    padding-top: 0.25rem;
}

.pd-progress-card__metric-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.3rem;
    font-size: 0.72rem;
}

.pd-progress-card__metric-label {
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--pd-ink-soft);
}

.pd-progress-card__metric-value {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.pd-progress-card__metric-value strong {
    margin-left: 0.3rem;
    font-weight: 800;
}

.pd-progress-card__bar {
    height: 0.35rem;
    overflow: hidden;
    border-radius: 9999px;
    background: var(--pd-metric-track);
}

.pd-progress-card__bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    transition: width 0.7s ease-out;
}

/* Narrow cards (the smallest fan sizes): tighter text */
@container progress-card (max-width: 190px) {
    .pd-card__body.pd-progress-card__body {
        padding-inline: 0.75rem;
    }

    .pd-card__name.pd-progress-card__name {
        font-size: 0.88rem;
    }
}

.pd-progress-card[data-motion='still'] .pd-progress-card__ring-value {
    transition: none;
}
</style>
