<template>
    <Head title="How-to Videos" />
    <div class="howto-page">
        <TheNavbar />

        <!-- Hero header -->
        <section class="hero" aria-labelledby="howto-hero-title">
            <div class="hero__pattern" aria-hidden="true"></div>
            <div class="hero__rings" aria-hidden="true">
                <span></span>
                <span></span>
                <span class="hero__rings-core"><Play :size="34" fill="currentColor" /></span>
            </div>

            <div class="hero__inner">
                <span class="hero__eyebrow"><PlayCircle :size="14" /> LEARNING &amp; DEVELOPMENT PORTAL</span>
                <h1 id="howto-hero-title">Learn. Explore. <span class="hero__accent">Do more.</span></h1>
                <p class="hero__lead">
                    Your step-by-step guide to navigating the Learning &amp; Development Portal, completing tasks, and making the most of its
                    features.
                </p>
                <div class="hero__tools">
                    <div class="search">
                        <Search :size="18" class="search__icon" aria-hidden="true" />
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="search"
                            placeholder="Search tutorials and guides..."
                            aria-label="Search tutorials and guides"
                        />
                        <button v-if="search" type="button" class="search__clear" aria-label="Clear search" @click="clearSearch">
                            <X :size="16" />
                        </button>
                    </div>
                    <button v-if="canManage" type="button" class="btn btn--primary btn--hero" @click="openCreate">
                        <Upload :size="16" /> Upload video
                    </button>
                </div>
            </div>
        </section>

        <main class="content">
            <!-- What this page is for -->
            <section class="intro" aria-labelledby="howto-intro-title">
                <div class="intro__text">
                    <h2 id="howto-intro-title">Explore Video Tutorials</h2>
                    <p>Browse step-by-step guides to learn common tasks and make the most of the portal's features.</p>
                </div>
                <ul class="facts">
                    <li class="fact">
                        <span class="fact__icon"><MonitorPlay :size="18" /></span>
                        <span>
                            <strong>{{ videos.length }} video guide{{ videos.length === 1 ? '' : 's' }}</strong>
                            <small>Available in the library</small>
                        </span>
                    </li>
                    <li class="fact">
                        <span class="fact__icon fact__icon--teal"><Clock :size="18" /></span>
                        <span>
                            <strong>Learn at your own pace</strong>
                            <small>Watch any guide whenever you need it</small>
                        </span>
                    </li>
                    <li class="fact">
                        <span class="fact__icon fact__icon--green"><ListChecks :size="18" /></span>
                        <span>
                            <strong>Step-by-step guidance</strong>
                            <small>Follow along with real portal tasks</small>
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Library -->
            <section class="library" aria-labelledby="howto-library-title">
                <div class="content__bar">
                    <h2 id="howto-library-title" class="library__title">Video library</h2>
                    <p class="count" aria-live="polite">
                        {{ filteredVideos.length }} video{{ filteredVideos.length === 1 ? '' : 's'
                        }}<span v-if="search.trim()"> matching “{{ search.trim() }}”</span>
                    </p>
                </div>

                <p v-if="notice" class="notice" role="status"><CheckCircle2 :size="16" /> {{ notice }}</p>

                <div v-if="filteredVideos.length" class="grid">
                    <HowToVideoCard v-for="video in filteredVideos" :key="video.id" :video="video" @play="playing = video">
                        <template v-if="canManage || video.steps_count" #actions>
                            <button v-if="video.steps_count" type="button" class="mini mini--doc" @click="openDocs(video)">
                                <BookOpenText :size="14" /> Read documentation
                            </button>
                            <button v-else type="button" class="mini mini--doc" @click="openDocs(video, true)">
                                <FilePlus2 :size="14" /> Add documentation
                            </button>
                            <template v-if="canManage">
                                <button type="button" class="mini" @click="openEdit(video)"><Pencil :size="14" /> Edit</button>
                                <button type="button" class="mini mini--danger" :disabled="deletingId === video.id" @click="remove(video)">
                                    <Loader2 v-if="deletingId === video.id" :size="14" class="spin" /><Trash2 v-else :size="14" /> Delete
                                </button>
                            </template>
                        </template>
                    </HowToVideoCard>
                </div>

                <div v-else class="empty">
                    <template v-if="videos.length">
                        <div class="empty__icon"><SearchX :size="26" /></div>
                        <h3>No videos found</h3>
                        <p>Nothing matches “{{ search.trim() }}”. Try a different keyword or browse the full library.</p>
                        <button type="button" class="btn btn--ghost" @click="clearSearch"><X :size="16" /> Clear search</button>
                    </template>
                    <template v-else>
                        <div class="empty__icon"><Clapperboard :size="26" /></div>
                        <h3>No how-to videos yet</h3>
                        <p v-if="canManage">Upload your first recorded walkthrough so admins can learn the system.</p>
                        <p v-else>Check back soon. Guides on how to use the system will appear here when they're available.</p>
                        <button v-if="canManage" type="button" class="btn btn--primary" @click="openCreate">
                            <Upload :size="16" /> Upload video
                        </button>
                    </template>
                </div>
            </section>
        </main>

        <TheFooter />

        <HowToVideoPlayer :video="playing" @close="playing = null" />
        <HowToDocModal
            :video="docVideo"
            :can-manage="canManage"
            :edit-on-open="docEditOnOpen"
            @close="docVideoId = null"
            @saved="showNotice('Documentation saved.')"
        />

        <!-- Upload / edit form (superadmin only) -->
        <Teleport to="body">
            <div v-if="formOpen" class="modal" role="dialog" aria-modal="true" aria-labelledby="video-form-title" @click.self="closeForm">
                <form class="modal__box" @submit.prevent="submit">
                    <div class="modal__head">
                        <span class="modal__badge" aria-hidden="true">
                            <Pencil v-if="editingId" :size="18" />
                            <Upload v-else :size="18" />
                        </span>
                        <div class="modal__heading">
                            <h2 id="video-form-title">{{ editingId ? 'Edit video' : 'Upload a how-to video' }}</h2>
                            <p>{{ editingId ? 'Update the details or replace the files.' : 'Share a recorded walkthrough with portal users.' }}</p>
                        </div>
                        <button type="button" class="modal__close" aria-label="Close" :disabled="form.processing" @click="closeForm">
                            <X :size="18" />
                        </button>
                    </div>

                    <div class="modal__body">
                        <label class="field">
                            <span>Title <b>*</b></span>
                            <input
                                id="video-title"
                                v-model="form.title"
                                type="text"
                                maxlength="150"
                                placeholder="e.g. How to create a program"
                                required
                            />
                            <small v-if="form.errors.title" class="error">{{ form.errors.title }}</small>
                        </label>

                        <label class="field">
                            <span>Description</span>
                            <textarea
                                id="video-description"
                                v-model="form.description"
                                rows="3"
                                maxlength="1000"
                                placeholder="What does this video show?"
                            ></textarea>
                            <small v-if="form.errors.description" class="error">{{ form.errors.description }}</small>
                        </label>

                        <label class="field">
                            <span>Video file <b v-if="!editingId">*</b></span>
                            <input id="video-file" type="file" accept="video/mp4,video/webm,video/quicktime" @change="pickVideo" />
                            <small class="hint">
                                MP4, WebM or MOV, up to {{ maxVideoMb }} MB.<span v-if="editingId"> Leave empty to keep the current video.</span>
                            </small>
                            <small v-if="videoError || form.errors.video" class="error">{{ videoError || form.errors.video }}</small>
                        </label>

                        <label class="field">
                            <span>Thumbnail image</span>
                            <input id="video-thumbnail" type="file" accept="image/*" @change="pickThumbnail" />
                            <small class="hint">Optional. Without one, a frame from the video is shown.</small>
                            <small v-if="form.errors.thumbnail" class="error">{{ form.errors.thumbnail }}</small>
                        </label>

                        <div
                            v-if="form.progress"
                            class="progress"
                            role="progressbar"
                            :aria-valuenow="form.progress.percentage"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div class="progress__label">
                                <small>Uploading…</small>
                                <strong>{{ form.progress.percentage }}%</strong>
                            </div>
                            <div class="progress__track"><span :style="{ width: `${form.progress.percentage}%` }"></span></div>
                        </div>
                    </div>

                    <div class="modal__foot">
                        <button type="button" class="btn btn--ghost" :disabled="form.processing" @click="closeForm">Cancel</button>
                        <button type="submit" class="btn btn--primary" :disabled="form.processing">
                            <Loader2 v-if="form.processing" :size="16" class="spin" />
                            {{ editingId ? 'Save changes' : 'Upload video' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import HowToDocModal from '@/components/HowToDocModal.vue';
import HowToVideoCard from '@/components/HowToVideoCard.vue';
import HowToVideoPlayer, { type HowToVideo } from '@/components/HowToVideoPlayer.vue';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BookOpenText,
    CheckCircle2,
    Clapperboard,
    Clock,
    FilePlus2,
    ListChecks,
    Loader2,
    MonitorPlay,
    Pencil,
    Play,
    PlayCircle,
    Search,
    SearchX,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

const props = defineProps<{
    videos: HowToVideo[];
    canManage: boolean;
    maxVideoMb: number;
}>();

const { confirmDialog } = useConfirm();

/* ---- Search ---- */
const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const filteredVideos = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.videos.filter((v) => !q || v.title.toLowerCase().includes(q) || (v.description ?? '').toLowerCase().includes(q));
});

