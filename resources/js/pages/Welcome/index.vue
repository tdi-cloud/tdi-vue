<template>
  <Head title="TESDA Development Institute" />
  <div class="tdi-home">
    <TheNavbar />
    <HeroSection />
    <EnrolledProgramsSection :programs="enrolledPrograms" />
    <ImagePanels />
    <AboutSection />
    <ProgramsSection />
    <ForeignScholarshipSection />
    <PathwaySection />
    <ResourcesSection />
    <CtaBanner />
    <TheFooter />
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import TheNavbar          from './sections/TheNavbar.vue'
import HeroSection        from './sections/HeroSection.vue'
import EnrolledProgramsSection from './sections/EnrolledProgramsSection.vue'
import ImagePanels        from './sections/ImagePanels.vue'
import AboutSection       from './sections/AboutSection.vue'
import ProgramsSection    from './sections/ProgramsSection.vue'
// import ScholarshipsSection from './sections/ScholarshipsSection.vue'
import PathwaySection     from './sections/PathwaySection.vue'
import ResourcesSection   from './sections/ResourcesSection.vue'
import CtaBanner          from './sections/CtaBanner.vue'
import TheFooter          from './sections/TheFooter.vue'
import ForeignScholarshipSection from './sections/ForeignScholarshipSection.vue'


defineProps({
  enrolledPrograms: { type: Array, default: () => [] },
})
</script>

<style>
/* Global base reset — only runs once */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.tdi-home {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  color: #1a2744;
  background: #fff;
  overflow-x: hidden;
}

@media (prefers-reduced-motion: reduce) {
  * { animation: none !important; transition: none !important; }
}
</style>