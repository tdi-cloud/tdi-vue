<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import EmployeeProgressModal from '@/components/EmployeeProgressModal.vue';
import WorkforceKpiCards from '@/components/employees-map/WorkforceKpiCards.vue';
import RegionalPerformanceTable from '@/components/employees-map/RegionalPerformanceTable.vue';
import LdAttentionPanel from '@/components/employees-map/LdAttentionPanel.vue';
import TrainingCoveragePanel from '@/components/employees-map/TrainingCoveragePanel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import axios from 'axios';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import {
    Map, X, Search, Users, RotateCcw, MousePointer2, Sparkles, ArrowRight,
} from 'lucide-vue-next';

interface RegionCount {
    region: string;
    total: number;
}

interface RegionMetric {
    region: string;
    total: number;
    participation: number;
    participation_pct: number;
    completed: number;
    completion_pct: number;
    avg_hours: number;
    pending: number;
    overdue: number;
}

interface Kpi {
    total_personnel: number;
    currently_in_training: number;
    training_completed: number;
    requirements_pending: number;
    needs_attention: number;
}

const props = defineProps<{
    regionCounts: RegionCount[];
    totalEmployees: number;
    regionMetrics: RegionMetric[];
    kpi: Kpi;
    regionalPerformance: (RegionMetric & { rank: number; status: string })[];
    attention: RegionMetric[];
    trainingCoverage: { type: string; total: number }[];
    filters: { region: string; office: string; year: string; plant_status: string[]; sg_min: number };
    filterOptions: { regions: string[]; plantilla_statuses: string[] };
    generatedAt: string;
}>();

/**
 * Tunay na hugis ng bawat rehiyon (mula sa geoJSON, MIT-licensed:
 * github.com/faeldon/philippines-json-maps) — pre-processed at naka-save
 * sa public/data/ph-regions.json. Ang "adm1_psgc" code sa orihinal na data
 * ay ni-map na papunta sa REGION values na ginagamit sa employees table.
 */
type Ring = [number, number][];
type Polygon = Ring[]; // [outerRing, hole1, hole2, ...]

interface RegionGeo {
    code: string;
    name: string;
    polygons: Polygon[];
    centroid: [number, number]; // [lng, lat]
}

interface RegionsData {
    bounds: { minLng: number; maxLng: number; minLat: number; maxLat: number };
    regions: RegionGeo[];
}

/**
 * Dalawang region sa employees table ang walang sariling polygon sa geoJSON
 * (hindi sila "totoong" administrative region): ang "CO" (TESDA Central
 * Office, matatagpuan sa Taguig, Metro Manila) at ang "NIR" (Negros Island
 * Region, na-absorb pabalik sa Region VI/VII sa opisyal na PSGC boundaries).
 * Ipinapakita sila bilang mga "pin marker" sa totoong coordinates nila sa
 * halip na isang buong region shape.
 */
const PIN_MARKERS: Record<string, { label: string; lng: number; lat: number }> = {
    CO:  { label: 'Central Office (Taguig)', lng: 121.0509, lat: 14.5176 },
    NIR: { label: 'Negros Island Region',    lng: 122.9689, lat: 10.6407 },
};

const SCALE = 9; // units per degree
const MAX_HEIGHT = 6; // banayad na relief pa rin — masyadong matangkad kung malapit sa SCALE, lumalabas na matataas na "cliff wall" ang mga baybayin
const MIN_HEIGHT = 0.6;
const PIN_RADIUS = 0.9;
const PIN_MARKER_HEIGHT = 2.2;
const ISLET_AREA_THRESHOLD = 1.5; // shape-space sq. units (~SCALE=9/degree)
const ISLET_HEIGHT = 0.5;

// Default/"reset" na camera position — bahagyang naka-angle/nakataas
// (hindi na halos-top-down) para makita ang extrusion depth, bevel, at
// shadows nang mas malinaw, habang komportable pa ring kasya ang buong
// bansa sa viewport. Ginagamit din ito ng "Reset View" (parehong
// posisyon/target — dating hiwalay/magkaiba ang dalawa).
const DEFAULT_CAMERA_POSITION = new THREE.Vector3(26, 76, 62);
const DEFAULT_CAMERA_TARGET = new THREE.Vector3(0, 0, 0);

// ── Data-driven na kulay (bawat map view metric ay may sariling gradient) ──
// BRIGHT/vibrant na palette (hindi rainbow/random, hindi rin muted-earthy) —
// 5-stop na gradient para sa "mas mataas = mas maganda" na mga metric
// (completion, participation), bright cyan→teal intensity scale para sa
// hours/headcount, at ang parehong bright palette (inverted) para sa
// requirements. Napaka-liit na lang ang `MUTE_MIX` (halos zero) — sapat
// lang para hindi mag-clash ang magkatabing pastel/vibrant na kulay,
// hindi para gawing desaturated/muddy ang mga ito.
const MUTE_MIX = 0.04;
const WHITE = new THREE.Color(0xffffff);
const COLOR_NO_DATA = new THREE.Color('#cbd5e1'); // light slate — NO DATA lang, hindi "mababang value"

function lerpColor(a: THREE.Color, b: THREE.Color, t: number): THREE.Color {
    return new THREE.Color().copy(a).lerp(b, Math.min(1, Math.max(0, t)));
}

function multiStopColor(stops: [number, THREE.Color][], t: number): THREE.Color {
    const clamped = Math.min(1, Math.max(0, t));
    for (let i = 0; i < stops.length - 1; i++) {
        const [t0, c0] = stops[i];
        const [t1, c1] = stops[i + 1];
        if (clamped >= t0 && clamped <= t1) {
            const localT = t1 === t0 ? 0 : (clamped - t0) / (t1 - t0);
            return lerpColor(c0, c1, localT);
        }
    }
    return stops[stops.length - 1][1].clone();
}

/**
 * Employee Distribution — bright sky-blue → vivid blue → vibrant teal.
 */
const HEADCOUNT_STOPS: [number, THREE.Color][] = [
    [0, new THREE.Color('#d9f3ff')],
    [0.25, new THREE.Color('#8dd8f5')],
    [0.5, new THREE.Color('#3fa9e8')],
    [0.75, new THREE.Color('#1976d2')],
    [1, new THREE.Color('#00a9a5')],
];

/**
 * Training Participation / Completion — bright coral → amber → yellow →
 * lime → vibrant teal (progress-oriented, "mas mataas = mas maganda").
 */
const PROGRESS_STOPS: [number, THREE.Color][] = [
    [0, new THREE.Color('#ff6b6b')],
    [0.25, new THREE.Color('#ffb84d')],
    [0.5, new THREE.Color('#ffd93d')],
    [0.75, new THREE.Color('#6ddc8a')],
    [1, new THREE.Color('#20c9a6')],
];

/** Training Hours — bright sky blue → cyan → vivid blue → blue/teal. */
const HOURS_STOPS: [number, THREE.Color][] = [
    [0, new THREE.Color('#ddf6ff')],
    [0.33, new THREE.Color('#7ddff2')],
    [0.66, new THREE.Color('#36b5e5')],
    [1, new THREE.Color('#00afa6')],
];

const COLOR_HEALTHY = new THREE.Color('#3ddc84');  // bright green
const COLOR_PENDING = new THREE.Color('#ffc93d');  // bright amber
const COLOR_OVERDUE = new THREE.Color('#ff5c5c');  // bright red/coral

/** 0–100 scale (mababa→mataas) gamit ang bright na PROGRESS_STOPS gradient. */
function trafficLightColor(pct: number): THREE.Color {
    return multiStopColor(PROGRESS_STOPS, pct / 100);
}

/**
 * Totoong (hindi hardcoded) na kulay kada region batay sa kasalukuyang
 * napiling map view at sa aktwal na `regionMetrics` mula sa backend. Kapag
 * walang employee ang region (`total === 0`), palaging neutral/gray na
 * kulay — hindi kailanman gumagawa ng peke/random na data-driven na kulay.
 */
