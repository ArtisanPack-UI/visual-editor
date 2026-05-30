---
title: 1.0.0 release-notes inputs
---

# 1.0.0 release-notes inputs

Handoff document for **#325 (M15 · Docs, website, release)**. Captures the
two facts plan 13 §3 (I8) and plan 12 §6 require the v1.0.0 release notes
to record: the pinned `@wordpress/*` package versions the editor ships
against, and the `artisanpack-ui/cms-framework` version pair the editor
is tagged against.

Both lists are derived from the working tree on `release/1.0` at the
moment the Phase I block fork completion gate (#416) closed. They are the
ground truth #325 should paste into the v1.0.0 release notes / `README` /
`CHANGELOG` sections that need a version table; #325 is free to reformat
but should not re-derive — re-deriving without re-running the I8 dev-app
soak risks shipping notes that disagree with the editor.

---

## 1. Pinned `@wordpress/*` package versions

Source of truth: [`package.json`](../package.json). All entries listed
with an exact version (no `^`/`~` range) per plan 11 §4.1 — Gutenberg's
cross-package version coupling assumes lockstep, and Renovate keeps the
group pinned (see §3 below).

### 1.1 Runtime (`dependencies`)

| Package | Pinned version | Purpose |
| --- | --- | --- |
| `@wordpress/block-editor` | `15.16.0` | Editor canvas, block selectors, inspector controls. |
| `@wordpress/blocks` | `15.16.0` | `registerBlockType`, `serialize`, `parse`. |
| `@wordpress/components` | `32.5.0` | Toolbar, panel, popover, modal primitives. |
| `@wordpress/core-data` | `7.43.0` | Aliased to the in-package shim at build time (`vite.config.ts`); pinned so the alias surface matches upstream. |
| `@wordpress/format-library` | `5.43.0` | Rich-text format registration. |
| `@wordpress/hooks` | `4.43.0` | Filter / action surface — also the integration seam for `artisanpack-ui/hooks`. |
| `@wordpress/i18n` | `6.17.0` | `__()`, `_x()`, `_n()` for the forked blocks' strings. |
| `@wordpress/patterns` | `2.43.0` | Pattern picker, synced / unsynced primitives. |
| `@wordpress/reusable-blocks` | `5.43.0` | Reusable-block edit + transform surface. |

### 1.2 Build-only (`devDependencies`)

| Package | Pinned version | Purpose |
| --- | --- | --- |
| `@wordpress/block-library` | `9.43.0` | **Build-only as of I7 (#415).** No longer registered at runtime. Kept so `scripts/upstream-diff.mjs` has a local upstream tree to diff each fork's `upstream-state.json` against. Renovate continues to bump it in the gutenberg group; the per-block diff job (§3) surfaces drift. |

### 1.3 Notes for #325

- Reproduce the table above verbatim — do **not** widen any pin to a
  range. Gutenberg minor bumps regularly require multi-package upgrades
  on the same day; ranges break the lockstep.
- Call out the I7 split: `@wordpress/block-library` is no longer in
  `dependencies`. Anyone bisecting a runtime regression should not look
  there.
- Pinned `@wordpress/*` baseline for the v1.0.0 schema-compatibility
  contract (plan 11 §4.2) is whichever WP core release shipped
  `@wordpress/block-editor@15.16.0`. Do not name a specific WP / Gutenberg
  version unless it's been verified against the WP release matrix —
  guessing it here would mis-pin the `theme.json` schema target. The
  global-styles docs ((plan 11 §4.2)) should record the verified
  `theme.json` schema version alongside the package list above.

---

## 2. cms-framework version pair

Source of truth: [`composer.json`](../composer.json) `require-dev` +
`suggest`. visual-editor V1.0.x does not tag until the matching
cms-framework V1.x is tagged and published (plan 12 §6, plan 14 §5.7).

### 2.1 The pair

| visual-editor | Minimum `artisanpack-ui/cms-framework` | Why |
| --- | --- | --- |
| `1.0.x` | `^1.1` | First cms-framework release shipping the Phase G surface — `HasBlockContent` migration on `posts` + `pages`, `site.*` settings, `QueryRuntime` service — plus the Phase H site-editor entities (templates, parts, patterns, global styles, navigation). |

Both smoke flows in this repo —
[`docs/g6-smoke-flow.md`](g6-smoke-flow.md) for Phase G,
[`docs/h8-smoke-flow.md`](h8-smoke-flow.md) for Phase H — run against this
pair before the v1.0.0 tag is cut. They are the gating verification, not
this document.

### 2.2 Where it already lives

- [`README.md` § Version compatibility](../README.md#version-compatibility)
  — the public-facing table host-app integrators read.
- [`composer.json`](../composer.json) — `require-dev` declares
  `artisanpack-ui/cms-framework: ^1.1`; `suggest` calls out the v1.1
  minimum for the site-editor route.

### 2.3 Notes for #325

- Re-state the v1.0.x ↔ cms-framework v1.x pair in the release notes
  alongside the link to the README table. Don't introduce a third
  source of truth.
- If cms-framework's v1.x tag covering the Phase G + H surface hasn't
  shipped at the moment v1.0.0 is cut, hold the visual-editor tag —
  this is the explicit blocker plan 12 §6 calls out.

---

## 3. Renovate + per-block diff CI

Plan 13 §I8 / §5.1 require Renovate `@wordpress/*` updates to resume on
the post-fork baseline, with the per-block upstream-diff CLI running in
CI on each Renovate PR so a human triages drift before it merges.

### 3.1 Renovate

Config: [`.github/renovate.json`](../.github/renovate.json). The
`@wordpress/*` group ships with `rangeStrategy: pin` and a `every 2 weeks
on monday` schedule — the resumed cadence plan 11 §4.1 paused during the
V1 expansion build. No additional action needed for I8 beyond closing the
gate; Renovate will open the first post-fork group PR on its next
scheduled run.

### 3.2 Per-block upstream-diff CI

Workflow: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)
`upstream-diff` job (added in this branch). Runs on every push +
pull-request; installs the JS toolchain, invokes
`npm run upstream-diff -- --json`, and fails the build when a file marked
`status=ported` in any block's `upstream-state.json` diverges from the
pinned `@wordpress/block-library` tree. The job's first cycle against the
1.0.0 baseline runs as part of the #416 PR — completing it satisfies the
"first per-block diff CI cycle completes successfully" acceptance
criterion.

### 3.3 Notes for #325

- The v1.0.0 release notes should mention the Renovate cadence and the
  upstream-diff CI gate so host-app integrators understand the
  post-tag maintenance commitment (plan 13 §5.7 reviewer load expectation:
  ~40–60 substantive triage decisions per year).

---

## 4. Confirmation snapshot — I8 acceptance criteria

Captured here for #325 cross-reference; do not edit without re-running
the dev-app soak.

- ✅ Clusters I0–I7 closed: #408, #409, #410, #411, #412, #413, #414, #415.
- ✅ Editor + Blade / React / Vue renderers run on `artisanpack/*` —
  `registerArtisanPackBlocks()` replaces `registerCoreBlocks()` in both
  editor bootstraps (`editor-app.tsx`, `site-editor-app.tsx`);
  `config/visual-editor.php` `enabled_blocks` lists only `artisanpack/*`;
  `disabled_blocks` is empty because `core/*` are no longer registered.
- ✅ `@wordpress/block-library` demoted to `devDependencies` (§1.2).
- ✅ Block library audit ([`docs/block-library-audit.md`](block-library-audit.md))
  carries the `core/*` → `artisanpack/*` fork mapping table with the
  upstream column preserved for the diff trail.
- ✅ Release-notes inputs (this document) handed to #325.
- ✅ Renovate cadence active; upstream-diff CI job wired (§3).
- 🟡 Dev-app soak window — exercised against this branch as part of the
  #416 PR; sign-off recorded in the PR description before the gate
  merges.