const clearSearch = () => {
    search.value = '';
    searchInput.value?.focus();
};

const playing = ref<HowToVideo | null>(null);

/* ---- Written documentation (modal) ---- */
const docVideoId = ref<number | null>(null);
const docEditOnOpen = ref(false);
// Looked up from props so the modal shows fresh steps after saving.
const docVideo = computed(() => props.videos.find((v) => v.id === docVideoId.value) ?? null);
const openDocs = (video: HowToVideo, edit = false) => {
    docEditOnOpen.value = edit;
    docVideoId.value = video.id;
};

/* ---- Upload / edit (superadmin) ---- */
const notice = ref('');
const formOpen = ref(false);
const editingId = ref<number | null>(null);
const videoError = ref('');
const form = useForm<{ title: string; description: string; video: File | null; thumbnail: File | null }>({
    title: '',
    description: '',
    video: null,
    thumbnail: null,
});

const showNotice = (message: string) => {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 4000);
};

const openCreate = () => {
    form.reset();
    form.clearErrors();
    videoError.value = '';
    editingId.value = null;
    formOpen.value = true;
};

const openEdit = (video: HowToVideo) => {
    form.clearErrors();
    form.title = video.title;
    form.description = video.description ?? '';
    form.video = null;
    form.thumbnail = null;
    videoError.value = '';
    editingId.value = video.id;
    formOpen.value = true;
};

