// Packs every frame a map uses into one PNG. Each frame gets a 2px gutter filled with its own edge pixels,
// so GPU sampling just outside a frame never picks up a neighbouring one.
import fs from 'node:fs';
import path from 'node:path';
import { PNG } from 'pngjs';

const MAX_SIDE = 4096;
const PAD = 2;

const decoded = new Map();
export function readPng(file) {
    if (!decoded.has(file)) {
        let buf = fs.readFileSync(file);
        // some game PNGs carry bytes after IEND; browsers ignore them, pngjs does not
        const iend = buf.lastIndexOf(Buffer.from('IEND'));
        if (iend > 0 && iend + 8 < buf.length) buf = buf.subarray(0, iend + 8);
        decoded.set(file, PNG.sync.read(buf));
    }
    return decoded.get(file);
}

/** frames: [textureRel, x, y, w, h][] -> { png: Buffer, frames: [0, x, y, w, h][] } */
export function packAtlas(texRoot, frames) {
    const order = frames.map((_, i) => i).sort((a, b) => frames[b][4] - frames[a][4] || frames[b][3] - frames[a][3]);
    const area = frames.reduce((s, f) => s + (f[3] + 2 * PAD) * (f[4] + 2 * PAD), 0);
    let width = 256;
    while (width * width < area * 1.1 && width < MAX_SIDE) width *= 2;

    const placed = new Array(frames.length);
    let x = 0, y = 0, shelf = 0;
    for (const i of order) {
        const w = frames[i][3] + 2 * PAD, h = frames[i][4] + 2 * PAD;
        if (x + w > width) { x = 0; y += shelf; shelf = 0; }
        placed[i] = [x + PAD, y + PAD];
        x += w;
        shelf = Math.max(shelf, h);
    }
    const height = Math.max(1, y + shelf);
    if (height > MAX_SIDE) throw new Error(`atlas would be taller than ${MAX_SIDE}px`);

    const atlas = new PNG({ width, height });
    frames.forEach(([rel, fx, fy, w, h], i) => {
        const src = readPng(path.join(texRoot, rel));
        const [ax, ay] = placed[i];
        PNG.bitblt(src, atlas, fx, fy, Math.min(w, src.width - fx), Math.min(h, src.height - fy), ax, ay);
        for (let k = 1; k <= PAD; k++) {
            PNG.bitblt(atlas, atlas, ax, ay, w, 1, ax, ay - k);
            PNG.bitblt(atlas, atlas, ax, ay + h - 1, w, 1, ax, ay + h - 1 + k);
        }
        for (let k = 1; k <= PAD; k++) {
            PNG.bitblt(atlas, atlas, ax, ay - PAD, 1, h + 2 * PAD, ax - k, ay - PAD);
            PNG.bitblt(atlas, atlas, ax + w - 1, ay - PAD, 1, h + 2 * PAD, ax + w - 1 + k, ay - PAD);
        }
    });
    return {
        png: PNG.sync.write(atlas, { deflateLevel: 9, filterType: -1 }),
        frames: frames.map((f, i) => [0, placed[i][0], placed[i][1], f[3], f[4]]),
        image: atlas,
        width, height,
    };
}