function regionBaseColor(regionCode: string): THREE.Color {
    const m = regionMetricsMap.value[regionCode];
    if (!m || m.total === 0) return COLOR_NO_DATA.clone();

    // Employee Distribution: sarili nitong 5-stop na blue→teal sequential
    // gradient, hindi dinadaan sa karaniwang MUTE_MIX (para manatiling
    // malinaw ang hakbang sa pagitan ng bawat antas ng density) — at
    // gumagamit ng sqrt() na ratio (hindi linear) para kumalat nang maayos
    // ang mga kulay kahit "long-tail"/skewed ang totoong headcount
    // distribution (hal. iisang region lang ang may malaking bilang).
    if (activeView.value === 'headcount') {
        const maxTotal = Math.max(1, ...props.regionMetrics.map((r) => r.total));
        const ratio = Math.sqrt(m.total / maxTotal);
        return multiStopColor(HEADCOUNT_STOPS, ratio);
    }

    let base: THREE.Color;
    switch (activeView.value) {
        case 'participation':
            base = trafficLightColor(m.participation_pct);
            break;
        case 'completion':
            base = trafficLightColor(m.completion_pct);
            break;
        case 'hours': {
            const maxHours = Math.max(1, ...props.regionMetrics.map((r) => r.avg_hours));
            base = multiStopColor(HOURS_STOPS, m.avg_hours / maxHours);
            break;
        }
        default: {
            // 'requirements': Healthy (bright green) → Pending (bright amber) → Overdue (bright red).
            const rate = m.total > 0 ? m.pending / m.total : 0;
            base = rate <= 0.05 ? COLOR_HEALTHY.clone() : rate <= 0.15 ? COLOR_PENDING.clone() : COLOR_OVERDUE.clone();
        }
    }

    return base.lerp(WHITE, MUTE_MIX);
}

const canvasWrap = ref<HTMLDivElement | null>(null);
const loading = ref(true);
const hoveredRegion = ref<{ region: string; label: string; total: number } | null>(null);
const tooltipStyle = ref({ left: '0px', top: '0px' });

const showPanel = ref(false);
const panelLoading = ref(false);
const panelRegion = ref<{ region: string; label: string; total: number } | null>(null);
const panelSearch = ref('');
const panelOffice = ref('all');
const panelEmployees = ref<any>(null);
const panelOfficeBreakdown = ref<{ office: string; total: number }[]>([]);
const panelOverview = ref<RegionMetric | null>(null);
const selectedEmpcode = ref<string | null>(null);

// ── Map view (metric-switchable) ────────────────────────────────────────
const MAP_VIEWS = [
    { key: 'headcount',     label: 'Employee Distribution', suffix: 'PERSONNEL',    unit: '' },
    { key: 'participation', label: 'Training Participation', suffix: 'IN TRAINING', unit: '' },
    { key: 'completion',    label: 'Training Completion',    suffix: 'COMPLETED',   unit: '' },
    { key: 'hours',         label: 'Training Hours',         suffix: 'AVG HRS',     unit: 'h' },
    { key: 'requirements',  label: 'Requirements Status',    suffix: 'PENDING',     unit: '' },
] as const;
type MapViewKey = typeof MAP_VIEWS[number]['key'];

const activeView = ref<MapViewKey>('headcount');
const activeViewMeta = computed(() => MAP_VIEWS.find((v) => v.key === activeView.value)!);

const regionMetricsMap = computed<Record<string, RegionMetric>>(() =>
    Object.fromEntries(props.regionMetrics.map((r) => [r.region, r]))
);

function metricValueFor(regionCode: string): number {
    const m = regionMetricsMap.value[regionCode];
    if (!m) return 0;
    switch (activeView.value) {
        case 'participation': return m.participation;
        case 'completion': return m.completed;
        case 'hours': return m.avg_hours;
        case 'requirements': return m.pending;
        default: return m.total;
    }
}

function formatMetricValue(value: number): string {
    return activeView.value === 'hours' ? value.toFixed(1) : String(Math.round(value));
}

// ── Toolbar filters (region/office/year) ────────────────────────────────
const filterRegion = ref(props.filters.region ?? 'ALL');
const filterOffice = ref(props.filters.office ?? 'ALL');
const filterYear = ref(props.filters.year ?? 'ALL');
const officeOptions = ref<string[]>([]);
const yearOptions = ref<string[]>([]);
const filtersLoading = ref(false);

async function loadOfficeOptions() {
    const { data } = await axios.get(route('dashboard.offices'), {
        params: { region: filterRegion.value !== 'ALL' ? filterRegion.value : undefined },
    });
    officeOptions.value = data;
}

async function loadYearOptions() {
    const { data } = await axios.get(route('dashboard.batch-years'));
    yearOptions.value = data;
}

function applyFilters() {
    filtersLoading.value = true;
    router.get(route('employees-map.index'), {
        region: filterRegion.value,
        office: filterOffice.value,
        year: filterYear.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { filtersLoading.value = false; },
    });
}

function onRegionFilterChange() {
    filterOffice.value = 'ALL';
    loadOfficeOptions();
    applyFilters();
}

let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let controls: OrbitControls | null = null;
let raycaster: THREE.Raycaster | null = null;
let animationId: number | null = null;
let terrainTexture: THREE.Texture | null = null;
let oceanMesh: THREE.Mesh | null = null;
const blockMeshes: THREE.Mesh[] = [];
let hoveredMesh: THREE.Mesh | null = null;
let regionsDataRef: RegionsData | null = null;
let projectRef: ((lng: number, lat: number) => [number, number]) | null = null;
let focusOutline: THREE.Object3D | null = null;
const labelSprites: Record<string, THREE.Sprite> = {};
const defaultOutlines: Record<string, THREE.Object3D> = {};
const defaultOutlineMaterials: Record<string, THREE.LineBasicMaterial[]> = {};
let hoveredRegionCode: string | null = null;
let pointLight: THREE.PointLight | null = null;
let pointLightTarget = 0;
let ambientLight: THREE.AmbientLight | null = null;
let hemiLight: THREE.HemisphereLight | null = null;
let sunLight: THREE.DirectionalLight | null = null;
let gridHelper: THREE.GridHelper | null = null;

const HOVER_ELEVATE = 0.22;
const SELECT_ELEVATE = 0.32;
const HOVER_EMISSIVE = 0.18;
const SELECT_EMISSIVE = 0.32;
const OUTLINE_BASE_OPACITY = 0.16;
const OUTLINE_HOVER_OPACITY = 0.55;
const EASE_SPEED = 0.14; // per-frame lerp factor — mabilis pa rin pero hindi biglaan

const TERRAIN_TILE_SIZE = 3; // world units kada tile ng texture

// ── Dark mode ────────────────────────────────────────────────────────────
const isDark = ref(document.documentElement.classList.contains('dark'));
let darkObserver: MutationObserver | null = null;

function applySceneBackground() {
    if (!scene) return;
    const bg = isDark.value ? 0x0b1220 : 0xeef4fb; // napaka-banayad na blue-gray sa light mode, hindi puro puti
    scene.background = new THREE.Color(bg);
    if (oceanMesh) {
        (oceanMesh.material as THREE.MeshBasicMaterial).color.set(bg);
    }

    // Sa dark mode, bawasan ang ambient fill at taasan nang bahagya ang
    // directional light para hindi "naka-wash out" ang mga rehiyon laban sa
    // madilim na background — mas mataas ang contrast/depth perception.
    if (ambientLight) ambientLight.intensity = isDark.value ? 0.55 : 0.88;
    if (hemiLight) hemiLight.intensity = isDark.value ? 0.45 : 0.7;
    if (sunLight) sunLight.intensity = isDark.value ? 0.8 : 0.6;
}

/**
 * Procedural, tileable na "grain" texture (speckle pattern, parang damo/lupa
 * na detalye) — gray-ish na base para hindi maapektuhan ang totoong kulay
 * (na galing sa vertex colors), multiply lang ito sa ibabaw para lang sa
 * fine surface detail.
 */