const closeForm = () => {
    if (form.processing) return;
    formOpen.value = false;
};

const pickVideo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    videoError.value =
        file && file.size > props.maxVideoMb * 1024 * 1024 ? `This video is larger than ${props.maxVideoMb} MB. Please upload a smaller file.` : '';
    form.video = file;
};

const pickThumbnail = (event: Event) => {
    form.thumbnail = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const submit = () => {
    if (!editingId.value && !form.video) {
        videoError.value = 'Choose a video file to upload.';
        return;
    }
    if (videoError.value) return;

    const wasEditing = editingId.value !== null;
    const url = wasEditing ? route('how-to-videos.update', editingId.value) : route('how-to-videos.store');
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
            showNotice(wasEditing ? 'Video updated.' : 'Video uploaded.');
        },
    });
};

const deletingId = ref<number | null>(null);
const remove = async (video: HowToVideo) => {
    if (!(await confirmDialog(`Delete “${video.title}”? The video file will be removed.`, { title: 'Delete video?' }))) return;
    deletingId.value = video.id;
    router.delete(route('how-to-videos.destroy', video.id), {
        preserveScroll: true,
        onSuccess: () => showNotice('Video deleted.'),
        onFinish: () => (deletingId.value = null),
    });
};
</script>

<style scoped>
/* ===================== THEME TOKENS =====================
   Follows the app's theme (Tailwind's `.dark` class on <html>). The modal is teleported to
   <body>, so it gets the same tokens. --vc-* tokens theme the shared HowToVideoCard. */
