// Catalog entries (shuttles.json / poi.json) taken straight from the prototypes, including the map each one uses.
import path from 'node:path';

function mapFileOf(game, mapPath) {
    if (!mapPath) return null;
    return path.join(game.res, String(mapPath).trim().replace(/^\/+/, ''));
}

function folderGroup(game, type, id) {
    const file = game.protoFile.get(`${type}:${id}`);
    return file ? path.basename(path.dirname(file)) : null;
}

function inDir(game, type, id, dir) {
    const file = game.protoFile.get(`${type}:${id}`);
    return !!file && file.startsWith(path.join(game.res, 'Prototypes', dir) + path.sep);
}

/** Vessels from Resources/Prototypes/_NF/Shipyard, grouped like the old shipyard export. */
export function shuttleItems(game) {
    const items = [];
    for (const [id, raw] of game.all('vessel')) {
        if (raw.abstract || !inDir(game, 'vessel', id, '_NF/Shipyard')) continue;
        const v = game.data('vessel', id);
        items.push({
            group: v.group ?? folderGroup(game, 'vessel', id) ?? 'Unknown',
            mapFile: mapFileOf(game, v.shuttlePath),
            mapRef: v.shuttlePath ?? null,
            entry: {
                id,
                name: v.name ?? id,
                image: `${id.toLowerCase()}.png`,
                price: v.price ?? 0,
                description: v.description ?? '',
                category: v.category ?? 'Small',
                class: v.class ?? [],
                engines: v.engine ?? [],
            },
        });
    }
    return items;
}

/** Points of interest and outposts. A file usually defines a pointOfInterest and a gameMap with the same id. */
export function poiItems(game) {
    const byId = new Map();
    const add = (type, dirs) => {
        for (const [id, raw] of game.all(type)) {
            if (raw.abstract || !dirs.some((d) => inDir(game, type, id, d))) continue;
            const p = game.data(type, id);
            const prev = byId.get(id) ?? {};
            byId.set(id, {
                ...prev,
                id,
                name: prev.name ?? p.name ?? p.mapName,
                group: prev.group ?? p.spawnGroup ?? p.group,
                map: prev.map ?? (type === 'pointOfInterest' ? p.gridPath : p.mapPath),
                price: prev.price ?? p.price,
                description: prev.description ?? p.description,
            });
        }
    };
    add('pointOfInterest', ['_NF/PointsOfInterest', '_NF/Maps/Outpost']);
    add('gameMap', ['_NF/PointsOfInterest', '_NF/Maps/Outpost']);
    return [...byId.values()].map((p) => ({
        group: p.group ?? 'Unknown',
        mapFile: mapFileOf(game, p.map),
        mapRef: p.map ?? null,
        entry: {
            id: p.id,
            name: p.name ?? p.id,
            image: `${p.id.toLowerCase()}.png`,
            price: p.price ?? 0,
            description: p.description ?? '',
            category: 'Small',
            class: [],
            engines: [],
        },
    }));
}

export const PAGES = {
    shuttles: { file: 'shuttles.json', rendersPath: 'renders/shuttles/', items: shuttleItems },
    poi: { file: 'poi.json', rendersPath: 'renders/poi/', items: poiItems },
};
