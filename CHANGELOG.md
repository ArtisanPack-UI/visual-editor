# ArtisanPack UI Visual Editor — Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] — 2026-06-08

First stable release of the V1 surface. Promotes `1.0.0-beta1` to GA
with the additions and fixes listed below. The post editor, site
editor, `artisanpack/*` block fork, and first-class
`artisanpack-ui/cms-framework` pairing — all introduced across
`1.0.0-alpha.1` and `1.0.0-beta1` — are now considered stable. See the
[README](README.md) and the [`docs/`](docs/) directory for the full V1
surface.

### Added

- **Laravel 13 support.** `illuminate/support` constraint updated to
  `^11.0|^12.0|^13.0` (Laravel 5.3–10 are no longer supported — the
  previous `>=5.3` floor was effectively dead code, since `orchestra/
  testbench` already pinned us to Laravel 11+). Laravel 13 requires
  PHP 8.3+, which is enforced transitively through L13's own `php`
  constraint; the package PHP floor (`^8.2`) is unchanged for users
  staying on Laravel 11/12.

### Fixed

- **Paragraph block-gap spacing.** Paragraph blocks now correctly
  inherit `is-layout-flow` block-gap spacing in the rendered output
  (#540).
- **`artisanpack/post-title` editable inline.** The post-title block
  now edits the live entity directly instead of getting stuck on its
  initial value (#546).
- **FontSizePicker duplicate-key warnings.** Font-size presets are
  now deduplicated before being handed to `FontSizePicker`, silencing
  the React duplicate-key warning that surfaced under certain
  theme.json configurations (#547).
- **`tsc --noEmit` errors in the core-data shim.** Resolved the
  TypeScript errors surfaced by `tsc --noEmit` in the core-data shim
  and its tests (#542).

## [1.0.0-beta1] — V1 beta release

First public beta of the V1 surface. Ships the post editor, the site
editor, the block fork to the `artisanpack/*` namespace, and first-class
pairing with `artisanpack-ui/cms-framework`. See the [README](README.md)
and the [`docs/`](docs/) directory for the full V1 surface.

### Added

- **Site editor (Phase H).** Mounted at `/visual-editor/site`. Templates,
  template parts, theme.json-backed global styles, navigation menus, and
  patterns — all editable through a custom shell built on
  `@wordpress/block-editor`. Backed by cms-framework's
  `Template`/`TemplatePart`/`GlobalStyles`/`Menu`/`Pattern` models when
  cms-framework is installed; fail-closed `SiteEditorAccessGate`
  defaults to deny until the host binds a permissive gate (or installs
  cms-framework, which auto-binds `CmsFrameworkInstallGate`).
- **Documentation set.** Fifteen new / refreshed docs covering install,
  content model, Blade component reference, post-editor surface, custom
  blocks, renderers, site-editor surface, templates, global styles,
  navigation, patterns, Livewire and Inertia embedding recipes,
  theming, troubleshooting, and migration. Entry point:
  [`docs/getting-started.md`](docs/getting-started.md).
- **V1 expansion plan retained as historical record:**
  [`docs/plans/11-v1-expansion.md`](docs/plans/11-v1-expansion.md).

### Changed

- README rewritten to reflect final V1 scope: post editor + site editor
  + patterns + Livewire/Inertia embedding.

## [1.0.0-alpha.1] — Gutenberg adoption marker

### Added

- **Block fork (Phase I) — `core/*` → `artisanpack/*`.** All 42 forked
  blocks plus the pre-existing `artisanpack/callout` and `artisanpack/form`
  now register under the `artisanpack/*` namespace. Clusters landed in
  order: I0 paragraph pilot (#408), I1 content (#409), I2 media (#410),
  I3 layout incl. grid/grid-item split (#411), I4 widgets (#412), I5
  entity (#413), I6 loop/feed (#414), I7 cutover (#415). The editor
  bootstraps in `editor-app.tsx` and `site-editor-app.tsx` call
  `registerArtisanPackBlocks()` instead of `registerCoreBlocks()`;
  `@wordpress/block-library` is demoted to `devDependencies` and consumed
  only by `scripts/upstream-diff.mjs`. Per-block `upstream-state.json`
  files keep the drift trail. `from:core/*` transforms ship on every fork
  so pasted upstream markup still converts. Full plan:
  [`docs/plans/13-block-fork.md`](docs/plans/13-block-fork.md).
- **Block fork completion gate (Phase I8, #416).** Confirms the cutover
  is complete and hands release-notes inputs to #325. Adds
  [`docs/release-notes-inputs-1.0.0.md`](docs/release-notes-inputs-1.0.0.md)
  with the pinned `@wordpress/*` table and the visual-editor ↔
  cms-framework version pair (`v1.0.x` ↔ `^1.1`). Wires the per-block
  upstream-diff CLI into CI so the first post-fork Renovate cycle
  triages drift before merging.
- **cms-framework integration (Phase G).** First-class pairing with [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework). When both packages are installed, the visual editor can edit cms-framework's `Post` and `Page` content end-to-end; `core/site-*` blocks read from cms-framework's settings store via `apGetSetting('site.*')`; `core/post-*`, `core/archives`, `core/categories`, `core/tag-cloud`, `core/query`, and `core/query-loop` come off the V1 deny-list and resolve against cms-framework's models and term endpoints. Loose coupling preserved — both packages remain usable standalone; cms-framework's editor wiring is guarded by `class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)`. Pair-versioning matrix lives in the README; the [`docs/g6-smoke-flow.md`](docs/g6-smoke-flow.md) flow runs against the version pair before every release tag. Full integration contract: [`docs/plans/12-cms-framework-integration.md`](docs/plans/12-cms-framework-integration.md).
- "Using with cms-framework" README section. Covers install, migrations, the merged resource map under `ap.visual-editor.resources`, and the version-pair contract.
- `visual_editor.*` permissions schema seeded into cms-framework's RBAC when both packages are installed (G5). Policies still use the "any authenticated user" baseline in V1.0; delegation lands in V1.1 behind `artisanpack.visual-editor.authorization.delegate_to_cms_framework`.
- `artisanpack/form` block. Dynamic block that lets authors pick a form from the artisanpack-ui/forms package via the InspectorControls sidebar, and renders a `<div data-keystone-form="…" data-form-id="…">` mount-point on the public site. The host application supplies a JS island that hydrates the mount-point with the forms package's React `FormRenderer`. Registration is gated on `class_exists(ArtisanPackUI\Forms\Models\Form::class)` so visual-editor still boots when forms is absent.
- Stale-selection guard in the form block's editor preview. When the persisted `formId` no longer matches any form returned by `/api/v1/forms`, the canvas renders a distinct `<Placeholder>` with a "Reset selection" button that clears the attribute back to `0`. Replaces the previous silent fall-through to the 404 "inactive" message, which mis-coded deleted forms as merely deactivated.

### Changed

- `FormBlock::validateAttrs()` now parses `formId` with `FILTER_VALIDATE_INT` via a `normalizeFormId()` helper. Rejects float strings (`"12.9"`) and scientific notation (`"1e2"`) that `is_numeric` + `(int)` previously truncated into unrelated form ids; non-positive values fall through to the "select a form" placeholder.
- Form block editor preview's 404 error message updated from "This form is inactive…" to "This form is unavailable — it may have been deleted or deactivated." — neutral wording that fits both branches of `FormBlock::render`'s server-side check.
- All `block.json` `textdomain` values aligned with the editor runtime's `TEXT_DOMAIN` constant (`artisanpack-visual-editor`). Translations for block titles/descriptions/keywords now resolve under the same domain as the rest of the editor strings.
