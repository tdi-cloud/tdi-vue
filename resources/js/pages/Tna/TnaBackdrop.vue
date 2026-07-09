<script setup>
/*
 | Fixed na background para sa lahat ng TNA pages:
 | blue gradient + malalaking outlined na "TRAINING NEEDS ANALYSIS" (Poppins).
 |
 | Naglalaman din ito ng GLOBAL (non-scoped) na style na pumipilit ng
 | LIGHT MODE sa lahat ng TNA pages — kahit naka-dark mode ang user.
 | Ilagay lang ang class na `tna-page` sa root div ng bawat TNA page.
 */
</script>

<template>
  <div class="tna-backdrop" aria-hidden="true">
    <div class="tna-backdrop__type">
      <span>TRAINING</span>
      <span>NEEDS</span>
      <span>ANALYSIS</span>
    </div>
  </div>
</template>

<!-- ===== GLOBAL: pilitin ang light mode sa buong TNA ===== -->
<style>
/* Sinasabi sa browser na light ang page — dito nagmumula ang
   pag-itim ng inputs, radios, at scrollbars sa dark mode. */
.tna-page,
.tna-page * {
  color-scheme: light !important;
}

/* Teksto sa loob ng inputs: laging madilim sa puting background.
   Hindi kasama ang radio/checkbox (may sariling rule sa ibaba). */
.tna-page :is(input, textarea, select):not([type='radio']):not([type='checkbox']) {
  background-color: #ffffff !important;
  background-image: none !important;
  color: #111827 !important;
  -webkit-text-fill-color: #111827 !important;
  border-color: #d1d5db !important;
  opacity: 1 !important;
}

/* Kapag naka-focus, panatilihing puti at asul ang border */
.tna-page :is(input, textarea, select):not([type='radio']):not([type='checkbox']):focus {
  background-color: #ffffff !important;
  border-color: #3b82f6 !important;
}

.tna-page :is(input, textarea)::placeholder {
  color: #9ca3af !important;
  -webkit-text-fill-color: #9ca3af !important;
}

/* Radio at checkbox: puting loob, asul kapag pinili */
.tna-page :is(input[type='radio'], input[type='checkbox']) {
  background-color: #ffffff !important;
  accent-color: #2563eb !important;
}

/* Autofill (Chrome) — huwag mag-itim/dilaw */
.tna-page input:-webkit-autofill,
.tna-page input:-webkit-autofill:hover,
.tna-page input:-webkit-autofill:focus {
  -webkit-text-fill-color: #111827 !important;
  -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
}
</style>

<!-- ===== SCOPED: ang backdrop mismo ===== -->
<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@800;900&display=swap');

.tna-backdrop {
  position: fixed;
  inset: 0;
  z-index: 0;
  overflow: hidden;
  pointer-events: none;
  /* dating gradient: from-blue-600 (#2563eb) -> to-blue-800 (#1e40af) */
  background: linear-gradient(to bottom right, #2563eb, #1e40af);
}

.tna-backdrop__type {
  position: absolute;
  left: -0.05em;
  bottom: -0.1em;

  display: flex;
  flex-direction: column;

  font-family: 'Poppins', 'Inter', system-ui, sans-serif;
  font-weight: 900;
  font-size: clamp(4.5rem, 13vw, 13rem);
  line-height: 0.74;
  letter-spacing: -0.015em;
  text-transform: uppercase;
  white-space: nowrap;

  color: transparent;
  -webkit-text-stroke: 1.5px rgba(255, 255, 255, 0.16);
  user-select: none;
}

@media (max-width: 640px) {
  .tna-backdrop__type {
    -webkit-text-stroke-width: 1px;
  }
}
</style>