function makeTerrainTexture(): THREE.Texture {
    const canvas = document.createElement('canvas');
    canvas.width = 128;
    canvas.height = 128;
    const ctx = canvas.getContext('2d')!;
    // Napaka-malapit na sa puti ang base (dating #dcdcdc/220) — dahil
    // MULTIPLY ang epekto ng isang diffuse `map` sa vertex colors sa Three.js,
    // kahit banayad na gray na texture ay lumalabas na "muddy"/desaturated
    // sa mga bright/vibrant na data color. Dito, halos hindi na
    // nakaka-apekto sa brightness ang texture — grain/noise variation na
    // lang ang natitirang epekto nito.
    ctx.fillStyle = '#f6f6f6';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    for (let i = 0; i < 1400; i++) {
        const x = Math.random() * canvas.width;
        const y = Math.random() * canvas.height;
        const shade = 232 + Math.random() * 23;
        ctx.fillStyle = `rgb(${shade}, ${shade}, ${shade})`;
        const size = 1 + Math.random() * 2;
        ctx.fillRect(x, y, size, size);
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    return texture;
}

/**
 * Simpleng multi-octave na pseudo-noise (sine waves lang, walang external
 * noise library) — ginagamit para sa natural na variation ng terrain color
 * (parang totoong lupa/gubat, hindi flat na solid color).
 */
function terrainNoise(x: number, y: number): number {
    const n =
        Math.sin(x * 0.15) * Math.cos(y * 0.13) * 0.5 +
        Math.sin(x * 0.37 + y * 0.29) * 0.3 +
        Math.sin((x + y) * 0.07) * 0.2;
    return (n + 1) / 2;
}

/**
 * Nagbibigay ng per-vertex na kulay sa isang region geometry: ang itaas
 * (top face) ay dominant na sa (pastel) na kategoryang kulay ng rehiyon
 * (may kaunting noise variation pa rin para hindi flat/patag ang
 * peke-terrain look), at ang mga tagiliran (cliff/side) ay mas madilim na
 * bersyon ng parehong kulay — para malinaw na "may sariling kulay ang
 * bawat rehiyon" (tulad ng karaniwang region map), habang may kaunti pa
 * ring 3D relief na texture. Ang `dataColor` na dumarating dito ay galing
 * sa `regionBaseColor()` (data-driven, mildly muted na), kaya dito ay
 * shading/relief na lang (top vs. side elevation feel) ang idinadagdag.
 */
function colorizeRegionGeometry(geometry: THREE.BufferGeometry, dataColor: THREE.Color) {
    geometry.computeVertexNormals();
    const pos = geometry.attributes.position;
    const norm = geometry.attributes.normal;
    const colorArr = new Float32Array(pos.count * 3);
    const uvArr = new Float32Array(pos.count * 2);

    const white = new THREE.Color(0xffffff);
    const topLow = new THREE.Color().copy(dataColor).multiplyScalar(0.98);
    const topHigh = new THREE.Color().copy(dataColor).lerp(white, 0.14);
    const tmp = new THREE.Color();

    for (let i = 0; i < pos.count; i++) {
        const nz = norm.getZ(i);

        if (nz > 0.5) {
            // Top surface — ito ang pangunahing representasyon ng metric,
            // kaya halos hindi ito dinidilim (bright, malinaw na kulay).
            const n = terrainNoise(pos.getX(i), pos.getY(i));
            tmp.copy(topLow).lerp(topHigh, n);
        } else if (nz < -0.5) {
            // Side walls — banayad lang na madidilim (hindi dark brown/black),
            // manatiling malinaw na "kaugnay" sa top surface color.
            tmp.copy(dataColor).multiplyScalar(0.82);
        } else {
            tmp.copy(dataColor).multiplyScalar(0.9);
        }

        colorArr[i * 3] = tmp.r;
        colorArr[i * 3 + 1] = tmp.g;
        colorArr[i * 3 + 2] = tmp.b;

        // World-space na UV (hindi yung default per-shape bbox UV ng
        // ExtrudeGeometry) para pare-pareho ang laki ng texture tile
        // kahit magkaiba ang laki ng bawat region.
        uvArr[i * 2] = pos.getX(i) / TERRAIN_TILE_SIZE;
        uvArr[i * 2 + 1] = pos.getY(i) / TERRAIN_TILE_SIZE;
    }

    geometry.setAttribute('color', new THREE.BufferAttribute(colorArr, 3));
    geometry.setAttribute('uv', new THREE.BufferAttribute(uvArr, 2));
}

const RANK_BADGE_COLORS: Record<number, string> = {
    1: '#facc15', // ginto
    2: '#cbd5e1', // pilak
    3: '#d97706', // tanso
};

/**
 * Premium na "chip"/pill label — malinis, minimal, rounded semi-transparent
 * dark background (parang enterprise GIS callout) sa halip na yung dating
 * "tactical/Call of Duty" na bold-outlined text na walang background. May
 * karagdagang ranking badge (top-right corner) na nagpapakita ng puwesto ng
 * region kumpara sa iba batay sa kasalukuyang napiling metric.
 */
function makeLabelSprite(text: string, sub: string, rank: number, suffix: string): THREE.Sprite {
    const canvas = document.createElement('canvas');
    canvas.width = 480;
    canvas.height = 168;
    const ctx = canvas.getContext('2d')!;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Rounded pill background — malabo/translucent dark chip.
    const chipX = 24;
    const chipY = 14;
    const chipW = canvas.width - 48;
    const chipH = 108;
    const radius = 22;
    ctx.beginPath();
    ctx.moveTo(chipX + radius, chipY);
    ctx.arcTo(chipX + chipW, chipY, chipX + chipW, chipY + chipH, radius);
    ctx.arcTo(chipX + chipW, chipY + chipH, chipX, chipY + chipH, radius);
    ctx.arcTo(chipX, chipY + chipH, chipX, chipY, radius);
    ctx.arcTo(chipX, chipY, chipX + chipW, chipY, radius);
    ctx.closePath();
    ctx.fillStyle = 'rgba(15, 23, 42, 0.72)';
    ctx.fill();
    ctx.lineWidth = 2;
    ctx.strokeStyle = 'rgba(94, 234, 212, 0.35)';
    ctx.stroke();

    ctx.textAlign = 'center';
    const title = text.toUpperCase();
    ctx.font = '700 46px Arial';
    ctx.fillStyle = '#ffffff';
    ctx.fillText(title, canvas.width / 2, chipY + 46);

    const subText = `${sub} ${suffix}`;
    ctx.font = '600 24px Arial';
    ctx.fillStyle = '#99f6e4';
    ctx.fillText(subText, canvas.width / 2, chipY + 84);

    // Ranking badge (top-right corner) — #1/#2/#3 ay may medal color, ang iba ay neutral gray.
    const badgeX = chipX + chipW - 8;
    const badgeY = chipY - 4;
    ctx.beginPath();
    ctx.arc(badgeX, badgeY, 24, 0, Math.PI * 2);
    ctx.fillStyle = RANK_BADGE_COLORS[rank] ?? '#64748b';
    ctx.fill();
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = 'rgba(15, 23, 42, 0.85)';
    ctx.stroke();

    ctx.textBaseline = 'middle';
    ctx.font = '700 20px Arial';
    ctx.fillStyle = '#111827';
    ctx.fillText(`#${rank}`, badgeX, badgeY + 1);
    ctx.textBaseline = 'alphabetic';

    const texture = new THREE.CanvasTexture(canvas);
    texture.minFilter = THREE.LinearFilter;
    const material = new THREE.SpriteMaterial({ map: texture, depthTest: false, transparent: true });
    const sprite = new THREE.Sprite(material);
    sprite.scale.set(7.2, 2.52, 1);
    return sprite;
}

/**
 * Simpleng equirectangular projection (sapat na ang accuracy para sa isang
 * bansang kasing-liit ng Pilipinas). lng/lat -> world (x, z).
 */
function makeProjector(bounds: RegionsData['bounds']) {
    const centerLng = (bounds.minLng + bounds.maxLng) / 2;
    const centerLat = (bounds.minLat + bounds.maxLat) / 2;
    const cosLat = Math.cos((centerLat * Math.PI) / 180);

    return (lng: number, lat: number): [number, number] => [
        (lng - centerLng) * cosLat * SCALE,
        (centerLat - lat) * SCALE,
    ];
}

/**
 * Ang ExtrudeGeometry (pinaikot ng -90° sa X axis para humiga) ay
 * nangangailangan ng 2D Shape kung saan: shape_x -> world_x,
 * shape_y -> -world_z. Ang extrusion depth (0..depth) ang nagiging world_y.
 */
function ringToShapePoints(ring: Ring, project: (lng: number, lat: number) => [number, number]): [number, number][] {
    return ring.map(([lng, lat]) => {
        const [x, z] = project(lng, lat);
        return [x, -z] as [number, number];
    });
}

/** Shoelace formula — area ng isang (closed) ring sa shape-space. */
function ringArea(ring: [number, number][]): number {
    let area = 0;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        area += ring[j][0] * ring[i][1] - ring[i][0] * ring[j][1];
    }
    return Math.abs(area) / 2;
}

