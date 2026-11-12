import { EMBLEM_GEAR, gearProfile, type EmblemShape } from '@/lib/positionDirectory';
import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

export interface DirectorySceneOptions {
    reducedMotion: boolean;
    /** Number of offices/divisions, drawn as nodes orbiting the crystal. */
    nodeCount: number;
    /** The centre object: a faceted crystal (default) or a spoked gear. */
    shape?: EmblemShape;
}

export interface DirectorySceneHandle {
    setNodeCount(count: number): void;
    /** Short light burst played when the selected position changes. */
    pulse(): void;
    setPaused(paused: boolean): void;
    dispose(): void;
}

const MAX_NODES = 40;
const NODE_RING_RADIUS = 2.2;
const DUST_COUNT = 90;
const PULSE_DURATION = 1.4;

const NAVY = 0x13306f;
const ROYAL = 0x2f6bff;
const PEARL = 0xeaf1ff;
const GOLD = 0xe6c372;

function canvasTexture(size: number, draw: (ctx: CanvasRenderingContext2D, size: number) => void): THREE.CanvasTexture {
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');
    if (ctx) {
        draw(ctx, size);
    }
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    return texture;
}

/**
 * Spoked navy gear with gold outlines and a raised gold hub, centred on the
 * origin with its axle along z. Returns the hub material (it pulses too).
 */
function addGear(target: THREE.Group, bodyMaterial: THREE.Material, edgeMaterial: THREE.LineBasicMaterial): THREE.MeshStandardMaterial {
    const profile = gearProfile(EMBLEM_GEAR);
    const toVectors = (points: [number, number][]) => points.map(([x, y]) => new THREE.Vector2(x, y));
    const axleRadius = 0.2;

    const gearShape = new THREE.Shape(toVectors(profile.outline));
    gearShape.holes = profile.windows.map((window) => new THREE.Path(toVectors(window)));
    const axle = new THREE.Path();
    axle.absarc(0, 0, axleRadius, 0, Math.PI * 2, true);
    gearShape.holes.push(axle);

    const depth = 0.34;
    const bodyGeometry = new THREE.ExtrudeGeometry(gearShape, { depth, bevelEnabled: false, curveSegments: 12 });
    bodyGeometry.translate(0, 0, -depth / 2);
    target.add(new THREE.Mesh(bodyGeometry, bodyMaterial));

    // Only the crisp edges (tooth corners, faces' outlines), not the facets of the curves.
    const edges = new THREE.LineSegments(new THREE.EdgesGeometry(bodyGeometry, 30), edgeMaterial);
    edges.scale.setScalar(1.002);
    target.add(edges);

    const hubShape = new THREE.Shape();
    hubShape.absarc(0, 0, EMBLEM_GEAR.hubRadius - 0.04, 0, Math.PI * 2, false);
    const hubHole = new THREE.Path();
    hubHole.absarc(0, 0, axleRadius, 0, Math.PI * 2, true);
    hubShape.holes.push(hubHole);

    const hubDepth = 0.52;
    const hubGeometry = new THREE.ExtrudeGeometry(hubShape, {
        depth: hubDepth,
        bevelEnabled: true,
        bevelThickness: 0.03,
        bevelSize: 0.03,
        bevelSegments: 2,
        curveSegments: 32,
    });
    hubGeometry.translate(0, 0, -hubDepth / 2);
    const hubMaterial = new THREE.MeshStandardMaterial({
        color: GOLD,
        metalness: 0.85,
        roughness: 0.28,
        emissive: 0x3d2c0a,
        emissiveIntensity: 0.25,
    });
    target.add(new THREE.Mesh(hubGeometry, hubMaterial));

    return hubMaterial;
}

function glowTexture(inner: string, outer: string): THREE.CanvasTexture {
    return canvasTexture(128, (ctx, size) => {
        const gradient = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
        gradient.addColorStop(0, inner);
        gradient.addColorStop(0.35, outer);
        gradient.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, size, size);
    });
}

/**
 * Hero centerpiece for the Employee Position Directory (and the Employees
 * page): a faceted navy crystal or a spoked navy gear with gold edges, orbit
 * rings, one node per office/division (or employee) and a little light dust.
 * Callers must call dispose() to release the WebGL
 * context, animation loop and listeners.
 */
