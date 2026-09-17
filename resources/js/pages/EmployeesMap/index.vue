<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Building2, ChevronDown, Info, Map, MapPin, RotateCcw, Search, Users, X } from 'lucide-vue-next';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface RegionCount {
    region: string;
    total: number;
}

const props = defineProps<{
    regionCounts: RegionCount[];
    totalEmployees: number;
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
    CO: { label: 'Central Office (Taguig)', lng: 121.0509, lat: 14.5176 },
    NIR: { label: 'Negros Island Region', lng: 122.9689, lat: 10.6407 },
};

const SCALE = 9; // units per degree
const MAX_HEIGHT = 4; // banayad na relief lang — masyadong matangkad kung malapit sa SCALE, lumalabas na matataas na "cliff wall" ang mga baybayin
const MIN_HEIGHT = 0.4;
const PIN_RADIUS = 0.9;
const PIN_MARKER_HEIGHT = 2.2;
const ISLET_AREA_THRESHOLD = 1.5; // shape-space sq. units (~SCALE=9/degree)
const ISLET_HEIGHT = 0.4;

// Default/"reset" na camera position — halos diretsong nakatingin pababa
// (top-down/"naka-tapat" sa buong mapa) sa halip na malaki ang tilt/anggulo
// (na parang "nakahiga" ang mapa). Bahagyang Z offset lang para may
// kaunting pakiramdam ng depth/relief, hindi ito literal na 90° top-down.
const DEFAULT_CAMERA_POSITION = new THREE.Vector3(0, 108, 18);
const DEFAULT_CAMERA_TARGET = new THREE.Vector3(0, 0, 0);

const maxCount = Math.max(1, ...props.regionCounts.map((r) => r.total));

/**
 * Coordinated, data-driven na institutional palette — 3-stop na blue →
 * cyan/teal → restrained emerald na scale batay mismo sa `ratio` (parehong
 * value na ginagamit na rin sa extrusion height), sa halip na hiwalay/
 * cycling na kulay kada rehiyon. Iisang magkakaugnay na "cool" hue journey
 * lang (hindi random/rainbow), papadilim/papasaturate habang tumataas ang
 * bilang ng empleyado — mas kaunti ang "visual noise" at mas informative pa
 * (kulay = bilang, hindi arbitrary). Naka-expose bilang plain hex strings
 * (hindi lang THREE.Color) para magamit din ito sa legend sa template.
 */
const SCALE_LOW_HEX = '#bfdbfe';
const SCALE_MID_HEX = '#22d3ee';
const SCALE_HIGH_HEX = '#047857';
const SCALE_LOW = new THREE.Color(SCALE_LOW_HEX);
const SCALE_MID = new THREE.Color(SCALE_MID_HEX);
const SCALE_HIGH = new THREE.Color(SCALE_HIGH_HEX);

/** Restrained gold — para lang sa Central Office marker (natatangi/"seat of power", hiwalay sa regular data scale). */
const CO_MARKER_HEX = '#e67700';

/** Gold accent — selected-region outline at emissive contrast boost. */
const SELECTED_HEX = '#f0b429';

/** Banayad na institutional blue-gray na scene/ocean background — pareho ng ginagamit na light section background sa ibang parte ng app. */
const SCENE_BACKGROUND_HEX = '#eef2fb';

/** Ibinabalik ang blue→cyan→emerald na kulay batay sa `ratio` (0..1, bilang ng empleyado / pinakamataas). */
const dataColor = (ratio: number): THREE.Color => {
    const r = Math.min(1, Math.max(0, ratio));
    const color = new THREE.Color();
    return r < 0.5 ? color.copy(SCALE_LOW).lerp(SCALE_MID, r / 0.5) : color.copy(SCALE_MID).lerp(SCALE_HIGH, (r - 0.5) / 0.5);
};

const canvasWrap = ref<HTMLDivElement | null>(null);
const loading = ref(true);
const hoveredRegion = ref<{ region: string; label: string; total: number } | null>(null);
const tooltipStyle = ref({ left: '0px', top: '0px' });
const showLegend = ref(true);

const showPanel = ref(false);
const panelLoading = ref(false);
const panelRegion = ref<{ region: string; label: string; total: number } | null>(null);
const panelSearch = ref('');
const panelOffice = ref('all');
const panelEmployees = ref<any>(null);
const panelOfficeBreakdown = ref<{ office: string; total: number }[]>([]);

