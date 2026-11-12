<script setup lang="ts">
import EmployeePhotoManager from '@/components/position-directory/EmployeePhotoManager.vue';
import EmployeePortrait from '@/components/position-directory/EmployeePortrait.vue';
import type { ProgressEmployee } from '@/lib/employeeProgress';
import type { MotionLevel, PhotoRules } from '@/lib/positionDirectory';
import { Building2, X } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * Superadmins add, change or remove an employee's picture from the Employees
 * page. Teleported, so it carries .pd-theme itself for the colour tokens.
 */
const props = defineProps<{
    open: boolean;
    employee: ProgressEmployee | null;
    /** The stored employee picture (with this session's changes), or null. */
    photoUrl: string | null;
    photoRules: PhotoRules;
    motionLevel: MotionLevel;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    photoChanged: [empcode: string, url: string | null];
}>();

const photoManagerRef = ref<InstanceType<typeof EmployeePhotoManager> | null>(null);
const previewUrl = ref<string | null>(null);

watch(
    () => [props.open, props.employee?.EMPCODE],
    () => (previewUrl.value = null),
);

/** Same order as the cards: the employee picture, then the account avatar. */
const shownUrl = computed(() => previewUrl.value ?? props.photoUrl ?? props.employee?.avatar ?? null);

const pictureSource = computed(() => {
    if (previewUrl.value) {
        return null;
    }
    if (props.photoUrl) {
        return 'Employee picture';
    }
    return props.employee?.avatar ? 'Showing the account avatar until a picture is added' : 'No picture yet';
});

const details = computed(() => {
    const employee = props.employee;
    if (!employee) {
        return [];
    }
    return [
        { label: 'Employee code', value: employee.EMPCODE },
        { label: 'Salary grade', value: employee.SG?.trim() },
        { label: 'Plantilla status', value: employee['PLANTILLA STATUS']?.trim() },
        { label: 'Region', value: employee.REGION?.trim() },
        { label: 'Office', value: employee.OFFICE?.trim() },
    ].filter((detail): detail is { label: string; value: string } => Boolean(detail.value));
});

// Opened to manage the picture, so keyboard focus starts on the picture controls.
function onOpenAutoFocus(event: Event): void {
    event.preventDefault();
    nextTick(() => photoManagerRef.value?.focus());
}

function onSaved(url: string | null): void {
    if (props.employee) {
        emit('photoChanged', props.employee.EMPCODE, url);
    }
}
</script>

<template>
    <DialogRoot :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogPortal>
            <DialogOverlay class="pd-dialog-overlay" />
            <DialogContent v-if="employee" class="pd-theme pd-dialog" :data-motion="motionLevel" @open-auto-focus="onOpenAutoFocus">
                <span class="pd-dialog__sweep" aria-hidden="true" />
                <span class="pd-dialog__corner" aria-hidden="true" />

                <div class="pd-dialog__layout">
                    <div class="pd-dialog__portrait-column">
                        <div class="pd-dialog__portrait">
                            <EmployeePortrait :src="shownUrl" :name="employee.name" eager />
                            <span v-if="previewUrl" class="pd-dialog__preview-badge">Preview</span>
                        </div>
                        <p v-if="pictureSource" class="pd-dialog__order">{{ pictureSource }}</p>
                    </div>

                    <div class="pd-dialog__info">
                        <p class="pd-dialog__position">{{ employee.POSITION }}</p>
                        <DialogTitle class="pd-dialog__name">{{ employee.name }}</DialogTitle>
                        <DialogDescription class="pd-dialog__office">
                            <Building2 class="h-4 w-4 shrink-0" aria-hidden="true" />
                            {{ employee['OFFICE/DIVISION']?.trim() || 'No office/division on record' }}
                        </DialogDescription>

                        <dl class="pd-dialog__details">
                            <div v-for="detail in details" :key="detail.label" class="pd-dialog__detail">
                                <dt>{{ detail.label }}</dt>
                                <dd>{{ detail.value }}</dd>
                            </div>
                        </dl>

                        <EmployeePhotoManager
                            ref="photoManagerRef"
                            :key="employee.EMPCODE"
                            :employee="{ empcode: employee.EMPCODE, name: employee.name }"
                            :current-url="photoUrl"
                            :rules="photoRules"
                            @preview="(url) => (previewUrl = url)"
                            @saved="onSaved"
                        />
                    </div>
                </div>

                <DialogClose class="pd-dialog__close" aria-label="Close">
                    <X class="h-4 w-4" aria-hidden="true" />
                </DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.pd-dialog-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    background: rgba(4, 12, 32, 0.55);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}

.pd-dialog-overlay[data-state='open'] {
    animation: pd-fade-in 0.3s ease both;
}

