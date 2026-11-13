// Draws a map from the render data built by tools/map-renderer onto a canvas.
// The view follows a panzoom transform of a (transparent) element sized like the map at 1:1,
// so panning, zooming and the parallax background behave exactly as with the PNG.
const PPM = 32;          // texture pixels per tile
const CHUNK = 32;        // tiles per cached chunk, rendered at 1:1
const MAX_CHUNKS = 96;
const MARGIN = 4;        // tiles drawn around the tile area for sprites sticking out of the hull

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error(`failed to load ${src}`));
        img.src = src;
    });
}

export class MapCanvas {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.options = { powered: true, subfloor: false, subfloorOnTop: false, kinds: {}, tiles: true, decals: true, objects: true, ...options };
        this.chunks = new Map();
        this.tints = new Map();
        this.transform = { x: 0, y: 0, scale: 1 };
        this.pending = false;
        this.highlight = null;   // [tileLeft, tileBottom] in world units, outlined on top of the map
    }

    /** Loads render data and its atlas; resolves with the map size in pixels at 1:1. */
    async load(url) {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`render data ${response.status}`);
        this.data = await response.json();
        this.atlas = await loadImage(new URL(this.data.atlas, new URL(url, window.location.href)).href);
        const [x0, y0, x1, y1] = this.data.bounds;
        this.origin = [x0, y1];
        return { width: (x1 - x0) * PPM, height: (y1 - y0) * PPM };
    }

    set(options) {
        Object.assign(this.options, options);
        this.chunks.clear();
        this.draw();
    }

    render(transform) {
        this.transform = transform;
        this.draw();
    }

    /** Number of subfloor entities per kind (hv, mv, lv, pipe, disposal, subfloor) for the layer labels. */
    subfloorCounts() {
        const seen = new Map();
        for (const s of this.data.sprites) {
            if (!s[6] || s[10] === 2) continue;
            if (!seen.has(s[7])) seen.set(s[7], new Set());
            seen.get(s[7]).add(s[8]);
        }
        return Object.fromEntries([...seen].map(([k, v]) => [k, v.size]));
    }

    /** Licences of the sprites this map uses, e.g. { "CC-BY-SA-3.0": [rsi, ...] }. */
    licenses() {
        return this.data.licenses ?? {};
    }

    /** Tile under a point given relative to the canvas, as [left, bottom] world coordinates, or null. */
    tileAt(px, py) {
        if (!this.data) return null;
        const { x, y, scale } = this.transform;
        const [ox, oy] = this.origin;
        const wx = ox + (px - x) / (PPM * scale), wy = oy - (py - y) / (PPM * scale);
        const [bx0, by0, bx1, by1] = this.data.bounds;
        if (wx < bx0 - MARGIN || wx > bx1 + MARGIN || wy < by0 - MARGIN || wy > by1 + MARGIN) return null;
        return [bx0 + Math.floor(wx - bx0), by0 + Math.floor(wy - by0)];
    }

    /** Names of the visible entities in a tile, topmost first; repeated names are counted. */
    entitiesAt(tile) {
        if (!tile) return [];
        const [l, b] = tile;
        const order = [], seen = new Set();
        const sprites = this.data.sprites;
        for (let i = sprites.length - 1; i >= 0; i--) {
            const s = sprites[i];
            if (seen.has(s[8]) || !this.visible(s)) continue;
            if (s[0] >= l && s[0] < l + 1 && s[1] >= b && s[1] < b + 1) { seen.add(s[8]); order.push(this.data.entities[s[8]][1]); }
        }
        const counted = new Map();
        for (const name of order) counted.set(name, (counted.get(name) ?? 0) + 1);
        return [...counted].map(([name, count]) => ({ name, count }));
    }

    setHighlight(tile) {
        const same = tile && this.highlight && tile[0] === this.highlight[0] && tile[1] === this.highlight[1];
        if (same || (!tile && !this.highlight)) return;
        this.highlight = tile;
        this.draw();
    }

    draw() {
        if (this.pending || !this.data) return;
        this.pending = true;
        requestAnimationFrame(() => {
            this.pending = false;
            this.paint();
        });
    }

    paint() {
        const { canvas, ctx } = this;
        const dpr = window.devicePixelRatio || 1;
        const W = Math.round(canvas.clientWidth * dpr), H = Math.round(canvas.clientHeight * dpr);
        if (canvas.width !== W || canvas.height !== H) { canvas.width = W; canvas.height = H; }
        const { x, y, scale } = this.transform;
        const tilePx = PPM * scale * dpr;
        const [ox, oy] = this.origin;
        // shared rounded edges: neighbouring chunks meet exactly, so there are no seams at any zoom
        const toX = (wx) => Math.round(x * dpr + (wx - ox) * tilePx);
        const toY = (wy) => Math.round(y * dpr + (oy - wy) * tilePx);
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.globalAlpha = 1;
        ctx.clearRect(0, 0, W, H);
        ctx.imageSmoothingEnabled = tilePx < PPM / 2;   // smooth only when strongly zoomed out

        const [bx0, by0, bx1, by1] = this.data.bounds.map((v, k) => v + (k < 2 ? -MARGIN : MARGIN));
        const wx0 = Math.max(bx0, ox - (x * dpr) / tilePx), wx1 = Math.min(bx1, ox + (W - x * dpr) / tilePx);
        const wy1 = Math.min(by1, oy + (y * dpr) / tilePx), wy0 = Math.max(by0, oy - (H - y * dpr) / tilePx);
        for (let i = Math.floor(wx0 / CHUNK); i <= Math.floor(wx1 / CHUNK); i++) {
            for (let j = Math.floor(wy0 / CHUNK); j <= Math.floor(wy1 / CHUNK); j++) {
                const x0 = toX(i * CHUNK), x1 = toX((i + 1) * CHUNK), y0 = toY((j + 1) * CHUNK), y1 = toY(j * CHUNK);
                if (x1 <= 0 || y1 <= 0 || x0 >= W || y0 >= H) continue;
                ctx.drawImage(this.chunk(i, j), x0, y0, x1 - x0, y1 - y0);
            }
        }
        if (this.highlight) {
            const [l, b] = this.highlight;
            const w = Math.max(1, Math.round(2 * dpr));
            ctx.strokeStyle = '#a29778';
            ctx.lineWidth = w;
            ctx.strokeRect(toX(l) + w / 2, toY(b + 1) + w / 2, toX(l + 1) - toX(l) - w, toY(b) - toY(b + 1) - w);
        }
    }

    chunk(i, j) {
        const key = `${i},${j}`;
        let c = this.chunks.get(key);
        if (!c) {
            c = document.createElement('canvas');
            c.width = c.height = CHUNK * PPM;
            this.renderAt(c.getContext('2d'), i * CHUNK, (j + 1) * CHUNK, c.width, c.height);
            if (this.chunks.size >= MAX_CHUNKS) this.chunks.delete(this.chunks.keys().next().value);
            this.chunks.set(key, c);
        }
        return c;
    }

    visible(s) {
        const o = this.options;
        if (s[10] && (s[10] === 1) !== o.powered) return false;
        if (s[6]) return o.subfloor && o.kinds?.[s[7]] !== false;
        return o.objects;
    }

    frame(fi, color) {
        const [, fx, fy, fw, fh] = this.data.frames[fi];
        if (!color || (color[0] === 1 && color[1] === 1 && color[2] === 1)) return { img: this.atlas, fx, fy, fw, fh };
        const key = `${fi}:${color[0]},${color[1]},${color[2]}`;
        let c = this.tints.get(key);
        if (!c) {
            c = document.createElement('canvas');
            c.width = fw; c.height = fh;
            const g = c.getContext('2d');
            g.drawImage(this.atlas, fx, fy, fw, fh, 0, 0, fw, fh);
            g.globalCompositeOperation = 'multiply';
            g.fillStyle = `rgb(${color[0] * 255},${color[1] * 255},${color[2] * 255})`;
            g.fillRect(0, 0, fw, fh);
            g.globalCompositeOperation = 'destination-in';
            g.drawImage(this.atlas, fx, fy, fw, fh, 0, 0, fw, fh);
            this.tints.set(key, c);
        }
        return { img: c, fx: 0, fy: 0, fw, fh };
    }

    // Draw one quad centred at world (x, y) into a 1:1 canvas whose top-left is world (ox, oy).
    blit(g, ox, oy, W, H, x, y, fi, color, pre) {
        const src = this.frame(fi, color);
        const sx = (x - ox) * PPM, sy = (oy - y) * PPM;
        const half = Math.max(src.fw, src.fh);
        if (sx < -half || sy < -half || sx > W + half || sy > H + half) return;
        g.globalAlpha = color ? color[3] : 1;
        if (!pre) {
            // whole texture pixels: an exact 1:1 copy never samples outside the frame
            g.setTransform(1, 0, 0, 1, 0, 0);
            g.drawImage(src.img, src.fx, src.fy, src.fw, src.fh, Math.round(sx - src.fw / 2), Math.round(sy - src.fh / 2), src.fw, src.fh);
            return;
        }
        g.setTransform(1, 0, 0, 1, Math.round(sx - src.fw / 2) + src.fw / 2, Math.round(sy - src.fh / 2) + src.fh / 2);
        pre(g);
        g.drawImage(src.img, src.fx, src.fy, src.fw, src.fh, -src.fw / 2, -src.fh / 2, src.fw, src.fh);
    }

    renderAt(g, ox, oy, W, H) {
        const { data, options } = this;
        g.setTransform(1, 0, 0, 1, 0, 0);
        g.globalAlpha = 1;
        g.imageSmoothingEnabled = false;
        g.clearRect(0, 0, W, H);
        if (options.tiles) {
            for (const [x, y, gr, rm, fi] of data.tiles) {
                this.blit(g, ox, oy, W, H, x, y, fi, null, (c) => {
                    if (gr) c.rotate(-gr);
                    if (rm > 3) c.scale(-1, 1);
                    if (rm % 4) c.rotate(-(rm % 4) * Math.PI / 2);
                });
            }
        }
        if (options.decals) {
            for (const [x, y, r, fi, color] of data.decals) this.blit(g, ox, oy, W, H, x, y, fi, color, r ? (c) => c.rotate(-r) : null);
        }
        const late = [];
        for (const s of data.sprites) {
            if (!this.visible(s)) continue;
            if (s[6] && options.subfloorOnTop) { late.push(s); continue; }
            this.blit(g, ox, oy, W, H, s[0], s[1], s[3], s[4], spritePre(s));
        }
        for (const s of late) this.blit(g, ox, oy, W, H, s[0], s[1], s[3], s[4], spritePre(s));
        g.setTransform(1, 0, 0, 1, 0, 0);
        g.globalAlpha = 1;
    }
}

// rotation, then per-layer scale (sprite record fields 2 and 11)
function spritePre(s) {
    if (!s[2] && !s[11]) return null;
    return (c) => {
        if (s[2]) c.rotate(-s[2]);
        if (s[11]) c.scale(s[11][0], s[11][1]);
    };
}