.howto-page,
.modal {
    --hv-bg: #f4f6fb;
    --hv-surface: #ffffff;
    --hv-surface-2: #f8f9fc;
    --hv-border: #e3e8f3;
    --hv-border-strong: #d0d7ec;
    --hv-text: #1a2744;
    --hv-text-2: #4b5671;
    --hv-muted: #6b7280;
    --hv-hover: #f1f4fb;
    --hv-accent: #1d3fc4;
    --hv-accent-hover: #1834a3;
    --hv-accent-soft: #eef2ff;
    --hv-focus: #4c6ef5;
    --hv-ring: rgba(76, 110, 245, 0.2);
    --hv-input-bg: #ffffff;
    --hv-danger: #c92a2a;
    --hv-danger-soft: #fff5f5;
    --hv-danger-border: #ffc9c9;
    --hv-success: #2b8a3e;
    --hv-success-soft: #ebfbee;
    --hv-success-border: #c3ecc9;
    --hv-shadow: 0 1px 2px rgba(15, 28, 72, 0.04), 0 4px 16px -6px rgba(15, 28, 72, 0.08);

    --vc-bg: var(--hv-surface);
    --vc-border: var(--hv-border);
    --vc-border-hover: #cdd7f2;
    --vc-title: var(--hv-text);
    --vc-text: var(--hv-muted);
    --vc-muted: #8a93a8;
    --vc-divider: #eef0f6;
    --vc-focus: var(--hv-accent);
}
.dark .howto-page,
.dark .modal {
    --hv-bg: #0b1222;
    --hv-surface: #111a2e;
    --hv-surface-2: #0e1628;
    --hv-border: #1f2a44;
    --hv-border-strong: #2b3857;
    --hv-text: #e6ebf5;
    --hv-text-2: #b6c0d6;
    --hv-muted: #8d99b3;
    --hv-hover: #18233b;
    --hv-accent: #4c6ef5;
    --hv-accent-hover: #5c7cfa;
    --hv-accent-soft: rgba(76, 110, 245, 0.14);
    --hv-focus: #748ffc;
    --hv-ring: rgba(116, 143, 252, 0.28);
    --hv-input-bg: #0b1222;
    --hv-danger: #ff8787;
    --hv-danger-soft: rgba(250, 82, 82, 0.1);
    --hv-danger-border: rgba(250, 82, 82, 0.35);
    --hv-success: #8ce99a;
    --hv-success-soft: rgba(64, 192, 87, 0.12);
    --hv-success-border: rgba(64, 192, 87, 0.3);
    --hv-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);

    --vc-border-hover: #34446b;
    --vc-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    --vc-shadow-hover: 0 16px 36px -10px rgba(0, 0, 0, 0.6);
    --vc-muted: #75819c;
    --vc-divider: #1b2540;
    --vc-focus: #748ffc;
}

.howto-page {
    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    color: var(--hv-text);
    background: var(--hv-bg);
    min-height: 100vh;
    overflow-x: hidden;
}
/* This page can be opened directly, so it can't rely on the homepage's global reset. */
.howto-page :where(*, *::before, *::after) {
    box-sizing: border-box;
}
.howto-page :where(h1, h2, h3, p, ul, fieldset, legend) {
    margin: 0;
    padding: 0;
}
.modal :where(*, *::before, *::after) {
    box-sizing: border-box;
}
.modal :where(h2, p, fieldset, legend) {
    margin: 0;
    padding: 0;
}

/* ===================== HERO =====================
   Always the branded navy, in both themes. */
.hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: clamp(7rem, 12vw, 8.5rem) 0 clamp(2.75rem, 5vw, 3.75rem);
    background:
        radial-gradient(70% 110% at 88% 0%, rgba(76, 110, 245, 0.42), transparent 62%),
        radial-gradient(45% 70% at 0% 100%, rgba(21, 170, 191, 0.14), transparent 70%), linear-gradient(160deg, #0c1739 0%, #0f1c48 55%, #13275f 100%);
    color: #fff;
}
.hero__pattern {
    position: absolute;
    inset: 0;
    z-index: -1;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.045) 1px, transparent 1px);
    background-size: 44px 44px;
    -webkit-mask-image: radial-gradient(80% 90% at 75% 30%, #000 20%, transparent 75%);
    mask-image: radial-gradient(80% 90% at 75% 30%, #000 20%, transparent 75%);
}
/* Abstract "play" mark: concentric rings, purely decorative. */
.hero__rings {
    position: absolute;
    z-index: -1;
    right: max(2rem, calc((100% - 1100px) / 2));
    top: 50%;
    width: 300px;
    aspect-ratio: 1;
    transform: translateY(-35%);
    display: grid;
    place-items: center;
}
.hero__rings > span {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(145, 167, 255, 0.16);
}
.hero__rings > span:nth-child(1) {
    inset: 0;
}
.hero__rings > span:nth-child(2) {
    inset: 18%;
    border-color: rgba(145, 167, 255, 0.22);
    background: radial-gradient(circle, rgba(76, 110, 245, 0.12), transparent 70%);
}
.hero__rings > .hero__rings-core {
    inset: 36%;
    display: grid;
    place-items: center;
    padding-left: 5px;
    background: linear-gradient(135deg, rgba(76, 110, 245, 0.35), rgba(21, 170, 191, 0.2));
    border-color: rgba(145, 167, 255, 0.35);
    color: rgba(255, 255, 255, 0.55);
}
@media (max-width: 1040px) {
    .hero__rings {
        display: none;
    }
}
.hero__inner {
    width: min(1100px, calc(100% - 2rem));
    margin-inline: auto;
}
.hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    color: #8ce99a;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: rgba(140, 233, 154, 0.08);
    border: 1px solid rgba(140, 233, 154, 0.22);
    margin-bottom: 1.1rem;
}
.hero h1 {
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.08;
    margin-bottom: 0.9rem;
    max-width: 18ch;
}
.hero__accent {
    background: linear-gradient(90deg, #91a7ff, #66d9e8);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.hero__lead {
    color: #c5cee8;
    max-width: 56ch;
    font-size: clamp(0.95rem, 1.6vw, 1.05rem);
    line-height: 1.65;
}
.hero__tools {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.9rem;
    max-width: 640px;
}
.search {
    flex: 1 1 280px;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0 0.5rem 0 1rem;
    height: 50px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #aab6d8;
    box-shadow: 0 10px 30px -12px rgba(0, 0, 0, 0.45);
    transition:
        border-color 0.2s,
        background 0.2s,
        box-shadow 0.2s;
}
.search:hover {
    border-color: rgba(255, 255, 255, 0.28);
}
.search:focus-within {
    border-color: #91a7ff;
    background: rgba(255, 255, 255, 0.13);
    box-shadow:
        0 0 0 3px rgba(145, 167, 255, 0.3),
        0 10px 30px -12px rgba(0, 0, 0, 0.45);
    color: #dbe4ff;
}
.search__icon {
    flex-shrink: 0;
}
/* Extra selector weight so the app's global input style (resources/css/app.css) doesn't override these. */
.howto-page .hero .search input[type='search'] {
    flex: 1;
    min-width: 0;
    height: 100%;
    background: none;
    border: 0;
    outline: none;
    box-shadow: none;
    color: #fff;
    font: inherit;
    font-size: 0.97rem;
}
.howto-page .hero .search input[type='search']::placeholder {
    color: #9aa6c7;
}
/* A custom clear button replaces the browser's own. */
.howto-page .hero .search input[type='search']::-webkit-search-cancel-button {
    -webkit-appearance: none;
    appearance: none;
}
.search__clear {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 0;
    background: rgba(255, 255, 255, 0.08);
    color: #dbe4ff;
    cursor: pointer;
    transition: background 0.2s;
}
.search__clear:hover {
    background: rgba(255, 255, 255, 0.18);
}
.search__clear:focus-visible {
    outline: 2px solid #91a7ff;
    outline-offset: 1px;
}

/* ===================== BUTTONS ===================== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    height: 44px;
    padding: 0 1.15rem;
    border-radius: 12px;
    font: inherit;
    font-size: 0.9rem;
    font-weight: 700;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition:
        background 0.2s,
        border-color 0.2s,
        box-shadow 0.2s,
        opacity 0.2s;
}
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn:focus-visible,
.mini:focus-visible,
.modal__close:focus-visible {
    outline: 2px solid var(--hv-focus);
    outline-offset: 2px;
}
.btn--primary {
    background: var(--hv-accent);
    color: #fff;
    box-shadow: 0 6px 16px -6px rgba(29, 63, 196, 0.55);
}
.btn--primary:hover:not(:disabled) {
    background: var(--hv-accent-hover);
}
.btn--hero {
    height: 50px;
    padding: 0 1.35rem;
    border-radius: 14px;
    background: #fff;
    color: #0f1c48;
    box-shadow: 0 10px 30px -12px rgba(0, 0, 0, 0.5);
}
.btn--hero:hover:not(:disabled) {
    background: #e7ecff;
}
.btn--hero:focus-visible {
    outline-color: #91a7ff;
}
.btn--ghost {
    background: var(--hv-surface);
    border-color: var(--hv-border-strong);
    color: var(--hv-text);
}
.btn--ghost:hover:not(:disabled) {
    background: var(--hv-hover);
}
.mini {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.4rem 0.7rem;
    border-radius: 8px;
    border: 1px solid var(--hv-border);
    background: transparent;
    color: var(--hv-text-2);
    cursor: pointer;
    transition:
        background 0.2s,
        border-color 0.2s,
        color 0.2s;
}
.mini:hover:not(:disabled) {
    background: var(--hv-hover);
    color: var(--hv-text);
}
.mini--danger {
    color: var(--hv-danger);
}
/* "Read documentation" sits on its own row above the superadmin buttons. */
.mini--doc {
    flex: 1 1 100%;
    justify-content: center;
    background: var(--hv-accent-soft);
    border-color: transparent;
    color: var(--hv-accent);
}
.mini--doc:hover:not(:disabled) {
    background: var(--hv-accent);
    color: #fff;
}
.mini--danger:hover:not(:disabled) {
    background: var(--hv-danger-soft);
    border-color: var(--hv-danger-border);
    color: var(--hv-danger);
}
.mini:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.spin {
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ===================== CONTENT ===================== */
.content {
    width: min(1100px, calc(100% - 2rem));
    margin: 0 auto;
    padding: clamp(1.75rem, 4vw, 2.5rem) 0 clamp(3rem, 6vw, 4.5rem);
}

/* ---- Intro ---- */
.intro {
    display: grid;
    gap: 1.25rem;
    padding-bottom: clamp(1.5rem, 3vw, 2rem);
    margin-bottom: clamp(1.5rem, 3vw, 2rem);
    border-bottom: 1px solid var(--hv-border);
}
.intro__text h2 {
    font-size: clamp(1.25rem, 2.4vw, 1.5rem);
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1.25;
    margin-bottom: 0.35rem;
}
.intro__text p {
    color: var(--hv-muted);
    line-height: 1.6;
    max-width: 62ch;
}
.facts {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr));
    gap: 0.75rem;
}
.fact {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    padding: 0.8rem 0.95rem;
    border-radius: 14px;
    background: var(--hv-surface);
    border: 1px solid var(--hv-border);
    box-shadow: var(--hv-shadow);
}
.fact > span:last-child {
    display: grid;
    min-width: 0;
}
.fact strong {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--hv-text);
}
.fact small {
    font-size: 0.78rem;
    color: var(--hv-muted);
    line-height: 1.4;
}
.fact__icon {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: var(--hv-accent-soft);
    color: var(--hv-accent);
}
.fact__icon--teal {
    background: rgba(21, 170, 191, 0.12);
    color: #1098ad;
}
.fact__icon--green {
    background: rgba(12, 166, 120, 0.12);
    color: #0ca678;
}
.dark .fact__icon--teal {
    color: #66d9e8;
}
.dark .fact__icon--green {
    color: #63e6be;
}

