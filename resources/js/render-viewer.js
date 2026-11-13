import panzoom from "panzoom";
import { MapCanvas } from "./map-canvas";

document.addEventListener("DOMContentLoaded", function () {
    const imageContainer = document.querySelector(
        ".render-viewer__image-container"
    );
    const image = document.querySelector(".render-viewer__image");

    const renderBackground = document.querySelector(".render-background");
    const starsCanvas1 = document.getElementById("stars-canvas-1");
    const starsCanvas2 = document.getElementById("stars-canvas-2");
    const layers = {
        back: document.querySelector(".render-background__layer--back"),
        front: document.querySelector(".render-background__layer--front"),
        stars1: starsCanvas1,
        stars2: starsCanvas2,
        meteors1: document.querySelector(".render-background__layer--meteors1"),
        meteors2: document.querySelector(".render-background__layer--meteors2"),
    };

    function generateStars(canvas, config) {
        if (!canvas) return;

        const width = 2048;
        const height = 2048;
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, width, height);

        function seededRandom(seed) {
            let value = seed;
            return function () {
                value = (value * 9301 + 49297) % 233280;
                return value / 233280;
            };
        }

        function hexToRgba(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        function brightenColor(hex, factor) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            const newR = Math.min(255, Math.floor(r * factor));
            const newG = Math.min(255, Math.floor(g * factor));
            const newB = Math.min(255, Math.floor(b * factor));
            return `#${newR
                .toString(16)
                .padStart(
                    2,
                    "0"
                )}${newG.toString(16).padStart(2, "0")}${newB.toString(16).padStart(2, "0")}`;
        }

        function generateStarLayer(layerConfig) {
            const random = seededRandom(layerConfig.seed);
            const count = layerConfig.count || 1000;
            const color = layerConfig.closecolor || "#FFFFFF";
            const pointSize = layerConfig.pointsize || 1;
            const brightColor = brightenColor(color, 1.3);

            for (let i = 0; i < count; i++) {
                const x = random() * width;
                const y = random() * height;

                if (layerConfig.mask) {
                    const maskValue = random();
                    if (maskValue > layerConfig.maskthreshold) {
                        continue;
                    }
                }

                const gradient = ctx.createRadialGradient(
                    x,
                    y,
                    0,
                    x,
                    y,
                    pointSize
                );
                gradient.addColorStop(0, hexToRgba(brightColor, 1.0));
                gradient.addColorStop(0.5, hexToRgba(color, 0.8));
                gradient.addColorStop(1, hexToRgba(color, 0));

                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.arc(x, y, pointSize, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        const stars1Config = {
            seed: 3472,
            count: 1000,
            closecolor: "#7E86BF",
            mask: true,
            maskthreshold: 0.37,
        };

        const stars1DimConfig = {
            seed: 3472,
            count: 1000,
            closecolor: "#3D415C",
            pointsize: 2,
            mask: true,
            maskthreshold: 0.37,
        };

        const yellowStarsConfig = {
            seed: 6454,
            count: 30,
            closecolor: "#FFD363",
        };

        const yellowStarsDimConfig = {
            seed: 6454,
            count: 30,
            closecolor: "#43371A",
            pointsize: 2,
        };

        if (config.type === "stars1") {
            generateStarLayer(stars1Config);
            generateStarLayer(stars1DimConfig);
            generateStarLayer(yellowStarsConfig);
            generateStarLayer(yellowStarsDimConfig);
        } else if (config.type === "stars2") {
            const stars2Config = { ...stars1Config, seed: 4810 };
            const stars2DimConfig = { ...stars1DimConfig, seed: 4810 };
            const yellowStars2Config = { ...yellowStarsConfig, seed: 9019 };
            const yellowStars2DimConfig = {
                ...yellowStarsDimConfig,
                seed: 9019,
            };

            generateStarLayer(stars2Config);
            generateStarLayer(stars2DimConfig);
            generateStarLayer(yellowStars2Config);
            generateStarLayer(yellowStars2DimConfig);
        }
    }

    let starsLayer1 = null;
    let starsLayer2 = null;

    if (starsCanvas1) {
        generateStars(starsCanvas1, { type: "stars1" });
        const dataUrl1 = starsCanvas1.toDataURL("image/png");
        starsLayer1 = document.createElement("div");
        starsLayer1.className =
            "render-background__layer render-background__layer--stars1";
        starsLayer1.style.backgroundImage = `url(${dataUrl1})`;
        starsCanvas1.parentNode.replaceChild(starsLayer1, starsCanvas1);
        layers.stars1 = starsLayer1;
    }

    if (starsCanvas2) {
        generateStars(starsCanvas2, { type: "stars2" });
        const dataUrl2 = starsCanvas2.toDataURL("image/png");
        starsLayer2 = document.createElement("div");
        starsLayer2.className =
            "render-background__layer render-background__layer--stars2";
        starsLayer2.style.backgroundImage = `url(${dataUrl2})`;
        starsCanvas2.parentNode.replaceChild(starsLayer2, starsCanvas2);
        layers.stars2 = starsLayer2;
    }

    let instance = null;
    const renderViewer = document.querySelector(".render-viewer");

    // Pan/zoom an element of the render's size; the PNG or the map canvas follows it.
    function startPanzoom(target) {
        instance = panzoom(imageContainer, {
            minZoom: 0.3,
            maxZoom: 5,
            bounds: false,
            smoothScroll: true,
            zoomSpeed: 0.05,
        });

        setTimeout(() => {
            if (renderViewer && target) {
                const viewerRect = renderViewer.getBoundingClientRect();
                const targetRect = target.getBoundingClientRect();

                const centerX = viewerRect.width / 2 - targetRect.width / 2;
                const centerY = viewerRect.height / 2 - targetRect.height / 2;

                instance.moveTo(centerX, centerY);
            }
        }, 0);

        if (renderViewer) {
            renderViewer.addEventListener(
                "wheel",
                function (e) {
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        e.stopPropagation();

                        const currentTransform = instance.getTransform();
                        const currentScale = currentTransform.scale;

                        const delta = e.deltaY * -0.01;
                        const zoomFactor = 1 + delta;
                        const newScale = Math.max(
                            0.3,
                            Math.min(5, currentScale * zoomFactor)
                        );

                        if (Math.abs(newScale - currentScale) > 0.001) {
                            const scaleMultiplier = newScale / currentScale;
                            instance.smoothZoom(
                                e.clientX,
                                e.clientY,
                                scaleMultiplier
                            );
                        }
                    }
                },
                { passive: false }
            );
        }
    }

    // Fallback: the PNG render, created only when the map data cannot be shown.
    function showImage(src) {
        const img = document.createElement("img");
        img.className = "render-viewer__image";
        img.alt = renderViewer?.dataset.alt || "";
        img.addEventListener("load", () => window.hideRenderLoader?.());
        img.addEventListener("error", () => window.hideRenderLoader?.());
        img.src = src;
        imageContainer.appendChild(img);
        startPanzoom(img);
    }

    // "Layers" dropdown: opens like the other two, shows one panel at a time.
    function setupLayers(map) {
        const toggle = document.querySelector(".layers-toggle");
        const panel = document.querySelector(".layers-dropdown");
        if (!toggle || !panel) return;
        const others = [document.querySelector(".dropdown-content"), document.querySelector(".filter-dropdown:not(.layers-dropdown)")];

        toggle.addEventListener("click", () => {
            const open = !panel.classList.contains("show");
            others.forEach((el) => el?.classList.remove("show"));
            panel.classList.toggle("show", open);
        });
        document.querySelectorAll(".render-toggle, .filter-toggle").forEach((b) =>
            b.addEventListener("click", () => panel.classList.remove("show")));
        document.addEventListener("click", (e) => {
            if (!toggle.contains(e.target) && !panel.contains(e.target)) panel.classList.remove("show");
        });

        const counts = map.subfloorCounts();
        panel.querySelectorAll("[data-count]").forEach((el) => {
            const n = counts[el.dataset.count] ?? 0;
            el.textContent = `(${n})`;
            if (!n) el.closest(".shuttle-item").hidden = true;
        });

        const inputs = [...panel.querySelectorAll("input[data-layer], input[data-kind]")];
        const apply = () => {
            const opts = { kinds: {} };
            for (const input of inputs) {
                if (input.dataset.layer) opts[input.dataset.layer] = input.checked;
                else opts.kinds[input.dataset.kind] = input.checked;
            }
            // sub-options only make sense while the subfloor is shown
            for (const input of inputs) {
                if (input.dataset.kind || input.dataset.layer === "subfloorOnTop") {
                    input.disabled = !opts.subfloor;
                    input.closest(".shuttle-item").style.opacity = opts.subfloor ? "1" : "0.5";
                }
            }
            map.set(opts);
        };
        inputs.forEach((input) => input.addEventListener("change", apply));
        apply();
    }

    // Names of everything in the tile under the cursor (or under a tap on touch screens).
    function setupHover(map, canvas) {
        const box = document.querySelector(".render-hover");
        if (!box) return;
        let lastPoint = null, downAt = null, frame = 0;

        const hide = () => {
            box.hidden = true;
            lastPoint = null;
            map.setHighlight(null);
        };
        const update = () => {
            frame = 0;
            if (!lastPoint) return;
            const rect = canvas.getBoundingClientRect();
            const tile = map.tileAt(lastPoint.x - rect.left, lastPoint.y - rect.top);
            const items = map.entitiesAt(tile);
            if (!items.length) { box.hidden = true; map.setHighlight(null); return; }
            map.setHighlight(tile);
            box.replaceChildren(...items.map(({ name, count }) => {
                const row = document.createElement("div");
                row.textContent = name;
                if (count > 1) {
                    const n = document.createElement("span");
                    n.className = "render-hover__count";
                    n.textContent = `×${count}`;
                    row.appendChild(n);
                }
                return row;
            }));
            box.hidden = false;
            const bw = box.offsetWidth, bh = box.offsetHeight;
            const left = Math.min(lastPoint.x + 16, window.innerWidth - bw - 8);
            const top = lastPoint.y + 16 + bh > window.innerHeight - 8 ? lastPoint.y - bh - 12 : lastPoint.y + 16;
            box.style.left = `${Math.max(8, left)}px`;
            box.style.top = `${Math.max(8, top)}px`;
        };
        const schedule = () => { if (!frame) frame = requestAnimationFrame(update); };

        renderViewer.addEventListener("pointermove", (e) => {
            if (e.pointerType !== "mouse") return;
            if (e.buttons) { hide(); return; }
            lastPoint = { x: e.clientX, y: e.clientY };
            schedule();
        });
        renderViewer.addEventListener("pointerleave", (e) => { if (e.pointerType === "mouse") hide(); });
        renderViewer.addEventListener("pointerdown", (e) => {
            downAt = { x: e.clientX, y: e.clientY };
            if (e.pointerType !== "mouse") hide();
        });
        renderViewer.addEventListener("pointerup", (e) => {
            if (e.pointerType === "mouse" || !downAt) return;
            if (Math.hypot(e.clientX - downAt.x, e.clientY - downAt.y) < 8) {
                lastPoint = { x: e.clientX, y: e.clientY };
                schedule();
            }
        });
        instance.on("transform", () => { if (lastPoint && !box.hidden) schedule(); });
    }

    // Sprite credits in the info panel.
    function setupAttribution(map) {
        const field = document.querySelector(".render-info-panel__attribution");
        const list = field?.querySelector(".render-info-panel__licenses");
        if (!field || !list) return;
        const licenses = Object.keys(map.licenses()).sort();
        list.replaceChildren(...licenses.map((name) => {
            const tag = document.createElement("span");
            tag.className = "render-info-panel__tags-item";
            tag.textContent = name;
            return tag;
        }));
        field.hidden = false;
    }

    const mapUrl = renderViewer?.dataset.mapUrl;
    const mapCanvasEl = renderViewer?.querySelector(".render-viewer__canvas");

    if (imageContainer && mapUrl && mapCanvasEl) {
        const map = new MapCanvas(mapCanvasEl);
        map.load(mapUrl)
            .then(({ width, height }) => {
                imageContainer.style.width = `${width}px`;
                imageContainer.style.height = `${height}px`;
                startPanzoom(imageContainer);
                instance.on("transform", () => map.render(instance.getTransform()));
                window.addEventListener("resize", () => map.render(instance.getTransform()));
                map.render(instance.getTransform());
                setupLayers(map);
                setupHover(map, mapCanvasEl);
                setupAttribution(map);
                window.hideRenderLoader?.();
            })
            .catch(() => {
                mapCanvasEl.remove();
                document.querySelector(".layers-toggle")?.remove();
                if (renderViewer.dataset.fallbackSrc) {
                    showImage(renderViewer.dataset.fallbackSrc);
                } else {
                    renderViewer.querySelector(".render-viewer__placeholder")?.removeAttribute("hidden");
                    window.hideRenderLoader?.();
                }
            });
    } else if (imageContainer && image) {
        startPanzoom(image);
    }

    const allLayersPresent = Object.values(layers).every(
        (layer) => layer !== null
    );

    if (allLayersPresent) {
        let cameraX = 0;
        let cameraY = 0;
        let cameraScale = 1;
        let scrollTime = 0;
        let lastFrameTime = performance.now();

        const layerConfig = {
            back: {
                slowness: 0.998046875,
                scrolling: [0, 0],
                scale: [1, 1],
            },
            front: {
                slowness: 0.998046875,
                scrolling: [0, 0],
                scale: [1, 1],
            },
            stars1: {
                slowness: 0.996625,
                scrolling: [0, 0],
                scale: [1, 1],
            },
            stars2: {
                slowness: 0.989375,
                scrolling: [0, 0],
                scale: [1, 1],
            },
            meteors1: {
                slowness: 0.97,
                scrolling: [0.0018, 0.001],
                scale: [1.5, 1.5],
            },
            meteors2: {
                slowness: 0.8,
                scrolling: [-0.00075, -0.0009],
                scale: [0.95, 0.95],
            },
        };

        function updateBackgroundAnimation() {
            const currentTime = performance.now();
            const deltaTime = (currentTime - lastFrameTime) / 1000;
            lastFrameTime = currentTime;

            if (instance) {
                const transform = instance.getTransform();
                cameraX = transform.x || 0;
                cameraY = transform.y || 0;
                cameraScale = transform.scale || 1;
            } else {
                cameraX = 0;
                cameraY = 0;
                cameraScale = 1;
            }

            scrollTime += deltaTime;

            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;

            Object.keys(layers).forEach((layerKey) => {
                const layer = layers[layerKey];
                const config = layerConfig[layerKey];

                if (!layer) return;

                const slowness = config.slowness;
                const scrolling = config.scrolling;
                const scale = config.scale;

                const parallaxX = cameraX * (1 - slowness);
                const parallaxY = cameraY * (1 - slowness);

                const scrollX = scrolling[0] * scrollTime * windowWidth;
                const scrollY = scrolling[1] * scrollTime * windowHeight;

                const totalX = parallaxX + scrollX;
                const totalY = parallaxY + scrollY;

                layer.style.backgroundPosition = `${totalX}px ${totalY}px`;

                const baseScale = scale[0];
                const limitedCameraScale = Math.max(
                    1.0,
                    Math.min(2.0, cameraScale)
                );
                const layerScale = baseScale * limitedCameraScale;

                const layerWidth = windowWidth * 2;
                const layerHeight = windowHeight * 2;

                const centerX = -layerWidth / 2 + windowWidth / 2;
                const centerY = -layerHeight / 2 + windowHeight / 2;

                layer.style.transform = `translate(${centerX}px, ${centerY}px) scale(${layerScale})`;
            });

            requestAnimationFrame(updateBackgroundAnimation);
        }

        updateBackgroundAnimation();
    }
});
