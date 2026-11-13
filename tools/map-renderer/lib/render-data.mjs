// Turns a map file into a draw list (tiles, decals, sprite layers) the browser and the PNG renderer can paint.
// The rules mirror Content.MapRenderer and the client visualizers of the game; see the design notes in README.md.
import fs from 'node:fs';
import path from 'node:path';
import { loadYaml, truthy } from './game.mjs';

export const PPM = 32;
// 6: grids are found by their MapGrid component; 7: listed under `grids:`
export const SUPPORTED_FORMATS = [6, 7];

const DRAW_DEPTH = {
    LowFloors: -20, ThickPipe: -19, ThickWire: -18, ThinPipeAlt2: -17, ThinPipeAlt1: -16,
    ThinPipe: -15, ThinWire: -14, BelowFloor: -13, FloorTiles: -12, FloorObjects: -11,
    Puddles: -10, HighFloorObjects: -5, DeadMobs: -4, SmallMobs: -3, Walls: -2,
    WallTops: -1, Objects: 0, Default: 0, SmallObjects: 1, WallMountedItems: 2,
    LargeObjects: 3, Items: 4, BelowMobs: 5, Mobs: 6, OverMobs: 7, Doors: 8,
    BlastDoors: 9, Overdoors: 10, Effects: 11, Ghosts: 12, Overlays: 13,
};
const depthOf = (v, fallback) => (v === undefined || v === null ? fallback : DRAW_DEPTH[String(v)] ?? (Number.isInteger(v) ? v : fallback));

// Layers that client visualizers keep hidden while the entity is idle (not welded, panel closed, ...)
const HIDDEN_BY_DEFAULT = new Set([
    'enum.WiresVisualLayers.MaintenancePanel', 'enum.WeldableLayers.BaseWelded',
    'enum.DoorVisualLayers.BaseBolted', 'enum.DoorVisualLayers.BaseEmergencyAccess',
    'enum.DisposalUnitVisualLayers.Unanchored', 'enum.DisposalUnitVisualLayers.BaseCharging',
    'enum.DisposalUnitVisualLayers.OverlayFlush', 'enum.DisposalUnitVisualLayers.OverlayCharging',
    'enum.DisposalUnitVisualLayers.OverlayReady', 'enum.DisposalUnitVisualLayers.OverlayFull',
    'enum.DisposalUnitVisualLayers.OverlayEngaged',
]);
const POWER_KEYS = ['enum.PowerDeviceVisuals.Powered', 'enum.ComputerVisuals.Powered'];

// PipeDirection flags (Content.Shared/Atmos/PipeDirection.cs)
const PN = 1, PS = 2, PW = 4, PE = 8;
const PIPE_DIRS = {
    None: 0, North: PN, South: PS, West: PW, East: PE, Longitudinal: PN | PS, Lateral: PW | PE,
    NWBend: PN | PW, NEBend: PN | PE, SWBend: PS | PW, SEBend: PS | PE,
    TNorth: PN | PW | PE, TSouth: PS | PW | PE, TWest: PW | PN | PS, TEast: PE | PN | PS,
    Fourway: PN | PS | PW | PE, All: PN | PS | PW | PE,
};
const PIPE_STEP = [[PN, 0, 1], [PS, 0, -1], [PW, -1, 0], [PE, 1, 0]];
const PIPE_OPPOSITE = { [PN]: PS, [PS]: PN, [PW]: PE, [PE]: PW };
const PIPE_CCW = [PS, PE, PN, PW];    // counter-clockwise starting at angle 0 (south)
const PIPE_RSI = { [PS]: 0, [PN]: 1, [PE]: 2, [PW]: 3 };
const PIPE_LAYERS = { Primary: 0, Secondary: 1, Tertiary: 2 };
const PIPE_LAYER_NAMES = ['Primary', 'Secondary', 'Tertiary'];
// pipe layers appear both by name (`Secondary`) and by number (`1`) in prototypes and maps
const pipeLayerOf = (v, fallback = 0) => {
    if (v === undefined || v === null) return fallback;
    if (Number.isInteger(v)) return v;
    return PIPE_LAYERS[String(v)] ?? (/^\d+$/.test(String(v)) ? Number(v) : fallback);
};
const PIPE_CONNECTOR_RSI = ['Structures/Piping/Atmospherics/pipe.rsi', 'Structures/Piping/Atmospherics/pipe_alt1.rsi',
    'Structures/Piping/Atmospherics/pipe_alt2.rsi'];