// Lightweight na summary — derived mula sa datos na fetched na (office
// breakdown), walang karagdagang API call.
const officeCount = computed(() => panelOfficeBreakdown.value.length);
const largestOffice = computed(() =>
    panelOfficeBreakdown.value.length ? panelOfficeBreakdown.value.reduce((max, o) => (o.total > max.total ? o : max)) : null,
);
const maxOfficeTotal = computed(() => Math.max(1, ...panelOfficeBreakdown.value.map((o) => o.total)));

/** Initials mula sa employee name — para sa lightweight na avatar circle, walang profile image. */
function initialsOf(name: string | null | undefined): string {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase() || '?';
}

let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let controls: OrbitControls | null = null;
let raycaster: THREE.Raycaster | null = null;
let animationId: number | null = null;
let terrainTexture: THREE.Texture | null = null;
const blockMeshes: THREE.Mesh[] = [];
let hoveredMesh: THREE.Mesh | null = null;
let selectedMesh: THREE.Mesh | null = null;
let regionsDataRef: RegionsData | null = null;
let projectRef: ((lng: number, lat: number) => [number, number]) | null = null;
let focusOutline: THREE.Object3D | null = null;
const labelSprites: Record<string, THREE.Sprite> = {};

const TERRAIN_TILE_SIZE = 3; // world units kada tile ng texture

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
    ctx.fillStyle = '#dcdcdc';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    for (let i = 0; i < 1400; i++) {
        const x = Math.random() * canvas.width;
        const y = Math.random() * canvas.height;
        const shade = 165 + Math.random() * 80;
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
    const n = Math.sin(x * 0.15) * Math.cos(y * 0.13) * 0.5 + Math.sin(x * 0.37 + y * 0.29) * 0.3 + Math.sin((x + y) * 0.07) * 0.2;
    return (n + 1) / 2;
}

/**
 * Nagbibigay ng per-vertex na kulay sa isang region geometry: ang itaas
 * (top face) ay dominant na sa data-driven na blue-scale na kulay ng
 * rehiyon (may kaunting noise variation pa rin para hindi flat/patag ang
 * peke-terrain look), at ang mga tagiliran (cliff/side) ay mas madilim na
 * bersyon ng parehong kulay — banayad na 3D relief, hindi hiwalay na
 * kategoryang kulay kada rehiyon.
 */
