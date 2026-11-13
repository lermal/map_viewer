#!/usr/bin/env node
// Builds the shipyard catalog (shuttles.json, poi.json) and per-map render data + texture atlases
// from a Frontier Station 14 checkout. Nothing in the game checkout is modified.
//
// usage: node build.mjs --game <frontier-station-14 checkout> --storage <site>/storage/app [--only Id1,Id2] [--pages shuttles,poi]
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { Game } from './lib/game.mjs';
import { buildRenderData } from './lib/render-data.mjs';
import { packAtlas } from './lib/atlas.mjs';
import { renderPreview } from './lib/preview.mjs';
import { PAGES } from './lib/catalog.mjs';

function args(argv) {
    const out = {};
    for (let i = 0; i < argv.length; i++) {
        if (argv[i].startsWith('--')) out[argv[i].slice(2)] = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
    }
    return out;
}

/** Write via a temporary file in the same directory and rename, so readers never see a partial file. */
function publish(file, content) {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    const tmp = path.join(path.dirname(file), `.${path.basename(file)}.${process.pid}.tmp`);
    fs.writeFileSync(tmp, content);
    fs.renameSync(tmp, file);
}

/** Keep the newest two published versions of an item (the page may still reference the previous one). */
function prune(dir, stem) {
    if (!fs.existsSync(dir)) return;
    const versions = fs.readdirSync(dir)
        .filter((f) => f.startsWith(`${stem}.`) && f.endsWith('.json'))
        .map((f) => ({ f, t: fs.statSync(path.join(dir, f)).mtimeMs }))
        .sort((a, b) => b.t - a.t);
    for (const { f } of versions.slice(2)) {
        const hash = f.slice(stem.length + 1, -'.json'.length);
        for (const ext of ['json', 'png']) fs.rmSync(path.join(dir, `${stem}.${hash}.${ext}`), { force: true });
    }
}

function latestPublished(dir, stem) {
    if (!fs.existsSync(dir)) return null;
    const files = fs.readdirSync(dir).filter((f) => f.startsWith(`${stem}.`) && f.endsWith('.json'))
        .sort((a, b) => fs.statSync(path.join(dir, b)).mtimeMs - fs.statSync(path.join(dir, a)).mtimeMs);
    return files[0] ?? null;
}

const opt = args(process.argv.slice(2));
if (!opt.game || !opt.storage) {
    console.error('usage: node build.mjs --game <frontier-station-14 checkout> --storage <site>/storage/app [--only Id1,Id2] [--pages shuttles,poi]');
    process.exit(2);
}
const only = opt.only ? new Set(String(opt.only).split(',')) : null;
const pages = opt.pages ? String(opt.pages).split(',') : Object.keys(PAGES);

const started = new Date();
const t0 = Date.now();
const game = new Game(path.resolve(opt.game));
console.log(`prototypes loaded in ${((Date.now() - t0) / 1000).toFixed(1)}s`);

const report = { startedAt: started.toISOString(), finishedAt: null, published: [], failed: [], missingMap: [], missingStates: {} };

for (const pageName of pages) {
    const page = PAGES[pageName];
    if (!page) { console.error(`unknown page ${pageName}`); process.exitCode = 2; continue; }
    const mapsDir = path.join(opt.storage, 'public', 'maps', pageName);
    const categories = {};
    for (const item of page.items(game)) {
        const { entry, group, mapFile } = item;
        const stem = entry.id.toLowerCase();
        const record = { ...entry };
        const inScope = !only || only.has(entry.id);

        if (inScope) {
            if (!mapFile || !fs.existsSync(mapFile)) {
                report.missingMap.push({ page: pageName, id: entry.id, map: item.mapRef });
            } else {
                try {
                    const t = Date.now();
                    const { data, stats } = buildRenderData(game, mapFile);
                    const atlas = packAtlas(game.tex, data.frames);
                    data.frames = atlas.frames;
                    const hash = crypto.createHash('sha256').update(atlas.png).update(JSON.stringify(data)).digest('hex').slice(0, 12);
                    data.atlas = `${stem}.${hash}.png`;
                    const jsonName = `${stem}.${hash}.json`;
                    const changed = !fs.existsSync(path.join(mapsDir, jsonName));
                    if (changed) {
                        publish(path.join(mapsDir, data.atlas), atlas.png);
                        publish(path.join(mapsDir, jsonName), JSON.stringify(data));
                    }
                    // static PNG where the old renderer kept it: og:image and the page fallback keep their URLs
                    const previewFile = path.join(opt.storage, 'public', page.rendersPath, entry.image);
                    if (changed || !fs.existsSync(previewFile)) publish(previewFile, renderPreview(data, atlas.image));
                    prune(mapsDir, stem);
                    for (const m of stats.missing) (report.missingStates[m] ??= []).push(entry.id);
                    report.published.push({ page: pageName, id: entry.id, file: jsonName, ms: Date.now() - t, ...stats, missing: stats.missing.length });
                    console.log(`${pageName}/${entry.id}: ${stats.drawn} entities, ${stats.layers} layers, atlas ${atlas.width}x${atlas.height}, ${Date.now() - t} ms`);
                } catch (err) {
                    report.failed.push({ page: pageName, id: entry.id, reason: String(err?.message ?? err) });
                    console.error(`${pageName}/${entry.id}: FAILED ${err?.message ?? err}`);
                }
            }
        }

        // reference the newest published data even if this run could not rebuild it
        const latest = latestPublished(mapsDir, stem);
        if (latest) record.map = `maps/${pageName}/${latest}`;
        (categories[group] ??= []).push(record);
    }
    if (!only) {
        publish(path.join(opt.storage, 'data', 'pages', page.file),
            JSON.stringify({ renders_path: page.rendersPath, categories }, null, 4));
    }
}

report.finishedAt = new Date().toISOString();
publish(path.join(opt.storage, 'data', 'map-renderer-report.json'), JSON.stringify(report, null, 2));
console.log(`done in ${((Date.now() - t0) / 1000).toFixed(1)}s: ${report.published.length} published, ` +
    `${report.failed.length} failed, ${report.missingMap.length} without map, ${Object.keys(report.missingStates).length} missing RSI states`);
if (report.failed.length || report.missingMap.length) process.exitCode = 1;
