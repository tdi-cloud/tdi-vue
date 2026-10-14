<template>
    <Head title="My Programs" />
    <div class="my-programs-page">
        <TheNavbar />
        <!-- The section renders its own dark page header (title, stats and filters) when not in summary mode. -->
        <EnrolledProgramsSection :programs="programs" />
        <TheFooter />
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EnrolledProgramsSection from '../Welcome/sections/EnrolledProgramsSection.vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

defineProps({
    programs: { type: Array, default: () => [] },
});
</script>

<style scoped>
.my-programs-page {
    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    color: #1a2744;
    background: #f7f9fc;
    min-height: 100vh;
    overflow-x: hidden;
}
/* Follows the app's light/dark mode (the `dark` class on <html>). */
.dark .my-programs-page {
    color: #e6ecf7;
    background: #0b1020;
}
/* This page can be opened directly, so it can't rely on the homepage's global reset.
   Fully wrapped in :where() (zero specificity) so it never overrides the section's own margins. */
:where(.my-programs-page) :where(*, *::before, *::after) {
    box-sizing: border-box;
}
:where(.my-programs-page) :where(h1, h2, h3, h4, p) {
    margin: 0;
    padding: 0;
}
</style>