/* ---- Library ---- */
.content__bar {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin-bottom: 1.25rem;
}
.library__title {
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.005em;
}
.count {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--hv-muted);
    overflow-wrap: anywhere;
}
.notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    background: var(--hv-success-soft);
    border: 1px solid var(--hv-success-border);
    color: var(--hv-success);
    font-size: 0.9rem;
    font-weight: 600;
}
.grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: clamp(1rem, 2vw, 1.5rem);
}
@media (max-width: 960px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 600px) {
    .grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
.empty {
    display: grid;
    justify-items: center;
    gap: 0.6rem;
    text-align: center;
    padding: clamp(2.5rem, 7vw, 4.5rem) 1.25rem;
    border-radius: 20px;
    border: 1px dashed var(--hv-border-strong);
    background: radial-gradient(60% 80% at 50% 0%, var(--hv-accent-soft), transparent 70%), var(--hv-surface);
}
.empty__icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    margin-bottom: 0.35rem;
    background: var(--hv-accent-soft);
    color: var(--hv-accent);
    box-shadow: 0 0 0 8px color-mix(in srgb, var(--hv-accent) 6%, transparent);
}
.empty h3 {
    font-size: 1.15rem;
    font-weight: 800;
    margin: 0;
}
.empty p {
    color: var(--hv-muted);
    max-width: 46ch;
    line-height: 1.6;
    margin-bottom: 0.6rem;
    overflow-wrap: anywhere;
}

/* ===================== FORM MODAL ===================== */
.modal {
    position: fixed;
    inset: 0;
    z-index: 90;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(8, 15, 35, 0.62);
    backdrop-filter: blur(4px);
    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    color: var(--hv-text);
}
.modal__box {
    width: min(580px, 100%);
    max-height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--hv-surface);
    border: 1px solid var(--hv-border);
    border-radius: 20px;
    box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}
