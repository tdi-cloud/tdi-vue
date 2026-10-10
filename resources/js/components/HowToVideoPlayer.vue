<template>
    <Teleport to="body">
        <Transition name="player-fade">
            <div v-if="video" class="player" role="dialog" aria-modal="true" :aria-label="video.title" @click.self="emit('close')">
                <div class="player__box">
                    <div class="player__head">
                        <h2 class="player__title">{{ video.title }}</h2>
                        <button type="button" class="player__close" aria-label="Close video" @click="emit('close')"><X :size="20" /></button>
                    </div>
                    <video
                        :key="video.id"
                        class="player__video"
                        :src="video.video_url"
                        :poster="video.thumbnail_url ?? undefined"
                        controls
                        autoplay
                        playsinline
                    ></video>
                    <p v-if="video.description" class="player__desc">{{ video.description }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted } from 'vue';

export interface HowToDocStep {
    id: number;
    title: string;
    body: string | null;
    image_url: string | null;
}

export interface HowToVideo {
    id: number;
    title: string;
    description: string | null;
    video_url: string;
    thumbnail_url: string | null;
    created_at: string | null;
    steps_count: number;
    documentation_url: string;
    steps?: HowToDocStep[];
}

const props = defineProps<{ video: HowToVideo | null }>();
const emit = defineEmits<{ close: [] }>();

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.video) emit('close');
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
.player {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(8, 15, 35, 0.78);
    backdrop-filter: blur(4px);
}
.player__box {
    width: min(1100px, 100%);
    max-height: 100%;
    overflow-y: auto;
    background: #0b1222;
    color: #f1f5f9;
    border-radius: 18px;
    box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.7);
}
.player__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1.1rem;
}
.player__title {
    min-width: 0;
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}
.player__close {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 0;
    background: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
    cursor: pointer;
}
.player__close:hover {
    background: rgba(255, 255, 255, 0.16);
}
.player__close:focus-visible {
    outline: 2px solid #748ffc;
    outline-offset: 2px;
}
.player__video {
    display: block;
    width: 100%;
    max-height: 75vh;
    background: #000;
}
.player__desc {
    padding: 0.9rem 1.1rem 1.1rem;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #cbd5e1;
}
.player-fade-enter-active,
.player-fade-leave-active {
    transition: opacity 0.2s ease;
}
.player-fade-enter-from,
.player-fade-leave-to {
    opacity: 0;
}
</style>
