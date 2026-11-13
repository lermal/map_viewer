import panzoom from "panzoom";
import { MapCanvas } from "./map-canvas";
import { startBackground } from "./background";

document.addEventListener("DOMContentLoaded", function () {
    const imageContainer = document.querySelector(
        ".render-viewer__image-container"
    );
    const image = document.querySelector(".render-viewer__image");

    let instance = null;
    const background = startBackground(() => instance?.getTransform());
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
        instance.on("transform", () => background.refresh());

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
});
