// Software renderer for the static PNG (social previews, search engines, fallback).
// Paints exactly like resources/js/map-canvas.js at 1:1: same order, pixel snapping, nearest-neighbour sampling.
import { PNG } from 'pngjs';
import { PPM } from './render-data.mjs';

const rot = (a) => [Math.cos(a), -Math.sin(a), Math.sin(a), Math.cos(a)];     // canvas rotate(a), y down
const mul = (m, n) => [m[0] * n[0] + m[1] * n[2], m[0] * n[1] + m[1] * n[3], m[2] * n[0] + m[3] * n[2], m[2] * n[1] + m[3] * n[3]];
const IDENTITY = [1, 0, 0, 1];

function blit(dst, atlas, frame, sx, sy, matrix, color) {
    const [, fx, fy, fw, fh] = frame;
    const W = dst.width, H = dst.height, out = dst.data, src = atlas.data, aw = atlas.width;
    const tr = color ? color[0] : 1, tg = color ? color[1] : 1, tb = color ? color[2] : 1, ga = color ? color[3] : 1;
    const left = Math.round(sx - fw / 2), top = Math.round(sy - fh / 2);
    const exact = matrix === IDENTITY;
    let x0, y0, x1, y1, inv = null, cx = 0, cy = 0;
    if (exact) {
        x0 = left; y0 = top; x1 = left + fw; y1 = top + fh;
    } else {
        cx = left + fw / 2; cy = top + fh / 2;
        const [a, b, c, d] = matrix, det = a * d - b * c;
        if (!det) return;
        inv = [d / det, -b / det, -c / det, a / det];
        const xs = [], ys = [];
        for (const [u, v] of [[-fw / 2, -fh / 2], [fw / 2, -fh / 2], [-fw / 2, fh / 2], [fw / 2, fh / 2]]) {
            xs.push(cx + a * u + b * v); ys.push(cy + c * u + d * v);
        }
        x0 = Math.floor(Math.min(...xs)); x1 = Math.ceil(Math.max(...xs));
        y0 = Math.floor(Math.min(...ys)); y1 = Math.ceil(Math.max(...ys));
    }
    for (let py = Math.max(0, y0); py < Math.min(H, y1); py++) {
        for (let px = Math.max(0, x0); px < Math.min(W, x1); px++) {
            let tx, ty;
            if (exact) { tx = px - left; ty = py - top; } else {
                const vx = px + 0.5 - cx, vy = py + 0.5 - cy;
                tx = Math.floor(inv[0] * vx + inv[1] * vy + fw / 2);
                ty = Math.floor(inv[2] * vx + inv[3] * vy + fh / 2);
                if (tx < 0 || ty < 0 || tx >= fw || ty >= fh) continue;
            }
            const si = ((fy + ty) * aw + fx + tx) * 4;
            const sa = (src[si + 3] / 255) * ga;
            if (sa <= 0) continue;
            const di = (py * W + px) * 4;
            const da = out[di + 3] / 255;
            const oa = sa + da * (1 - sa);
            const k = da * (1 - sa);
            out[di] = Math.round((src[si] * tr * sa + out[di] * k) / oa);
            out[di + 1] = Math.round((src[si + 1] * tg * sa + out[di + 1] * k) / oa);
            out[di + 2] = Math.round((src[si + 2] * tb * sa + out[di + 2] * k) / oa);
            out[di + 3] = Math.round(oa * 255);
        }
    }
}

/** Render the tile area of a map (powered, subfloor hidden) and return PNG bytes. */
export function renderPreview(data, atlas) {
    const [x0, y0, x1, y1] = data.bounds;
    const W = Math.round((x1 - x0) * PPM), H = Math.round((y1 - y0) * PPM);
    const img = new PNG({ width: W, height: H });
    const at = (x, y) => [(x - x0) * PPM, (y1 - y) * PPM];

    for (const [x, y, gr, rm, fi] of data.tiles) {
        let m = gr ? rot(-gr) : IDENTITY;
        if (rm > 3) m = mul(m, [-1, 0, 0, 1]);
        if (rm % 4) m = mul(m, rot((-(rm % 4) * Math.PI) / 2));
        blit(img, atlas, data.frames[fi], ...at(x, y), m, null);
    }
    for (const [x, y, r, fi, color] of data.decals) blit(img, atlas, data.frames[fi], ...at(x, y), r ? rot(-r) : IDENTITY, color);
    for (const s of data.sprites) {
        if (s[6] || s[10] === 2) continue;   // no subfloor, powered variant
        let m = s[2] ? rot(-s[2]) : IDENTITY;
        if (s[11]) m = mul(m, [s[11][0], 0, 0, s[11][1]]);
        blit(img, atlas, data.frames[s[3]], ...at(s[0], s[1]), m, s[4]);
    }
    return PNG.sync.write(img, { deflateLevel: 6 });
}