/**
 * Subtle na bevel lang sa mainland regions (para may premium "raised block"
 * na dating imbes na parang sharp-edged flat cutout) — naka-off ito para sa
 * maliliit na islet dahil ang bevel thickness/size ay maaaring lumaki
 * kumpara sa sobrang liit na footprint nila (distorted ang hugis).
 */
function extrudeShapes(shapes: THREE.Shape[], depth: number, color: THREE.Color, beveled = true): THREE.Mesh {
    const geometry = new THREE.ExtrudeGeometry(shapes, beveled
        ? { depth, bevelEnabled: true, bevelThickness: 0.05, bevelSize: 0.045, bevelSegments: 2 }
        : { depth, bevelEnabled: false });
    colorizeRegionGeometry(geometry, color);
    const material = new THREE.MeshStandardMaterial({
        vertexColors: true,
        map: terrainTexture,
        roughness: 0.82,
        metalness: 0.06,
        emissive: new THREE.Color('#2dd4bf'),
        emissiveIntensity: 0,
    });
    const mesh = new THREE.Mesh(geometry, material);
    mesh.rotation.x = -Math.PI / 2;
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
}

/**
 * Ang mga rehiyon sa totoong geoJSON ay may kasamang dose-dosenang maliliit
 * na islet (bato/sandbar) bukod sa mainland. Kung pareho ang extrusion
 * height ng lahat (base sa napiling metric), ang mga makikitid na islet ay
 * lumalabas na parang matataas na "spike" sa halip na patag na maliliit na
 * isla. Kaya hiwalay ang extrusion: buong height (data-driven) lang para sa
 * malalaking lupain, maliit/fixed na height para sa islet.
 */
function buildRegionMesh(polygons: Polygon[], project: (lng: number, lat: number) => [number, number], color: THREE.Color, height: number): THREE.Mesh[] {
    const mainShapes: THREE.Shape[] = [];
    const isletShapes: THREE.Shape[] = [];

    polygons.forEach((polygon) => {
        const outer = ringToShapePoints(polygon[0], project);
        const shape = new THREE.Shape();
        outer.forEach(([x, y], i) => (i === 0 ? shape.moveTo(x, y) : shape.lineTo(x, y)));
        shape.closePath();

        for (let i = 1; i < polygon.length; i++) {
            const holePts = ringToShapePoints(polygon[i], project);
            const holePath = new THREE.Path();
            holePts.forEach(([x, y], j) => (j === 0 ? holePath.moveTo(x, y) : holePath.lineTo(x, y)));
            holePath.closePath();
            shape.holes.push(holePath);
        }

        (ringArea(outer) >= ISLET_AREA_THRESHOLD ? mainShapes : isletShapes).push(shape);
    });

    const meshes: THREE.Mesh[] = [];
    if (mainShapes.length) meshes.push(extrudeShapes(mainShapes, height, color, true));
    if (isletShapes.length) meshes.push(extrudeShapes(isletShapes, ISLET_HEIGHT, color, false));
    return meshes;
}

function buildPinMarker(x: number, z: number, color: THREE.Color, height: number): THREE.Mesh {
    const geometry = new THREE.CylinderGeometry(PIN_RADIUS * 0.4, PIN_RADIUS, height, 16);
    const material = new THREE.MeshStandardMaterial({
        color, roughness: 0.78, metalness: 0.08,
        emissive: new THREE.Color('#2dd4bf'), emissiveIntensity: 0,
    });
    const mesh = new THREE.Mesh(geometry, material);
    mesh.position.set(x, height / 2, z);
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
}

interface OutlineOptions {
    color?: number;
    opacity?: number;
    collect?: THREE.LineBasicMaterial[];
}

/**
 * Outline sa paligid ng buong sakop ng isang region (bawat polygon ring nito),
 * o simpleng bilog na outline para sa mga pin marker na walang totoong
 * polygon (CO/NIR). Reusable para sa dalawang estado: (1) subtle/low-opacity
 * na default outline sa LAHAT ng region (para malinaw ang hangganan kahit
 * walang hover/select), at (2) mas matingkad na "focus" outline kapag
 * na-hover o na-select ang isang region — parehong geometry-building logic,
 * iba lang ang material.
 */
function buildRegionOutline(regionCode: string, topY: number, opts: OutlineOptions = {}): THREE.Object3D | null {
    if (!projectRef) return null;
    const OUTLINE_Y_OFFSET = 0.05;
    const material = new THREE.LineBasicMaterial({
        color: opts.color ?? 0x2dd4bf,
        opacity: opts.opacity ?? 1,
        transparent: true,
        depthTest: false,
    });
    opts.collect?.push(material);

    const region = regionsDataRef?.regions.find((r) => r.code === regionCode);
    if (region) {
        const group = new THREE.Group();
        region.polygons.forEach((polygon) => {
            const points = polygon[0].map(([lng, lat]) => {
                const [x, z] = projectRef!(lng, lat);
                return new THREE.Vector3(x, topY + OUTLINE_Y_OFFSET, z);
            });
            const geometry = new THREE.BufferGeometry().setFromPoints(points);
            const loop = new THREE.LineLoop(geometry, material);
            loop.renderOrder = 999;
            group.add(loop);
        });
        return group;
    }

    const pin = PIN_MARKERS[regionCode];
    if (pin) {
        const [x, z] = projectRef(pin.lng, pin.lat);
        const curve = new THREE.EllipseCurve(0, 0, PIN_RADIUS * 1.8, PIN_RADIUS * 1.8);
        const points = curve.getPoints(48).map((p) => new THREE.Vector3(p.x, topY + OUTLINE_Y_OFFSET, p.y));
        const geometry = new THREE.BufferGeometry().setFromPoints(points);
        const loop = new THREE.LineLoop(geometry, material);
        loop.renderOrder = 999;
        loop.position.set(x, 0, z);
        return loop;
    }

    return null;
}

/** Tinatanggal ang kasalukuyang focus outline sa scene (kung meron), kasama ang dispose ng geometry/material nito. */
function clearFocusOutline() {
    if (!focusOutline) return;
    scene?.remove(focusOutline);
    focusOutline.traverse((obj) => {
        if (obj instanceof THREE.Line) {
            obj.geometry.dispose();
            (obj.material as THREE.Material).dispose();
        }
    });
    focusOutline = null;
}

/** Tinatanggal ang lahat ng kasalukuyang region mesh/label bago mag-rebuild (hal. paglipat ng map view metric). */
function disposeRegionMeshes() {
    blockMeshes.forEach((mesh) => {
        scene?.remove(mesh);
        mesh.geometry.dispose();
        (mesh.material as THREE.Material).dispose();
    });
    blockMeshes.length = 0;

    Object.values(labelSprites).forEach((sprite) => {
        scene?.remove(sprite);
        (sprite.material as THREE.SpriteMaterial).map?.dispose();
        (sprite.material as THREE.Material).dispose();
    });
    for (const code in labelSprites) delete labelSprites[code];

    Object.values(defaultOutlines).forEach((outline) => {
        scene?.remove(outline);
        outline.traverse((obj) => {
            if (obj instanceof THREE.Line) obj.geometry.dispose();
        });
    });
    Object.values(defaultOutlineMaterials).flat().forEach((mat) => mat.dispose());
    for (const code in defaultOutlines) delete defaultOutlines[code];
    for (const code in defaultOutlineMaterials) delete defaultOutlineMaterials[code];

    hoveredMesh = null;
    hoveredRegionCode = null;
}

/**
 * Binubuo (o binubuo-ulit, kapag lumipat ng map view metric) ang lahat ng
 * region mesh + label sprite — taas/laki ay proporsyonal sa halaga ng
 * kasalukuyang napiling metric (`activeView`), hindi na palaging headcount.
 */
