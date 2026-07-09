<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const visible = ref(false)

function onScroll() {
  visible.value = window.scrollY > 400
}

function toTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
  <Transition name="btt">
    <button
      v-if="visible"
      type="button"
      class="btt"
      aria-label="Back to top"
      title="Back to top"
      @click="toTop"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
        stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 19V5M5 12l7-7 7 7" />
      </svg>
    </button>
  </Transition>
</template>

<style scoped>
.btt {
  position: fixed;
  right: 1.5rem;
  bottom: 1.5rem;
  z-index: 50;

  display: flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;

  border: none;
  border-radius: 50%;
  background: #ffffff;
  color: #1d4ed8;
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(15, 28, 72, 0.28);
  transition: transform 0.18s, box-shadow 0.18s;
}
.btt:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(15, 28, 72, 0.36);
}
.btt svg { width: 20px; height: 20px; }

.btt-enter-active,
.btt-leave-active { transition: opacity 0.2s, transform 0.2s; }
.btt-enter-from,
.btt-leave-to { opacity: 0; transform: translateY(8px); }

@media (max-width: 640px) {
  .btt { right: 1rem; bottom: 1rem; width: 42px; height: 42px; }
}
</style>