# Visual Editor — V1 architecture notes

Persisted reference for future work on this package. Read on every
session start; update when architecture decisions change.

---

## Package shape

- Composer package: `artisanpack-ui/visual-editor` (namespace `ArtisanPackUI\VisualEditor\`, src under `src/`)
- npm package: `@artisanpack-ui/visual-editor` (built from `resources/js/visual-editor/`)
- Three sub-packages under `packages/`:
  - `artisanpack-ui/visual-editor-renderer-blade` (Packagist; published in V1)
  - `@artisanpack-ui/visual-editor-renderer-react` (npm; mirror unpublished in V1.0.0)
  - `@artisanpack-ui/visual-editor-renderer-vue` (npm; mirror unpublished in V1.0.0)

## V1 surfaces

Two surfaces, mounted independently:

1. **Post editor** — `<x-visual-editor :model="$model" />` Blade
   component. Boots a React tree on `[data-ap-visual-editor]`. Edits
   any model that uses `HasBlockContent`.
2. **Site editor** — `GET /visual-editor/site/{path?}`. Templates,
   template parts, patterns, global styles, navigation. Gated by
   `SiteEditorAccessGate` (`DenyByDefaultGate` by default;
   cms-framework auto-binds `CmsFrameworkInstallGate`).

## Content model

- `ArtisanPackUI\VisualEditor\Concerns\HasBlockContent` on any Eloquent
  model. Default column: `content` (JSON); override via
  `$blockContentColumn`. Optional scope via `$blockContentScope`.
- Resource map: slug → model class. Read from
  `config('artisanpack.visual-editor.resources')` + `ap.visual-editor.resources`
  filter. Static config wins on key collision. Validation deferred to
  first request (not boot), so contributor packages don't trip boot
  when absent.
- Resolver: `ArtisanPackUI\VisualEditor\Resources\ResourceResolver`. Applies
  `forVisualEditor` scope; throws `InvalidArgumentException` on unknown
  resource / missing trait.

## REST surface

All under `/visual-editor/api`, middleware
`config('artisanpack.visual-editor.api.middleware')` (default
`['api', 'auth']`).

Endpoint families:

- `{resource}/{id}/content` — block content CRUD
- `blocks/preview`, `blocks` — block registry + dynamic preview
- `query/resolve` — `core/query` resolution
- `templates`, `template-parts`, `patterns`, `global-styles`, `menus`,
  `menu-items`, `menu-locations` — site-editor entities (wp_template
  shape; matches Gutenberg's REST contract so core-data shim resolves
  unchanged)
- `attachments/{id}`, `search`, `site/{id}` — media, link picker,
  site-meta

cms-framework adds: `posts`, `pages` (REST adapters into
cms-framework's models).

## @wordpress/* pins

All exact-version pinned, lockstep, audited per release. V1.0.0 ships
against the `15.16.0` / `32.5.0` / `7.43.0` baseline (see
`docs/release-notes-inputs-1.0.0.md` §1 + §5). Renovate is paused for the
group during V1.x expansion phases and resumes post-tag.

Schema version: theme.json **v3** (pinned in
`config('artisanpack.visual-editor.global_styles.schema_version')` and
enforced by `UpdateGlobalStylesRequest`).

Bump procedure: `docs/troubleshooting.md` §3 + `docs/global-styles.md` §8.

## Core-data shim

`resources/js/visual-editor/vendor/core-data-shim.ts` — aliased in
`vite.config.ts` as `@wordpress/core-data`. Provides:

- Entities: `wp_template`, `wp_template_part`, `wp_navigation`, `wp_block`
  (patterns), `root:globalStyles`, attachments, site-meta.
- Selectors: `getEntityRecord`, `getEntityRecords`, `getEditedEntityRecord`,
  `canUser`, `getCurrentUser`, `getMedia`, plus resolver tracking
  (`hasFinishedResolution`, `isResolving`).

Adding a new entity or selector goes here. Surface only what Gutenberg
demands; broader surface = more re-verification per upstream bump.

## Block fork (Phase I — closed)

- 42 forked core blocks under `artisanpack/*`. Source of truth:
  `resources/js/visual-editor/blocks/`.
- `core/*` no longer registered. `from:core/*` transforms on every fork
  catch pasted upstream markup.
- `@wordpress/block-library` demoted to devDependencies; only consumed
  by `npm run upstream-diff`.
- Per-block `upstream-state.json` tracks the upstream commit each fork
  derives from. CI runs the diff on every Renovate PR.

## Renderers

Three packages, all consume the same block-tree JSON. Parity verified
by `npm run verify:parity`. Static blocks: per-block partial/component
in each renderer. Dynamic blocks: `DynamicBlock` subclass renders
server-side; React + Vue fall through to `<DynamicBlock>` that fetches
from `/visual-editor/api/blocks/preview`.

## Embedding stacks

- Livewire: `wire:ignore` + Alpine listeners on `ve:editor:*` events.
  See `docs/livewire.md`.
- Inertia React: imperative `mountVisualEditor(el, config)`.
- Inertia Vue: `<VisualEditor>` SFC from `@artisanpack-ui/visual-editor/vue`.

Same three browser events fire regardless of stack:
`ve:editor:change` (debounce edge), `ve:editor:autosave` (debounced
save returns 200), `ve:editor:save` (explicit save returns 200).

## Theming

- Editor chrome restyled to DaisyUI via `@artisanpack-ui/react`.
- Known issues: `@artisanpack-ui/react` Popover + Dropdown freeze the
  canvas — use inline manual patterns. Tabs needs
  `tabListClassName="tabs-box"` for DaisyUI 5.
- React package: use subpath imports (`/form`, `/layout`, etc.); root
  import breaks tests via missing `react-apexcharts` peer.

## Site-editor + cms-framework version pair

V1.x ↔ V1.x. cms-framework provides:

- Models: `Post`, `Page`, `Template`, `TemplatePart`, `Pattern`,
  `GlobalStyles`, `Menu`, `MenuItem`.
- Service contracts: `QueryResolver`, `SiteEditorAccessGate` impl
  (`CmsFrameworkInstallGate`), site-meta via `apGetSetting('site.*')`.
- Migrations: `block_content` column on `posts`/`pages`, full
  site-editor tables.

Loose coupling — cms-framework's editor wiring guarded by
`class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)`.

## Smoke flows

Both run against the version pair before every release tag:

- `docs/g6-smoke-flow.md` — Phase G (posts, pages, site-meta, query).
- `docs/h8-smoke-flow.md` — Phase H (templates, parts, patterns,
  global styles, navigation).

## Tests

- PHP: `./vendor/bin/pest` (Pest 3.x, Orchestra Testbench).
- JS: `npm test` (Vitest, jsdom).
- Parity: `npm run verify:parity`.
- Upstream diff: `npm run upstream-diff`.

## What's deferred to V1.1+

- `@wordpress/*` upgrade pass (paused during V1.0.0; resumes post-tag).
- Style variations (theme-level presets).
- Revision history of global-styles edits.
- Standalone-without-cms-framework polish (currently usable but the
  site editor requires cms-framework).
- WordPress import companion package
  (`artisanpack-ui/visual-editor-wp-import`).
