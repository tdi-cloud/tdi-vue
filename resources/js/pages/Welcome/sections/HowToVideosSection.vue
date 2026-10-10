<template>
    <section v-if="videos.length" id="how-to-videos" class="howto">
        <div class="howto__inner">
            <div class="howto__header">
                <div>
                    <span class="eyebrow">HOW-TO VIDEOS</span>
                    <h2 class="section-title">Learn the system, <span class="section-title__accent">one short video at a time.</span></h2>
                    <p class="section-sub">Step-by-step guides on how to use the Learning &amp; Development Portal.</p>
                </div>
                <Link :href="route('how-to-videos.index')" class="howto__all">View all videos <ArrowRight :size="16" /></Link>
            </div>

            <div class="howto__grid">
                <HowToVideoCard v-for="video in videos" :key="video.id" :video="video" @play="playing = video">
                    <template v-if="video.steps_count" #actions>
                        <Link :href="video.documentation_url" class="howto__doc"><BookOpenText :size="14" /> Read documentation</Link>
                    </template>
                </HowToVideoCard>
            </div>
        </div>

        <HowToVideoPlayer :video="playing" @close="playing = null" />
    </section>
</template>

<script setup lang="ts">
import HowToVideoCard from '@/components/HowToVideoCard.vue';
import HowToVideoPlayer, { type HowToVideo } from '@/components/HowToVideoPlayer.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpenText } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{ videos: HowToVideo[] }>();

const playing = ref<HowToVideo | null>(null);
</script>

<style scoped>
.howto {
    padding: 5rem 2rem;
    background: #fff;
}
.howto__inner {
    max-width: 1100px;
    margin: 0 auto;
}
.howto__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 2.5rem;
}
.eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: #0ca678;
    margin-bottom: 0.75rem;
    display: block;
}
.section-title {
    font-size: clamp(1.6rem, 3vw, 2.5rem);
    font-weight: 800;
    letter-spacing: -0.01em;
    color: #1a2744;
    line-height: 1.2;
    margin-bottom: 0.75rem;
    max-width: 22ch;
}
.section-title__accent {
    color: #1d3fc4;
}
.section-sub {
    color: #6b7280;
    line-height: 1.65;
}
.howto__doc {
    flex: 1 1 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.45rem 0.7rem;
    border-radius: 8px;
    background: #eef2ff;
    color: #1d3fc4;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
}
.howto__doc:hover {
    background: #1d3fc4;
    color: #fff;
}
.howto__all {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    font-size: 0.9rem;
    color: #1d3fc4;
    padding: 0.65rem 1.1rem;
    border-radius: 10px;
    border: 1px solid #dbe2f7;
    text-decoration: none;
    transition: background 0.2s;
}
.howto__all:hover {
    background: #eef2ff;
}
.howto__grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1.25rem;
}
@media (max-width: 1000px) {
    .howto__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 560px) {
    .howto {
        padding: 3.5rem 1rem;
    }
    .howto__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
