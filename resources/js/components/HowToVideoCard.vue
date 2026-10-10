<template>
    <article class="vcard">
        <button type="button" class="vcard__open" :aria-label="`Play ${video.title}`" @click="emit('play')">
            <span class="vcard__thumb">
                <span class="vcard__fallback" aria-hidden="true"><Film :size="28" /></span>
                <img v-if="video.thumbnail_url" :src="video.thumbnail_url" :alt="video.title" loading="lazy" />
                <video v-else :src="`${video.video_url}#t=1`" preload="metadata" muted playsinline tabindex="-1" aria-hidden="true"></video>
                <span class="vcard__shade" aria-hidden="true"></span>
                <span class="vcard__play" aria-hidden="true"><Play :size="20" fill="currentColor" /></span>
            </span>
            <span class="vcard__body">
                <span class="vcard__title" :title="video.title">{{ video.title }}</span>
                <span v-if="video.description" class="vcard__desc" :title="video.description">{{ video.description }}</span>
                <span v-if="addedOn" class="vcard__meta"><CalendarDays :size="13" /> Added {{ addedOn }}</span>
            </span>
        </button>
        <div v-if="$slots.actions" class="vcard__actions"><slot name="actions" /></div>
    </article>
</template>

<script setup lang="ts">
import { CalendarDays, Film, Play } from 'lucide-vue-next';
import { computed } from 'vue';
import type { HowToVideo } from './HowToVideoPlayer.vue';

const props = defineProps<{ video: HowToVideo }>();
const emit = defineEmits<{ play: [] }>();

const addedOn = computed(() => {
    if (!props.video.created_at) return '';
    const date = new Date(props.video.created_at);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
});
</script>

<style scoped>
/*
 * Colors come from optional --vc-* tokens so a page can theme the card (e.g. dark mode on the
 * How-to Videos page). The fallbacks are the light defaults used on the homepage.
 */
.vcard {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;
    background: var(--vc-bg, #fff);
    border: 1px solid var(--vc-border, #e6eaf4);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--vc-shadow, 0 1px 2px rgba(15, 28, 72, 0.04));
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        border-color 0.25s ease;
}
.vcard:hover,
.vcard:focus-within {
    transform: translateY(-3px);
    border-color: var(--vc-border-hover, #d3dcf3);
    box-shadow: var(--vc-shadow-hover, 0 14px 32px -8px rgba(15, 28, 72, 0.16));
}
.vcard__open {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    text-align: left;
    font: inherit;
    color: inherit;
    background: none;
    border: 0;
    padding: 0;
    cursor: pointer;
}
.vcard__open:focus-visible {
    outline: 2px solid var(--vc-focus, #1d3fc4);
    outline-offset: -2px;
    border-radius: 16px;
}
.vcard__thumb {
    position: relative;
    display: block;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: radial-gradient(80% 90% at 80% 10%, rgba(76, 110, 245, 0.45), transparent 60%), linear-gradient(135deg, #0f1c48, #1a2f73);
}
.vcard__fallback {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    color: rgba(255, 255, 255, 0.28);
}
.vcard__thumb img,
.vcard__thumb video {
    position: relative;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
    transition: transform 0.45s ease;
}
.vcard:hover .vcard__thumb img,
.vcard:hover .vcard__thumb video {
    transform: scale(1.04);
}
.vcard__shade {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 45%, rgba(8, 15, 35, 0.45));
    opacity: 0.6;
    transition: opacity 0.25s ease;
}
.vcard:hover .vcard__shade {
    opacity: 1;
}
.vcard__play {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 52px;
    height: 52px;
    margin: -26px 0 0 -26px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    padding-left: 3px;
    background: rgba(29, 63, 196, 0.92);
    color: #fff;
    box-shadow:
        0 0 0 6px rgba(255, 255, 255, 0.16),
        0 10px 24px rgba(0, 0, 0, 0.3);
    transition:
        transform 0.25s ease,
        background 0.25s ease;
}
.vcard:hover .vcard__play,
.vcard__open:focus-visible .vcard__play {
    transform: scale(1.08);
    background: #1d3fc4;
}
.vcard__body {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    flex: 1;
    padding: 1rem 1.1rem 1.1rem;
}
.vcard__title {
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--vc-title, #1a2744);
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
}
.vcard__desc {
    font-size: 0.85rem;
    color: var(--vc-text, #6b7280);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
}
.vcard__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: auto;
    padding-top: 0.35rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--vc-muted, #8a93a8);
}
.vcard__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem 1.1rem 1rem;
    border-top: 1px solid var(--vc-divider, #eef0f6);
}
@media (prefers-reduced-motion: reduce) {
    .vcard,
    .vcard__thumb img,
    .vcard__thumb video,
    .vcard__shade,
    .vcard__play {
        transition: none;
    }
    .vcard:hover,
    .vcard:focus-within,
    .vcard:hover .vcard__thumb img,
    .vcard:hover .vcard__thumb video,
    .vcard:hover .vcard__play {
        transform: none;
    }
}
</style>