function buildRegionMeshesAndLabels() {
    if (!regionsDataRef || !projectRef || !scene) return;
    disposeRegionMeshes();

    const rankableCodes = [...regionsDataRef.regions.map((r) => r.code), ...Object.keys(PIN_MARKERS)];
    const valueByCode: Record<string, number> = Object.fromEntries(
        rankableCodes.map((code) => [code, metricValueFor(code)])
    );
    const localMax = Math.max(1, ...Object.values(valueByCode));

    const rankByCode: Record<string, number> = Object.fromEntries(
        [...rankableCodes]
            .sort((a, b) => (valueByCode[b] ?? 0) - (valueByCode[a] ?? 0))
            .map((code, i) => [code, i + 1])
    );

    for (const region of regionsDataRef.regions) {
        const value = valueByCode[region.code] ?? 0;
        const ratio = value / localMax;
        const regionHeight = MIN_HEIGHT + ratio * (MAX_HEIGHT - MIN_HEIGHT);
        const color = regionBaseColor(region.code);

        const meshes = buildRegionMesh(region.polygons, projectRef, color, regionHeight);
        meshes.forEach((mesh) => {
            mesh.userData = { region: region.code, label: region.name, total: regionMetricsMap.value[region.code]?.total ?? 0, baseY: mesh.position.y };
            scene!.add(mesh);
            blockMeshes.push(mesh);
        });

        const [cx, cz] = projectRef(region.centroid[0], region.centroid[1]);
        const sprite = makeLabelSprite(region.code, formatMetricValue(value), rankByCode[region.code], activeViewMeta.value.suffix);
        sprite.position.set(cx, regionHeight + 1.5, cz);
        scene!.add(sprite);
        labelSprites[region.code] = sprite;

        const materials: THREE.LineBasicMaterial[] = [];
        const outline = buildRegionOutline(region.code, regionHeight, { opacity: OUTLINE_BASE_OPACITY, collect: materials });
        if (outline) {
            scene!.add(outline);
            defaultOutlines[region.code] = outline;
            defaultOutlineMaterials[region.code] = materials;
        }
    }

    // Espesyal na "pin" markers (CO at NIR) — hindi sila totoong administrative
    // region kaya walang sariling polygon; nakatalsik na marker na lang sa
    // totoong coordinates nila. Fixed/maliit lang ang height nito (hindi
    // sinusukat base sa data) para hindi ito lumitaw na parang tumataas na
    // tore/spike sa mapa.
    for (const [code, pin] of Object.entries(PIN_MARKERS)) {
        const value = valueByCode[code] ?? 0;
        const color = regionBaseColor(code);

        const [x, z] = projectRef(pin.lng, pin.lat);
        const mesh = buildPinMarker(x, z, color, PIN_MARKER_HEIGHT);
        mesh.userData = { region: code, label: pin.label, total: regionMetricsMap.value[code]?.total ?? 0, baseY: mesh.position.y };
        scene!.add(mesh);
        blockMeshes.push(mesh);

        const sprite = makeLabelSprite(code, formatMetricValue(value), rankByCode[code], activeViewMeta.value.suffix);
        sprite.position.set(x, PIN_MARKER_HEIGHT + 1.5, z);
        scene!.add(sprite);
        labelSprites[code] = sprite;

        const materials: THREE.LineBasicMaterial[] = [];
        const outline = buildRegionOutline(code, PIN_MARKER_HEIGHT, { opacity: OUTLINE_BASE_OPACITY, collect: materials });
        if (outline) {
            scene!.add(outline);
            defaultOutlines[code] = outline;
            defaultOutlineMaterials[code] = materials;
        }
    }

    // Kung may naka-focus na region (drawer open), panatilihin ang
    // "naka-hide ang ibang labels" na state kahit lumipat ng map view.
    if (panelRegion.value) {
        Object.entries(labelSprites).forEach(([code, sprite]) => {
            sprite.visible = code === panelRegion.value?.region;
        });
    }
}

function onMapViewChange() {
    buildRegionMeshesAndLabels();
}

async function buildStaticScene(container: HTMLDivElement) {
    const regionsData: RegionsData = await (await fetch('/data/ph-regions.json')).json();
    const project = makeProjector(regionsData.bounds);
    regionsDataRef = regionsData;
    projectRef = project;

    terrainTexture = makeTerrainTexture();

    scene = new THREE.Scene();
    applySceneBackground();

    const width = container.clientWidth;
    const height = container.clientHeight;

    camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 500);
    camera.position.copy(DEFAULT_CAMERA_POSITION);

    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    container.appendChild(renderer.domElement);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.minDistance = 10;
    controls.maxDistance = 200;
    controls.maxPolarAngle = Math.PI / 2.05;
    controls.target.set(0, 0, 0);
    controls.update();

    // Studio-style na lighting — soft ambient/hemisphere fill + isang
    // directional "sun" na may malambot na shadow, para may malinaw na
    // depth/elevation ang extruded regions nang hindi na parang flat na
    // satellite map. Naka-store ang references para ma-adjust ang
    // intensities sa `applySceneBackground()` kapag lumipat ng dark mode.
    ambientLight = new THREE.AmbientLight(0xffffff, 0.88);
    scene.add(ambientLight);
    hemiLight = new THREE.HemisphereLight(0xeaf7ff, 0x3a5d8f, 0.7);
    scene.add(hemiLight);
    sunLight = new THREE.DirectionalLight(0xffffff, 0.6);
    sunLight.position.set(48, 70, 34);
    sunLight.castShadow = true;
    sunLight.shadow.mapSize.set(1536, 1536);
    sunLight.shadow.camera.left = -110;
    sunLight.shadow.camera.right = 110;
    sunLight.shadow.camera.top = 110;
    sunLight.shadow.camera.bottom = -110;
    sunLight.shadow.radius = 3;
    scene.add(sunLight);

    // Soft na teal point light, naka-off/invisible by default — inililipat
    // at pinapaandar lang ito papunta sa isang region kapag na-focus
    // (hover/select), para sa banayad na "glow" na hinihingi ng premium
    // dashboard look, sa halip na mabigat na post-processing bloom.
    pointLight = new THREE.PointLight(0x5eead4, 0, 22, 2);
    pointLight.position.set(0, 6, 0);
    scene.add(pointLight);

    // Ground plane — "unlit" (MeshBasicMaterial, hindi apektado ng scene
    // lighting) para pare-pareho ang kulay nito sa scene background kahit
    // paakyat/patagilid ang anggulo ng camera. Nagbibigay lang ito ng
    // "grounding"/contact feel, hindi ito dinisenyo para magmukhang tubig.
    oceanMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(400, 400),
        new THREE.MeshBasicMaterial({ color: 0xffffff })
    );
    oceanMesh.rotation.x = -Math.PI / 2;
    oceanMesh.position.set(0, -0.3, 0);
    oceanMesh.receiveShadow = true;
    scene.add(oceanMesh);

    // Napaka-subtle na GIS-style grid sa ilalim ng ground plane — halos
    // hindi mapapansin, pero nagbibigay ng bahagyang "data visualization"
    // na pakiramdam sa background nang hindi nagiging sci-fi interface.
    gridHelper = new THREE.GridHelper(360, 36, 0x5eead4, 0x5eead4);
    gridHelper.position.y = -0.32;
    (gridHelper.material as THREE.Material).transparent = true;
    (gridHelper.material as THREE.Material).opacity = 0.06;
    scene.add(gridHelper);

    applySceneBackground();

    buildRegionMeshesAndLabels();

    raycaster = new THREE.Raycaster();

    animate();
}

/** Cinematic camera "fly-to" — tinween ang position/target papunta sa isang region. */
interface CameraFlight {
    fromPos: THREE.Vector3;
    toPos: THREE.Vector3;
    fromTarget: THREE.Vector3;
    toTarget: THREE.Vector3;
    start: number;
    duration: number;
    onComplete?: () => void;
}
let cameraFlight: CameraFlight | null = null;

function easeInOutCubic(t: number): number {
    return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
}

function flyCameraTo(toPos: THREE.Vector3, toTarget: THREE.Vector3, duration = 1100, onComplete?: () => void) {
    if (!camera || !controls) return;
    cameraFlight = {
        fromPos: camera.position.clone(),
        toPos: toPos.clone(),
        fromTarget: controls.target.clone(),
        toTarget: toTarget.clone(),
        start: performance.now(),
        duration,
        onComplete,
    };
}