export function createDirectoryScene(container: HTMLElement, options: DirectorySceneOptions): DirectorySceneHandle {
    const { reducedMotion } = options;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.setClearColor(0x000000, 0);
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';
    renderer.domElement.setAttribute('aria-hidden', 'true');
    container.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    const room = new RoomEnvironment();
    const envTexture = pmrem.fromScene(room, 0.04).texture;
    room.dispose();
    scene.environment = envTexture;

    const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 100);
    // Close enough that the crystal and its rings fill most of the frame.
    camera.position.set(0, 0.35, 7.4);
    camera.lookAt(0, 0, 0);

    // ─── Lights ──────────────────────────────────────────────────────────────
    scene.add(new THREE.AmbientLight(0x9fb7ff, 0.55));
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.4);
    keyLight.position.set(3, 4, 5);
    scene.add(keyLight);
    const goldLight = new THREE.PointLight(GOLD, 22, 0, 2);
    const blueLight = new THREE.PointLight(ROYAL, 26, 0, 2);
    scene.add(goldLight, blueLight);

    // ─── Halo behind the crystal ─────────────────────────────────────────────
    const haloTexture = glowTexture('rgba(190,215,255,0.95)', 'rgba(70,120,255,0.35)');
    const haloMaterial = new THREE.SpriteMaterial({
        map: haloTexture,
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        opacity: 0.55,
    });
    const halo = new THREE.Sprite(haloMaterial);
    halo.scale.setScalar(5.2);
    halo.position.z = -1.2;
    scene.add(halo);

    // ─── Centre object: crystal or gear ──────────────────────────────────────
    const isGear = options.shape === 'gear';
    // Outer group tumbles/sways; the inner one turns the gear on its axle.
    const crystalGroup = new THREE.Group();
    const spinGroup = new THREE.Group();
    crystalGroup.add(spinGroup);
    scene.add(crystalGroup);

    const crystalMaterial = new THREE.MeshStandardMaterial({
        color: NAVY,
        metalness: 0.6,
        roughness: 0.22,
        flatShading: true,
        emissive: 0x0a1d52,
        emissiveIntensity: 0.35,
    });
    const edgeMaterial = new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.85 });
    let hubMaterial: THREE.MeshStandardMaterial | null = null;

    if (isGear) {
        hubMaterial = addGear(spinGroup, crystalMaterial, edgeMaterial);
        // Seen at an angle it looks narrower than the crystal; a little larger evens that out.
        spinGroup.scale.setScalar(1.1);
    } else {
        const crystalGeometry = new THREE.IcosahedronGeometry(1.3, 0);
        spinGroup.add(new THREE.Mesh(crystalGeometry, crystalMaterial));

        const edges = new THREE.LineSegments(new THREE.EdgesGeometry(crystalGeometry), edgeMaterial);
        edges.scale.setScalar(1.002);
        spinGroup.add(edges);
    }

    // ─── Orbit rings ─────────────────────────────────────────────────────────
    const ringSpecs = [
        { radius: 1.85, tube: 0.009, color: GOLD, opacity: 0.75, tilt: [1.2, 0.2, 0] },
        { radius: NODE_RING_RADIUS, tube: 0.006, color: PEARL, opacity: 0.5, tilt: [1.45, -0.35, 0.2] },
        { radius: 2.65, tube: 0.01, color: ROYAL, opacity: 0.65, tilt: [0.5, 0.9, -0.3] },
    ];
    const rings = ringSpecs.map((spec) => {
        const material = new THREE.MeshBasicMaterial({ color: spec.color, transparent: true, opacity: spec.opacity, depthWrite: false });
        const mesh = new THREE.Mesh(new THREE.TorusGeometry(spec.radius, spec.tube, 8, 160), material);
        const pivot = new THREE.Group();
        pivot.rotation.set(spec.tilt[0], spec.tilt[1], spec.tilt[2]);
        pivot.add(mesh);
        scene.add(pivot);
        return { pivot, mesh };
    });

    // ─── Office/division nodes (on the middle ring, so they orbit with it) ───
    const nodeHost = rings[1].mesh;
    const nodeGeometry = new THREE.SphereGeometry(0.065, 16, 12);
    const nodeMaterial = new THREE.MeshBasicMaterial({ color: 0xffffff });
    const nodes = new THREE.InstancedMesh(nodeGeometry, nodeMaterial, MAX_NODES);
    nodeHost.add(nodes);

    const linkMaterial = new THREE.LineBasicMaterial({ color: 0x8fb4ff, transparent: true, opacity: 0.28, depthWrite: false });
    const links = new THREE.LineSegments(new THREE.BufferGeometry(), linkMaterial);
    nodeHost.add(links);

    const matrix = new THREE.Matrix4();
    const pearlColor = new THREE.Color(PEARL);
    const goldColor = new THREE.Color(GOLD);

    function layoutNodes(count: number): void {
        const total = Math.max(1, Math.min(MAX_NODES, Math.round(count)));
        const linkPositions: number[] = [];

        for (let i = 0; i < total; i++) {
            const angle = (i / total) * Math.PI * 2;
            const x = Math.cos(angle) * NODE_RING_RADIUS;
            const y = Math.sin(angle) * NODE_RING_RADIUS;
            matrix.makeTranslation(x, y, 0);
            nodes.setMatrixAt(i, matrix);
            nodes.setColorAt(i, i % 5 === 0 ? goldColor : pearlColor);

            // Spoke towards the crystal and a chord to the next node: a quiet "network".
            linkPositions.push(x, y, 0, x * 0.5, y * 0.5, 0);
            if (total > 1) {
                const next = ((i + 1) / total) * Math.PI * 2;
                linkPositions.push(x, y, 0, Math.cos(next) * NODE_RING_RADIUS, Math.sin(next) * NODE_RING_RADIUS, 0);
            }
        }

        nodes.count = total;
        nodes.instanceMatrix.needsUpdate = true;
        if (nodes.instanceColor) {
            nodes.instanceColor.needsUpdate = true;
        }

        // Fresh geometry (and free the old GPU buffer) rather than swapping attributes.
        const linkGeometry = new THREE.BufferGeometry();
        linkGeometry.setAttribute('position', new THREE.Float32BufferAttribute(linkPositions, 3));
        links.geometry.dispose();
        links.geometry = linkGeometry;
    }
    layoutNodes(options.nodeCount);

    // ─── Light dust ──────────────────────────────────────────────────────────
    const dustTexture = glowTexture('rgba(255,255,255,1)', 'rgba(255,240,200,0.5)');
    const dustPositions = new Float32Array(DUST_COUNT * 3);
    const dustColors = new Float32Array(DUST_COUNT * 3);
    const dustColorChoices = [new THREE.Color(PEARL), new THREE.Color(GOLD), new THREE.Color(0x7fa8ff)];
    for (let i = 0; i < DUST_COUNT; i++) {
        const radius = 2.9 + Math.random() * 1.5;
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        dustPositions[i * 3] = radius * Math.sin(phi) * Math.cos(theta);
        dustPositions[i * 3 + 1] = radius * Math.cos(phi) * 0.7;
        dustPositions[i * 3 + 2] = radius * Math.sin(phi) * Math.sin(theta);
        const color = dustColorChoices[i % dustColorChoices.length];
        dustColors.set([color.r, color.g, color.b], i * 3);
    }
    const dustGeometry = new THREE.BufferGeometry();
    dustGeometry.setAttribute('position', new THREE.BufferAttribute(dustPositions, 3));
    dustGeometry.setAttribute('color', new THREE.BufferAttribute(dustColors, 3));
    const dustMaterial = new THREE.PointsMaterial({
        size: 0.075,
        map: dustTexture,
        vertexColors: true,
        transparent: true,
        opacity: 0.75,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
    });
    const dust = new THREE.Points(dustGeometry, dustMaterial);
    scene.add(dust);

    // ─── Animation ───────────────────────────────────────────────────────────
    const pointer = { x: 0, y: 0, targetX: 0, targetY: 0 };
    // Frame timing (THREE.Clock is deprecated in this three.js version).
    let lastFrameAt = performance.now();
    let elapsed = 0;
    let pulseStartedAt = -Infinity;
    let frameId = 0;
    let paused = false;
    let disposed = false;

    function applyFrame(delta: number): void {
        const pulseProgress = Math.min(1, (elapsed - pulseStartedAt) / PULSE_DURATION);
        const pulse = pulseProgress < 1 ? Math.sin(pulseProgress * Math.PI) : 0;

        crystalMaterial.emissiveIntensity = 0.35 + pulse * 0.9;
        edgeMaterial.opacity = 0.85 + pulse * 0.15;
        haloMaterial.opacity = 0.55 + pulse * 0.35;
        halo.scale.setScalar(5.2 + pulse * 1.1);
        if (hubMaterial) {
            hubMaterial.emissiveIntensity = 0.25 + pulse * 0.6;
        }

        if (reducedMotion) {
            // Gear: a three-quarter view, so its thickness and teeth read as 3D.
            crystalGroup.rotation.set(isGear ? 0.42 : 0.35, isGear ? -0.5 : 0.6, 0);
            return;
        }

        pointer.x += (pointer.targetX - pointer.x) * Math.min(1, delta * 3);
        pointer.y += (pointer.targetY - pointer.y) * Math.min(1, delta * 3);
        camera.position.x = pointer.x * 0.9;
        camera.position.y = 0.35 + pointer.y * 0.6;
        camera.lookAt(0, 0, 0);

        const spin = 1 + pulse * 3;
        if (isGear) {
            // Turns steadily on its axle while the whole gear sways, showing both faces' depth.
            spinGroup.rotation.z -= delta * 0.45 * spin;
            crystalGroup.rotation.x = 0.42 + Math.sin(elapsed * 0.45) * 0.1;
            crystalGroup.rotation.y = -0.5 + Math.sin(elapsed * 0.25) * 0.35;
        } else {
            crystalGroup.rotation.y += delta * 0.28 * spin;
            crystalGroup.rotation.x = 0.35 + Math.sin(elapsed * 0.45) * 0.12;
        }
        crystalGroup.position.y = Math.sin(elapsed * 0.9) * 0.06;

        rings.forEach(({ mesh }, index) => {
            mesh.rotation.z += delta * (0.12 + index * 0.05) * (index % 2 === 0 ? 1 : -1) * spin;
            mesh.scale.setScalar(1 + pulse * (0.12 + index * 0.04));
        });

        dust.rotation.y += delta * 0.03;
        goldLight.position.set(Math.cos(elapsed * 0.5) * 3.4, 1.6, Math.sin(elapsed * 0.5) * 3.4);
        blueLight.position.set(Math.cos(elapsed * 0.5 + Math.PI) * 3.4, -1.2, Math.sin(elapsed * 0.5 + Math.PI) * 3.4);
    }

    function renderOnce(): void {
        applyFrame(0);
        renderer.render(scene, camera);
    }

    function loop(): void {
        if (disposed || paused) {
            frameId = 0;
            return;
        }
        const now = performance.now();
        const delta = Math.min((now - lastFrameAt) / 1000, 0.05);
        lastFrameAt = now;
        elapsed += delta;
        applyFrame(delta);
        renderer.render(scene, camera);
        frameId = requestAnimationFrame(loop);
    }

    function start(): void {
        if (disposed || reducedMotion || paused || frameId || document.hidden) {
            return;
        }
        lastFrameAt = performance.now();
        frameId = requestAnimationFrame(loop);
    }

    function stop(): void {
        if (frameId) {
            cancelAnimationFrame(frameId);
            frameId = 0;
        }
    }

    function resize(): void {
        const width = Math.max(1, container.clientWidth);
        const height = Math.max(1, container.clientHeight);
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    }

    // Static placements for the reduced-motion still frame.
    goldLight.position.set(2.6, 1.6, 2.4);
    blueLight.position.set(-2.6, -1.2, -2.4);

    // ─── Listeners ───────────────────────────────────────────────────────────
    const resizeObserver = new ResizeObserver(() => {
        resize();
        if (reducedMotion || paused) {
            renderOnce();
        }
    });
    resizeObserver.observe(container);

    function onPointerMove(event: PointerEvent): void {
        const rect = container.getBoundingClientRect();
        pointer.targetX = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
        pointer.targetY = -((event.clientY - rect.top) / rect.height - 0.5) * 2;
    }

    function onPointerLeave(): void {
        pointer.targetX = 0;
        pointer.targetY = 0;
    }

    function onVisibilityChange(): void {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    }

    if (!reducedMotion) {
        container.addEventListener('pointermove', onPointerMove);
        container.addEventListener('pointerleave', onPointerLeave);
    }
    document.addEventListener('visibilitychange', onVisibilityChange);

    resize();
    renderOnce();
    start();

    return {
        setNodeCount(count: number): void {
            layoutNodes(count);
            if (reducedMotion || paused) {
                renderOnce();
            }
        },
        pulse(): void {
            pulseStartedAt = elapsed;
            if (reducedMotion || paused) {
                renderOnce();
            }
        },
        setPaused(nextPaused: boolean): void {
            paused = nextPaused;
            if (paused) {
                stop();
            } else {
                start();
            }
        },
        dispose(): void {
            if (disposed) {
                return;
            }
            disposed = true;
            stop();
            resizeObserver.disconnect();
            container.removeEventListener('pointermove', onPointerMove);
            container.removeEventListener('pointerleave', onPointerLeave);
            document.removeEventListener('visibilitychange', onVisibilityChange);

            const materials = new Set<THREE.Material>();
            scene.traverse((object) => {
                if (object instanceof THREE.Mesh || object instanceof THREE.Points || object instanceof THREE.LineSegments) {
                    object.geometry.dispose();
                    (Array.isArray(object.material) ? object.material : [object.material]).forEach((material) => materials.add(material));
                }
            });
            nodes.dispose();
            materials.forEach((material) => material.dispose());
            haloMaterial.dispose();
            haloTexture.dispose();
            dustTexture.dispose();
            envTexture.dispose();
            pmrem.dispose();
            renderer.dispose();
            renderer.forceContextLoss();
            renderer.domElement.remove();
        },
    };
}
