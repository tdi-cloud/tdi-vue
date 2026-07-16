<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import {
    Map, X, Search, Users, RotateCcw, MousePointer2,
} from 'lucide-vue-next';

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
    CO:  { label: 'Central Office (Taguig)', lng: 121.0509, lat: 14.5176 },
    NIR: { label: 'Negros Island Region',    lng: 122.9689, lat: 10.6407 },
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
 * Kategoryang (categorical) na palette — bawat rehiyon ay may sariling
 * kulay (parang mga karaniwang "region map" na larawan), sa halip na
 * data-driven na heat-color. Na-validate ang 4 na kulay na ito gamit ang
 * dataviz palette validator (all-pairs CVD/contrast checks, light mode) —
 * ligtas itong paikot-ikutin sa mga rehiyon dahil may sariling text label
 * (region code + bilang) naman ang bawat isa sa mapa mismo.
 */
const REGION_PALETTE = ['#ec4899', '#06b6d4', '#8b5cf6', '#f59e0b'].map((hex) => new THREE.Color(hex));
const PASTEL_MIX = 0.4; // gaano ka-lapit sa puti — mas mataas = mas pastel/light

/** Ibinabalik ang pastel (light, washed-out papuntang puti) na bersyon ng slot sa REGION_PALETTE. */
const categoricalColor = (index: number): THREE.Color =>
    new THREE.Color().copy(REGION_PALETTE[index % REGION_PALETTE.length]).lerp(new THREE.Color(0xffffff), PASTEL_MIX);

const canvasWrap = ref<HTMLDivElement | null>(null);
const loading = ref(true);
const hoveredRegion = ref<{ region: string; label: string; total: number } | null>(null);
const tooltipStyle = ref({ left: '0px', top: '0px' });

const showPanel = ref(false);
const panelLoading = ref(false);
const panelRegion = ref<{ region: string; label: string; total: number } | null>(null);
const panelSearch = ref('');
const panelEmployees = ref<any>(null);
const panelOfficeBreakdown = ref<{ office: string; total: number }[]>([]);

let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let controls: OrbitControls | null = null;
let raycaster: THREE.Raycaster | null = null;
let animationId: number | null = null;
let terrainTexture: THREE.Texture | null = null;
const blockMeshes: THREE.Mesh[] = [];
let hoveredMesh: THREE.Mesh | null = null;
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
 * ring 3D relief na texture. Ang `dataColor` na dumarating dito ay
 * pastel na ('categoricalColor()' na ang nagpapagaan/nag-lelerp papuntang
 * puti), kaya dito ay shading/relief na lang ang idinadagdag.
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

/**
 * Minimalist, walang background na label — parang "Call of Duty" na map
 * callout (bold all-caps na text na may black outline, manipis na
 * underline, walang card/pill na background). May karagdagang ranking badge
 * (top-right corner) na nagpapakita ng puwesto ng region kumpara sa iba
 * batay sa bilang ng empleyado.
 */
