<template>
    <Head :title="`${video.title} · Documentation`" />
    <div class="doc-page">
        <TheNavbar class="no-print" />

        <section class="hero" aria-labelledby="doc-title">
            <div class="hero__inner">
                <Link :href="route('how-to-videos.index')" class="hero__back no-print"><ArrowLeft :size="15" /> All how-to videos</Link>
                <span class="hero__eyebrow"><BookOpenText :size="14" /> DOCUMENTATION</span>
                <h1 id="doc-title">{{ video.title }}</h1>
                <p v-if="video.description" class="hero__lead">{{ video.description }}</p>
                <p class="hero__meta">
                    <ListChecks :size="15" /> {{ steps.length }} step{{ steps.length === 1 ? '' : 's' }}
                    <span v-if="updatedOn"> · Added {{ updatedOn }}</span>
                </p>
                <div class="hero__tools no-print">
                    <button type="button" class="btn btn--primary" @click="playing = video"><PlayCircle :size="16" /> Watch the video</button>
                    <button type="button" class="btn btn--glass" @click="copyLink">
                        <Check v-if="copied" :size="16" /><Link2 v-else :size="16" /> {{ copied ? 'Link copied' : 'Copy link' }}
                    </button>
                    <button type="button" class="btn btn--glass" @click="printPage"><Printer :size="16" /> Print / Save as PDF</button>
                    <button v-if="canManage" type="button" class="btn btn--glass" @click="editing = true">
                        <Pencil :size="16" /> Edit documentation
                    </button>
                </div>
                <input
                    v-if="copyFallback"
                    class="copy-fallback no-print"
                    :value="pageUrl"
                    readonly
                    aria-label="Documentation link"
                    @focus="($event.target as HTMLInputElement).select()"
                />
            </div>
        </section>

        <main class="content">
            <article class="doc-card">
                <HowToDocSteps v-if="steps.length" :steps="steps" />
                <div v-else class="empty">
                    <FileText :size="28" />
                    <h2>No written steps yet</h2>
                    <p v-if="canManage">Add the steps and screenshots so admins can follow along without watching the video.</p>
                    <p v-else>The written guide for this video hasn't been added yet. You can still watch the video.</p>
                    <button v-if="canManage" type="button" class="btn btn--primary" @click="editing = true">
                        <Pencil :size="16" /> Write documentation
                    </button>
                </div>
            </article>
        </main>

        <TheFooter class="no-print" />

        <HowToVideoPlayer :video="playing" @close="playing = null" />
        <HowToDocModal :video="editing ? video : null" :can-manage="canManage" edit-on-open @close="editing = false" @saved="editing = false" />
    </div>
</template>

<script setup lang="ts">
import HowToDocModal from '@/components/HowToDocModal.vue';
import HowToDocSteps from '@/components/HowToDocSteps.vue';
import HowToVideoPlayer, { type HowToVideo } from '@/components/HowToVideoPlayer.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpenText, Check, FileText, Link2, ListChecks, Pencil, PlayCircle, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

const props = defineProps<{ video: HowToVideo; canManage: boolean }>();

const steps = computed(() => props.video.steps ?? []);
const playing = ref<HowToVideo | null>(null);
const editing = ref(false);

const updatedOn = computed(() => {
    if (!props.video.created_at) return '';
    const date = new Date(props.video.created_at);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
});

const pageUrl = computed(() => props.video.documentation_url);
const copied = ref(false);
const copyFallback = ref(false);
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(pageUrl.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard blocked (e.g. plain http): show the link so it can be copied by hand.
        copyFallback.value = true;
    }
};

const printPage = () => window.print();
</script>

<style scoped>
.doc-page {
    --hd-bg: #f4f6fb;
    --hd-surface: #ffffff;
    --hd-surface-2: #f8f9fc;
    --hd-border: #e3e8f3;
    --hd-text: #1a2744;
    --hd-text-2: #4b5671;
    --hd-muted: #6b7280;
    --hd-accent: #1d3fc4;

    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    color: var(--hd-text);
    background: var(--hd-bg);
    min-height: 100vh;
    overflow-x: hidden;
}
.dark .doc-page {
    --hd-bg: #0b1222;
    --hd-surface: #111a2e;
    --hd-surface-2: #0e1628;
    --hd-border: #1f2a44;
    --hd-text: #e6ebf5;
    --hd-text-2: #b6c0d6;
    --hd-muted: #8d99b3;
    --hd-accent: #4c6ef5;
}
.doc-page :where(*, *::before, *::after) {
    box-sizing: border-box;
}
.doc-page :where(h1, h2, p) {
    margin: 0;
}

.hero {
    padding: 8rem 0 2.75rem;
    background: radial-gradient(90% 120% at 85% 0%, rgba(76, 110, 245, 0.35), transparent 60%), #0f1c48;
    color: #fff;
}
.hero__inner {
    width: min(900px, calc(100% - 2rem));
    margin-inline: auto;
    display: grid;
    gap: 0.75rem;
    justify-items: start;
}
.hero__back {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #c5cee8;
    text-decoration: none;
}
.hero__back:hover {
    color: #fff;
}
.hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: #8ce99a;
}
.hero h1 {
    font-size: clamp(1.7rem, 3.4vw, 2.5rem);
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1.15;
    text-wrap: balance;
}
.hero__lead {
    max-width: 65ch;
    line-height: 1.6;
    color: #c5cee8;
}
.hero__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    color: #9aa6c7;
}
.hero__tools {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-top: 0.5rem;
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    height: 42px;
    padding: 0 1rem;
    border-radius: 11px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
}
.btn:focus-visible {
    outline: 2px solid #91a7ff;
    outline-offset: 2px;
}
.btn--primary {
    background: #1d3fc4;
    color: #fff;
}
.btn--primary:hover {
    background: #1834a3;
}
.btn--glass {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.btn--glass:hover {
    background: rgba(255, 255, 255, 0.18);
}
.copy-fallback {
    width: min(520px, 100%);
    height: 38px;
    padding: 0 0.7rem;
    border-radius: 9px;
    border: 1px solid #91a7ff;
    background: rgba(255, 255, 255, 0.95);
    color: #1a2744;
    font: inherit;
    font-size: 0.85rem;
}

.content {
    width: min(900px, calc(100% - 2rem));
    margin: 0 auto;
    padding: 2rem 0 4rem;
}
.doc-card {
    padding: 2rem;
    border-radius: 18px;
    border: 1px solid var(--hd-border);
    background: var(--hd-surface);
}
@media (max-width: 560px) {
    .doc-card {
        padding: 1.25rem;
    }
}
.empty {
    display: grid;
    justify-items: center;
    gap: 0.6rem;
    padding: 2.5rem 1rem;
    text-align: center;
    color: var(--hd-muted);
}
.empty h2 {
    font-size: 1.1rem;
    color: var(--hd-text);
}
.empty p {
    max-width: 46ch;
    margin-bottom: 0.4rem;
}

@media print {
    .no-print {
        display: none !important;
    }
    .doc-page {
        background: #fff;
        color: #000;
    }
    .hero {
        padding: 0 0 1rem;
        background: none;
        color: #000;
    }
    .hero__lead,
    .hero__meta {
        color: #333;
    }
    .hero__eyebrow {
        color: #1d3fc4;
    }
    .doc-card {
        border: 0;
        padding: 0;
    }
}
</style>