/** I-focus ang camera sa isang na-click na region mesh, tapos mag-auto-orbit paikot dito. */
function focusOnMesh(mesh: THREE.Mesh) {
    if (!camera || !controls || !scene) return;

    const { region: regionCode } = mesh.userData as { region: string };

    const box = new THREE.Box3().setFromObject(mesh);
    const center = box.getCenter(new THREE.Vector3());
    const size = box.getSize(new THREE.Vector3());
    const horizontalExtent = Math.max(size.x, size.z, 4);
    const distance = Math.max(9, horizontalExtent * 1.4);

    const dir = new THREE.Vector3(0.55, 0.65, 0.55).normalize();
    const toPos = center.clone().add(dir.multiplyScalar(distance));

    // I-tago ang labels ng ibang region para lang ang naka-focus ang makita.
    Object.entries(labelSprites).forEach(([code, sprite]) => {
        sprite.visible = code === regionCode;
    });

    // Palitan ang outline ng buong sakop ng region na ito — mas matingkad
    // na teal/blue "glow" outline (hindi na yellow) para sa selected state.
    clearFocusOutline();
    const outline = buildRegionOutline(regionCode, box.max.y, { color: 0x2dd4bf, opacity: 0.9 });
    if (outline) {
        scene.add(outline);
        focusOutline = outline;
    }

    // Banayad na teal point light sa ibabaw ng na-focus na region.
    if (pointLight) {
        pointLight.position.set(center.x, box.max.y + 5, center.z);
        pointLightTarget = 1.1;
    }

    controls.autoRotate = false;
    flyCameraTo(toPos, center, 1100, () => {
        if (!controls) return;
        controls.autoRotate = true;
        controls.autoRotateSpeed = 1.4;
    });
}

/** Simpleng frame-rate-independent-enough na lerp step (damping factor style). */
function ease(current: number, target: number, speed = EASE_SPEED): number {
    return current + (target - current) * speed;
}

function animate() {
    if (!renderer || !scene || !camera || !controls) return;
    animationId = requestAnimationFrame(animate);

    if (cameraFlight) {
        const elapsed = performance.now() - cameraFlight.start;
        const t = Math.min(1, elapsed / cameraFlight.duration);
        const eased = easeInOutCubic(t);
        camera.position.lerpVectors(cameraFlight.fromPos, cameraFlight.toPos, eased);
        controls.target.lerpVectors(cameraFlight.fromTarget, cameraFlight.toTarget, eased);
        if (t >= 1) {
            const onComplete = cameraFlight.onComplete;
            cameraFlight = null;
            onComplete?.();
        }
    }

    // Smooth (hindi biglaan) na hover/selected elevation + glow — bawat
    // region mesh ay may sariling target base sa kung ito ba ang currently
    // hovered at/o ang currently-selected (drawer open) na region.
    const selectedCode = showPanel.value ? panelRegion.value?.region ?? null : null;
    blockMeshes.forEach((mesh) => {
        const data = mesh.userData as { region: string; baseY?: number };
        const isHovered = data.region === hoveredRegionCode;
        const isSelected = data.region === selectedCode;
        const baseY = data.baseY ?? 0;
        const targetY = baseY + (isSelected ? SELECT_ELEVATE : isHovered ? HOVER_ELEVATE : 0);
        const targetEmissive = isSelected ? SELECT_EMISSIVE : isHovered ? HOVER_EMISSIVE : 0;

        mesh.position.y = ease(mesh.position.y, targetY);
        const material = mesh.material as THREE.MeshStandardMaterial;
        material.emissiveIntensity = ease(material.emissiveIntensity, targetEmissive);
    });

    // Parehong pag-ease para sa default (always-on, subtle) na outline kada
    // region — bahagyang tumitingkad kapag ho-hover.
    Object.entries(defaultOutlineMaterials).forEach(([code, materials]) => {
        const target = code === hoveredRegionCode || code === selectedCode ? OUTLINE_HOVER_OPACITY : OUTLINE_BASE_OPACITY;
        materials.forEach((mat) => {
            mat.opacity = ease(mat.opacity, target);
        });
    });

    if (pointLight) {
        pointLight.intensity = ease(pointLight.intensity, pointLightTarget);
    }

    controls.update();
    renderer.render(scene, camera);
}

function getPointerNDC(event: PointerEvent): THREE.Vector2 {
    const rect = renderer!.domElement.getBoundingClientRect();
    return new THREE.Vector2(
        ((event.clientX - rect.left) / rect.width) * 2 - 1,
        -((event.clientY - rect.top) / rect.height) * 2 + 1
    );
}

function handlePointerMove(event: PointerEvent) {
    if (!raycaster || !camera) return;
    raycaster.setFromCamera(getPointerNDC(event), camera);
    const hits = raycaster.intersectObjects(blockMeshes);

    if (hits.length) {
        const mesh = hits[0].object as THREE.Mesh;
        hoveredMesh = mesh;
        hoveredRegionCode = (mesh.userData as { region: string }).region;
        hoveredRegion.value = mesh.userData as { region: string; label: string; total: number };
        tooltipStyle.value = { left: `${event.clientX}px`, top: `${event.clientY}px` };
        renderer!.domElement.style.cursor = 'pointer';
    } else {
        hoveredMesh = null;
        hoveredRegionCode = null;
        hoveredRegion.value = null;
        renderer!.domElement.style.cursor = 'grab';
    }
}

const hoveredMetric = computed(() => (hoveredRegion.value ? regionMetricsMap.value[hoveredRegion.value.region] : null));

/** CSS gradient + low/high labels para sa floating legend — sumasabay sa data-driven na kulay ng mapa. */
const legendGradient = computed(() => {
    switch (activeView.value) {
        case 'participation':
        case 'completion':
            return { css: 'linear-gradient(90deg, #ff6b6b, #ffb84d, #ffd93d, #6ddc8a, #20c9a6)', low: 'Low', high: 'High' };
        case 'hours':
            return { css: 'linear-gradient(90deg, #ddf6ff, #7ddff2, #36b5e5, #00afa6)', low: 'Fewer hrs', high: 'More hrs' };
        case 'requirements':
            return { css: 'linear-gradient(90deg, #3ddc84, #ffc93d, #ff5c5c)', low: 'Healthy', high: 'Overdue' };
        default:
            return { css: 'linear-gradient(90deg, #d9f3ff, #8dd8f5, #3fa9e8, #1976d2, #00a9a5)', low: 'Fewer employees', high: 'More employees' };
    }
});

async function openRegionPanel(region: string, label: string, total: number) {
    panelRegion.value = { region, label, total };
    showPanel.value = true;
    panelSearch.value = '';
    panelOffice.value = 'all';
    panelOverview.value = null;
    await fetchRegionEmployees();
}

async function fetchRegionEmployees(page = 1) {
    if (!panelRegion.value) return;
    panelLoading.value = true;
    try {
        const { data } = await axios.get(route('employees-map.region'), {
            params: {
                region: panelRegion.value.region,
                search: panelSearch.value || undefined,
                office: panelOffice.value !== 'all' ? panelOffice.value : undefined,
                page,
            },
        });
        panelEmployees.value = data.employees;
        panelOfficeBreakdown.value = data.officeBreakdown;
        panelOverview.value = data.overview;
    } finally {
        panelLoading.value = false;
    }
}

const maxOfficeTotal = computed(() => Math.max(1, ...panelOfficeBreakdown.value.map((o) => o.total)));

function filterByOffice(office: string) {
    panelOffice.value = office;
    onPanelOfficeChange();
}

let searchDebounce: ReturnType<typeof setTimeout>;
function onPanelSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchRegionEmployees(1), 350);
}

function onPanelOfficeChange() {
    fetchRegionEmployees(1);
}

function handleClick(event: PointerEvent) {
    if (!raycaster || !camera) return;
    raycaster.setFromCamera(getPointerNDC(event), camera);
    const hits = raycaster.intersectObjects(blockMeshes);
    if (hits.length) {
        const mesh = hits[0].object as THREE.Mesh;
        const { region, label, total } = mesh.userData as { region: string; label: string; total: number };
        openRegionPanel(region, label, total);
        focusOnMesh(mesh);
    }
}

function viewRegionFromPanelList(region: string) {
    const meta = regionMetricsMap.value[region];
    const label = regionsDataRef?.regions.find((r) => r.code === region)?.name ?? PIN_MARKERS[region]?.label ?? region;
    openRegionPanel(region, label, meta?.total ?? 0);

    const mesh = blockMeshes.find((m) => (m.userData as { region: string }).region === region);
    if (mesh) focusOnMesh(mesh);
}

function closePanel() {
    showPanel.value = false;
    resetCamera();
}

function resetCamera() {
    if (!controls) return;
    controls.autoRotate = false;
    Object.values(labelSprites).forEach((sprite) => {
        sprite.visible = true;
    });
    clearFocusOutline();
    pointLightTarget = 0;
    flyCameraTo(DEFAULT_CAMERA_POSITION, DEFAULT_CAMERA_TARGET, 1000);
}