function makeLabelSprite(text: string, sub: string, rank: number): THREE.Sprite {
    const canvas = document.createElement('canvas');
    canvas.width = 480;
    canvas.height = 150;
    const ctx = canvas.getContext('2d')!;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.textAlign = 'center';

    const title = text.toUpperCase();
    ctx.font = 'bold 64px Arial';
    ctx.lineWidth = 9;
    ctx.strokeStyle = 'rgba(0,0,0,0.85)';
    ctx.strokeText(title, canvas.width / 2, 64);
    ctx.fillStyle = '#ffffff';
    ctx.fillText(title, canvas.width / 2, 64);

    // Manipis na underline (tactical map marker style)
    ctx.strokeStyle = 'rgba(0,0,0,0.85)';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.moveTo(canvas.width / 2 - 70, 82);
    ctx.lineTo(canvas.width / 2 + 70, 82);
    ctx.stroke();
    ctx.strokeStyle = 'rgba(255,255,255,0.95)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(canvas.width / 2 - 70, 82);
    ctx.lineTo(canvas.width / 2 + 70, 82);
    ctx.stroke();

    const subText = `${sub} PERSONNEL`;
    ctx.font = 'bold 30px Arial';
    ctx.lineWidth = 6;
    ctx.strokeStyle = 'rgba(0,0,0,0.85)';
    ctx.strokeText(subText, canvas.width / 2, 122);
    ctx.fillStyle = '#e5e7eb';
    ctx.fillText(subText, canvas.width / 2, 122);

    // Ranking badge (top-right corner) — #1/#2/#3 ay may medal color, ang iba ay neutral gray.
    const badgeX = canvas.width - 38;
    const badgeY = 34;
    ctx.beginPath();
    ctx.arc(badgeX, badgeY, 28, 0, Math.PI * 2);
    ctx.fillStyle = RANK_BADGE_COLORS[rank] ?? '#64748b';
    ctx.fill();
    ctx.lineWidth = 3;
    ctx.strokeStyle = 'rgba(0,0,0,0.85)';
    ctx.stroke();

    ctx.textBaseline = 'middle';
    ctx.font = 'bold 24px Arial';
    ctx.fillStyle = '#111827';
    ctx.fillText(`#${rank}`, badgeX, badgeY + 1);
    ctx.textBaseline = 'alphabetic';

    const texture = new THREE.CanvasTexture(canvas);
    texture.minFilter = THREE.LinearFilter;
    const material = new THREE.SpriteMaterial({ map: texture, depthTest: false, transparent: true });
    const sprite = new THREE.Sprite(material);
    sprite.scale.set(8, 2.5, 1);
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
    const material = new THREE.LineBasicMaterial({ color: 0xffd400, depthTest: false });

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
    scene.background = new THREE.Color(0xffffff);

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

    // Ocean — pinagawang "unlit" na plain white (MeshBasicMaterial, hindi
    // apektado ng scene lighting) sa halip na MeshStandardMaterial. Dahil
    // paakyat/patagilid ang anggulo ng camera, ang ocean plane na ito ang
    // sumasakop sa halos buong background na nakikita — kung PBR-lit
    // material ito, lalabas itong gray kahit puti ang base color nito
    // (hindi sapat ang liwanag para umabot sa "puro puti").
    const ocean = new THREE.Mesh(
        new THREE.PlaneGeometry(400, 400),
        new THREE.MeshBasicMaterial({ color: 0xffffff })
    );
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
        [...rankableCodes]
            .sort((a, b) => (countsByRegion[b] ?? 0) - (countsByRegion[a] ?? 0))
            .map((code, i) => [code, i + 1])
    );

    let colorIndex = 0;
    for (const region of regionsData.regions) {
        const total = countsByRegion[region.code] ?? 0;
        const ratio = total / maxCount;
        const regionHeight = MIN_HEIGHT + ratio * (MAX_HEIGHT - MIN_HEIGHT);
        const color = categoricalColor(colorIndex++);

        const meshes = buildRegionMesh(region.polygons, project, color, regionHeight);
        meshes.forEach((mesh) => {
            mesh.userData = { region: region.code, label: region.name, total };
            scene.add(mesh);
            blockMeshes.push(mesh);
        });

        const [cx, cz] = project(region.centroid[0], region.centroid[1]);
        const sprite = makeLabelSprite(region.code, String(total), rankByCode[region.code]);
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
        const color = categoricalColor(colorIndex++);

        const [x, z] = project(pin.lng, pin.lat);
        const mesh = buildPinMarker(x, z, color, PIN_MARKER_HEIGHT);
        mesh.userData = { region: code, label: pin.label, total };
        scene.add(mesh);
        blockMeshes.push(mesh);

        const sprite = makeLabelSprite(code, String(total), rankByCode[code]);
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
        if (hoveredMesh !== mesh) {
            if (hoveredMesh) (hoveredMesh.material as THREE.MeshStandardMaterial).emissive.setHex(0x000000);
            hoveredMesh = mesh;
            (mesh.material as THREE.MeshStandardMaterial).emissive.setHex(0x333333);
        }
        hoveredRegion.value = mesh.userData as { region: string; label: string; total: number };
        tooltipStyle.value = { left: `${event.clientX}px`, top: `${event.clientY}px` };
        renderer!.domElement.style.cursor = 'pointer';
    } else {
        if (hoveredMesh) {
            (hoveredMesh.material as THREE.MeshStandardMaterial).emissive.setHex(0x000000);
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
    searchDebounce = setTimeout(() => fetchRegionEmployees(1), 350);
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
    flyCameraTo(new THREE.Vector3(35, 60, 90), new THREE.Vector3(0, 0, 0), 1000);
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
            <div ref="canvasWrap" class="absolute inset-0 bg-white cursor-grab" />

            <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white">
                <p class="text-sm text-muted-foreground">Loading 3D map...</p>
            </div>

            <!-- Header + Total Employees (HUD overlay sa loob ng canvas) -->
            <div class="absolute top-3 left-3 right-3 z-10 flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-3 rounded-2xl border bg-white/90 dark:bg-background/90 backdrop-blur px-4 py-3 shadow-sm">
                    <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-teal-600 to-blue-600 flex items-center justify-center shadow-sm shrink-0">
                        <Map class="h-5.5 w-5.5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-extrabold leading-tight">Employees Map</h1>
                        <p class="text-xs text-muted-foreground mt-0.5 max-w-xs">
                            3D distribution of employees across the Philippines. Click a region to see who's there.
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white/90 dark:bg-background/90 backdrop-blur px-4 py-2.5 flex items-center gap-2.5 shadow-sm">
                    <Users class="h-4 w-4 text-teal-600" />
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold">Total Employees</p>
                        <p class="text-lg font-extrabold leading-none">{{ totalEmployees }}</p>
                    </div>
                </div>
            </div>

            <!-- Controls hint -->
            <div class="absolute bottom-3 left-3 z-10 rounded-lg bg-black/60 backdrop-blur px-3 py-2 text-[11px] text-white flex items-center gap-1.5">
                <MousePointer2 class="h-3 w-3" />
                Drag to rotate · Scroll to zoom · Right-drag to pan · Click a region to view employees
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
                class="fixed z-50 pointer-events-none rounded-lg bg-black/80 text-white text-xs px-3 py-2 shadow-lg"
                :style="{ left: `calc(${tooltipStyle.left} + 14px)`, top: `calc(${tooltipStyle.top} + 14px)` }"
            >
                <p class="font-bold">{{ hoveredRegion.label }}</p>
                <p class="text-white/80">{{ hoveredRegion.total }} employee(s)</p>
            </div>
        </div>

        <!-- ===== Region Employees Side Panel ===== -->
        <Transition name="slide">
            <div v-if="showPanel" class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-background border-l shadow-2xl flex flex-col">
                <div class="sticky top-0 bg-gradient-to-r from-teal-600 to-blue-600 text-white px-5 py-4 flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <h2 class="font-bold text-sm truncate">{{ panelRegion?.label }}</h2>
                        <p class="text-xs text-white/75">{{ panelRegion?.total }} employee(s)</p>
                    </div>
                    <button class="text-white/80 hover:text-white transition-colors" @click="closePanel">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="panelOfficeBreakdown.length" class="p-4 border-b bg-muted/30">
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold mb-2">
                        Breakdown per Office
                    </p>
                    <div class="flex flex-col gap-1.5 max-h-40 overflow-y-auto pr-1">
                        <div
                            v-for="item in panelOfficeBreakdown"
                            :key="item.office"
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <span class="truncate">{{ item.office }}</span>
                            <span class="font-bold text-teal-600 shrink-0">{{ item.total }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-b">
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
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <p v-if="panelLoading" class="text-xs text-muted-foreground text-center py-8">Loading...</p>

                    <div v-else-if="panelEmployees?.data?.length" class="flex flex-col gap-2">
                        <div v-for="emp in panelEmployees.data" :key="emp.id" class="rounded-xl border px-3 py-2.5">
                            <p class="font-bold text-sm leading-tight">{{ emp.name?.toUpperCase() }}</p>
                            <p class="text-xs text-muted-foreground">{{ emp.POSITION }}</p>
                            <p class="text-xs text-muted-foreground">{{ emp['OFFICE/DIVISION'] }}</p>
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
        </Transition>
        <div v-if="showPanel" class="fixed inset-0 z-40 bg-black/30" @click="closePanel" />
    </AppLayout>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active { transition: transform 0.25s ease; }
.slide-enter-from,
.slide-leave-to { transform: translateX(100%); }
</style>
