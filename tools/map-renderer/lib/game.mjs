// Access to a Frontier Station 14 checkout: prototypes, localisation, engine colours, RSI metadata.
import fs from 'node:fs';
import path from 'node:path';
import yaml from 'js-yaml';

// The game tags polymorphic data as `!type:Name`; the tag itself carries nothing we need, so any `!type:*`
// node is read as plain data (an empty tagged node becomes null). js-yaml's default schema is YAML 1.2,
// matching the engine: `on`/`off`/`yes` stay strings.
const SCHEMA = yaml.DEFAULT_SCHEMA.extend(['scalar', 'sequence', 'mapping'].map((kind) =>
    new yaml.Type('!type:', { kind, multi: true, construct: (data) => (data === '' ? null : data) })));

export function loadYaml(file) {
    // json: duplicate keys override instead of throwing, as in the engine's parser
    return yaml.load(fs.readFileSync(file, 'utf8'), { schema: SCHEMA, json: true });
}

function walk(dir, ext, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, ext, out);
        else if (entry.name.endsWith(ext)) out.push(full);
    }
    return out;
}

export const truthy = (v) => String(v).toLowerCase() === 'true';

export class Game {
    constructor(root) {
        this.root = root;
        this.res = path.join(root, 'Resources');
        this.tex = path.join(this.res, 'Textures');
        this.protos = new Map();      // type -> Map(id -> raw prototype)
        this.protoFile = new Map();   // "type:id" -> file
        this.resolved = new Map();
        this.rsiCache = new Map();
        this.loadPrototypes();
        this.loadLocale();
        this.loadColors();
    }

    loadPrototypes() {
        for (const file of walk(path.join(this.res, 'Prototypes'), '.yml')) {
            let docs;
            try { docs = loadYaml(file); } catch { continue; }
            if (!Array.isArray(docs)) continue;
            for (const d of docs) {
                if (!d || typeof d !== 'object' || d.id === undefined || !d.type) continue;
                if (!this.protos.has(d.type)) this.protos.set(d.type, new Map());
                this.protos.get(d.type).set(String(d.id), d);
                this.protoFile.set(`${d.type}:${d.id}`, file);
            }
        }
    }

    proto(type, id) { return this.protos.get(type)?.get(String(id)); }
    all(type) { return this.protos.get(type) ?? new Map(); }

    // Entity prototypes: components merged through the parent chain. The engine pushes parents in order onto
    // the child and keeps values already set, so the first parent listed wins (SerializationManager.PushComposition).
    entity(id) {
        if (this.resolved.has(id)) return this.resolved.get(id);
        const p = this.proto('entity', id);
        const res = { comps: {}, name: null };
        this.resolved.set(id, res);   // guards against inheritance cycles
        if (!p) return res;
        for (const parent of [].concat(p.parent ?? [])) {
            const pr = this.entity(String(parent));
            for (const [k, v] of Object.entries(pr.comps)) res.comps[k] = { ...v, ...(res.comps[k] ?? {}) };
            res.name ??= pr.name;
        }
        for (const c of p.components ?? []) {
            if (c && typeof c === 'object' && c.type) res.comps[c.type] = { ...(res.comps[c.type] ?? {}), ...c };
        }
        res.name = p.name ?? res.name;
        return res;
    }

    // Non-entity prototypes (vessel, pointOfInterest, gameMap): top-level fields merged the same way.
    data(type, id, seen = new Set()) {
        const p = this.proto(type, id);
        if (!p || seen.has(id)) return {};
        seen.add(id);
        let out = {};
        for (const parent of [].concat(p.parent ?? [])) out = { ...this.data(type, String(parent), seen), ...out };
        const own = { ...p };
        delete own.parent;
        delete own.abstract;
        return { ...out, ...own };
    }

    loadLocale() {
        this.loc = new Map();
        const dir = path.join(this.res, 'Locale', 'en-US');
        if (!fs.existsSync(dir)) return;
        for (const file of walk(dir, '.ftl')) {
            for (const line of fs.readFileSync(file, 'utf8').replace(/^﻿/, '').split('\n')) {
                const m = /^ent-([A-Za-z0-9_]+)\s*=\s*(.*?)\s*$/.exec(line);
                if (m) this.loc.set(m[1], m[2]);
            }
        }
    }

    displayName(id, yamlName) {
        let v = this.loc.get(id);
        const seen = new Set();
        let m;
        while (v && (m = /^\{\s*ent-([A-Za-z0-9_]+)\s*\}$/.exec(v)) && !seen.has(m[1])) {
            seen.add(m[1]);
            v = this.loc.get(m[1]);
        }
        return v || yamlName || id;
    }

    // Named colours exactly as the engine defines them: "Green" is #008000, "Lime" is #00FF00.
    loadColors() {
        this.named = new Map();
        const src = fs.readFileSync(path.join(this.root, 'RobustToolbox', 'Robust.Shared.Maths', 'Color.cs'), 'utf8');
        for (const m of src.matchAll(/public static Color (\w+) => new\((\d+), (\d+), (\d+), (\d+)\)/g)) {
            this.named.set(m[1].toLowerCase(), [m[2], m[3], m[4], m[5]].map((v) => Number(v) / 255));
        }
    }

    /** [r, g, b, a] in 0..1, or null for "no tint". */
    color(v) {
        if (v === undefined || v === null) return null;
        let s = String(v).trim();
        if (!s.startsWith('#')) return this.named.get(s.toLowerCase()) ?? null;
        s = s.slice(1);
        if (s.length === 3 || s.length === 4) s = [...s].map((c) => c + c).join('');
        if (s.length === 6) s += 'FF';
        return [0, 2, 4, 6].map((i) => parseInt(s.slice(i, i + 2), 16) / 255);
    }

    texPath(rel) {
        let r = String(rel).replace(/^\/+/, '');
        if (r.startsWith('Textures/')) r = r.slice('Textures/'.length);
        return r;
    }

    rsi(rel) {
        const key = this.texPath(rel);
        if (!this.rsiCache.has(key)) {
            let meta = null;
            try {
                meta = JSON.parse(fs.readFileSync(path.join(this.tex, key, 'meta.json'), 'utf8').replace(/^﻿/, ''));
                meta.byName = new Map((meta.states ?? []).map((s) => [s.name, s]));
            } catch { meta = null; }
            this.rsiCache.set(key, meta);
        }
        return { key, meta: this.rsiCache.get(key) };
    }
}