function handleResize() {
    if (!canvasWrap.value || !renderer || !camera) return;
    const width = canvasWrap.value.clientWidth;
    const height = canvasWrap.value.clientHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
}

const generatedAtLabel = computed(() => {
    try {
        return new Date(props.generatedAt).toLocaleString('en-PH', {
            month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
        });
    } catch {
        return '';
    }
});

onMounted(async () => {
    await Promise.all([loadOfficeOptions(), loadYearOptions()]);

    if (!canvasWrap.value) return;
    await buildStaticScene(canvasWrap.value);
    loading.value = false;

    canvasWrap.value.addEventListener('pointermove', handlePointerMove);
    canvasWrap.value.addEventListener('click', handleClick);
    window.addEventListener('resize', handleResize);

    darkObserver = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
        applySceneBackground();
    });
    darkObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onBeforeUnmount(() => {
    if (animationId) cancelAnimationFrame(animationId);
    window.removeEventListener('resize', handleResize);
    darkObserver?.disconnect();
    if (canvasWrap.value) {
        canvasWrap.value.removeEventListener('pointermove', handlePointerMove);
        canvasWrap.value.removeEventListener('click', handleClick);
    }

    disposeRegionMeshes();
    clearFocusOutline();
    terrainTexture?.dispose();
    oceanMesh?.geometry.dispose();
    (oceanMesh?.material as THREE.Material | undefined)?.dispose();
    gridHelper?.geometry.dispose();
    (gridHelper?.material as THREE.Material | undefined)?.dispose();

    controls?.dispose();
    renderer?.dispose();
    if (renderer && canvasWrap.value?.contains(renderer.domElement)) {
        canvasWrap.value.removeChild(renderer.domElement);
    }
});
</script>

