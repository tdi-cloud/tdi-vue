<script setup lang="ts">
import { canDownload, downloadName, formatDate, formatHours, type MyCertificate, typeMeta } from '@/lib/certificates';
import { ArrowRight, Download, Eye, Hourglass, Info, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';
import CertificateArtwork from './CertificateArtwork.vue';
import CertificateSeal from './CertificateSeal.vue';

/**
 * The latest issued certificate on a dark exhibition stage. Without an issued
 * certificate yet, the stage invites the employee to complete one instead.
 */
const props = defineProps<{
    certificate: MyCertificate | null;
    pendingCount: number;
}>();

const emit = defineEmits<{
    (e: 'preview', certificate: MyCertificate): void;
    (e: 'details', certificate: MyCertificate): void;
    (e: 'show-pending'): void;
}>();

const meta = computed(() => (props.certificate ? typeMeta(props.certificate.type) : null));
</script>

<template>
    <section class="spot" aria-labelledby="spotlight-title">
        <div class="spot__stage">
            <span class="spot__beam" aria-hidden="true"></span>

            <div v-if="certificate && meta" class="spot__grid">
                <div class="spot__info">
                    <p class="spot__eyebrow"><Sparkles :size="13" aria-hidden="true" /> Achievement Spotlight</p>
                    <h2 id="spotlight-title" class="spot__title">Your latest achievement</h2>
                    <p class="spot__lede">
                        A {{ meta.label.toLowerCase() }} for <strong>{{ certificate.program_title }}</strong
                        ><template v-if="certificate.issued_date">, issued {{ formatDate(certificate.issued_date) }}</template
                        >.
                    </p>

                    <div class="spot__placard">
                        <img
                            v-if="certificate.cover_image"
                            :src="certificate.cover_image"
                            :alt="`Cover of ${certificate.program_title}`"
                            class="spot__thumb"
                        />
                        <dl class="spot__facts">
                            <div v-if="certificate.program_code">
                                <dt>Program Code</dt>
                                <dd>{{ certificate.program_code }}</dd>
                            </div>
                            <div v-if="certificate.batch_label">
                                <dt>Batch</dt>
                                <dd>{{ certificate.batch_label }}</dd>
                            </div>
                            <div v-if="certificate.hours > 0">
                                <dt>Training Hours</dt>
                                <dd>{{ formatHours(certificate.hours) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="spot__actions">
                        <button v-if="certificate.file_url" type="button" class="spot__btn spot__btn--primary" @click="emit('preview', certificate)">
                            <Eye :size="17" aria-hidden="true" /> View Certificate
                        </button>
                        <button v-else type="button" class="spot__btn spot__btn--primary" @click="emit('details', certificate)">
                            <Info :size="17" aria-hidden="true" /> View Certificate
                        </button>
                        <a
                            v-if="canDownload(certificate)"
                            :href="certificate.file_url!"
                            :download="downloadName(certificate)"
                            class="spot__btn spot__btn--ghost"
                        >
                            <Download :size="17" aria-hidden="true" /> Download PDF
                        </a>
                    </div>
                    <button type="button" class="spot__more" @click="emit('details', certificate)">
                        All certificate details <ArrowRight :size="15" aria-hidden="true" />
                    </button>
                </div>

                <div class="spot__exhibit">
                    <div class="spot__float">
                        <!-- Mouse shortcut to the details; keyboard users reach them through "All certificate details". -->
                        <div class="spot__artwork" @click="emit('details', certificate)">
                            <CertificateArtwork :certificate="certificate" />
                        </div>
                    </div>
                    <span class="spot__plinth" aria-hidden="true"></span>
                </div>
            </div>

            <!-- No issued certificate yet -->
            <div v-else class="spot__invite">
                <div class="spot__ghost" aria-hidden="true">
                    <span class="spot__ghost-seal"><CertificateSeal :icon="Hourglass" status="Pending" ribbons /></span>
                </div>
                <div class="spot__invite-text">
                    <p class="spot__eyebrow"><Sparkles :size="13" aria-hidden="true" /> Achievement Spotlight</p>
                    <h2 id="spotlight-title" class="spot__title">Your spotlight awaits</h2>
                    <p class="spot__lede">
                        Once a certificate is officially issued to you, it will be showcased here as the centerpiece of your collection.
                    </p>
                    <button v-if="pendingCount" type="button" class="spot__btn spot__btn--ghost" @click="emit('show-pending')">
                        <Hourglass :size="16" aria-hidden="true" /> View {{ pendingCount }} awaiting issuance
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.spot {
    --serif: 'Iowan Old Style', 'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', Georgia, serif;
    --champagne: #e9d29a;
}
/* A dark exhibition wall, lit from above */
.spot__stage {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: clamp(1.5rem, 4.5vw, 3.5rem);
    color: #fff;
    background:
        radial-gradient(ellipse 45% 75% at 70% -10%, rgb(255 233 190 / 0.2), transparent 70%),
        radial-gradient(ellipse 60% 45% at 70% 112%, rgb(76 110 245 / 0.2), transparent 70%), linear-gradient(180deg, #101c42 0%, #0a1230 100%);
    border: 1px solid rgb(212 175 106 / 0.2);
    border-radius: 18px;
    box-shadow: 0 50px 90px -50px rgb(7 14 36 / 0.75);
}
.spot__beam {
    position: absolute;
    top: 0;
    left: 70%;
    z-index: -1;
    width: 120%;
    height: 120%;
    background: conic-gradient(
        from 180deg at 50% 0%,
        rgb(255 240 210 / 0.12) 0deg,
        transparent 17deg,
        transparent 343deg,
        rgb(255 240 210 / 0.12) 360deg
    );
    filter: blur(8px);
    transform: translateX(-50%);
}
.spot__grid {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.55fr);
    align-items: center;
    gap: clamp(2rem, 4vw, 3.5rem);
}
.spot__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0 0 0.85rem;
    color: var(--champagne);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.26em;
    text-transform: uppercase;
}
.spot__title {
    margin: 0;
    font-family: var(--serif);
    font-size: clamp(1.75rem, 3vw, 2.35rem);
    font-weight: 700;
    line-height: 1.12;
}
.spot__lede {
    margin: 0.85rem 0 0;
    color: rgb(214 222 240 / 0.82);
    font-size: 0.95rem;
    line-height: 1.65;
}
.spot__lede strong {
    color: #fff;
    font-weight: 600;
}
.spot__placard {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 1.5rem;
    padding: 0.85rem;
    background: rgb(255 255 255 / 0.04);
    border: 1px solid rgb(233 210 154 / 0.16);
    border-radius: 10px;
}
.spot__thumb {
    width: 64px;
    height: 64px;
    flex-shrink: 0;
    object-fit: cover;
    border-radius: 6px;
    box-shadow: 0 0 0 1px rgb(233 210 154 / 0.3);
}
.spot__facts {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem 1.5rem;
    margin: 0;
}
.spot__facts dt {
    color: rgb(233 210 154 / 0.75);
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}
.spot__facts dd {
    margin: 0.15rem 0 0;
    color: #fff;
    font-size: 0.88rem;
    font-weight: 600;
}
.spot__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
    margin-top: 1.6rem;
}
.spot__btn {
    display: inline-flex;
    min-height: 46px;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.7rem 1.25rem;
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 10px;
    cursor: pointer;
    transition:
        transform 0.2s ease,
        background 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}
.spot__btn--primary {
    color: #1a1405;
    background: linear-gradient(180deg, #f3dda2 0%, #d4af5f 100%);
    border: 1px solid #e9cf8d;
    box-shadow: 0 10px 24px -10px rgb(212 175 95 / 0.7);
}
.spot__btn--primary:hover {
    background: linear-gradient(180deg, #f8e6b6 0%, #ddbb6f 100%);
    box-shadow: 0 14px 30px -10px rgb(212 175 95 / 0.8);
    transform: translateY(-1px);
}
.spot__btn--ghost {
    color: #fff;
    background: rgb(255 255 255 / 0.04);
    border: 1px solid rgb(255 255 255 / 0.28);
}
.spot__btn--ghost:hover {
    background: rgb(255 255 255 / 0.1);
    border-color: rgb(255 255 255 / 0.5);
}
.spot__more {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 1.1rem;
    padding: 0;
    color: rgb(233 210 154 / 0.9);
    font-size: 0.82rem;
    font-weight: 600;
    background: none;
    border: 0;
    cursor: pointer;
}
.spot__more:hover {
    color: #fff;
}
.spot__btn:focus-visible,
.spot__more:focus-visible {
    outline: 2px solid var(--champagne);
    outline-offset: 4px;
}

.spot__exhibit {
    position: relative;
    min-width: 0;
}
.spot__float {
    animation:
        spot-enter 1s cubic-bezier(0.2, 0.8, 0.2, 1) both,
        spot-float 7s ease-in-out 1s infinite;
}
@keyframes spot-enter {
    from {
        opacity: 0;
        transform: perspective(1200px) translateY(28px) rotateX(10deg);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
@keyframes spot-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
.spot__artwork {
    border-radius: 4px;
    cursor: pointer;
    box-shadow:
        0 40px 70px -30px rgb(0 0 0 / 0.75),
        0 0 0 1px rgb(233 210 154 / 0.25),
        0 0 60px -10px rgb(255 233 190 / 0.18);
    transition: box-shadow 0.3s ease;
}
.spot__artwork:hover {
    box-shadow:
        0 46px 80px -30px rgb(0 0 0 / 0.8),
        0 0 0 1px rgb(233 210 154 / 0.45),
        0 0 80px -10px rgb(255 233 190 / 0.28);
}
.spot__plinth {
    display: block;
    width: 78%;
    height: 26px;
    margin: 1.4rem auto 0;
    background: radial-gradient(ellipse at center, rgb(0 0 0 / 0.5), transparent 70%);
    filter: blur(2px);
}

/* Invitation (no issued certificate yet) */
.spot__invite {
    display: grid;
    grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
    align-items: center;
    gap: clamp(1.5rem, 4vw, 3.5rem);
}
.spot__ghost {
    position: relative;
    display: grid;
    aspect-ratio: 1.414;
    place-items: center;
    background: rgb(251 248 240 / 0.05);
    border: 1.5px dashed rgb(233 210 154 / 0.45);
    border-radius: 4px;
}
.spot__ghost::before {
    position: absolute;
    inset: 10px;
    border: 1px solid rgb(233 210 154 / 0.15);
    content: '';
}
.spot__ghost-seal {
    width: 22%;
    aspect-ratio: 100 / 130;
    opacity: 0.9;
}
.spot__invite .spot__btn {
    margin-top: 1.4rem;
}

/* Light theme gets the same stage; dark theme only softens the edge. */
.dark .spot__stage {
    border-color: rgb(212 175 106 / 0.14);
    box-shadow: 0 50px 90px -50px rgb(0 0 0 / 0.9);
}

@media (max-width: 960px) {
    .spot__grid,
    .spot__invite {
        grid-template-columns: 1fr;
    }
    .spot__exhibit {
        order: -1;
    }
    .spot__ghost {
        max-width: 420px;
        width: 100%;
        justify-self: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .spot__float {
        animation: none;
    }
    .spot__btn,
    .spot__artwork {
        transition: none;
    }
    .spot__btn--primary:hover {
        transform: none;
    }
}
</style>
