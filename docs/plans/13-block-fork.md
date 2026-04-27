# Visual Editor — `artisanpack/*` Block Fork Plan

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 2.0.0
**Created:** April 27, 2026
**Status:** Planning — V2 (post-V1.0.0)
**Tracks:** #331 (umbrella — port core blocks into the package as `artisanpack/*`)
**Relates to:** #309 (V1 umbrella), [`11-v1-expansion.md`](11-v1-expansion.md), [`12-cms-framework-integration.md`](12-cms-framework-integration.md), [`block-library-audit.md`](../block-library-audit.md)

---

## 1. Why this plan exists

`#309` (V1) made the deliberate bet that we would **adopt** `@wordpress/block-library` as a runtime dependency so we could ship a Gutenberg-class editor in months instead of years. That bet held — V1 ships M0–M14 plus the V1-expansion phases against upstream blocks plus shallow Gutenberg-filter customizations.

`#331` is the inversion. Filter hooks (`blocks.registerBlockType`, `editor.BlockEdit`, `editor.BlockListBlock`) work for adding attributes, toolbar/inspector controls, and wrapping the edit/save output. They hit a hard ceiling when the customization needs to:

- Change the block's saved HTML markup.
- Change the block's attribute schema.
- Change the block's edit-component structure (e.g. swap a `RichText` for a Tiptap-backed editor we already ship for callout).
- Add or remove deprecation entries.

Past attempts at deep customization through filters alone have run into this ceiling in practice. Owning the block source is the only path that gives full control. The tradeoff is maintenance: each fork still pulls a dozen `@wordpress/*` runtime deps (`block-editor`, `components`, `i18n`, `data`, `hooks`, `rich-text`, …) so we don't escape the WP ecosystem — we just take ownership of the block source, including deprecations, i18n strings, a11y affordances, and shared SCSS, and commit to diffing against upstream on a release cadence.

**This plan is V2.** [`11-v1-expansion.md`](11-v1-expansion.md) §6 explicitly parks #331 in v2.x: V1's premise is to **adopt** upstream blocks; inverting that bet inside V1 is self-contradictory and would push the v1.0.0 ship date past any reasonable horizon. This document captures the plan now so the team can pick it up cleanly once V1 is out the door.

---

## 2. V2 scope (final)

### 2.1 Namespace

All forked blocks land under `artisanpack/*` (e.g. `artisanpack/paragraph`, `artisanpack/heading`, `artisanpack/post-content`). No stored content uses `core/*` names yet — V1 ships with the upstream blocks under `core/*` and V2 introduces the fork — so we own the "no migration cost" property only as long as we land V2 before any host app starts persisting `core/*` block trees in production. **Decision:** ship V2 alpha within ~6 months of v1.0.0 GA so the no-migration window stays open. If it slips, plan a one-time `wp:rename-blocks` Artisan command that walks `block_content` JSON and rewrites `core/X` → `artisanpack/X` for every forked block.

`artisanpack/callout` already lives under the namespace and is the reference pattern for what a forked block looks like end-to-end — `block.json`, edit/save, three renderers (Blade/React/Vue), styles, tests. It is **not** "ported" in this plan — it stays as-is and informs the pilot.

### 2.2 Full block list (42 core blocks)