<template>
    <Head title="Employees Map" />

    <AppLayout>
        <div class="flex flex-1 flex-col overflow-y-auto bg-muted/20">

            <!-- ===== Header + KPI cards + Toolbar ===== -->
            <div class="shrink-0 border-b bg-white/80 dark:bg-background/80 backdrop-blur px-4 py-4 flex flex-col gap-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-teal-600 to-blue-600 flex items-center justify-center shadow-sm shrink-0">
                            <Map class="h-5.5 w-5.5 text-white" />
                        </div>
                        <div>
                            <h1 class="text-xl font-extrabold leading-tight">L&amp;D Workforce Intelligence</h1>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                3D distribution of employees and training performance across the Philippines.
                            </p>
                        </div>
                    </div>
                    <p v-if="generatedAtLabel" class="text-[11px] text-muted-foreground shrink-0">
                        Last updated {{ generatedAtLabel }}
                    </p>
                </div>

                <WorkforceKpiCards :kpi="kpi" :loading="filtersLoading" />

                <!-- Toolbar: map view selector + region/office/year filters -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1 rounded-xl border bg-background p-1 shadow-sm overflow-x-auto">
                        <button
                            v-for="view in MAP_VIEWS"
                            :key="view.key"
                            type="button"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors"
                            :class="activeView === view.key
                                ? 'bg-teal-600 text-white shadow-sm'
                                : 'text-muted-foreground hover:bg-muted'"
                            @click="activeView = view.key; onMapViewChange();"
                        >
                            {{ view.label }}
                        </button>
                        <button
                            type="button"
                            disabled
                            title="TNA is not yet aggregated at the regional level."
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap text-muted-foreground/50 cursor-not-allowed flex items-center gap-1"
                        >
                            <Sparkles class="h-3 w-3" /> TNA Priority
                        </button>
                    </div>

                    <div class="flex items-center gap-2 ml-auto flex-wrap">
                        <select v-model="filterRegion" class="rounded-lg border px-2 py-1.5 text-xs bg-background shadow-sm" @change="onRegionFilterChange">
                            <option value="ALL">All Regions</option>
                            <option v-for="r in filterOptions.regions" :key="r" :value="r">{{ r }}</option>
                        </select>
                        <select v-model="filterOffice" class="rounded-lg border px-2 py-1.5 text-xs bg-background shadow-sm max-w-[10rem]" @change="applyFilters">
                            <option value="ALL">All Offices</option>
                            <option v-for="o in officeOptions" :key="o" :value="o">{{ o }}</option>
                        </select>
                        <select v-model="filterYear" class="rounded-lg border px-2 py-1.5 text-xs bg-background shadow-sm" @change="applyFilters">
                            <option value="ALL">All Years</option>
                            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ===== 3D Map ===== -->
            <div class="relative h-[65vh] min-h-[440px] shrink-0 border-b">
                <div ref="canvasWrap" class="absolute inset-0 bg-white dark:bg-[#0b1220] cursor-grab" />

                <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white dark:bg-[#0b1220]">
                    <p class="text-sm text-muted-foreground">Loading 3D map...</p>
                </div>

                <!-- Legend (floating, swaps with the active view) -->
                <div class="absolute top-3 left-3 z-10 rounded-xl border bg-white/90 dark:bg-background/90 backdrop-blur px-3 py-2.5 shadow-sm text-[11px] max-w-[220px]">
                    <p class="font-bold flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-teal-500" /> {{ activeViewMeta.label }}
                    </p>
                    <div class="h-1.5 rounded-full mt-2" :style="{ background: legendGradient.css }" />
                    <div class="flex items-center justify-between text-[10px] text-muted-foreground mt-1">
                        <span>{{ legendGradient.low }}</span>
                        <span>{{ legendGradient.high }}</span>
                    </div>
                    <p class="text-muted-foreground mt-1.5">Taller block = higher {{ activeViewMeta.suffix.toLowerCase() }}. Gray = no data.</p>
                </div>

                <!-- Total Employees -->
                <div class="absolute top-3 right-3 z-10 rounded-xl border bg-white/90 dark:bg-background/90 backdrop-blur px-4 py-2.5 flex items-center gap-2.5 shadow-sm">
                    <Users class="h-4 w-4 text-teal-600" />
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold">Total Employees</p>
                        <p class="text-lg font-extrabold leading-none">{{ totalEmployees }}</p>
                    </div>
                </div>

                <!-- Controls hint -->
                <div class="absolute bottom-3 left-3 z-10 rounded-lg bg-black/60 backdrop-blur px-3 py-2 text-[11px] text-white flex items-center gap-1.5">
                    <MousePointer2 class="h-3 w-3" />
                    Drag to rotate · Scroll to zoom · Right-drag to pan · Click a region to view details
                </div>

                <!-- Reset camera -->
                <button
                    type="button"
                    class="absolute bottom-3 right-3 z-10 inline-flex items-center gap-1.5 text-xs font-semibold bg-white/90 dark:bg-background/90 backdrop-blur px-3 py-1.5 rounded-lg shadow-sm border hover:bg-white dark:hover:bg-background transition-colors"
                    @click="resetCamera"
                >
                    <RotateCcw class="h-3.5 w-3.5" /> Reset View
                </button>

                <!-- Hover tooltip -->
                <div
                    v-if="hoveredRegion"
                    class="fixed z-50 pointer-events-none rounded-xl bg-slate-900/85 backdrop-blur-sm text-white text-xs px-3.5 py-3 shadow-xl border border-white/10 min-w-[190px]"
                    :style="{ left: `calc(${tooltipStyle.left} + 14px)`, top: `calc(${tooltipStyle.top} + 14px)` }"
                >
                    <p class="font-bold text-sm">{{ hoveredRegion.label }}</p>
                    <p class="text-white/70 mb-2">{{ hoveredRegion.total }} personnel</p>
                    <div v-if="hoveredMetric" class="grid grid-cols-2 gap-x-3 gap-y-1 border-t border-white/10 pt-2">
                        <span class="text-white/60">Completion</span>
                        <span class="text-right font-semibold text-teal-300">{{ hoveredMetric.completion_pct }}%</span>
                        <span class="text-white/60">Participation</span>
                        <span class="text-right font-semibold">{{ hoveredMetric.participation_pct }}%</span>
                        <span class="text-white/60">Avg. Hours</span>
                        <span class="text-right font-semibold">{{ hoveredMetric.avg_hours }}</span>
                        <span class="text-white/60">Pending</span>
                        <span class="text-right font-semibold" :class="hoveredMetric.pending > 0 ? 'text-amber-300' : ''">{{ hoveredMetric.pending }}</span>
                    </div>
                    <p class="text-teal-300/90 mt-2 pt-2 border-t border-white/10 text-[11px] font-semibold">View Regional Details →</p>
                </div>
            </div>

            <!-- ===== Below-the-fold panels ===== -->
            <div class="p-4 flex flex-col gap-4">
                <RegionalPerformanceTable :rows="regionalPerformance" @view-region="viewRegionFromPanelList" />

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <LdAttentionPanel :rows="attention" @view-region="viewRegionFromPanelList" />
                    <TrainingCoveragePanel :rows="trainingCoverage" />
                </div>

                <div class="rounded-2xl border bg-white/90 dark:bg-background/90 backdrop-blur shadow-sm p-4 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-violet-100 dark:bg-violet-950/40 flex items-center justify-center shrink-0">
                            <Sparkles class="h-4.5 w-4.5 text-violet-600" />
                        </div>
                        <div>
                            <p class="text-sm font-bold">TNA Priority — Coming Soon</p>
                            <p class="text-xs text-muted-foreground">Regional TNA scoring isn't aggregated yet. View individual recommendations in TNA Summary.</p>
                        </div>
                    </div>
                    <Link :href="route('tna-summary.index')" class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-600 hover:text-violet-700 shrink-0">
                        View TNA Recommendations <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- ===== Region Employees Side Panel / Bottom Sheet =====
             Teleported to <body> (tulad ng EmployeeProgressModal) para hindi
             maapektuhan ng anumang transformed na ancestor sa AppLayout/
             sidebar — kung hindi, magiging "containing block" ng fixed-
             positioned na drawer na ito ang ancestor na iyon sa halip na
             ang viewport, kaya sira ang max-height/scroll behavior nito. -->
        <Teleport to="body">
        <div v-if="showPanel" class="fixed inset-0 z-40 bg-black/30" @click="closePanel" />
        <Transition name="slide">
            <div
                v-if="showPanel"
                class="fixed inset-x-0 bottom-0 z-50 max-h-[85vh] rounded-t-2xl border-t sm:inset-y-0 sm:right-0 sm:left-auto sm:bottom-auto sm:max-h-none sm:w-full sm:max-w-md sm:rounded-t-none sm:border-t-0 sm:border-l bg-background shadow-2xl flex flex-col"
            >
                <div class="sticky top-0 bg-gradient-to-r from-teal-600 to-blue-600 text-white px-5 py-4 flex items-center gap-3 rounded-t-2xl sm:rounded-t-none">
                    <div class="flex-1 min-w-0">
                        <h2 class="font-bold text-sm truncate">{{ panelRegion?.label }}</h2>
                        <p class="text-xs text-white/75">{{ panelRegion?.total }} employee(s)</p>
                    </div>
                    <button class="text-white/80 hover:text-white transition-colors" @click="closePanel">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto">

                    <!-- Regional Training Overview -->
                    <div v-if="panelOverview" class="p-4 border-b grid grid-cols-4 gap-2">
                        <div class="rounded-lg bg-muted/40 p-2 text-center">
                            <p class="text-sm font-extrabold text-emerald-600">{{ panelOverview.completion_pct }}%</p>
                            <p class="text-[9px] uppercase tracking-wide text-muted-foreground font-semibold mt-0.5">Completion</p>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2 text-center">
                            <p class="text-sm font-extrabold text-blue-600">{{ panelOverview.participation_pct }}%</p>
                            <p class="text-[9px] uppercase tracking-wide text-muted-foreground font-semibold mt-0.5">Participation</p>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2 text-center">
                            <p class="text-sm font-extrabold text-amber-600">{{ panelOverview.avg_hours }}</p>
                            <p class="text-[9px] uppercase tracking-wide text-muted-foreground font-semibold mt-0.5">Avg Hours</p>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2 text-center">
                            <p class="text-sm font-extrabold" :class="panelOverview.pending > 0 ? 'text-rose-600' : 'text-muted-foreground'">{{ panelOverview.pending }}</p>
                            <p class="text-[9px] uppercase tracking-wide text-muted-foreground font-semibold mt-0.5">Pending</p>
                        </div>
                    </div>

                    <div v-if="panelOfficeBreakdown.length" class="p-4 border-b bg-muted/30">
                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold mb-2">
                            Breakdown per Office (click to filter)
                        </p>
                        <div class="flex flex-col gap-2 max-h-40 overflow-y-auto pr-1">
                            <button
                                v-for="item in panelOfficeBreakdown"
                                :key="item.office"
                                type="button"
                                class="text-left"
                                :class="panelOffice === item.office ? 'opacity-100' : 'opacity-80 hover:opacity-100'"
                                @click="filterByOffice(item.office)"
                            >
                                <div class="flex items-center justify-between gap-2 text-xs mb-0.5">
                                    <span class="truncate">{{ item.office }}</span>
                                    <span class="font-bold text-teal-600 shrink-0">{{ item.total }}</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-teal-500"
                                        :style="{ width: `${(item.total / maxOfficeTotal) * 100}%` }"
                                    />
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="p-4 border-b flex flex-col gap-2">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <input
                                v-model="panelSearch"
                                type="text"
                                placeholder="Search name or empcode..."
                                class="w-full border rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-background shadow-sm"
                                @input="onPanelSearchInput"
                            />
                        </div>

                        <select
                            v-if="panelOfficeBreakdown.length"
                            v-model="panelOffice"
                            class="w-full border rounded-xl px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-teal-500 shadow-sm"
                            @change="onPanelOfficeChange"
                        >
                            <option value="all">All Offices</option>
                            <option v-for="item in panelOfficeBreakdown" :key="item.office" :value="item.office">
                                {{ item.office }} ({{ item.total }})
                            </option>
                        </select>
                    </div>

                    <div class="p-4">
                        <p v-if="panelLoading" class="text-xs text-muted-foreground text-center py-8">Loading...</p>

                        <div v-else-if="panelEmployees?.data?.length" class="flex flex-col gap-2">
                            <div v-for="emp in panelEmployees.data" :key="emp.id" class="rounded-xl border px-3 py-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-bold text-sm leading-tight truncate">{{ emp.name?.toUpperCase() }}</p>
                                        <p class="text-xs text-muted-foreground truncate">{{ emp.POSITION }}</p>
                                        <p class="text-xs text-muted-foreground truncate">{{ emp['OFFICE/DIVISION'] }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="shrink-0 text-[11px] font-semibold text-teal-600 hover:text-teal-700 whitespace-nowrap"
                                        @click="selectedEmpcode = emp.EMPCODE"
                                    >
                                        View Profile →
                                    </button>
                                </div>

                                <p v-if="emp.programs_attended > 0" class="text-[11px] text-emerald-700 dark:text-emerald-400 font-semibold mt-1.5">
                                    🎓 {{ emp.programs_attended }} program(s) · {{ emp.training_hours }} hrs
                                </p>
                                <p v-else class="text-[11px] text-muted-foreground mt-1.5">
                                    No training data available.
                                </p>
                            </div>

                            <!-- Pagination -->
                            <div v-if="panelEmployees.last_page > 1" class="flex items-center justify-between pt-2 text-xs">
                                <button
                                    type="button"
                                    class="px-2 py-1 rounded border disabled:opacity-40"
                                    :disabled="panelEmployees.current_page <= 1"
                                    @click="fetchRegionEmployees(panelEmployees.current_page - 1)"
                                >
                                    Previous
                                </button>
                                <span class="text-muted-foreground">
                                    Page {{ panelEmployees.current_page }} of {{ panelEmployees.last_page }}
                                </span>
                                <button
                                    type="button"
                                    class="px-2 py-1 rounded border disabled:opacity-40"
                                    :disabled="panelEmployees.current_page >= panelEmployees.last_page"
                                    @click="fetchRegionEmployees(panelEmployees.current_page + 1)"
                                >
                                    Next
                                </button>
                            </div>
                        </div>

                        <p v-else class="text-xs text-muted-foreground text-center py-8">No employees found.</p>
                    </div>
                </div>
            </div>
        </Transition>
        </Teleport>

        <EmployeeProgressModal :empcode="selectedEmpcode" @close="selectedEmpcode = null" />
    </AppLayout>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active { transition: transform 0.25s ease; }
.slide-enter-from,
.slide-leave-to { transform: translateY(100%); }

@media (min-width: 640px) {
    .slide-enter-from,
    .slide-leave-to { transform: translateX(100%); }
}
</style>
