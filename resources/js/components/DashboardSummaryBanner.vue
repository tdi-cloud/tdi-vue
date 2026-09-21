<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CalendarCheck, ClipboardList, GraduationCap, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    summary: {
        employees: number;
        programs: number;
        active_batches: number;
        pending_submissions: number;
    };
}>();

const page = usePage<SharedData>();

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});

const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? '');

// TEMPORARY BER MONTHS THEME — EASILY REVERTIBLE
// Set to false after the season to restore the complete default banner design.
const isBerMonthsTheme = true;

const stats = computed(() => [
    { label: 'Employees Monitored', value: props.summary.employees, icon: Users, accent: 'blue' },
    { label: 'Programs Conducted', value: props.summary.programs, icon: GraduationCap, accent: 'green' },
    { label: 'Active Batches', value: props.summary.active_batches, icon: CalendarCheck, accent: 'gold' },
    { label: 'Pending Submissions', value: props.summary.pending_submissions, icon: ClipboardList, accent: 'purple' },
]);

const festiveLightColors = ['gold', 'warm', 'red', 'gold', 'warm', 'green', 'gold', 'warm', 'red', 'gold', 'warm', 'green'];
</script>

<template>
    <div
        class="relative isolate overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600 px-6 py-5 text-white shadow-md"
        :class="{ 'ber-months-theme': isBerMonthsTheme }"
    >
        <!-- Decorative background texture — purely visual, sits behind existing content via negative z-index -->
        <div class="tdi-texture-institutional pointer-events-none absolute inset-0 z-[-1] opacity-[0.07]" aria-hidden="true"></div>

        <!-- TEMPORARY BER MONTHS THEME — EASILY REVERTIBLE -->
        <div v-if="isBerMonthsTheme" class="ber-festive-scene pointer-events-none" aria-hidden="true">
            <div class="ber-string-lights">
                <span v-for="(color, index) in festiveLightColors" :key="index" class="ber-light" :class="`ber-light--${color}`"></span>
            </div>
            <div class="ber-pine ber-pine--top-left"></div>
            <div class="ber-ornament ber-ornament--one"></div>
            <div class="ber-ornament ber-ornament--two"></div>
            <div class="ber-parol"><span></span><i></i></div>
            <div class="ber-lantern"><span></span></div>
            <div class="ber-pine ber-pine--bottom-right"></div>
            <div class="ber-gift"></div>
            <i class="ber-bokeh ber-bokeh--one"></i>
            <i class="ber-bokeh ber-bokeh--two"></i>
            <i class="ber-bokeh ber-bokeh--three"></i>
        </div>

        <div class="relative z-10">
            <p v-if="isBerMonthsTheme" class="ber-seasonal-label">A Season of Growth and Gratitude</p>
            <p class="text-lg font-bold" :class="{ 'sm:text-xl': isBerMonthsTheme }">
                {{ greeting }}<template v-if="firstName">, <span :class="{ 'ber-name': isBerMonthsTheme }">{{ firstName }}</span></template> 👋
            </p>
            <p v-if="isBerMonthsTheme" class="mt-0.5 text-sm text-white/85">Celebrating the season of learning, growth, and shared accomplishments.</p>
            <p v-else class="mt-0.5 text-sm text-white/80">Here's a quick look at what's happening across your programs today.</p>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur-sm"
                    :class="isBerMonthsTheme ? ['ber-stat-card', `ber-stat-card--${stat.accent}`] : ''"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15" :class="isBerMonthsTheme ? 'ber-stat-icon' : ''">
                        <component :is="stat.icon" class="h-4.5 w-4.5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-lg font-extrabold leading-tight">{{ stat.value.toLocaleString() }}</p>
                        <p class="truncate text-[11px] font-medium text-white/75">{{ stat.label }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* TDI Institutional Micro-Texture — the exact same fine grid + radial-fade
   technique used in the homepage "Digital Resources" section, tinted white for
   the banner's blue gradient. The least visible texture in the system. */
.tdi-texture-institutional {
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.6) 1px, transparent 1px);
    background-size: 32px 32px;
    -webkit-mask-image: radial-gradient(ellipse at center, black 0%, transparent 72%);
    mask-image: radial-gradient(ellipse at center, black 0%, transparent 72%);
}