function colorizeRegionGeometry(geometry: THREE.BufferGeometry, dataColor: THREE.Color) {
    geometry.computeVertexNormals();
    const pos = geometry.attributes.position;
    const norm = geometry.attributes.normal;
    const colorArr = new Float32Array(pos.count * 3);
    const uvArr = new Float32Array(pos.count * 2);

    const white = new THREE.Color(0xffffff);
    const topLow = new THREE.Color().copy(dataColor).multiplyScalar(0.94);
    const topHigh = new THREE.Color().copy(dataColor).lerp(white, 0.18);
    const tmp = new THREE.Color();

    for (let i = 0; i < pos.count; i++) {
        const nz = norm.getZ(i);

        if (nz > 0.5) {
            const n = terrainNoise(pos.getX(i), pos.getY(i));
            tmp.copy(topLow).lerp(topHigh, n);
        } else if (nz < -0.5) {
            tmp.copy(dataColor).multiplyScalar(0.75);
        } else {
            tmp.copy(dataColor).multiplyScalar(0.85);
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

/** Rounded-rect helper para sa canvas-drawn na label chip (walang built-in `roundRect` sa lahat ng environment). */
function drawRoundedRect(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

/**
 * Hinahati ang pattern na "Region III (Central Luzon)" → primary "Region
 * III" + secondary "Central Luzon". Kung walang parenthetical part sa
 * datos (hal. ilang PIN_MARKERS label), isang linya na lang ang label
 * (null ang secondary) — hindi ito nag-iimbento ng bagong text.
 */
function splitRegionName(fullName: string): { primary: string; secondary: string | null } {
    const match = fullName.match(/^(.*?)\s*\(([^)]+)\)\s*$/);
    return match ? { primary: match[1].trim(), secondary: match[2].trim() } : { primary: fullName, secondary: null };
}

/** Pina-shrink ang font size (hanggang sa `minPx`) hanggang bumagay ang `text` sa loob ng `maxWidth`. */
function fitFont(ctx: CanvasRenderingContext2D, text: string, maxWidth: number, startPx: number, minPx: number): number {
    let size = startPx;
    ctx.font = `bold ${size}px Arial`;
    while (size > minPx && ctx.measureText(text).width > maxWidth) {
        size -= 2;
        ctx.font = `bold ${size}px Arial`;
    }
    return size;
}

/**
 * Institutional na label chip — malinis na puting "card" (parang mapa-pin
 * ng isang professional analytics dashboard) sa halip na bold/black-outline
 * na "tactical map" na callout. Dalawang linya (primary + secondary na
 * pangalan ng region) kapag available, tapos ang bilang ng empleyado. May
 * maliit na ranking badge (top-right corner) na nagpapakita ng puwesto ng
 * region batay sa bilang ng empleyado.
 */
function makeLabelSprite(primary: string, secondary: string | null, sub: string, rank: number): THREE.Sprite {
    const canvas = document.createElement('canvas');
    canvas.width = 520;
    canvas.height = secondary ? 190 : 160;
    const ctx = canvas.getContext('2d')!;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.textAlign = 'center';

    // Card background — puti, manipis na border, banayad na drop shadow.
    const chipX = 30;
    const chipY = 20;
    const chipW = canvas.width - 60;
    const chipH = canvas.height - 40;
    const innerWidth = chipW - 36;
    ctx.save();
    ctx.shadowColor = 'rgba(15, 28, 72, 0.28)';
    ctx.shadowBlur = 14;
    ctx.shadowOffsetY = 4;
    ctx.fillStyle = 'rgba(255,255,255,0.96)';
    drawRoundedRect(ctx, chipX, chipY, chipW, chipH, 22);
    ctx.fill();
    ctx.restore();
    ctx.lineWidth = 2;
    ctx.strokeStyle = 'rgba(29, 63, 196, 0.25)';
    drawRoundedRect(ctx, chipX, chipY, chipW, chipH, 22);
    ctx.stroke();

    let cursorY = chipY + 44;

    const title = primary.toUpperCase();
    fitFont(ctx, title, innerWidth, 38, 20);
    ctx.fillStyle = '#1a2744';
    ctx.fillText(title, canvas.width / 2, cursorY);

    if (secondary) {
        cursorY += 30;
        fitFont(ctx, secondary, innerWidth, 22, 13);
        ctx.fillStyle = '#5b6b8c';
        ctx.fillText(secondary, canvas.width / 2, cursorY);
    }

    cursorY += 34;
    ctx.font = 'bold 22px Arial';
    ctx.fillStyle = '#0d6f6f';
    ctx.fillText(`${sub} EMPLOYEES`, canvas.width / 2, cursorY);

    // Ranking badge (top-right corner) — #1/#2/#3 ay may medal color, ang iba ay neutral gray.
    const badgeX = chipX + chipW - 8;
    const badgeY = chipY + 4;
    ctx.beginPath();
    ctx.arc(badgeX, badgeY, 22, 0, Math.PI * 2);
    ctx.fillStyle = RANK_BADGE_COLORS[rank] ?? '#cbd5e1';
    ctx.fill();
    ctx.lineWidth = 2;
    ctx.strokeStyle = 'rgba(255,255,255,0.9)';
    ctx.stroke();

    ctx.textBaseline = 'middle';
    ctx.font = 'bold 18px Arial';
    ctx.fillStyle = '#1a2744';
    ctx.fillText(`#${rank}`, badgeX, badgeY + 1);
    ctx.textBaseline = 'alphabetic';

    const texture = new THREE.CanvasTexture(canvas);
    texture.minFilter = THREE.LinearFilter;
    const material = new THREE.SpriteMaterial({ map: texture, depthTest: false, transparent: true });
    const sprite = new THREE.Sprite(material);
    sprite.scale.set(secondary ? 8.6 : 8, secondary ? 3.15 : 2.5, 1);
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

    return (lng: number, lat: number): [number, number] => [(lng - centerLng) * cosLat * SCALE, (centerLat - lat) * SCALE];
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

function extrudeShapes(shapes: THREE.Shape[], depth: number, color: THREE.Color): THREE.Mesh {
    const geometry = new THREE.ExtrudeGeometry(shapes, { depth, bevelEnabled: false });
    colorizeRegionGeometry(geometry, color);
    const material = new THREE.MeshStandardMaterial({
        vertexColors: true,
        map: terrainTexture,
        roughness: 0.9,
        metalness: 0.02,
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
 * height ng lahat (base sa bilang ng empleyado), ang mga makikitid na islet
 * ay lumalabas na parang matataas na "spike" sa halip na patag na
 * maliliit na isla. Kaya hiwalay ang extrusion: buong height (data-driven)
 * lang para sa malalaking lupain, maliit/fixed na height para sa islet.
 */
function buildRegionMesh(
    polygons: Polygon[],
    project: (lng: number, lat: number) => [number, number],
    color: THREE.Color,
    height: number,
): THREE.Mesh[] {
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
    if (mainShapes.length) meshes.push(extrudeShapes(mainShapes, height, color));
    if (isletShapes.length) meshes.push(extrudeShapes(isletShapes, ISLET_HEIGHT, color));
    return meshes;
}

function buildPinMarker(x: number, z: number, color: THREE.Color, height: number): THREE.Mesh {
    const geometry = new THREE.CylinderGeometry(PIN_RADIUS * 0.4, PIN_RADIUS, height, 8);
    const material = new THREE.MeshStandardMaterial({ color, roughness: 0.7, metalness: 0.15 });
    const mesh = new THREE.Mesh(geometry, material);
    mesh.position.set(x, height / 2, z);
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
}

/**
 * Outline sa paligid ng buong sakop ng isang region (bawat polygon ring nito),
 * o simpleng bilog na outline para sa mga pin marker na walang totoong
 * polygon (CO/NIR) — ginagamit para i-highlight ang currently-focused region.
 */
function buildRegionOutline(regionCode: string, topY: number): THREE.Object3D | null {
    if (!projectRef) return null;
    const OUTLINE_Y_OFFSET = 0.05;
    const material = new THREE.LineBasicMaterial({ color: SELECTED_HEX, depthTest: false });

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

async function buildScene(container: HTMLDivElement) {
    const regionsData: RegionsData = await (await fetch('/data/ph-regions.json')).json();
    const project = makeProjector(regionsData.bounds);
    regionsDataRef = regionsData;
    projectRef = project;

    terrainTexture = makeTerrainTexture();

    scene = new THREE.Scene();
    // Banayad na institutional blue-gray (hindi stark white) — pareho ng
    // kulay ng ibang light section sa app, para "coordinated" pakiramdam
    // ang buong analytics module, hindi flat/generic na puti.
    scene.background = new THREE.Color(SCENE_BACKGROUND_HEX);

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
    controls.target.copy(DEFAULT_CAMERA_TARGET);
    controls.update();

    // Lighting — flat/even na map-style lighting (mataas ang ambient/fill,
    // banayad lang ang shadow) sa halip na dramatic na "game" lighting, para
    // mas parang totoong satellite/terrain map view (hal. Google Maps).
    scene.add(new THREE.AmbientLight(0xffffff, 0.85));
    scene.add(new THREE.HemisphereLight(0xffffff, 0x4a3c28, 0.5));
    const sun = new THREE.DirectionalLight(0xffffff, 0.55);
    sun.position.set(60, 90, 40);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.camera.left = -120;
    sun.shadow.camera.right = 120;
    sun.shadow.camera.top = 120;
    sun.shadow.camera.bottom = -120;
    scene.add(sun);

    // Ocean — pinagawang "unlit" (MeshBasicMaterial, hindi apektado ng scene
    // lighting) sa halip na MeshStandardMaterial. Dahil paakyat/patagilid
    // ang anggulo ng camera, ang ocean plane na ito ang sumasakop sa halos
    // buong background na nakikita — kaya ito rin ang tugma sa parehong
    // banayad na institutional blue-gray tint ng `scene.background`.
    const ocean = new THREE.Mesh(new THREE.PlaneGeometry(400, 400), new THREE.MeshBasicMaterial({ color: SCENE_BACKGROUND_HEX }));
    ocean.rotation.x = -Math.PI / 2;
    ocean.position.set(0, -0.3, 0);
    scene.add(ocean);

    // Region landmasses — extruded sa taas na proporsyonal sa bilang ng empleyado
    const countsByRegion = Object.fromEntries(props.regionCounts.map((r) => [r.region, r.total]));

    // Ranggo ng bawat region (1 = pinakamaraming empleyado) — kasama ang mga
    // pin marker (CO/NIR) sa parehong ranggo dahil parte rin sila ng REGION
    // counts.
    const rankableCodes = [...regionsData.regions.map((r) => r.code), ...Object.keys(PIN_MARKERS)];
    const rankByCode: Record<string, number> = Object.fromEntries(
        [...rankableCodes].sort((a, b) => (countsByRegion[b] ?? 0) - (countsByRegion[a] ?? 0)).map((code, i) => [code, i + 1]),
    );

    for (const region of regionsData.regions) {
        const total = countsByRegion[region.code] ?? 0;
        const ratio = total / maxCount;
        const regionHeight = MIN_HEIGHT + ratio * (MAX_HEIGHT - MIN_HEIGHT);
        const color = dataColor(ratio);

        const meshes = buildRegionMesh(region.polygons, project, color, regionHeight);
        meshes.forEach((mesh) => {
            mesh.userData = { region: region.code, label: region.name, total };
            scene.add(mesh);
            blockMeshes.push(mesh);
        });

        const [cx, cz] = project(region.centroid[0], region.centroid[1]);
        const { primary, secondary } = splitRegionName(region.name);
        const sprite = makeLabelSprite(primary, secondary, String(total), rankByCode[region.code]);
        sprite.position.set(cx, regionHeight + 1.5, cz);
        scene.add(sprite);
        labelSprites[region.code] = sprite;
    }

    // Espesyal na "pin" markers (CO at NIR) — hindi sila totoong administrative
    // region kaya walang sariling polygon; nakatalsik na marker na lang sa
    // totoong coordinates nila. Fixed/maliit lang ang height nito (hindi
    // sinusukat base sa bilang ng empleyado) para hindi ito lumitaw na
    // parang tumataas na tore/spike sa mapa.
    for (const [code, pin] of Object.entries(PIN_MARKERS)) {
        const total = countsByRegion[code] ?? 0;
        // CO (Central Office/"seat of power") ay may natatanging restrained
        // gold marker — hiwalay sa regular data scale; NIR gamit pa rin ang
        // normal na scale, kaya coherent pa rin ang buong visualization.
        const color = code === 'CO' ? new THREE.Color(CO_MARKER_HEX) : dataColor(total / maxCount);

        const [x, z] = project(pin.lng, pin.lat);
        const mesh = buildPinMarker(x, z, color, PIN_MARKER_HEIGHT);
        mesh.userData = { region: code, label: pin.label, total };
        scene.add(mesh);
        blockMeshes.push(mesh);

        const { primary, secondary } = splitRegionName(pin.label);
        const sprite = makeLabelSprite(primary, secondary, String(total), rankByCode[code]);
        sprite.position.set(x, PIN_MARKER_HEIGHT + 1.5, z);
        scene.add(sprite);
        labelSprites[code] = sprite;
    }

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

    // Palitan ang outline ng buong sakop ng region na ito.
    clearFocusOutline();
    const outline = buildRegionOutline(regionCode, box.max.y);
    if (outline) {
        scene.add(outline);
        focusOutline = outline;
    }

    controls.autoRotate = false;
    flyCameraTo(toPos, center, 1100, () => {
        if (!controls) return;
        controls.autoRotate = true;
        controls.autoRotateSpeed = 1.4;
    });
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

    controls.update();
    renderer.render(scene, camera);
}

function getPointerNDC(event: PointerEvent): THREE.Vector2 {
    const rect = renderer!.domElement.getBoundingClientRect();
    return new THREE.Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
}

// Subtle na "contrast boost" glow para sa currently-SELECTED na region (hindi
// hover) — hiwalay ito sa gold OUTLINE (na siyang pangunahing "selected"
// indicator); ito ay banayad lang na pag-liwanag ng mismong fill kada mesh.
const SELECTED_EMISSIVE_HEX = 0x1e293b;

/** Ibinabalik ang tamang "resting" (di-naka-hover) na emissive kulay ng isang mesh — may glow pa rin kung ito ang currently-selected na region. */
function restingEmissiveHex(mesh: THREE.Mesh): number {
    return mesh === selectedMesh ? SELECTED_EMISSIVE_HEX : 0x000000;
}

/** Itinatakda (o kinakalimutan, kung `null`) ang currently-selected na region mesh, kasama ang subtle emissive glow nito. */
function setSelectedMesh(mesh: THREE.Mesh | null) {
    if (selectedMesh && selectedMesh !== mesh && selectedMesh !== hoveredMesh) {
        (selectedMesh.material as THREE.MeshStandardMaterial).emissive.setHex(0x000000);
    }
    selectedMesh = mesh;
    if (mesh && mesh !== hoveredMesh) {
        (mesh.material as THREE.MeshStandardMaterial).emissive.setHex(SELECTED_EMISSIVE_HEX);
    }
}

function handlePointerMove(event: PointerEvent) {
    if (!raycaster || !camera) return;
    raycaster.setFromCamera(getPointerNDC(event), camera);
    const hits = raycaster.intersectObjects(blockMeshes);

    if (hits.length) {
        const mesh = hits[0].object as THREE.Mesh;
        if (hoveredMesh !== mesh) {
            if (hoveredMesh) (hoveredMesh.material as THREE.MeshStandardMaterial).emissive.setHex(restingEmissiveHex(hoveredMesh));
            hoveredMesh = mesh;
            (mesh.material as THREE.MeshStandardMaterial).emissive.setHex(0x333333);
        }
        hoveredRegion.value = mesh.userData as { region: string; label: string; total: number };
        tooltipStyle.value = { left: `${event.clientX}px`, top: `${event.clientY}px` };
        renderer!.domElement.style.cursor = 'pointer';
    } else {
        if (hoveredMesh) {
            (hoveredMesh.material as THREE.MeshStandardMaterial).emissive.setHex(restingEmissiveHex(hoveredMesh));
            hoveredMesh = null;
        }
        hoveredRegion.value = null;
        renderer!.domElement.style.cursor = 'grab';
    }
}

async function openRegionPanel(region: string, label: string, total: number) {
    panelRegion.value = { region, label, total };
    showPanel.value = true;
    panelSearch.value = '';
    panelOffice.value = 'all';
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
    } finally {
        panelLoading.value = false;
    }
}

let searchDebounce: ReturnType<typeof setTimeout>;
function onPanelSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchRegionEmployees(1), 300);
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
        setSelectedMesh(mesh);
    }
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
    setSelectedMesh(null);
    flyCameraTo(new THREE.Vector3(35, 60, 90), DEFAULT_CAMERA_TARGET, 1000);
}

function handleResize() {
    if (!canvasWrap.value || !renderer || !camera) return;
    const width = canvasWrap.value.clientWidth;
    const height = canvasWrap.value.clientHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
}

onMounted(async () => {
    if (!canvasWrap.value) return;
    await buildScene(canvasWrap.value);
    loading.value = false;

    canvasWrap.value.addEventListener('pointermove', handlePointerMove);
    canvasWrap.value.addEventListener('click', handleClick);
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    if (animationId) cancelAnimationFrame(animationId);
    window.removeEventListener('resize', handleResize);
    if (canvasWrap.value) {
        canvasWrap.value.removeEventListener('pointermove', handlePointerMove);
        canvasWrap.value.removeEventListener('click', handleClick);
    }

    // Buong cleanup ng GPU resources (geometries/materials/textures) — isang
    // beses lang ito tumatakbo sa unmount, hindi per-frame/per-interaction,
    // kaya walang epekto sa runtime performance ng map habang ginagamit ito.
    clearFocusOutline();
    blockMeshes.forEach((mesh) => {
        mesh.geometry.dispose();
        (mesh.material as THREE.Material).dispose();
    });
    blockMeshes.length = 0;
    Object.values(labelSprites).forEach((sprite) => {
        sprite.material.map?.dispose();
        sprite.material.dispose();
    });
    terrainTexture?.dispose();

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
        <div class="relative flex flex-1 flex-col overflow-hidden">
            <div ref="canvasWrap" class="absolute inset-0 cursor-grab bg-blue-50" />

            <div v-if="loading" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-blue-50 dark:bg-background">
                <div class="map-spinner" aria-hidden="true"></div>
                <div class="text-center">
                    <p class="text-sm font-bold text-foreground">Preparing Employee Map</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Loading regional employee data...</p>
                </div>
            </div>

            <!-- Header + summary metrics (HUD overlay sa loob ng canvas) -->
            <div class="absolute left-3 right-3 top-3 z-10 flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-3 rounded-2xl border bg-white/90 px-4 py-3 shadow-sm backdrop-blur dark:bg-background/90">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-700 to-blue-900 shadow-sm"
                    >
                        <Map class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">
                            Institutional Personnel Analytics
                        </p>
                        <h1 class="text-base font-extrabold leading-tight text-foreground">Employees Map</h1>
                        <p class="mt-0.5 max-w-xs text-xs text-muted-foreground">
                            Explore the distribution of TESDA personnel across regions and offices.
                        </p>
                    </div>
                </div>

                <!-- Compact summary — Total Employees + Regions lang, parehong galing sa existing props (walang bagong API call). -->
                <div class="flex items-stretch overflow-hidden rounded-xl border bg-white/90 shadow-sm backdrop-blur dark:bg-background/90">
                    <div class="w-1 shrink-0 bg-amber-500" aria-hidden="true"></div>
                    <div class="flex items-center divide-x">
                        <div class="flex items-center gap-2 py-2.5 pl-3 pr-4">
                            <Users class="h-4 w-4 text-blue-700 dark:text-blue-400" />
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Total Employees</p>
                                <p class="text-lg font-extrabold leading-none">{{ totalEmployees }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 py-2.5 pl-4 pr-4">
                            <MapPin class="h-4 w-4 text-blue-700 dark:text-blue-400" />
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Regions</p>
                                <p class="text-lg font-extrabold leading-none">{{ regionCounts.length }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend / controls (compact, collapsible) -->
            <div
                class="absolute bottom-3 left-3 z-10 w-64 max-w-[75vw] overflow-hidden rounded-xl border bg-white/95 shadow-sm backdrop-blur dark:bg-background/95"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left"
                    :aria-expanded="showLegend"
                    aria-controls="map-legend-body"
                    @click="showLegend = !showLegend"
                >
                    <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-blue-700 dark:text-blue-400">
                        <Info class="h-3.5 w-3.5" /> Employee Distribution
                    </span>
                    <ChevronDown class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform" :class="{ '-rotate-180': !showLegend }" />
                </button>

                <div v-if="showLegend" id="map-legend-body" class="space-y-2 border-t px-3 py-2.5 text-[11px] text-muted-foreground">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="flex items-center gap-1"><span class="legend-dot" :style="{ background: SCALE_LOW_HEX }"></span> Low</span>
                        <span class="flex items-center gap-1"><span class="legend-dot" :style="{ background: SCALE_MID_HEX }"></span> Medium</span>
                        <span class="flex items-center gap-1"><span class="legend-dot" :style="{ background: SCALE_HIGH_HEX }"></span> High</span>
                        <span class="flex items-center gap-1"
                            ><span class="legend-dot" :style="{ background: SELECTED_HEX }"></span> Selected Region</span
                        >
                    </div>
                    <p>Higher region elevation represents a higher number of employees.</p>
                    <p>
                        <strong class="text-foreground">Hover</strong> — Preview &nbsp;·&nbsp; <strong class="text-foreground">Click</strong> — View
                        employees
                    </p>
                    <p><strong class="text-foreground">Drag</strong> — Rotate &nbsp;·&nbsp; <strong class="text-foreground">Scroll</strong> — Zoom</p>
                </div>
            </div>

            <!-- Reset camera -->
            <button
                type="button"
                aria-label="Reset map view"
                class="absolute bottom-3 right-3 z-10 inline-flex items-center gap-1.5 rounded-lg border bg-white/90 px-3 py-1.5 text-xs font-semibold shadow-sm backdrop-blur transition-colors hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600 dark:bg-background/90 dark:hover:bg-background"
                @click="resetCamera"
            >
                <RotateCcw class="h-3.5 w-3.5" /> Reset View
            </button>

            <!-- Hover tooltip -->
            <div
                v-if="hoveredRegion"
                class="pointer-events-none fixed z-50 rounded-lg bg-black/80 px-3 py-2 text-xs text-white shadow-lg"
                :style="{ left: `calc(${tooltipStyle.left} + 14px)`, top: `calc(${tooltipStyle.top} + 14px)` }"
            >
                <p class="font-bold">{{ hoveredRegion.label }}</p>
                <p class="text-white/80">{{ hoveredRegion.total }} employees</p>
            </div>
        </div>

        <!-- ===== Region Employees Side Panel ===== -->
        <Transition name="slide">
            <div v-if="showPanel" class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l bg-background shadow-2xl">
                <div class="sticky top-0 flex items-center gap-3 bg-gradient-to-r from-blue-700 to-blue-900 px-5 py-4 text-white">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-blue-200">Region Profile</p>
                        <h2 class="truncate text-sm font-bold">{{ panelRegion?.label }}</h2>
                        <p class="text-xs text-white/75">{{ panelRegion?.total }} employees</p>
                    </div>
                    <button class="text-white/80 transition-colors hover:text-white" aria-label="Close panel" @click="closePanel">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Regional summary — derived from data already fetched, walang karagdagang API call -->
                <div v-if="officeCount" class="grid grid-cols-3 gap-2 border-b bg-muted/20 px-4 py-3 text-center">
                    <div>
                        <p class="text-sm font-extrabold leading-none">{{ panelRegion?.total }}</p>
                        <p class="mt-1 text-[9px] font-semibold uppercase tracking-wide text-muted-foreground">Employees</p>
                    </div>
                    <div>
                        <p class="text-sm font-extrabold leading-none">{{ officeCount }}</p>
                        <p class="mt-1 text-[9px] font-semibold uppercase tracking-wide text-muted-foreground">Offices</p>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-extrabold leading-none" :title="largestOffice?.office">{{ largestOffice?.office }}</p>
                        <p class="mt-1 text-[9px] font-semibold uppercase tracking-wide text-muted-foreground">Largest Office</p>
                    </div>
                </div>

                <!-- Office Distribution — lightweight CSS bars, walang chart library -->
                <div v-if="panelOfficeBreakdown.length" class="border-b p-4">
                    <p class="mb-2.5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">
                        <Building2 class="h-3 w-3" /> Office Distribution
                    </p>
                    <div class="flex max-h-40 flex-col gap-2 overflow-y-auto pr-1">
                        <div v-for="item in panelOfficeBreakdown" :key="item.office" class="text-xs">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="truncate">{{ item.office }}</span>
                                <span class="shrink-0 font-bold text-blue-700 dark:text-blue-400">{{ item.total }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-blue-700 dark:bg-blue-500"
                                    :style="{ width: `${(item.total / maxOfficeTotal) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 border-b p-4">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="panelSearch"
                            type="text"
                            placeholder="Search name or employee code..."
                            aria-label="Search employees by name or employee code"
                            class="w-full rounded-xl border bg-background py-2 pl-9 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600"
                            @input="onPanelSearchInput"
                        />
                    </div>

                    <select
                        v-if="panelOfficeBreakdown.length"
                        v-model="panelOffice"
                        aria-label="Filter by office or division"
                        class="w-full rounded-xl border bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600"
                        @change="onPanelOfficeChange"
                    >
                        <option value="all">All Offices</option>
                        <option v-for="item in panelOfficeBreakdown" :key="item.office" :value="item.office">
                            {{ item.office }} ({{ item.total }})
                        </option>
                    </select>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <p v-if="panelLoading" class="py-8 text-center text-xs text-muted-foreground">Loading...</p>

                    <div v-else-if="panelEmployees?.data?.length" class="flex flex-col gap-2">
                        <div
                            v-for="emp in panelEmployees.data"
                            :key="emp.id"
                            class="flex items-center gap-3 rounded-xl border px-3 py-2.5 transition-colors hover:bg-muted/40"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                                aria-hidden="true"
                            >
                                {{ initialsOf(emp.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold leading-tight">{{ emp.name?.toUpperCase() }}</p>
                                <p v-if="emp.POSITION" class="truncate text-xs text-muted-foreground">{{ emp.POSITION }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ emp['OFFICE/DIVISION'] }}</p>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="panelEmployees.last_page > 1" class="flex items-center justify-between pt-2 text-xs">
                            <button
                                type="button"
                                class="rounded border px-2 py-1 disabled:opacity-40"
                                :disabled="panelEmployees.current_page <= 1"
                                @click="fetchRegionEmployees(panelEmployees.current_page - 1)"
                            >
                                Previous
                            </button>
                            <span class="text-muted-foreground"> Page {{ panelEmployees.current_page }} of {{ panelEmployees.last_page }} </span>
                            <button
                                type="button"
                                class="rounded border px-2 py-1 disabled:opacity-40"
                                :disabled="panelEmployees.current_page >= panelEmployees.last_page"
                                @click="fetchRegionEmployees(panelEmployees.current_page + 1)"
                            >
                                Next
                            </button>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center gap-1 py-10 text-center">
                        <p class="text-sm font-semibold text-foreground">No employees found</p>
                        <p class="text-xs text-muted-foreground">Try adjusting your search or office filter.</p>
                    </div>
                </div>
            </div>
        </Transition>
        <div v-if="showPanel" class="fixed inset-0 z-40 bg-black/30" @click="closePanel" />
    </AppLayout>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.25s ease;
}
.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

.map-spinner {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 3px solid rgba(29, 63, 196, 0.15);
    border-top-color: #1d4ed8;
    animation: map-spin 0.8s linear infinite;
}

.legend-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
@keyframes map-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .slide-enter-active,
    .slide-leave-active {
        transition: none;
    }
    .map-spinner {
        animation-duration: 2s;
    }
}
</style>