The set of upstream blocks to fork is the union of (a) the current `enabled_blocks` allow-list in `config/visual-editor.php` after E4 (#381) — 37 `core/*` blocks plus the existing `artisanpack/callout` — and (b) the additional blocks that [`12-cms-framework-integration.md`](12-cms-framework-integration.md) Phase G4 promotes from the deny-list into the allow-list — 5 more `core/*` blocks. Total: **42 `core/*` blocks** to fork.

**Source-unit count: 40.** `core/group`, `core/row`, and `core/stack` share a single upstream source — `core/row` and `core/stack` are registered as variations of `core/group`. We fork the source once and carry the variations forward. The ported pair becomes `artisanpack/group` with `artisanpack/row` and `artisanpack/stack` variations.

#### 2.2.1 Currently enabled (post-E4)

**Content (9):** `paragraph`, `heading`, `list`, `quote`, `code`, `preformatted`, `pullquote`, `verse`, `table`.

**Media (8):** `image`, `gallery`, `video`, `audio`, `file`, `embed`, `cover`, `media-text`.

**Layout (8):** `columns`, `group`, `row` (group variation), `stack` (group variation), `buttons`, `separator`, `spacer`, `details`.

**Widgets (2):** `search`, `latest-posts`.

**Site / post entity (10):** `template-part`, `post-title`, `post-content`, `post-excerpt`, `post-date`, `post-author`, `post-featured-image`, `site-title`, `site-tagline`, `site-logo`, `navigation`.

#### 2.2.2 Coming online via the cms-framework integration (Phase G4)

[`12-cms-framework-integration.md`](12-cms-framework-integration.md) re-enables the loop/feed cluster against cms-framework's term endpoints and the new `QueryRuntime` service. V2 forks all 5 once they ship behind upstream blocks in V1 / V1.1:

**Loop / feed (5):** `archives`, `categories`, `tag-cloud`, `query`, `query-loop`.

#### 2.2.3 Out of fork scope (V2)

- `core/latest-comments` — explicitly deferred to V1.1 in plan 12 §2.7. Forking is a V2.x follow-on once cms-framework's Comments module ships.
- Any block not in the post-E4 + post-G4 allow-list (the eight `empty-state` and the nine `crash-or-backend-required` rows from the audit doc that the audit keeps disabled). Forking blocks we don't expose is wasted work; if a future V1.1+ phase enables one of them, add it to the V2 fork list at that time.
- `artisanpack/callout` is already in-namespace; nothing to fork.

### 2.3 Non-goals

- Removing `@wordpress/block-editor`, `@wordpress/components`, etc. — those stay as runtime dependencies; only `@wordpress/block-library` is removed at cutover (and even then likely only demoted to `devDependency` for diff tooling).
- Rewriting the underlying edit experience for any forked block on day one. Day-one parity = byte-equivalent edit/save/deprecations against upstream, just under the new namespace. Genuine customization (e.g. replacing `RichText` with Tiptap for `artisanpack/paragraph`) ships as separate post-fork work, scoped per block.
- Localization. We carry forward upstream's i18n strings under our own text-domain (`artisanpack-visual-editor`) and re-extract via `wp i18n make-pot`. Translating new strings is not in this plan.

---

## 3. Phase ordering

```
Phase H — Block fork (V2)

  Pilot (sequential)
    H0   Paragraph pilot.
         - Fork core/paragraph → artisanpack/paragraph end-to-end.
         - Branch: feature/H0-paragraph-pilot off main (NOT release/2.0).
         - Validate block.json, edit, save, deprecations, i18n.
         - Identify shared primitives that need to be vendored
           alongside the block (icons, SCSS utilities, shared
           `@wordpress/block-editor` helpers that are intra-package
           imports in upstream).
         - Document the upstream-diff workflow in
           docs/block-fork-workflow.md.
         - Produce a real cost estimate in engineer-days for the
           remaining 41 blocks.

  Cluster milestones (parallelizable after H0)
    H1   Content cluster (8 blocks):
         heading, list, quote, code, preformatted, pullquote, verse, table.
    H2   Media cluster (8 blocks):
         image, gallery, video, audio, file, embed, cover, media-text.
         Coordinates with the media-bridge contract (host-app
         MediaUpload registration). No bridge changes; the forks
         consume the same registered MediaUpload component.
    H3   Layout cluster (8 logical blocks, 6 source units):
         columns, group (+row, +stack variations), buttons,
         separator, spacer, details.
    H4   Widgets cluster (2 blocks):
         search, latest-posts.
    H5   Entity cluster (11 blocks): template-part, post-title,
         post-content, post-excerpt, post-date, post-author,
         post-featured-image, site-title, site-tagline, site-logo,
         navigation.
         Coordinates with cms-framework's Post/Page entity adapter
         (plan 12 G3) and the core-data shim resolution (#395 / G0).
         These forks read entity data through the same
         useEntityRecord / useEntityRecords surface the upstream
         blocks use — no API change required.
    H6   Loop / feed cluster (5 blocks):
         archives, categories, tag-cloud, query, query-loop.
         Hard-couples to plan 12 G4b / G4c — does not start until
         cms-framework's term endpoints (G4b) and QueryRuntime
         service (G4c) are tagged in a cms-framework release.

  Cutover (sequential, after all clusters)
    H7   - Drop registerCoreBlocks() from
           resources/js/visual-editor/editor/editor-app.tsx
           and resources/js/visual-editor/site-editor/site-editor-app.tsx.
         - Replace with the artisanpack/* registration entrypoint.
         - Demote @wordpress/block-library from runtime dependency
           (devDependency only if still useful for diff tooling).
         - Update config/visual-editor.php enabled_blocks defaults
           to artisanpack/* names.
         - Update docs/block-library-audit.md to reference
           artisanpack/* names; keep the upstream column so the
           diff trail is preserved.
         - Update the JS-side mirror in site-editor-app.tsx
           (D2_DISABLED_BLOCKS) to match.
         - If we're past the no-migration window (§2.1), ship
           wp:rename-blocks Artisan command alongside the cutover
           release.

  Ship
    H8   v2.0.0-alpha.1 → v2.0.0-beta.1 → v2.0.0.
         Beta tag at end of H6 once all forks are landed but before
         cutover; GA after H7 has a soak window in the dev-app.
```

**Critical path:** H0 → (any one cluster) → H6 → H7. H6 cannot start until cms-framework's V1.x release tagged with G4b/G4c is published.

**Rough duration:** the H0 pilot's cost estimate refines this. Provisional budget — 1 week pilot, 1.5–2 weeks per cluster (six clusters), 1 week cutover. ~10–12 engineer-weeks total assuming clusters parallelize across two people; ~16–18 weeks single-engineer. Lock the real number after H0.

---

## 4. Architecture details

### 4.1 Per-block fork structure

Every forked block lives under `resources/js/visual-editor/blocks/{block-name}/` and follows the shape `artisanpack/callout` already establishes:

```
resources/js/visual-editor/blocks/paragraph/
├── block.json                 # { "name": "artisanpack/paragraph", "category": "...", ... }
├── index.ts                   # registerBlockType wiring
├── edit.tsx                   # Edit component (forked from upstream)
├── save.tsx                   # Save component (forked from upstream)
├── deprecated.ts              # Deprecation entries (forked, namespace-rewritten)
├── transforms.ts              # to/from transforms (incl. core/paragraph → artisanpack/paragraph)
├── editor.css                 # Editor-only styles
├── style.css                  # Front-end styles (vendored from @wordpress/block-library/src/paragraph/style.scss)
└── __tests__/
    ├── edit.test.tsx
    └── save.test.ts           # Round-trip serialization parity vs upstream
```

The renderer packages each get a matching file:

```
packages/visual-editor-renderer-blade/resources/views/blocks/artisanpack/paragraph.blade.php
packages/visual-editor-renderer-react/src/blocks/artisanpack/paragraph.tsx
packages/visual-editor-renderer-vue/src/blocks/artisanpack/paragraph.ts
```

### 4.2 `core/*` → `artisanpack/*` transforms

Every fork ships a `transforms.ts` with both directions:

- `from: { type: 'block', blocks: ['core/paragraph'] }` — converts a `core/paragraph` block instance to `artisanpack/paragraph` losslessly. Lets host apps with mid-V1 content (if any persisted before V2 ships) upgrade by pasting / re-inserting.
- `to: { type: 'block', blocks: ['core/paragraph'] }` — round-trip, in case the user wants to drop back. Removed at H7 cutover when `core/paragraph` is no longer registered.

The transforms cover the no-migration window's edge case: existing host-app content that may have hit `core/*` names. The Artisan command in §2.1 is the bulk-conversion fallback if the window closes before H7.

### 4.3 Upstream-diff workflow

This is the maintenance commitment we take on at H7 — and the one most likely to rot if we don't bake a workflow at H0.

**Tooling:** a `scripts/upstream-diff.ts` CLI in this package that takes a block name and a target upstream `@wordpress/block-library` version. It:

1. Resolves the source path inside `node_modules/@wordpress/block-library/src/{block}/`.
2. Walks the same files in our fork (`resources/js/visual-editor/blocks/{block}/`).
3. Emits a per-file unified diff plus a summary table.
4. Emits a JSON manifest (`upstream-state.json`) per fork that records the upstream version we last reconciled against.

**Cadence:** once per `@wordpress/block-library` minor version bump. The Renovate config (paused during V1 expansion, resumed post-V1) opens one PR per `@wordpress/*` package; the per-block diff CLI runs in CI on that PR and posts the per-block summary as a PR comment.

**Triage rule:** for each upstream change, the reviewer chooses one of three labels:
- **port** — change is a real bug fix or improvement; apply to our fork.
- **skip** — change is style-only, internal refactor, or contradicts our customization.
- **superseded** — our fork has already diverged in this area; record the reasoning and move on.

`upstream-state.json` keeps the per-block decision log — what we last reviewed, what we ported, what we skipped, what we superseded. When the next bump lands, the diff baseline is the recorded version, not the previous live `node_modules` version.

**Documented in:** `docs/block-fork-workflow.md` (created in H0). Keeping the workflow doc in the package, not just in the team handbook, means it travels with the source.

### 4.4 Vendored shared primitives

`@wordpress/block-library` is not a flat directory of self-contained blocks. Many blocks `import` from `@wordpress/block-library/src/utils/`, `@wordpress/block-library/src/components/`, or sibling block folders (`core/list-item` imports from `core/list/utils.ts`, etc.). Forking a single block without those primitives produces broken imports.

The H0 pilot enumerates the primitives `core/paragraph` actually imports, then we make a per-primitive call:

- **Vendor** — copy the primitive into `resources/js/visual-editor/blocks/_shared/` if it is genuinely block-library-private and not exported from a public `@wordpress/*` package.
- **Re-import from public WP package** — if it is re-exported from `@wordpress/block-editor`, `@wordpress/components`, or `@wordpress/rich-text`, switch the import.

By H6 we expect to have ~5–10 vendored primitives in `_shared/` covering the bulk of the entity blocks' bridge code. The pilot's deliverable includes the first iteration of this directory.

### 4.5 Block re-registration sequencing

V2 introduces a new entrypoint:

```ts
// resources/js/visual-editor/blocks/index.ts
export function registerArtisanPackBlocks(): void {
    registerBlockType( /* artisanpack/paragraph */ );
    registerBlockType( /* artisanpack/heading */ );
    // ... 40 more
}
```

Both editor bootstraps (`editor-app.tsx`, `site-editor-app.tsx`) call `registerArtisanPackBlocks()` instead of `registerCoreBlocks()` at H7. During the cluster phases (H1–H6), forked blocks register **alongside** their `core/*` counterparts so reviewers can A/B them in the dev-app — the inserter will show duplicates, gated behind a `?fork=on` query flag in the dev-app router. Production host apps continue to see `core/*` only until H7.

### 4.6 Coordination with cms-framework (H5 / H6)

**H5 — entity cluster.** The forked entity blocks (`artisanpack/post-*`, `artisanpack/site-*`, `artisanpack/template-part`, `artisanpack/navigation`) read entity records through the same `useEntityRecord` / `useEntityRecords` selectors the upstream blocks use. Plan 12 G0 (#395) plus G3 already wire those entities through `dispatch('core').addEntities(...)` — the forks inherit the wiring. No change to the cms-framework adapter.

**H6 — loop / feed cluster.** `artisanpack/query` and `artisanpack/query-loop` call into cms-framework's `QueryRuntime` (plan 12 G4c) via the same `POST /visual-editor/api/query/resolve` endpoint and Blade direct-call seam the upstream blocks use. The fork is contract-stable against G4c — no QueryRuntime API changes needed for the fork.

**H6 — feed widgets.** `artisanpack/archives`, `artisanpack/categories`, `artisanpack/tag-cloud` consume cms-framework's existing term endpoints (`/api/post-categories`, `/api/post-tags`, `/api/page-categories`, `/api/page-tags`) per plan 12 G4b. Same contract; the fork inherits.

The minimum-required cms-framework version for V2.0.0 is **the same** as for V1.0.0 plus whatever V1.x cms-framework release tags G4b and G4c. Document the version pair in V2.0.0 release notes per plan 12 §6.

---

## 5. Risks of record

### 5.1 Maintenance debt compounds quietly

41 forks × N upstream releases × M files per fork = a long tail of diff-and-port work. If the diff workflow rots — nobody triages the Renovate PR comments, `upstream-state.json` falls behind — the forks silently diverge from upstream a11y / security fixes. **Mitigation:** the H0 pilot ships the workflow doc *and* a CI job that fails the build if `upstream-state.json` is more than two minor versions behind the installed `@wordpress/block-library`. Forces the triage to happen on cadence.

### 5.2 Block-library private API churn

`@wordpress/block-library` exposes some helpers via top-level exports and some via deep imports (`@wordpress/block-library/src/utils/...`). The deep imports are not part of the public API surface and can break on minor version bumps. Vendoring those primitives (per §4.4) is the long-term answer — the pilot's job is to enumerate them so we don't discover the private-API problem block-by-block during clusters.

### 5.3 Deprecation chain compatibility

Each upstream block carries a chain of `deprecated` entries that handle saved-content from older block versions. Our fork has to carry those forward unchanged for round-trip parity, *and* every host app's persisted block content has to deserialize through the chain after the namespace rename. **Mitigation:** the per-block `__tests__/save.test.ts` round-trips a fixture per deprecation entry against upstream-saved markup. CI fails if any chain breaks. The fixtures live in `resources/js/visual-editor/blocks/{block}/__fixtures__/upstream/`.

### 5.4 Renderer parity

The package ships three renderers — Blade, React, Vue. Each fork lands in all three. If a renderer drifts (e.g. Vue forgets to register `artisanpack/post-content`), front-end output silently regresses. **Mitigation:** the block-registration entrypoint in each renderer asserts the registered set matches a shared manifest emitted by the JS package (`blocks/manifest.json`). Mismatch fails build.

### 5.5 cms-framework version coupling tightens

V1 already version-pairs visual-editor and cms-framework (plan 12 §5.5). V2 H6 hardens the pairing — if cms-framework's `QueryRuntime` API shifts post-G4c, the H6 forks break. **Mitigation:** the `QueryRuntime` PHP contract becomes a versioned interface (`ArtisanPackUI\CMSFramework\Contracts\QueryRuntime\V1`). cms-framework V2 introducing `V2` doesn't break our forks; we adopt `V2` as separate post-V2 work.

### 5.6 No-migration window closes early

§2.1 banks on V2 shipping within ~6 months of V1.0.0 GA. If V2 slips past that, host apps are persisting `core/*` block trees and our claim of "no stored content uses `core/*` names yet" no longer holds. **Mitigation:** the `wp:rename-blocks` Artisan command in §2.1 plus H7's cutover. Treated as an escape valve, not the primary plan.

### 5.7 Diff fatigue across 41 blocks

Even with tooling, a human has to triage every Renovate-driven diff. With 41 forks × 13 minor releases per year × 5 files per fork average, that's ~2,600 file-diffs annually. Some will be no-ops, some won't. **Mitigation:** the diff CLI groups identical no-op diffs (whitespace-only, version-bump-only) and reports them as a single line. Realistic reviewer load lands closer to ~40–60 substantive triage decisions per year. Track actuals after V2.0.0 ships and reassess at V2.1.

---

## 6. Branching + release strategy

- **Pilot branch:** `feature/H0-paragraph-pilot` cut from `main` per #331's pilot directive — V2 work does not gate on V1's `release/1.0` integration branch.
- **Integration branch:** `release/2.0` cut from `main` once V1.0.0 is tagged. All H1–H7 work merges into `release/2.0`.
- **Cluster branches:** `feature/H{n}-{cluster}` cut from + merged into `release/2.0`.
- **Tags:**
  - `v2.0.0-alpha.1` — at the end of H4 (content + media + layout + widgets clusters in).
  - `v2.0.0-alpha.2` — at the end of H5 (entity cluster in).
  - `v2.0.0-beta.1` — at the end of H6 (loop/feed cluster in; all forks landed; pre-cutover).
  - `v2.0.0` — after H7 + dev-app soak.
- `main` remains release-only; V2.0.0 merges back per existing workflow.

---

## 7. Issue tracking approach

Following plan 11 §6 — create milestone-level tracking issues one phase at a time, as each phase kicks off. The cluster breakdown in §3 is provisional until the H0 pilot's cost estimate refines it; filing eight detailed cluster issues now produces stale tickets by the time H1 starts.

- **H0** — file as a single issue under #331 once V1.0.0 ships and the team commits to V2 kickoff.
- **H1–H6** — one umbrella issue per cluster, filed at the start of each cluster's work. Each cluster issue spawns one child issue per block in the cluster as the cluster begins. Per-block issues have their own acceptance criteria covering edit/save parity, deprecations, transforms, three-renderer parity, and `__fixtures__` coverage.
- **H7** — single cutover issue, filed at the start of H7.
- **#331** stays open through the lifecycle; close only when v2.0.0 is tagged.

`#338` (`core/search` `buttonUseIcon` a11y) is a V1 Phase F issue per plan 11 §6 and is unaffected by this plan — when `artisanpack/search` lands at H4, it inherits whatever fix V1 shipped.

---

## 8. Open questions to answer at phase kickoff

Not blocking this plan, but worth naming:

- **H0 pilot location** — `feature/H0-paragraph-pilot` off `main` is per the issue body. Do we *also* land the pilot's `docs/block-fork-workflow.md` and `scripts/upstream-diff.ts` on `main` after H0 even if the rest of V2 stays on `release/2.0`? Leaning yes — the workflow doc is useful before V2 starts and not packaged into the published bundle.
- **Vendored primitives directory naming** — `_shared/` (mirrors `_legacy/` precedent) vs `vendor/` (mirrors `core-data-shim` location at `resources/js/visual-editor/vendor/`). Settle at H0 once the actual primitives are visible.
- **Per-block customization budget** — at fork time, each block lands as byte-equivalent to upstream. When does post-fork customization (e.g. swapping `RichText` for Tiptap on `artisanpack/paragraph`) get scoped — alongside the fork or as a separate post-V2 backlog item per block? Leaning post-V2, scoped per block, so V2 itself stays a parity exercise.
- **`@wordpress/block-library` final disposition** — devDependency for diff tooling, or removed entirely? The `scripts/upstream-diff.ts` workflow needs *some* path to upstream source. devDependency is the simpler answer; "remove and pull from a pinned tarball at CI time" is the smaller-attack-surface answer. Decide at H7.
- **Style.scss / shared SCSS** — `@wordpress/block-library/src/style.scss` aggregates per-block styles and applies layout normalization. We'll need to fork the equivalent or rebuild. Cost surfaces at H0.
- **i18n text-domain** — confirm `artisanpack-visual-editor` is the right text-domain for forked `__()` calls (vs reusing `default` or per-package domains). Settle at H0.
- **Renderer parity manifest** — JSON file emitted by JS build, consumed by Blade (PHP) and Vue / React renderer build steps? Or a TS module that all three import? Decide at H4 once the cluster shape is concrete.
- **Block category labels** — upstream uses `text`, `media`, `design`, `widgets`, `theme`, `embed`. Do we keep those names under `artisanpack/*`, or rename to match the package's vocabulary? Leaning keep — host apps' inserter UI is built against the upstream category names and we don't want to invalidate that.
