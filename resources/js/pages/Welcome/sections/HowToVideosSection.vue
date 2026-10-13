<template>
    <section v-if="isAdmin && videos.length" id="how-to-videos" class="howto">
        <div class="howto__inner">
            <div class="howto__header">
                <div>
                    <span class="eyebrow">HOW-TO VIDEOS</span>
                    <h2 class="section-title">Learn the system, <span class="section-title__accent">one short video at a time.</span></h2>
                    <p class="section-sub">Step-by-step guides on how to use the Learning &amp; Development Portal.</p>
                </div>
                <Link :href="route('how-to-videos.index')" class="howto__all">View all videos <ArrowRight :size="16" /></Link>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { type HowToVideo } from '@/components/HowToVideoPlayer.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';

// Only used to decide whether to show the section — the video list itself lives on the How-to Videos page.
defineProps<{ videos: HowToVideo[] }>();

// Admins and above only. The server already sends no videos to anyone else
// (TutorialVideoController::homepageData); this keeps the section hidden regardless.
const page = usePage();
const isAdmin = computed(() => ['admin', 'superadmin'].includes((page.props.auth as any)?.user?.access));
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
@media (max-width: 560px) {
    .howto {
        padding: 3.5rem 1rem;
    }
}
</style>
