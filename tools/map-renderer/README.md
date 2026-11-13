# Map renderer

Builds everything the site needs about ships and points of interest straight from a
[Frontier Station 14](https://github.com/new-frontiers-14/frontier-station-14) checkout.
The game is neither built nor run, and nothing in the checkout is modified.

For every vessel (`Resources/Prototypes/_NF/Shipyard`) and every point of interest / outpost
(`Resources/Prototypes/_NF/PointsOfInterest`, `Resources/Prototypes/_NF/Maps/Outpost`) it publishes:

| File | Used for |
|---|---|
| `storage/app/data/pages/shuttles.json`, `poi.json` | catalog pages; same format as before plus a `map` field |
| `storage/app/public/maps/<page>/<id>.<hash>.json` | draw list read by `resources/js/map-canvas.js` |
| `storage/app/public/maps/<page>/<id>.<hash>.png` | texture atlas for that draw list |
| `storage/app/public/renders/<page>/<id>.png` | static image: `og:image` and fallback when the canvas cannot be used |
| `storage/app/data/map-renderer-report.json` | report of the last run |

File names of render data contain a hash of their content, so they can be cached forever.
The two newest versions of each item are kept. Every file is written to a temporary name and renamed,
so the site never reads a half-written file; catalogs are written last.

## Usage

```bash
cd tools/map-renderer
npm install --omit=dev
node build.mjs --game /path/to/frontier-station-14 --storage /path/to/site/storage/app
```

Options:

- `--only Barnacle,Spectre` — rebuild only these items (catalogs are left untouched).
- `--pages shuttles` — only one catalog page (`shuttles`, `poi`).

The checkout must include the `RobustToolbox` submodule: named colours are read from
`RobustToolbox/Robust.Shared.Maths/Color.cs`. Node 18 or newer is required. A full build takes under a minute;
unchanged maps skip the static image step.

The process exits with code 1 when an item failed or its map file is missing; details are in the report.

## How rendering works

The draw list mirrors the game's own map renderer and client visualizers for a freshly loaded map:

- prototypes are merged through their `parent` chain (first parent wins, child fields override);
- tiles with variants and rotation/mirroring, decals by z-index, sprite layers by draw depth, then by height;
- sprite direction from the entity rotation (`GetCardinalDir`/`GetDir` with layer direction offsets);
  only single-direction layers rotate;
- client visual states: wall/window smoothing, cable connections, pipe connectors and pipe layers,
  subfloor visibility, doors, lockers and fridges, paper, vending machines, stacks, APCs, AME cores,
  folding chairs, `GenericVisualizer` values the server sets on startup;
- entities whose look depends on power get both variants; the page shows the powered one by default.

Map formats 6 and 7 are supported; anything else is reported as a failure.