.modal__head {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid var(--hv-border);
}
.modal__badge {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: var(--hv-accent-soft);
    color: var(--hv-accent);
}
.modal__heading {
    flex: 1;
    min-width: 0;
}
.modal__head h2 {
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.3;
}
.modal__head p {
    font-size: 0.82rem;
    color: var(--hv-muted);
    margin-top: 0.1rem;
}
.modal__close {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--hv-border);
    background: var(--hv-surface-2);
    color: var(--hv-text-2);
    cursor: pointer;
    transition: background 0.2s;
}
.modal__close:hover:not(:disabled) {
    background: var(--hv-hover);
    color: var(--hv-text);
}
.modal__close:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.modal__body {
    display: grid;
    gap: 1.15rem;
    padding: 1.35rem 1.25rem;
    overflow-y: auto;
    overscroll-behavior: contain;
    min-height: 0;
}
.modal__foot {
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.6rem;
    padding: 0.9rem 1.25rem;
    border-top: 1px solid var(--hv-border);
    background: var(--hv-surface-2);
}
@media (max-width: 420px) {
    .modal {
        padding: 0.5rem;
    }
    .modal__foot .btn {
        flex: 1;
    }
}
.field {
    display: grid;
    gap: 0.4rem;
    border: 0;
    min-width: 0;
}
.field > span,
.field legend {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hv-text);
}
.field b {
    color: var(--hv-danger);
}
.modal .modal__box .field input[type='text'],
.modal .modal__box .field textarea {
    font: inherit;
    font-size: 0.9rem;
    padding: 0.65rem 0.8rem;
    border-radius: 11px;
    border: 1px solid var(--hv-border-strong);
    color: var(--hv-text);
    background: var(--hv-input-bg);
    resize: vertical;
    transition:
        border-color 0.2s,
        box-shadow 0.2s;
}
.modal .modal__box .field input[type='text']::placeholder,
.modal .modal__box .field textarea::placeholder {
    color: var(--hv-muted);
    opacity: 0.8;
}
.modal .modal__box .field input[type='text']:focus,
.modal .modal__box .field textarea:focus {
    outline: none;
    border-color: var(--hv-focus);
    box-shadow: 0 0 0 3px var(--hv-ring);
}
/* Native file input, styled around its own picker button. */
.modal .modal__box .field input[type='file'] {
    font: inherit;
    font-size: 0.85rem;
    min-width: 0;
    max-width: 100%;
    padding: 0.5rem;
    border-radius: 11px;
    border: 1px dashed var(--hv-border-strong);
    background: var(--hv-surface-2);
    color: var(--hv-text-2);
    cursor: pointer;
    transition:
        border-color 0.2s,
        background 0.2s;
}
.modal .modal__box .field input[type='file']:hover {
    border-color: var(--hv-focus);
}
.modal .modal__box .field input[type='file']:focus-visible {
    outline: none;
    border-color: var(--hv-focus);
    box-shadow: 0 0 0 3px var(--hv-ring);
}
.modal .modal__box .field input[type='file']::file-selector-button {
    font: inherit;
    font-weight: 600;
    font-size: 0.82rem;
    margin-right: 0.75rem;
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    border: 0;
    background: var(--hv-accent-soft);
    color: var(--hv-accent);
    cursor: pointer;
}
.hint {
    font-size: 0.78rem;
    color: var(--hv-muted);
    line-height: 1.45;
}
.error {
    font-size: 0.8rem;
    color: var(--hv-danger);
    font-weight: 600;
}
.progress {
    display: grid;
    gap: 0.45rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: var(--hv-surface-2);
    border: 1px solid var(--hv-border);
}
.progress__label {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}
.progress__label small {
    font-size: 0.8rem;
    color: var(--hv-muted);
}
.progress__label strong {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hv-accent);
    font-variant-numeric: tabular-nums;
}
.progress__track {
    height: 8px;
    border-radius: 999px;
    background: var(--hv-border);
    overflow: hidden;
}
.progress__track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--hv-accent), #15aabf);
    transition: width 0.2s;
}

/* ===================== SMALL SCREENS ===================== */
@media (max-width: 480px) {
    .hero__tools .btn--hero {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .btn,
    .mini,
    .search,
    .search__clear,
    .modal__close,
    .progress__track span {
        transition: none;
    }
}
</style>
