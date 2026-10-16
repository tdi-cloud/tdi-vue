import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

export interface TrophyArenaOptions {
    reducedMotion: boolean;
}

export interface TrophyArenaHandle {
    setPaused(paused: boolean): void;
    dispose(): void;
}

const PARTICLE_COUNT = 80;
const INTRO_DELAY = 0.25;
const INTRO_DURATION = 1.4;

function clamp01(value: number): number {
    return Math.min(1, Math.max(0, value));
}

function easeOutCubic(value: number): number {
    return 1 - Math.pow(1 - value, 3);
}

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

/** Soft round sprite for the floating gold particles. */
function particleSprite(): THREE.CanvasTexture {
    return canvasTexture(64, (ctx, size) => {
        const gradient = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
        gradient.addColorStop(0, 'rgba(255,255,255,1)');
        gradient.addColorStop(0.25, 'rgba(255,236,170,0.85)');
        gradient.addColorStop(1, 'rgba(255,200,80,0)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, size, size);
    });
}

/** Vertical streaks faded at both ends, wrapped around an open cone to fake volumetric light rays. */
function rayTexture(): THREE.CanvasTexture {
    return canvasTexture(256, (ctx, size) => {
        for (let x = 0; x < size; x += 4) {
            const alpha = 0.15 + Math.pow(Math.random(), 3) * 0.85;
            ctx.fillStyle = `rgba(255,214,120,${alpha})`;
            ctx.fillRect(x, 0, 2 + Math.random() * 4, size);
        }
        ctx.globalCompositeOperation = 'destination-in';
        const fade = ctx.createLinearGradient(0, 0, 0, size);
        fade.addColorStop(0, 'rgba(0,0,0,0)');
        fade.addColorStop(0.25, 'rgba(0,0,0,0.9)');
        fade.addColorStop(0.8, 'rgba(0,0,0,0.35)');
        fade.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = fade;
        ctx.fillRect(0, 0, size, size);
    });
}

function starShape(outer: number, inner: number): THREE.Shape {
    const shape = new THREE.Shape();
    for (let i = 0; i < 10; i++) {
        const radius = i % 2 === 0 ? outer : inner;
        const angle = (i / 10) * Math.PI * 2 + Math.PI / 2;
        const x = Math.cos(angle) * radius;
        const y = Math.sin(angle) * radius;
        if (i === 0) {
            shape.moveTo(x, y);
        } else {
            shape.lineTo(x, y);
        }
    }
    shape.closePath();
    return shape;
}

/** Lathe profile (radius, height) of the cup, from the foot up and back down the inside. */
const TROPHY_PROFILE: Array<[number, number]> = [
    [0, 0],
    [0.4, 0],
    [0.4, 0.05],
    [0.33, 0.09],
    [0.22, 0.15],
    [0.12, 0.24],
    [0.085, 0.36],
    [0.085, 0.46],
    [0.15, 0.52],
    [0.17, 0.57],
    [0.15, 0.62],
    [0.09, 0.68],
    [0.1, 0.76],
    [0.22, 0.84],
    [0.4, 0.95],
    [0.52, 1.1],
    [0.58, 1.28],
    [0.61, 1.46],
    [0.64, 1.58],
    [0.66, 1.62],
    [0.62, 1.63],
    [0.58, 1.56],
    [0.52, 1.36],
    [0.4, 1.12],
    [0.2, 0.98],
    [0, 0.95],
];

/**
 * Builds the gold trophy arena (trophy, laurel, podium, light rays, particles)
 * inside the given container. Callers must call dispose() to release the
 * WebGL context, animation loop, and listeners.
 */
export function createTrophyArena(container: HTMLElement, options: TrophyArenaOptions): TrophyArenaHandle {
    const { reducedMotion } = options;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.localClippingEnabled = true;
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

    const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 50);
    const lookTarget = new THREE.Vector3(0, 1.05, 0);

    // ─── Materials ───────────────────────────────────────────────────────────

    // Clip anything below the podium top so the trophy "rises" out of it.
    const floorClip = [new THREE.Plane(new THREE.Vector3(0, 1, 0), 0)];

    const gold = new THREE.MeshPhysicalMaterial({
        color: 0xf2b84b,
        metalness: 1,
        roughness: 0.2,
        clearcoat: 0.5,
        clearcoatRoughness: 0.15,
        envMapIntensity: 1.35,
        clippingPlanes: floorClip,
    });
    const brightGold = new THREE.MeshPhysicalMaterial({
        color: 0xffd772,
        metalness: 1,
        roughness: 0.12,
        envMapIntensity: 1.6,
        clippingPlanes: floorClip,
    });
    const plinthMaterial = new THREE.MeshStandardMaterial({
        color: 0x0b1a33,
        metalness: 0.75,
        roughness: 0.3,
        clippingPlanes: floorClip,
    });
    const podiumMaterial = new THREE.MeshStandardMaterial({ color: 0x0d203f, metalness: 0.6, roughness: 0.38 });
    const goldGlow = new THREE.MeshBasicMaterial({ color: 0xffc857, toneMapped: false });
    const cyanGlow = new THREE.MeshBasicMaterial({
        color: 0x22d3ee,
        transparent: true,
        opacity: 0.55,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        side: THREE.DoubleSide,
        toneMapped: false,
    });
    const emeraldGlow = cyanGlow.clone();
    emeraldGlow.color.set(0x34d399);
    emeraldGlow.opacity = 0.3;

    // ─── Trophy ──────────────────────────────────────────────────────────────

    const trophy = new THREE.Group();
    scene.add(trophy);

    const plinth = new THREE.Mesh(new THREE.CylinderGeometry(0.52, 0.6, 0.26, 8), plinthMaterial);
    plinth.position.y = 0.13;
    trophy.add(plinth);

    const plinthTrim = new THREE.Mesh(new THREE.TorusGeometry(0.535, 0.016, 8, 64), brightGold);
    plinthTrim.rotation.x = Math.PI / 2;
    plinthTrim.position.y = 0.26;
    trophy.add(plinthTrim);

    const cup = new THREE.Mesh(
        new THREE.LatheGeometry(
            TROPHY_PROFILE.map(([x, y]) => new THREE.Vector2(x, y)),
            72,
        ),
        gold,
    );
    cup.position.y = 0.26;
    trophy.add(cup);

    const handleGeometry = new THREE.TorusGeometry(0.26, 0.042, 16, 48, Math.PI);
    for (const side of [-1, 1]) {
        const handle = new THREE.Mesh(handleGeometry, gold);
        handle.rotation.z = (side * -Math.PI) / 2;
        handle.position.set(side * 0.5, 1.51, 0);
        trophy.add(handle);
    }

    const starGeometry = new THREE.ExtrudeGeometry(starShape(0.13, 0.055), {
        depth: 0.03,
        bevelEnabled: true,
        bevelThickness: 0.012,
        bevelSize: 0.01,
        bevelSegments: 2,
    });
    starGeometry.center();
    for (const side of [1, -1]) {
        const star = new THREE.Mesh(starGeometry, brightGold);
        star.position.set(0, 1.47, side * 0.585);
        star.rotation.y = side === 1 ? 0 : Math.PI;
        trophy.add(star);
    }

    trophy.traverse((child) => {
        if (child instanceof THREE.Mesh) {
            child.castShadow = true;
        }
    });

    // ─── Laurel wreath (static, behind the trophy) ───────────────────────────

    const leafGeometry = new THREE.SphereGeometry(1, 10, 6);
    const leavesPerSide = 13;
    const laurel = new THREE.InstancedMesh(leafGeometry, gold, leavesPerSide * 2 * 2);
    const dummy = new THREE.Object3D();
    let leafIndex = 0;
    for (const side of [-1, 1]) {
        for (let i = 0; i < leavesPerSide; i++) {
            const progress = i / (leavesPerSide - 1);
            const theta = THREE.MathUtils.degToRad(25 + progress * 130);
            const radius = 1.05;
            const baseX = side * radius * Math.sin(theta);
            const baseY = -radius * Math.cos(theta);
            const tangent = Math.atan2(Math.sin(theta), side * Math.cos(theta));
            const size = 1 - progress * 0.45;
            for (const alternate of [-1, 1]) {
                dummy.position.set(baseX + alternate * 0.05 * Math.sin(theta) * side, baseY - alternate * 0.05 * Math.cos(theta), 0);
                dummy.rotation.set(0, 0, tangent + alternate * 0.55);
                dummy.scale.set(0.11 * size, 0.034 * size, 0.045 * size);
                dummy.updateMatrix();
                laurel.setMatrixAt(leafIndex++, dummy.matrix);
            }
        }
    }
    laurel.instanceMatrix.needsUpdate = true;
    laurel.position.set(0, 1.3, -0.32);
    laurel.castShadow = true;
    scene.add(laurel);

    // ─── Podium & floor rings ────────────────────────────────────────────────

    const podium = new THREE.Mesh(new THREE.CylinderGeometry(1.15, 1.22, 0.22, 64), podiumMaterial);
    podium.position.y = -0.11;
    podium.receiveShadow = true;
    scene.add(podium);

    const podiumRim = new THREE.Mesh(new THREE.TorusGeometry(1.15, 0.014, 8, 128), goldGlow);
    podiumRim.rotation.x = Math.PI / 2;
    scene.add(podiumRim);

    const innerRing = new THREE.Mesh(new THREE.RingGeometry(0.72, 0.745, 96), cyanGlow);
    innerRing.rotation.x = -Math.PI / 2;
    innerRing.position.y = 0.002;
    scene.add(innerRing);

    const floorRing = new THREE.Mesh(new THREE.RingGeometry(1.36, 1.41, 128), cyanGlow);
    floorRing.rotation.x = -Math.PI / 2;
    floorRing.position.y = -0.22;
    scene.add(floorRing);

    const outerRing = new THREE.Mesh(new THREE.RingGeometry(1.62, 1.64, 128), emeraldGlow);
    outerRing.rotation.x = -Math.PI / 2;
    outerRing.position.y = -0.22;
    scene.add(outerRing);

    // ─── Light rays ──────────────────────────────────────────────────────────

    const raysTexture = rayTexture();
    raysTexture.wrapS = THREE.RepeatWrapping;
    raysTexture.repeat.set(3, 1);
    const raysMaterial = new THREE.MeshBasicMaterial({
        map: raysTexture,
        transparent: true,
        opacity: 0,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        side: THREE.DoubleSide,
        toneMapped: false,
    });
    const rays = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 1.05, 3, 48, 1, true), raysMaterial);
    rays.position.y = 1.5;
    scene.add(rays);

    // ─── Floating particles ──────────────────────────────────────────────────

    const particlePositions = new Float32Array(PARTICLE_COUNT * 3);
    const particleSeeds = new Float32Array(PARTICLE_COUNT * 3);
    for (let i = 0; i < PARTICLE_COUNT; i++) {
        const angle = Math.random() * Math.PI * 2;
        const radius = 0.45 + Math.random() * 1.05;
        particleSeeds[i * 3] = angle;
        particleSeeds[i * 3 + 1] = radius;
        particleSeeds[i * 3 + 2] = 0.12 + Math.random() * 0.25;
        particlePositions[i * 3] = Math.cos(angle) * radius;
        particlePositions[i * 3 + 1] = Math.random() * 2.8;
        particlePositions[i * 3 + 2] = Math.sin(angle) * radius;
    }
    const particleGeometry = new THREE.BufferGeometry();
    particleGeometry.setAttribute('position', new THREE.BufferAttribute(particlePositions, 3));
    const sprite = particleSprite();
    const particleMaterial = new THREE.PointsMaterial({
        map: sprite,
        color: 0xffd27a,
        size: 0.06,
        sizeAttenuation: true,
        transparent: true,
        opacity: 0,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        toneMapped: false,
    });
    const particles = new THREE.Points(particleGeometry, particleMaterial);
    scene.add(particles);

    // ─── Lights ──────────────────────────────────────────────────────────────

    scene.add(new THREE.HemisphereLight(0x9fd8ff, 0x0a1428, 0.6));

    const key = new THREE.SpotLight(0xffe2a8, 70, 0, 0.5, 0.6, 2);
    key.position.set(2.2, 5, 3.5);
    key.target.position.copy(lookTarget);
    key.castShadow = true;
    key.shadow.mapSize.set(512, 512);
    key.shadow.bias = -0.0005;
    key.shadow.normalBias = 0.02;
    scene.add(key, key.target);

    const cyanRim = new THREE.PointLight(0x22d3ee, 9, 10, 2);
    cyanRim.position.set(-2.2, 2.2, -1.8);
    const emeraldRim = new THREE.PointLight(0x34d399, 6, 10, 2);
    emeraldRim.position.set(2.4, 1.5, -2);
    const fill = new THREE.DirectionalLight(0xffffff, 0.6);
    fill.position.set(0, 2, 5);
    scene.add(cyanRim, emeraldRim, fill);

    // ─── Sizing ──────────────────────────────────────────────────────────────

    function resize(): void {
        const width = Math.max(1, container.clientWidth);
        const height = Math.max(1, container.clientHeight);
        const aspect = width / height;
        renderer.setSize(width, height, false);
        camera.aspect = aspect;
        // Keep the whole arena (≈3.3 tall, ≈3.3 wide) in frame on narrow or wide containers.
        const halfTan = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
        const distance = Math.max(1.75 / halfTan, 1.7 / (halfTan * aspect)) + 0.4;
        camera.position.set(0, 1.75, distance);
        camera.lookAt(lookTarget);
        camera.updateProjectionMatrix();
    }

    // ─── Animation ───────────────────────────────────────────────────────────

    const tilt = { x: 0, y: 0, targetX: 0, targetY: 0 };
    const clock = new THREE.Clock();
    let elapsed = 0;
    let frameId = 0;
    let paused = false;
    let disposed = false;

    function applyFrame(delta: number): void {
        const intro = reducedMotion ? 1 : easeOutCubic(clamp01((elapsed - INTRO_DELAY) / INTRO_DURATION));
        const lateIntro = reducedMotion ? 1 : easeOutCubic(clamp01((elapsed - INTRO_DELAY - 0.5) / INTRO_DURATION));

        trophy.position.y = -2 * (1 - intro);
        trophy.scale.setScalar(0.75 + 0.25 * intro);
        laurel.scale.setScalar(Math.max(0.001, lateIntro));

        raysMaterial.opacity = 0.2 * lateIntro;
        particleMaterial.opacity = 0.9 * lateIntro;

        if (reducedMotion) {
            trophy.rotation.set(0, -0.35, 0);
            return;
        }

        tilt.x += (tilt.targetX - tilt.x) * Math.min(1, delta * 4);
        tilt.y += (tilt.targetY - tilt.y) * Math.min(1, delta * 4);

        trophy.rotation.y = elapsed * 0.45 + (1 - intro) * Math.PI * 1.5 + tilt.y;
        trophy.rotation.x = tilt.x;
        trophy.position.y += Math.sin(elapsed * 1.4) * 0.025 * intro;
        rays.rotation.y = elapsed * 0.08;

        for (let i = 0; i < PARTICLE_COUNT; i++) {
            const seedAngle = particleSeeds[i * 3] + elapsed * 0.12;
            const radius = particleSeeds[i * 3 + 1];
            let y = particlePositions[i * 3 + 1] + particleSeeds[i * 3 + 2] * delta;
            if (y > 2.9) {
                y = 0;
            }
            particlePositions[i * 3] = Math.cos(seedAngle) * radius;
            particlePositions[i * 3 + 1] = y;
            particlePositions[i * 3 + 2] = Math.sin(seedAngle) * radius;
        }
        particleGeometry.attributes.position.needsUpdate = true;
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
        const delta = Math.min(clock.getDelta(), 0.05);
        elapsed += delta;
        applyFrame(delta);
        renderer.render(scene, camera);
        frameId = requestAnimationFrame(loop);
    }

    function start(): void {
        if (disposed || reducedMotion || paused || frameId || document.hidden) {
            return;
        }
        clock.getDelta();
        frameId = requestAnimationFrame(loop);
    }

    function stop(): void {
        if (frameId) {
            cancelAnimationFrame(frameId);
            frameId = 0;
        }
    }

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
        tilt.targetY = ((event.clientX - rect.left) / rect.width - 0.5) * 0.35;
        tilt.targetX = ((event.clientY - rect.top) / rect.height - 0.5) * 0.12;
    }

    function onPointerLeave(): void {
        tilt.targetX = 0;
        tilt.targetY = 0;
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
                if (object instanceof THREE.Mesh || object instanceof THREE.Points) {
                    object.geometry.dispose();
                    (Array.isArray(object.material) ? object.material : [object.material]).forEach((material) => materials.add(material));
                }
                if (object instanceof THREE.Light && object.shadow?.map) {
                    object.shadow.map.dispose();
                }
            });
            materials.forEach((material) => material.dispose());
            laurel.dispose();
            raysTexture.dispose();
            sprite.dispose();
            envTexture.dispose();
            pmrem.dispose();
            renderer.dispose();
            renderer.forceContextLoss();
            renderer.domElement.remove();
        },
    };
}