.pd-dialog-overlay[data-state='closed'] {
    animation: pd-fade-out 0.2s ease both;
}

.pd-dialog {
    position: fixed;
    left: 50%;
    top: 50%;
    z-index: 50;
    width: min(56rem, calc(100vw - 1.5rem));
    max-height: calc(100svh - 1.5rem);
    overflow-y: auto;
    overscroll-behavior: contain;
    border-radius: 1.75rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-dialog-surface);
    color: var(--pd-ink);
    box-shadow:
        0 40px 120px -40px rgba(4, 12, 32, 0.8),
        0 0 0 1px rgba(226, 194, 122, 0.18) inset;
    transform: translate(-50%, -50%);
    perspective: 1200px;
}

.pd-dialog:focus {
    outline: none;
}

.pd-dialog[data-state='open'] {
    animation: pd-dialog-in 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.pd-dialog[data-state='closed'] {
    animation: pd-dialog-out 0.22s ease both;
}

.pd-dialog__sweep {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: inherit;
    pointer-events: none;
    background: linear-gradient(110deg, transparent 30%, var(--pd-sheen) 48%, transparent 62%);
    background-size: 260% 100%;
    background-position: 140% 0;
    animation: pd-dialog-sweep 1.4s ease 0.15s both;
}

.pd-dialog__corner {
    position: absolute;
    top: 0;
    left: 0;
    width: 12rem;
    height: 12rem;
    border-top-left-radius: inherit;
    background: radial-gradient(circle at 0 0, var(--pd-gold-halo), transparent 70%);
    pointer-events: none;
}

.pd-dialog__layout {
    position: relative;
    display: grid;
    gap: 1.5rem;
    padding: 1.5rem;
}

@media (min-width: 768px) {
    .pd-dialog__layout {
        grid-template-columns: minmax(0, 18rem) minmax(0, 1fr);
        gap: 2rem;
        padding: 2.25rem;
    }
}

.pd-dialog__portrait-column {
    display: grid;
    align-content: start;
    gap: 0.75rem;
    justify-items: center;
}

.pd-dialog__portrait {
    position: relative;
    width: min(100%, 16rem);
    aspect-ratio: 4 / 5;
    overflow: hidden;
    border-radius: 1.4rem;
    box-shadow:
        0 0 0 1px var(--pd-gold),
        0 0 0 6px var(--pd-gold-halo),
        0 30px 60px -30px var(--pd-card-shadow);
}

@media (min-width: 768px) {
    .pd-dialog__portrait {
        width: 100%;
    }
}

.pd-dialog__preview-badge {
    position: absolute;
    left: 50%;
    bottom: 0.75rem;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: rgba(10, 29, 82, 0.8);
    padding: 0.2rem 0.75rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #f5e6bf;
}

.pd-dialog__order {
    text-align: center;
    font-size: 0.72rem;
    color: var(--pd-ink-soft);
}

.pd-dialog__order strong {
    color: var(--pd-ink);
}

.pd-dialog__info {
    display: grid;
    align-content: start;
    gap: 0.75rem;
    min-width: 0;
}

.pd-dialog__position {
    padding-right: 2.5rem;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--pd-gold-ink);
}

.pd-dialog__name {
    font-size: clamp(1.4rem, 1.1rem + 1.4vw, 2.1rem);
    font-weight: 800;
    line-height: 1.12;
    letter-spacing: -0.02em;
    text-wrap: balance;
}

.pd-dialog__office {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--pd-ink-soft);
}

.pd-dialog__details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 11rem), 1fr));
    gap: 0.5rem;
    margin-top: 0.25rem;
}

.pd-dialog__detail {
    border-radius: 0.85rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface);
    padding: 0.6rem 0.8rem;
}

.pd-dialog__detail dt {
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pd-ink-soft);
}

.pd-dialog__detail dd {
    margin-top: 0.15rem;
    font-size: 0.9rem;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.pd-dialog__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: inline-flex;
    width: 2.25rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    color: var(--pd-ink);
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease;
}

.pd-dialog__close:hover {
    border-color: var(--pd-gold);
}

.pd-dialog__close:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

@keyframes pd-fade-in {
    from {
        opacity: 0;
    }
}

@keyframes pd-fade-out {
    to {
        opacity: 0;
    }
}

@keyframes pd-dialog-in {
    from {
        opacity: 0;
        transform: translate(-50%, -46%) rotateX(10deg) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

@keyframes pd-dialog-out {
    to {
        opacity: 0;
        transform: translate(-50%, -48%) scale(0.97);
    }
}

@keyframes pd-dialog-sweep {
    to {
        background-position: -40% 0;
    }
}

.pd-dialog[data-motion='still'],
.pd-dialog[data-motion='still'] .pd-dialog__sweep {
    animation-duration: 0.01s !important;
}
</style>