/* TEMPORARY BER MONTHS THEME — EASILY REVERTIBLE */
.ber-months-theme {
    background:
        radial-gradient(circle at 82% 28%, rgba(255, 204, 104, 0.25), transparent 20%),
        radial-gradient(circle at 22% 100%, rgba(32, 132, 159, 0.22), transparent 31%),
        linear-gradient(125deg, #071a3d 0%, #0b3270 52%, #176da1 100%);
    box-shadow: 0 14px 35px rgba(4, 21, 58, 0.28), inset 0 1px rgba(255, 232, 177, 0.2);
}

.ber-festive-scene,
.ber-festive-scene > * { position: absolute; }

.ber-festive-scene { inset: 0; overflow: hidden; }

.ber-string-lights {
    top: 0;
    left: 7%;
    right: 7%;
    display: flex;
    justify-content: space-between;
    height: 25px;
    border-top: 2px solid rgba(255, 224, 151, 0.5);
}

.ber-light {
    top: 5px;
    width: 7px;
    height: 11px;
    border-radius: 50% 50% 45% 45%;
    box-shadow: 0 0 7px currentColor, 0 0 14px currentColor;
    animation: ber-glow 3.2s ease-in-out infinite alternate;
}

.ber-light::before { position: absolute; top: -4px; left: 2px; width: 3px; height: 4px; background: #cfa14c; content: ''; }
.ber-light--gold { color: #ffe19a; background: #ffe19a; }
.ber-light--warm { color: #fff4d1; background: #fff4d1; animation-delay: 0.8s; }
.ber-light--red { color: #f2998e; background: #f2998e; animation-delay: 1.4s; }
.ber-light--green { color: #9fd5b4; background: #9fd5b4; animation-delay: 2s; }

.ber-pine { width: 150px; height: 78px; opacity: 0.55; background: repeating-linear-gradient(145deg, transparent 0 8px, #2a755c 9px 11px, transparent 12px 18px); filter: drop-shadow(0 6px 9px rgba(2, 26, 37, 0.4)); }
.ber-pine--top-left { top: -22px; left: -28px; transform: rotate(12deg); }
.ber-pine--bottom-right { right: -25px; bottom: -26px; transform: rotate(192deg); }

.ber-ornament { top: -6px; width: 22px; height: 28px; border: 2px solid rgba(255, 236, 177, 0.7); border-radius: 50%; box-shadow: 0 0 18px rgba(255, 198, 86, 0.45); }
.ber-ornament::before { position: absolute; top: -16px; left: 9px; width: 1px; height: 15px; background: #e7bc66; content: ''; }
.ber-ornament--one { left: 14%; background: #a9444b; }
.ber-ornament--two { left: 20%; top: 2px; width: 17px; height: 22px; background: #d9ac55; }

.ber-parol { top: 22px; right: 5.5%; width: 76px; height: 76px; clip-path: polygon(50% 0, 61% 30%, 85% 15%, 70% 39%, 100% 50%, 70% 61%, 85% 85%, 61% 70%, 50% 100%, 39% 70%, 15% 85%, 30% 61%, 0 50%, 30% 39%, 15% 15%, 39% 30%); background: linear-gradient(135deg, #fff5c7, #d49636 48%, #b64145); filter: drop-shadow(0 0 10px #ffd26d) drop-shadow(0 0 25px rgba(255, 192, 82, 0.85)); animation: ber-parol-glow 4s ease-in-out infinite alternate; }
.ber-parol span { position: absolute; inset: 18px; border-radius: 50%; background: #fff9dd; box-shadow: 0 0 22px 8px rgba(255, 239, 172, 0.9); }
.ber-parol i { position: absolute; right: 34px; bottom: -25px; width: 8px; height: 27px; background: linear-gradient(#d39a35, #b54144); }

.ber-lantern { bottom: -6px; left: 6%; width: 35px; height: 44px; border: 2px solid #e9bd62; border-radius: 5px 5px 9px 9px; background: linear-gradient(90deg, rgba(168, 70, 50, 0.9), rgba(255, 221, 129, 0.95), rgba(168, 70, 50, 0.9)); box-shadow: 0 0 21px rgba(255, 198, 90, 0.75); }
.ber-lantern::before { position: absolute; top: -12px; left: 8px; width: 15px; height: 10px; border: 2px solid #e9bd62; border-bottom: 0; border-radius: 8px 8px 0 0; content: ''; }
.ber-lantern span { position: absolute; top: 7px; bottom: 7px; left: 15px; width: 2px; background: rgba(142, 63, 49, 0.7); }

.ber-gift { right: 8%; bottom: 9px; width: 29px; height: 24px; border-radius: 3px; background: #a7444d; box-shadow: 0 4px 10px rgba(2, 19, 48, 0.4); }
.ber-gift::before, .ber-gift::after { position: absolute; background: #edc46e; content: ''; }
.ber-gift::before { top: 0; bottom: 0; left: 12px; width: 4px; }
.ber-gift::after { top: -4px; left: -2px; width: 33px; height: 5px; }

.ber-bokeh { border-radius: 50%; background: #ffe4a2; filter: blur(1px); opacity: 0.42; box-shadow: 0 0 14px 5px rgba(255, 221, 135, 0.38); animation: ber-glow 4s ease-in-out infinite alternate; }
.ber-bokeh--one { top: 34%; left: 34%; width: 6px; height: 6px; }
.ber-bokeh--two { right: 26%; bottom: 22%; width: 9px; height: 9px; animation-delay: 1.2s; }
.ber-bokeh--three { top: 15%; left: 47%; width: 4px; height: 4px; animation-delay: 2.1s; }

.ber-seasonal-label { margin-bottom: 0.25rem; color: #ffe5a1; font-size: 0.625rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
.ber-name { color: #ffe19a; text-shadow: 0 1px 12px rgba(255, 206, 106, 0.5); }
.ber-stat-card { border: 1px solid rgba(255, 234, 180, 0.22); background: linear-gradient(135deg, rgba(7, 31, 72, 0.66), rgba(28, 93, 137, 0.38)); box-shadow: inset 0 1px rgba(255, 255, 255, 0.11), 0 8px 20px rgba(2, 18, 48, 0.2); }
.ber-stat-card:hover { transform: translateY(-1px); border-color: rgba(255, 224, 151, 0.45); }
.ber-stat-icon { border: 1px solid rgba(255, 255, 255, 0.18); box-shadow: 0 0 14px rgba(255, 233, 177, 0.17); }
.ber-stat-card--blue .ber-stat-icon { background: rgba(51, 153, 255, 0.3); }
.ber-stat-card--green .ber-stat-icon { background: rgba(42, 163, 125, 0.3); }
.ber-stat-card--gold .ber-stat-icon { background: rgba(220, 161, 53, 0.34); }
.ber-stat-card--purple .ber-stat-icon { background: rgba(143, 102, 205, 0.34); }

@keyframes ber-glow { from { opacity: 0.55; transform: scale(0.88); } to { opacity: 1; transform: scale(1); } }
@keyframes ber-parol-glow { from { transform: scale(0.94); } to { transform: scale(1); } }

@media (max-width: 640px) {
    .ber-parol { top: 18px; right: -14px; width: 58px; height: 58px; opacity: 0.65; }
    .ber-ornament--two, .ber-pine--top-left { display: none; }
    .ber-string-lights { left: 3%; right: 3%; }
    .ber-lantern { left: 2%; opacity: 0.65; }
    .ber-seasonal-label { max-width: 235px; line-height: 1.4; }
}

@media (prefers-reduced-motion: reduce) {
    .ber-months-theme *, .ber-months-theme *::before, .ber-months-theme *::after { animation: none !important; }
}
</style>