// RsiDirection index (S=0, N=1, E=2, W=3, SE=4, SW=5, NE=6, NW=7) for Direction (S=0 .. SW=7)
const DIR4 = { 0: 0, 2: 2, 4: 1, 6: 3 };
const DIR8 = { 0: 0, 1: 4, 2: 2, 3: 6, 4: 1, 5: 7, 6: 3, 7: 5 };
const OFFSETS = {
    None: [0, 1, 2, 3],
    Clockwise: [3, 2, 0, 1],        // S->W, N->E, E->S, W->N
    CounterClockwise: [2, 3, 1, 0], // S->E, N->W, E->N, W->S
    Flip: [1, 0, 3, 2],
};

const TAU = 2 * Math.PI;
const mod = (a, n) => ((a % n) + n) % n;
const cardinalDir = (t) => (Math.floor((mod(t, TAU) + Math.PI / 4) / (Math.PI / 2)) * 2) % 8;
const anyDir = (t) => Math.floor((mod(t, TAU) + Math.PI / 8) / (Math.PI / 4)) % 8;
const r4 = (v) => Math.round(v * 1e4) / 1e4;

function parseAngle(v) {
    if (v === undefined || v === null) return 0;
    const s = String(v).trim();
    if (s.endsWith('rad')) return parseFloat(s);
    if (s.endsWith('deg')) return (parseFloat(s) * Math.PI) / 180;
    return parseFloat(s) || 0;
}

function parseVec(v, fallback = [0, 0]) {
    if (v === undefined || v === null) return fallback;
    if (Array.isArray(v)) return [Number(v[0]), Number(v[1])];
    const [a, b] = String(v).split(',');
    return [parseFloat(a), parseFloat(b)];
}

const mulColor = (a, b) => (!a ? b : !b ? a : a.map((x, i) => x * b[i]));

function pngWidth(file) {
    const fd = fs.openSync(file, 'r');
    const buf = Buffer.alloc(24);
    fs.readSync(fd, buf, 0, 24, 0);
    fs.closeSync(fd);
    return [buf.readUInt32BE(16), buf.readUInt32BE(20)];
}

/** Collects the texture frames a map uses; the atlas packer turns them into one image. */
class Frames {
    constructor(game) {
        this.game = game;
        this.list = [];        // [textureRel, x, y, w, h]
        this.index = new Map();
        this.licenses = new Map();
        this.missing = new Set();
    }

    frame(rel, x, y, w, h) {
        const key = `${rel}|${x}|${y}|${w}|${h}`;
        if (!this.index.has(key)) { this.index.set(key, this.list.length); this.list.push([rel, x, y, w, h]); }
        return this.index.get(key);
    }

    stateDirs(rsi, state) {
        const { meta } = this.game.rsi(rsi);
        return meta?.byName.get(state)?.directions ?? (meta?.byName.has(state) ? 1 : 0);
    }

    rsiFrame(rsi, state, rsidir) {
        const { key, meta } = this.game.rsi(rsi);
        const st = meta?.byName.get(state);
        if (!st) { this.missing.add(`${key}:${state}`); return null; }
        const ndirs = st.directions ?? 1;
        if (rsidir >= ndirs) rsidir = 0;
        let index = 0;
        for (let d = 0; d < rsidir; d++) index += st.delays?.[d]?.length ?? 1;
        const rel = `${key}/${state}.png`;
        const file = path.join(this.game.tex, rel);
        if (!fs.existsSync(file)) { this.missing.add(`${key}:${state}`); return null; }
        const sx = meta.size.x, sy = meta.size.y;
        const cols = Math.max(1, Math.floor(pngWidth(file)[0] / sx));
        if (meta.license) {
            if (!this.licenses.has(meta.license)) this.licenses.set(meta.license, new Set());
            this.licenses.get(meta.license).add(key);
        }
        return this.frame(rel, (index % cols) * sx, Math.floor(index / cols) * sy, sx, sy);
    }

    texture(rel) {
        const r = this.game.texPath(rel);
        const file = path.join(this.game.tex, r);
        if (!fs.existsSync(file)) { this.missing.add(r); return null; }
        const [w, h] = pngWidth(file);
        return this.frame(r, 0, 0, w, h);
    }
}

function decodeTiles(chunks, tilemap) {
    const out = new Map();
    for (const [key, ch] of Object.entries(chunks ?? {})) {
        const [cx, cy] = String(ch.ind ?? key).split(',').map(Number);
        const raw = Buffer.from(ch.tiles, 'base64');
        const ver = Number(ch.version ?? 1);
        const size = ver >= 7 ? 7 : ver >= 6 ? 6 : 4;
        const n = Math.floor(raw.length / size);
        const cs = Math.round(Math.sqrt(n));
        for (let i = 0; i < n; i++) {
            const o = i * size;
            const tid = ver >= 6 ? raw.readInt32LE(o) : raw.readUInt16LE(o);
            const p = ver >= 6 ? 4 : 2;
            const name = tilemap.get(tid);
            if (!name || name === 'Space') continue;
            out.set(`${cx * cs + (i % cs)},${cy * cs + Math.floor(i / cs)}`, [name, raw[o + p + 1], ver >= 7 ? raw[o + p + 2] : 0]);
        }
    }
    return out;
}

