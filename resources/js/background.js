// Parallax space background. The picture matches the original per-frame animation:
// each layer is offset by the camera (pan/zoom on a render page) times (1 - slowness)
// plus its own drift. Layers that do not drift are repositioned only when the camera
// or the window changes; drifting layers move with `transform` alone, which the
// browser composites without repainting.

const LAYERS = {
    back: { slowness: 0.998046875, drift: [0, 0], scale: 1 },
    front: { slowness: 0.998046875, drift: [0, 0], scale: 1 },
    stars1: { slowness: 0.996625, drift: [0, 0], scale: 1 },
    stars2: { slowness: 0.989375, drift: [0, 0], scale: 1 },
    meteors1: { slowness: 0.97, drift: [0.0018, 0.001], scale: 1.5, tile: 480 },
    meteors2: { slowness: 0.8, drift: [-0.00075, -0.0009], scale: 0.95, tile: 480 },
};

const MAX_FRAME_SECONDS = 0.1;

/**
 * @param {() => ({x: number, y: number, scale: number} | null | undefined)} getCamera
 * @returns {{refresh: () => void}} call refresh() when the camera moves
 */
export function startBackground(getCamera) {
    const elements = {};
    for (const key of Object.keys(LAYERS)) {
        elements[key] = document.querySelector(`.render-background__layer--${key}`);
        if (!elements[key]) return { refresh() {} };
    }

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    let driftTime = 0;
    let lastFrame = null;
    let frameRequest = 0;
    let lastState = "";

    function camera() {
        const t = getCamera?.();
        return { x: t?.x || 0, y: t?.y || 0, scale: t?.scale || 1 };
    }

    function place(key, cam, width, height) {
        const layer = LAYERS[key];
        const element = elements[key];
        const scale = layer.scale * Math.max(1, Math.min(2, cam.scale));
        const offsetX = cam.x * (1 - layer.slowness) + layer.drift[0] * driftTime * width;
        const offsetY = cam.y * (1 - layer.slowness) + layer.drift[1] * driftTime * height;

        if (!layer.tile) {
            // twice the window, centred and scaled around the window centre
            element.style.backgroundPosition = `${offsetX}px ${offsetY}px`;
            element.style.transform = `translate(${-width / 2}px, ${-height / 2}px) scale(${scale})`;
            return;
        }

        // Same tile grid as a centred layer with background-position, expressed as a translation
        // of an element anchored at its top-left corner; the grid repeats every tile, so the
        // translation is wrapped to stay within one tile left/up of the window edge.
        // The scale goes into background-size, so the tiles keep their crisp pixels; the
        // transform is only a whole-pixel shift, which is composited without repainting.
        const period = layer.tile * scale;
        const size = `${period}|${width}|${height}`;
        if (element.dataset.size !== size) {
            element.dataset.size = size;
            element.style.backgroundSize = `${period}px ${period}px`;
            element.style.width = `${Math.ceil(width + period) + 2}px`;
            element.style.height = `${Math.ceil(height + period) + 2}px`;
        }
        const wrap = (value) => Math.round(((value % period) + period) % period - period);
        const x = wrap(width / 2 + scale * (offsetX - width));
        const y = wrap(height / 2 + scale * (offsetY - height));
        element.style.transform = `translate3d(${x}px, ${y}px, 0)`;
    }

    function update(force) {
        const width = window.innerWidth;
        const height = window.innerHeight;
        const cam = camera();
        const state = `${cam.x},${cam.y},${cam.scale},${width},${height}`;
        const cameraChanged = force || state !== lastState;
        lastState = state;

        for (const [key, layer] of Object.entries(LAYERS)) {
            if (layer.tile || cameraChanged) place(key, cam, width, height);
        }
    }

    function frame(now) {
        // a long pause (hidden tab, slow device) must not make the meteors jump
        const seconds = lastFrame === null ? 0 : Math.min(MAX_FRAME_SECONDS, (now - lastFrame) / 1000);
        lastFrame = now;
        driftTime += seconds;
        update(false);
        frameRequest = requestAnimationFrame(frame);
    }

    function start() {
        if (frameRequest || reducedMotion.matches || document.hidden) return;
        lastFrame = null;
        frameRequest = requestAnimationFrame(frame);
    }

    function stop() {
        cancelAnimationFrame(frameRequest);
        frameRequest = 0;
    }

    document.addEventListener("visibilitychange", () => (document.hidden ? stop() : start()));
    reducedMotion.addEventListener?.("change", () => (reducedMotion.matches ? stop() : start()));
    window.addEventListener("resize", () => update(true));

    update(true);
    start();

    return {
        // the animation loop already follows the camera; without it (reduced motion) follow it here
        refresh() {
            if (!frameRequest) update(false);
        },
    };
}
