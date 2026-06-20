<template>
  <section class="scholarships" id="scholarships">
    <div class="scholarships__inner">

      <!-- Left: copy + image -->
      <div class="scholarships__text">
        <span class="eyebrow eyebrow--gold">SCHOLARSHIP HUB</span>
        <h2 class="scholarships__heading">Invest in your next level of growth.</h2>
        <p class="scholarships__body">
          Discover opportunities that support advanced study, deeper expertise,
          and broader professional capability.
        </p>
        <div class="scholarships__img-wrap">
          <img
            src="https://files01.pna.gov.ph/ograph/2019/12/11/img0222.jpg"
            alt="Graduates"
            class="scholarships__img"
          />
        </div>
      </div>

      <!-- Right: eligibility checker card -->
      <div class="eligibility-card">
        <div class="eligibility-card__header">
          <div>
            <span class="eyebrow eyebrow--dark">QUICK ASSESSMENT</span>
            <h3>Scholarship Eligibility Checker</h3>
          </div>
          <button class="help-btn" title="Help" aria-label="Help">?</button>
        </div>

        <div class="field">
          <label>Position Level</label>
          <select v-model="form.position">
            <option value="">Select position level</option>
            <option>Salary Grade 1–10</option>
            <option>Salary Grade 11–15</option>
            <option>Salary Grade 16–20</option>
            <option>Salary Grade 21+</option>
          </select>
        </div>

        <div class="field">
          <label>Years of Service</label>
          <select v-model="form.years">
            <option value="">Select years of service</option>
            <option>Less than 2 years</option>
            <option>2–5 years</option>
            <option>6–10 years</option>
            <option>More than 10 years</option>
          </select>
        </div>

        <div class="field">
          <label>Latest Performance Rating</label>
          <select v-model="form.rating">
            <option value="">Select latest rating</option>
            <option>Outstanding</option>
            <option>Very Satisfactory</option>
            <option>Satisfactory</option>
            <option>Unsatisfactory</option>
          </select>
        </div>

        <!-- Result message -->
        <transition name="fade">
          <div v-if="result" class="result" :class="`result--${result.type}`">
            <span>{{ result.icon }}</span> {{ result.message }}
          </div>
        </transition>

        <div class="eligibility-card__actions">
          <button class="btn btn--primary" @click="checkEligibility">Check My Eligibility</button>
          <button class="btn btn--outline-dark" @click="reset">Reset</button>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'

const form = ref({ position: '', years: '', rating: '' })
const result = ref(null)

function checkEligibility() {
  const { position, years, rating } = form.value

  if (!position || !years || !rating) {
    result.value = { type: 'warn', icon: '⚠️', message: 'Please complete all fields to check your eligibility.' }
    return
  }

  const goodRating   = ['Outstanding', 'Very Satisfactory'].includes(rating)
  const enoughYears  = years !== 'Less than 2 years'

  if (goodRating && enoughYears) {
    result.value = { type: 'success', icon: '✅', message: 'You may be eligible for TDI scholarships. Sign in to apply.' }
  } else {
    result.value = { type: 'info', icon: 'ℹ️', message: 'You may not yet meet all criteria. Keep growing — check again after your next review.' }
  }
}

function reset() {
  form.value  = { position: '', years: '', rating: '' }
  result.value = null
}
</script>

<style scoped>
.scholarships { padding: 5rem 2rem; background: #1a2744; }

.scholarships__inner {
  max-width: 1100px; margin: 0 auto;
  display: grid; grid-template-columns: 1fr 1.1fr; gap: 4rem; align-items: start;
}

/* Left column */
.eyebrow        { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; display: block; margin-bottom: 0.75rem; }
.eyebrow--gold  { color: #f5b800; }
.eyebrow--dark  { color: #6b7280; }

.scholarships__heading { font-size: clamp(1.8rem, 3.5vw, 3rem); font-weight: 800; color: #fff; line-height: 1.15; margin: 0.75rem 0 1rem; }
.scholarships__body    { color: rgba(255,255,255,0.7); line-height: 1.65; margin-bottom: 1.5rem; }
.scholarships__img-wrap { border-radius: 16px; overflow: hidden; }
.scholarships__img { width: 100%; display: block; border-radius: 16px; }

/* Card */
.eligibility-card {
  background: #fff; border-radius: 20px; padding: 2rem;
  box-shadow: 0 8px 40px rgba(0,0,0,0.2);
}
.eligibility-card__header {
  display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;
}
.eligibility-card__header h3 { font-size: 1.1rem; font-weight: 700; color: #1a2744; margin-top: 0.25rem; }

.help-btn {
  width: 32px; height: 32px; border-radius: 50%;
  border: 1.5px solid #d1d5db; background: none; cursor: pointer;
  color: #6b7280; font-weight: 700; font-size: 0.9rem;
}

/* Fields */
.field { margin-bottom: 1rem; }
.field label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 0.4rem; }
.field select {
  width: 100%; padding: 0.6rem 0.85rem;
  border: 1.5px solid #d1d5db; border-radius: 8px;
  font-size: 0.9rem; color: #374151; background: #fff;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' viewBox='0 0 24 24'%3E%3Cpath stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 0.75rem center;
  cursor: pointer;
}
.field select:focus { outline: none; border-color: #1d3fc4; }

/* Result */
.result {
  border-radius: 10px; padding: 0.75rem 1rem;
  font-size: 0.85rem; margin-bottom: 1rem; font-weight: 500;
}
.result--success { background: #ecfdf5; color: #065f46; }
.result--info    { background: #eff6ff; color: #1e40af; }
.result--warn    { background: #fffbeb; color: #92400e; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

/* Actions */
.eligibility-card__actions { display: flex; gap: 0.75rem; margin-top: 1.25rem; }
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 0.6rem 1.25rem; border-radius: 8px; font-size: 0.875rem;
  font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.btn--primary { background: #1d3fc4; color: #fff; }
.btn--primary:hover { background: #1535a8; }
.btn--outline-dark { border: 1.5px solid #1d3fc4; color: #1d3fc4; background: transparent; }
.btn--outline-dark:hover { background: #eef1fc; }

@media (max-width: 1024px) { .scholarships__inner { grid-template-columns: 1fr; gap: 2.5rem; } }

.scholarships__img-wrap {
  border-radius: 16px;
  overflow: hidden;
  max-height: 250px;
  width: 80%;
}

.scholarships__img {
  width: 100%;
  height: 100%;
  display: block;
  border-radius: 16px;
  object-fit: cover;
  object-position: top center;  /* baguhin depende sa gusto */
}
</style>