const keysOf = (layer) => new Set([].concat(layer.map ?? []));

/**
 * Build the draw list for one map file.
 * Returns { data, frames } where data.frames still refers to game textures (the atlas step rewrites it).
 */
export function buildRenderData(game, mapFile) {
    const doc = loadYaml(mapFile);
    const format = Number(doc?.meta?.format);
    if (!SUPPORTED_FORMATS.includes(format)) throw new Error(`unsupported map format ${doc?.meta?.format}`);

    const tilemap = new Map(Object.entries(doc.tilemap ?? {}).map(([k, v]) => [Number(k), v]));
    const fr = new Frames(game);
    const TILE = game.all('tile'), DECAL = game.all('decal'), STACK = game.all('stack');

    // ---- entities with merged components
    const ents = new Map();
    for (const group of doc.entities ?? []) {
        const proto = group.proto ?? '';
        const base = proto ? game.entity(proto) : { comps: {}, name: null };
        const name = proto ? game.displayName(proto, base.name) : '';
        for (const e of group.entities ?? []) {
            const comps = {};
            for (const [k, v] of Object.entries(base.comps)) comps[k] = { ...v };
            for (const c of e.components ?? []) if (c && typeof c === 'object' && c.type) comps[c.type] = { ...(comps[c.type] ?? {}), ...c };
            ents.set(e.uid, { uid: e.uid, proto, name, c: comps });
        }
    }

    const gridUids = new Set(doc.grids ?? [...ents.values()].filter((e) => e.c.MapGrid).map((e) => e.uid));

    // contained entities are hidden unless the container shows them
    const hidden = new Set();
    for (const e of ents.values()) {
        for (const cont of Object.values(e.c.ContainerContainer?.containers ?? {})) {
            if (!cont || typeof cont !== 'object' || truthy(cont.showEnts)) continue;
            for (const u of cont.ents ?? []) hidden.add(u);
            if (cont.ent !== undefined && cont.ent !== null && cont.ent !== 'invalid') hidden.add(cont.ent);
        }
    }

    // ---- transforms
    const localXf = (e) => {
        const x = e.c.Transform ?? {};
        const [px, py] = parseVec(x.pos);
        return [px, py, parseAngle(x.rot), x.parent];
    };
    const world = new Map();
    const worldXf = (uid, depth = 0) => {
        if (world.has(uid)) return world.get(uid);
        const e = ents.get(uid);
        if (!e || depth > 32) return [0, 0, 0, null];
        const [px, py, rot, parent] = localXf(e);
        const grid = gridUids.has(uid) ? uid : null;
        let res;
        if (parent === undefined || parent === null || parent === 'invalid' || !ents.has(parent)) {
            res = [px, py, rot, grid];
        } else {
            const [ppx, ppy, prot, pgrid] = worldXf(parent, depth + 1);
            const c = Math.cos(prot), s = Math.sin(prot);
            res = [ppx + c * px - s * py, ppy + s * px + c * py, prot + rot, grid ?? pgrid];
        }
        world.set(uid, res);
        return res;
    };
    const gridLocal = (uid) => {
        const [px, py, rot, parent] = localXf(ents.get(uid));
        if (gridUids.has(parent)) return [parent, px, py, rot];
        const [wx, wy, wr, g] = worldXf(uid);
        if (g === null) return [null, wx, wy, wr];
        const [gx, gy, gr] = worldXf(g);
        const c = Math.cos(-gr), s = Math.sin(-gr), dx = wx - gx, dy = wy - gy;
        return [g, c * dx - s * dy, s * dx + c * dy, wr - gr];
    };

    // ---- tiles per grid
    const gridTiles = new Map();
    for (const g of gridUids) gridTiles.set(g, decodeTiles(ents.get(g)?.c.MapGrid?.chunks, tilemap));
    const isSubfloor = (g, tx, ty) => {
        const t = gridTiles.get(g)?.get(`${tx},${ty}`);
        return t === undefined ? true : truthy(TILE.get(t[0])?.isSubfloor ?? false);
    };

    // ---- anchored occupancy for smoothing and connectivity
    const occ = new Map();
    const info = new Map();
    const occAt = (g, x, y) => occ.get(`${g}|${x}|${y}`) ?? [];
    for (const [uid, e] of ents) {
        if (gridUids.has(uid) || hidden.has(uid)) continue;
        const [g, lx, ly, lrot] = gridLocal(uid);
        const anchored = truthy(e.c.Transform?.anchored ?? false);
        const tx = Math.floor(lx), ty = Math.floor(ly);
        info.set(uid, { g, lx, ly, lrot, anchored, tx, ty });
        if (anchored && g !== null) {
            const k = `${g}|${tx}|${ty}`;
            if (!occ.has(k)) occ.set(k, []);
            occ.get(k).push(uid);
        }
    }

    const smoothMatch = (smooth, g, x, y) => occAt(g, x, y).some((o) => {
        const os = ents.get(o).c.IconSmooth;
        if (!os || os.key === undefined || os.key === null || String(os.enabled ?? true).toLowerCase() === 'false') return false;
        return os.key === smooth.key || (smooth.additionalKeys ?? []).includes(os.key);
    });

    const rotatePipe = (mask, rot) => {
        const k = mod(Math.round(rot / (Math.PI / 2)), 4);
        let out = 0;
        PIPE_CCW.forEach((d, i) => { if (mask & d) out |= PIPE_CCW[(i + k) % 4]; });
        return out;
    };
    const pipeNodes = new Map();
    for (const [uid, { g, lrot, anchored }] of info) {
        if (!anchored || g === null) continue;
        const c = ents.get(uid).c;
        const defaultLayer = pipeLayerOf(c.AtmosPipeLayers?.pipeLayer);
        const list = [];
        for (const nd of Object.values(c.NodeContainer?.nodes ?? {})) {
            if (!nd || typeof nd !== 'object' || nd.pipeDirection === undefined) continue;
            if (String(nd.connectionsEnabled ?? true).toLowerCase() === 'false') continue;
            let mask = PIPE_DIRS[String(nd.pipeDirection)] ?? (Number.isInteger(nd.pipeDirection) ? nd.pipeDirection : 0);
            if (String(nd.rotationsEnabled ?? true).toLowerCase() !== 'false') mask = rotatePipe(mask, lrot);
            // the server moves every pipe node to the component's layer on startup (AtmosPipeLayersSystem)
            list.push([nd.nodeGroupID ?? 'Pipe', c.AtmosPipeLayers ? defaultLayer : pipeLayerOf(nd.pipeLayer), mask]);
        }
        if (list.length) pipeNodes.set(uid, list);
    }
    const pipeConnections = (uid, g, tx, ty) => {
        const conn = new Map();
        for (const [group, layer, mask] of pipeNodes.get(uid) ?? []) {
            for (const [d, dx, dy] of PIPE_STEP) {
                if (!(mask & d)) continue;
                const linked = occAt(g, tx + dx, ty + dy).some((o) =>
                    (pipeNodes.get(o) ?? []).some(([og, ol, om]) => og === group && ol === layer && (om & PIPE_OPPOSITE[d])));
                if (linked) conn.set(layer, (conn.get(layer) ?? 0) | d);
            }
        }
        return conn;
    };

    const cableKind = (e) => e.c.CableVisualizer?.statePrefix ?? null;
    const cableMask = (e, g, tx, ty) => {
        const kind = cableKind(e);
        let mask = 0;
        for (const [bit, dx, dy] of [[1, 0, 1], [2, 0, -1], [4, 1, 0], [8, -1, 0]]) {
            if (occAt(g, tx + dx, ty + dy).some((o) => cableKind(ents.get(o)) === kind)) mask |= bit;
        }
        return mask;
    };

    // ---- per-entity layer emulation
    const entityLayers = (uid, e, powered) => {
        const c = e.c, sp = c.Sprite;
        const { g, lx, ly, lrot, anchored, tx, ty } = info.get(uid);
        const [wx, wy, wrot] = worldXf(uid);
        let baseRsi = sp.sprite;
        let layers = [];
        if (Array.isArray(sp.layers) && sp.layers.length) {
            layers = sp.layers.filter((l) => l && typeof l === 'object').map((l) => ({ ...l }));
        } else if (sp.state || sp.texture) {
            layers.push({ state: sp.state, texture: sp.texture });
        }
        const setLayers = (key, fields) => { for (const l of layers) if (keysOf(l).has(key)) Object.assign(l, fields); };

        // pipe layer sprites (AtmosPipeLayersSystem.SetPipeLayer on startup): alternative layers use shifted RSIs
        const apl = c.AtmosPipeLayers;
        if (apl) {
            const idx = pipeLayerOf(apl.pipeLayer);
            const pick = (paths) => paths?.[PIPE_LAYER_NAMES[idx]] ?? paths?.[idx];
            const baseOverride = pick(apl.spriteRsiPaths);
            if (baseOverride) baseRsi = baseOverride;
            for (const [layerKey, paths] of Object.entries(apl.spriteLayersRsiPaths ?? {})) {
                const rsi = pick(paths);
                if (rsi) setLayers(layerKey, { sprite: rsi });
            }
        }

        for (const l of layers) if ([...keysOf(l)].some((k) => HIDDEN_BY_DEFAULT.has(k))) l.visible = false;

        const gv = c.GenericVisualizer?.visuals ?? {};
        if (Object.keys(gv).length) {
            const values = {
                'enum.DisposalTubeVisuals.VisualState': anchored ? 'Anchored' : 'Free',
                'enum.PowerDeviceVisuals.Powered': powered,
                'enum.ComputerVisuals.Powered': powered,
                'enum.PowerDeviceVisuals.VisualState': 'Idle',
                'enum.FoldedVisuals.State': truthy(c.Foldable?.folded ?? false),
            };
            // SharedEntityStorageSystem reports open/closed on startup; charger status (CellVisual) only comes later
            if (c.EntityStorage) values['enum.StorageVisuals.Open'] = truthy(c.EntityStorage.open ?? false);
            if ('enum.AmeShieldVisuals.Core' in gv && g !== null) {
                // AME: a shield surrounded by shields on all 8 sides is a core (AmeNodeGroup)
                let core = true;
                for (let dx = -1; dx <= 1; dx++) for (let dy = -1; dy <= 1; dy++) {
                    if ((dx || dy) && !occAt(g, tx + dx, ty + dy).some((o) => ents.get(o).proto === e.proto)) core = false;
                }
                values['enum.AmeShieldVisuals.Core'] = core;
                values['enum.AmeShieldVisuals.CoreState'] = core ? 'Weak' : 'Off';
            }
            for (const [vkey, perLayer] of Object.entries(gv)) {
                if (!(vkey in values) || !perLayer || typeof perLayer !== 'object') continue;
                const val = String(values[vkey]);
                for (const [lkey, options] of Object.entries(perLayer)) {
                    const data = options?.[val] ?? options?.[val.toLowerCase()];
                    if (data && typeof data === 'object') {
                        const f = {};
                        for (const n of ['state', 'visible', 'color']) if (n in data) f[n] = data[n];
                        setLayers(lkey, f);
                    }
                }
            }
        }

        let depthOverride = null;
        if (c.Door) {
            const open = String(c.Door.state ?? 'Closed') === 'Open';
            setLayers('enum.DoorVisualLayers.Base', { state: open ? (c.Door.openSpriteState ?? 'open') : (c.Door.closedSpriteState ?? 'closed') });
            depthOverride = depthOf(open ? c.Door.openDrawDepth : c.Door.closedDrawDepth, DRAW_DEPTH.Doors);
        }

        const esv = c.EntityStorageVisuals;
        if (esv) {
            const open = truthy(c.EntityStorage?.open ?? false);
            const base = open ? (esv.stateBaseOpen ?? esv.stateBaseClosed) : esv.stateBaseClosed;
            if (base) setLayers('enum.StorageVisualLayers.Base', { state: base });
            const door = open ? esv.stateDoorOpen : esv.stateDoorClosed;
            setLayers('enum.StorageVisualLayers.Door', door ? { state: door, visible: true } : { visible: false });
            const dd = open ? esv.openDrawDepth : esv.closedDrawDepth;
            if (dd !== undefined && dd !== null) depthOverride = depthOf(dd, depthOverride);
        }

        if (c.Paper) {
            if (String(c.Paper.content ?? '').trim()) setLayers('enum.PaperVisualLayers.Writing', { visible: true });
            if (c.Paper.stampState) setLayers('enum.PaperVisualLayers.Stamp', { state: c.Paper.stampState, visible: true });
        }

        const stack = c.Stack;
        if (stack?.layerStates && !truthy(stack.composite ?? false)) {
            const states = [...stack.layerStates];
            let actual = Number(stack.count ?? 30);
            let max = Number(stack.maxCountOverride ?? STACK.get(String(stack.stackType))?.maxCount ?? 2 ** 31 - 1);
            if (stack.layerFunction === 'Threshold') {
                const thresholds = c.StackLayerThreshold?.thresholds ?? [];
                max = Math.min(thresholds.length + 1, max);
                actual = Math.min(thresholds.filter((t) => actual >= Number(t)).length, max);
            }
            if (states.length > 1) {
                const idx = actual >= max ? states.length - 1 : actual <= 0 ? 0 : Math.trunc((actual / max) * states.length);
                setLayers(stack.baseLayer ?? '', { state: states[idx] });
            }
        }

        // thrusters glow while enabled: anchored, powered and, unless exempt, with open space behind the nozzle
        // (ThrusterSystem.CanEnable / NozzleExposed); without that the server never sends the visual state
        if (c.Thruster && powered && anchored && g !== null && truthy(c.Thruster.enabled ?? true)) {
            let on = true;
            if (truthy(c.Thruster.requireSpace ?? true)) {
                const nx = Math.floor(lx - Math.sin(lrot)), ny = Math.floor(ly + Math.cos(lrot));
                on = !gridTiles.get(g)?.has(`${nx},${ny}`);
            }
            if (on) setLayers('enum.ThrusterVisualLayers.ThrustOn', { visible: true });
        }

        if (c.ApcVisuals) {
            const prefix = c.ApcVisuals.screenPrefix ?? 'display';
            const suffixes = c.ApcVisuals.screenSuffixes ?? ['lack', 'charging', 'full', 'remote'];
            setLayers('enum.ApcVisualLayers.ChargeState', { state: `${prefix}-${suffixes[powered ? 2 : 0]}` });
        }

        const vm = c.VendingMachine;
        if (vm) {
            setLayers('enum.VendingMachineVisualLayers.Base', { state: vm.offState ?? 'off', visible: true });
            if (vm.normalState ?? 'normal-unshaded') setLayers('enum.VendingMachineVisualLayers.BaseUnshaded', { state: vm.normalState ?? 'normal-unshaded', visible: true });
            if (vm.screenState) setLayers('enum.VendingMachineVisualLayers.Screen', { state: vm.screenState, visible: true });
        }

        const smooth = c.IconSmooth;
        if (smooth && String(smooth.enabled ?? true).toLowerCase() !== 'false' && g !== null) {
            const mode = smooth.mode ?? 'Corners', sbase = smooth.base ?? '';
            const m = (dx, dy) => smoothMatch(smooth, g, tx + dx, ty + dy);
            if (mode === 'Corners') {
                const n = m(0, 1), ne = m(1, 1), ee = m(1, 0), se = m(1, -1), s = m(0, -1), sw = m(-1, -1), w = m(-1, 0), nw = m(-1, 1);
                let cNE = (n ? 1 : 0) | (ne ? 2 : 0) | (ee ? 4 : 0);
                let cSE = (ee ? 1 : 0) | (se ? 2 : 0) | (s ? 4 : 0);
                let cSW = (s ? 1 : 0) | (sw ? 2 : 0) | (w ? 4 : 0);
                let cNW = (w ? 1 : 0) | (nw ? 2 : 0) | (n ? 4 : 0);
                const d = cardinalDir(lrot);
                if (d === 4) [cNE, cNW, cSW, cSE] = [cSW, cSE, cNE, cNW];
                else if (d === 6) [cNE, cNW, cSW, cSE] = [cSE, cNE, cNW, cSW];
                else if (d === 2) [cNE, cNW, cSW, cSE] = [cNW, cSW, cSE, cNE];
                for (const [fill, off] of [[cSE, 'None'], [cNE, 'CounterClockwise'], [cNW, 'Flip'], [cSW, 'Clockwise']]) {
                    layers.push({ state: `${sbase}${fill}`, _diroff: off });
                }
            } else if (mode === 'CardinalFlags' && layers.length) {
                layers[0].state = `${sbase}${(m(0, 1) ? 1 : 0) | (m(0, -1) ? 2 : 0) | (m(1, 0) ? 4 : 0) | (m(-1, 0) ? 8 : 0)}`;
            } else if ((mode === 'Diagonal' || mode === 'DiagonalNF') && layers.length) {
                const cr = Math.cos(lrot), sr = Math.sin(lrot);
                const rot = (vx, vy) => [Math.round(cr * vx - sr * vy), Math.round(sr * vx + cr * vy)];
                const a = m(...rot(1, 0)), b = m(...rot(0, -1)), dg = m(...rot(1, -1));
                if (mode === 'Diagonal') layers[0].state = `${sbase}${a && b && dg ? 1 : 0}`;
                else {
                    let v = (a ? 1 : 0) | (b ? 2 : 0);
                    if (v === 3 && dg) v = 4;
                    layers[0].state = `${sbase}${v}`;
                }
            }
        }

        const cv = c.CableVisualizer;
        if (cv && layers.length && g !== null) {
            const mask = cableMask(e, g, tx, ty);
            layers[0].state = `${cv.statePrefix ?? ''}${mask}`;
            if (cv.extraLayerPrefix && layers.length > 1) layers[1].state = `${cv.extraLayerPrefix}${mask}`;
        }

        const pipeColor = c.AtmosPipeColor ? game.color(c.AtmosPipeColor.color) : null;
        if (c.PipeAppearance && g !== null && pipeNodes.has(uid)) {
            const cfg = c.PipeAppearance.sprite;
            const nLayers = c.AtmosPipeLayers ? 3 : 1;
            const gridRot = wrot - lrot;
            for (const [layerIdx, mask] of [...pipeConnections(uid, g, tx, ty)].sort((a, b) => a[0] - b[0])) {
                if (layerIdx >= nLayers) continue;
                const spec = Array.isArray(cfg) && cfg[layerIdx] && typeof cfg[layerIdx] === 'object' ? cfg[layerIdx] : {};
                for (const d of [PS, PN, PE, PW]) {
                    if (!(mask & d)) continue;
                    layers.push({
                        sprite: spec.sprite ?? PIPE_CONNECTOR_RSI[layerIdx], state: spec.state ?? 'pipeConnector',
                        _rsidir: PIPE_RSI[rotatePipe(d, gridRot)], _color: pipeColor ? [pipeColor[0], pipeColor[1], pipeColor[2], 1] : null,
                        map: [`pipeConnector${d}`],
                    });
                }
            }
        }

        const sub = c.SubFloorHide;
        const covered = !!sub && anchored && g !== null && !isSubfloor(g, tx, ty);
        const visibleKeys = new Set(sub?.visibleLayers ?? []);
        let kind = 'other';
        if (cv) kind = { hvcable_: 'hv', mvcable_: 'mv', lvcable_: 'lv' }[cv.statePrefix] ?? 'cable';
        else if (c.AtmosPipeColor || c.PipeAppearance) kind = 'pipe';
        else if (c.DisposalTube || (e.proto ?? '').includes('Disposal')) kind = 'disposal';
        else if (sub) kind = 'subfloor';

        let depth = depthOf(sp.drawdepth ?? 'Objects', 0);
        if (depthOverride !== null) depth = depthOverride;
        const spriteColor = game.color(sp.color);
        const [sox, soy] = parseVec(sp.offset);
        const noRot = truthy(sp.noRot ?? false), snap = truthy(sp.snapCardinals ?? false);
        const [ssx, ssy] = parseVec(sp.scale, [1, 1]);

        const out = [];
        layers.forEach((layer, li) => {
            if (String(layer.visible ?? true).toLowerCase() === 'false') return;
            const lkeys = keysOf(layer);
            const subLayer = covered && ![...lkeys].some((k) => visibleKeys.has(k));
            let color = mulColor(spriteColor, game.color(layer.color));
            if ('_color' in layer) color = layer._color;   // connectors get the pipe colour assigned, not multiplied
            if (pipeColor && lkeys.has('enum.PipeVisualLayers.Pipe')) color = mulColor(color, [pipeColor[0], pipeColor[1], pipeColor[2], 1]);
            const [lox, loy] = parseVec(layer.offset);
            const [lsx, lsy] = parseVec(layer.scale, [1, 1]);
            const scale = [r4(ssx * lsx), r4(ssy * lsy)];
            let frame = null, rotate = 0;
            if (layer.texture && !layer.state) {
                frame = fr.texture(layer.texture);
                rotate = noRot || snap ? 0 : wrot;
            } else {
                const rsi = layer.sprite ?? baseRsi;
                const state = layer.state;
                if (!rsi || state === undefined || state === null || state === '') return;
                const ndirs = fr.stateDirs(rsi, String(state));
                if (!ndirs) { fr.missing.add(`${game.texPath(rsi)}:${state}`); return; }
                let rsidir;
                if (ndirs === 1) rsidir = 0;
                else if ('_rsidir' in layer) rsidir = layer._rsidir;
                else if (ndirs === 4) rsidir = OFFSETS[layer._diroff ?? 'None'][DIR4[cardinalDir(wrot)]];
                else rsidir = DIR8[anyDir(wrot)];
                frame = fr.rsiFrame(rsi, String(state), rsidir);
                if (ndirs === 1 && !noRot && !snap) rotate = wrot;
            }
            if (frame === null) return;
            out.push([r4(wx + sox + lox), r4(wy + soy + loy), r4(rotate), frame,
                color ? color.map((v) => Math.round(v * 1000) / 1000) : null, depth, subLayer ? 1 : 0, kind, li,
                scale[0] === 1 && scale[1] === 1 ? null : scale]);
        });
        return out;
    };

    const sprites = [], entities = [];
    const stats = { entities: 0, drawn: 0, power: 0 };
    for (const [uid, e] of ents) {
        if (!info.has(uid)) continue;
        const c = e.c, sp = c.Sprite;
        if (!sp || c.Marker) continue;
        // SubFloorHide drives sprite visibility itself (revealed when not covered)
        if (String(sp.visible ?? true).toLowerCase() === 'false' && !c.SubFloorHide) continue;
        stats.entities++;
        const off = entityLayers(uid, e, false);
        const gv = c.GenericVisualizer?.visuals ?? {};
        const on = POWER_KEYS.some((k) => k in gv) || c.ApcVisuals || c.Thruster ? entityLayers(uid, e, true) : off;
        if (!off.length && !on.length) continue;
        const idx = entities.length;
        const emit = (rows, pw) => { for (const r of rows) sprites.push([...r.slice(0, 8), idx, r[8], pw, r[9]]); };
        if (JSON.stringify(on) === JSON.stringify(off)) emit(off, 0);
        else { stats.power++; emit(off, 2); emit(on, 1); }
        stats.drawn++;
        entities.push([e.proto, e.name]);
    }
    // engine order: draw depth, then higher y first, then entity order, then layer order
    sprites.sort((a, b) => a[5] - b[5] || b[1] - a[1] || a[8] - b[8] || a[9] - b[9]);

    // ---- tiles
    const tiles = [];
    for (const [g, tl] of gridTiles) {
        const [gx, gy, gr] = worldXf(g);
        const c = Math.cos(gr), s = Math.sin(gr);
        for (const [key, [tname, variant, rotmir]] of tl) {
            const td = TILE.get(tname);
            if (!td?.sprite) continue;
            const rel = game.texPath(td.sprite);
            if (!fs.existsSync(path.join(game.tex, rel))) { fr.missing.add(rel); continue; }
            const variants = Number(td.variants ?? 1);
            const frame = fr.frame(rel, (variant < variants ? variant : 0) * PPM, 0, PPM, PPM);
            const [x, y] = key.split(',').map(Number);
            const cx = x + 0.5, cy = y + 0.5;
            tiles.push([r4(gx + c * cx - s * cy), r4(gy + s * cx + c * cy), r4(gr), rotmir, frame, truthy(td.isSubfloor ?? false) ? 1 : 0, g]);
        }
    }

    // ---- decals
    const decals = [];
    for (const g of gridUids) {
        const coll = ents.get(g)?.c.DecalGrid?.chunkCollection ?? {};
        const [gx, gy, gr] = worldXf(g);
        const c = Math.cos(gr), s = Math.sin(gr);
        for (const node of coll.nodes ?? []) {
            const nd = node.node ?? {};
            const proto = DECAL.get(String(nd.id));
            if (!proto) continue;
            const spec = proto.sprite ?? {};
            const frame = typeof spec === 'object' && spec.sprite ? fr.rsiFrame(spec.sprite, String(spec.state), 0)
                : typeof spec === 'string' ? fr.texture(spec) : null;
            if (frame === null) continue;
            const w = fr.list[frame][3] / PPM, h = fr.list[frame][4] / PPM;
            const color = game.color(nd.color);
            const ang = parseAngle(nd.angle);
            const z = Number(nd.zIndex ?? 0) || 0;
            for (const pos of Object.values(node.decals ?? {})) {
                const [x, y] = parseVec(pos);
                const cx = x + w / 2, cy = y + h / 2;
                decals.push([r4(gx + c * cx - s * cy), r4(gy + s * cx + c * cy), r4(gr + ang), frame,
                    color ? color.map((v) => Math.round(v * 1000) / 1000) : null, z]);
            }
        }
    }
    decals.sort((a, b) => a[5] - b[5]);

    // bounds = tile area, like the PNG of the old renderer (keeps the page centring identical);
    // sprites sticking out (thrusters, overhangs) are still drawn by the viewer
    const pts = tiles.length ? tiles : sprites;
    if (!pts.length) throw new Error('map has nothing to draw');
    const half = tiles.length ? 0.5 : 0;
    const bounds = [Math.min(...pts.map((p) => p[0])) - half, Math.min(...pts.map((p) => p[1])) - half,
        Math.max(...pts.map((p) => p[0])) + half, Math.max(...pts.map((p) => p[1])) + half].map((v) => Math.round(v * 1e4) / 1e4);

    return {
        data: {
            version: 1, bounds, frames: fr.list, tiles, decals, sprites, entities,
            licenses: Object.fromEntries([...fr.licenses].map(([k, v]) => [k, [...v].sort()])),
        },
        stats: { ...stats, tiles: tiles.length, decals: decals.length, layers: sprites.length, missing: [...fr.missing].sort() },
    };
}
