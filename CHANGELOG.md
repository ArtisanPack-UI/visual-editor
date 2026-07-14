# ArtisanPack UI Visual Editor — Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **CSS positioning support for blocks (#640).** Per-block Position
  panel in the inspector with a `static / relative / absolute / fixed
  / sticky` dropdown, per-side offset inputs (`px / % / rem / em /
  vh / vw / auto`), z-index, and per-breakpoint overrides via the
  responsive tab pattern from #487. Opt-in per block via
  `supports.position: true` in `block.json` (Gutenberg's native
  `{ sticky: true }` object shape also counts). Emits scoped CSS
  in the editor canvas and inline styles + a `<style
  data-ve-position>` block on the frontend via the Blade renderer.
  Inspector shows a warning notice when `position: absolute` is
  applied but no ancestor is positioned.

### Upgrade notes

- **Hosts that ran `php artisan vendor:publish
  --tag=visual-editor-blade-views` on a prior version must
  re-publish with `--force` when upgrading.** The published
  `blocks.blade.php` and `template.blade.php` files shadow the
  package source; the pre-1.4 published copies don't include the
  new `<style data-ve-position>` output block, so the position CSS
  accumulator will flush its rules but they'll never land in the
  response body — sticky/absolute blocks render unpositioned on the
  frontend. Command:
  `php artisan vendor:publish --tag=visual-editor-blade-views --force`.


## [1.3.0] - 2026-07-07

### Added

- **AI-powered authoring affordances (#610–#614).** Five optional
  AI-assisted authoring features built on top of
  [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai). All
  default to *off* and honor per-feature toggles from the AI package's
  `FeatureRegistry`; each surfaces suggestions the author must
  explicitly accept — no automatic mutations. Auto-registered via
  `VisualEditorServiceProvider::aiFeatures()`, so hosts with the AI
  package installed pick up every affordance with zero manual wiring.

  Three agents live in this package under `src/Ai/Agents/`:

  - **`ContentBlockSuggestionAgent` (#610)** — `visual_editor.suggest_next_block`.
    Inline "+ suggest next block" affordance that ranks likely next
    blocks given the document so far. Ships as
    `<SuggestNextBlockButton />`.
  - **`LayoutSuggestionAgent` (#611)** — `visual_editor.suggest_layout`.
    Given a section's content and your available pattern library,
    ranks matching section patterns from a whitelist. Ships as
    `<SuggestLayoutPanel />`.
  - **`HeadingHierarchyAgent` (#614)** — `visual_editor.heading_hierarchy`.
    Audits the document for skipped heading levels, duplicate h1s,
    and ambiguous headings; returns suggested fixes with nested
    `innerBlocks` traversal. Ships as `<HeadingHierarchyPanel />`.

  Two agents are consumed directly from `artisanpack-ui/ai` so the
  same prompt + feature toggle powers them across every package that
  opts in:

  - **`AltTextGenerationAgent` (#612)** — `ai.alt_text`. Suggests
    accessibility-friendly alt text when an image block is added or
    its `src` changes and `alt` is empty. Ships as
    `<AltTextSuggestionCard />`.
  - **`ContentRewriteAgent` (#613)** — `ai.content_rewrite`.
    Selection-toolbar / slash-command surface for "make shorter",
    "more formal", "reading level 6", and similar rewrites. Ships as
    `<RewriteToolbar />`.

  **Transports.** Every affordance is reachable from any host stack:

  - **HTTP** — six JSON endpoints under `/visual-editor/api/ai/*`:
    `GET /features`, `POST /suggest-next-block`,
    `POST /suggest-layout`, `POST /alt-text`, `POST /rewrite`,
    `POST /heading-hierarchy`. Each endpoint has a dedicated Form
    Request class in `src/Http/Requests/Ai/` and a consistent error
    envelope. All routes are guarded on
    `class_exists(FeatureRegistry)` so hosts without the AI package
    installed don't 500.
  - **Livewire** — `artisanpack-visual-editor.ai.tools` component
    listens for `ap-ve-ai:*` browser events and dispatches shaped
    `success` / `invalid-input` / `disabled` /
    `missing-credentials` / `error` events back. Blade/Livewire
    hosts get the same behavior as the React surface without pulling
    in a client bundle.
  - **React** — `resources/js/visual-editor/ai/` ships
    `createAiApiClient`, `useAiFeatures` gate, per-feature hooks,
    and 5 UI components. See
    [`docs/ai-features.md`](docs/ai-features.md) for the full
    authoring guide.

  **Requirements:** `artisanpack-ui/ai` `^1.0` installed and
  configured, per-feature toggle enabled in the AI settings surface,
  and CSRF middleware active on `/visual-editor/api/*` so the shipped
  JS client's `X-CSRF-TOKEN` header is honored.

### Changed

- `composer.json` now requires PHP 8.2+ and `artisanpack-ui/ai` `^1.0`
  as an optional-but-recommended companion dependency. Hosts without
  the AI package installed continue to work — the AI surface simply
  stays hidden and the routes short-circuit before dispatching an
  agent.

### CI

- Test suite runs on PHP 8.2 with AI dev deps excluded so hosts still
  targeting 8.2 have a green matrix even before adopting the AI
  package.

## [1.2.0] - 2026-06-18

### Added

- **Box / drop shadow control with solid + gradient color (#607).**
  New Shadow tools panel in the inspector's Styles group, auto-enabled
  on every block with `__experimentalBorder` support (~94 blocks, no
  block.json changes required). Exposes X/Y offset, blur, spread,
  solid color, gradient color (with theme palette), inset toggle, and
  a preset chip row backed by the new `settings.shadow.presets` slot
  in `theme.json`. Writes route through the standard `artisanpackStates`
  / `artisanpackResponsive` HOCs so per-state and per-breakpoint
  shadow overrides land in the right cascade bag automatically. Three
  emission modes (preset / solid / gradient) share one scoped `<style>`
  code path; gradient and inset-gradient shadows render through a
  `::before` / `::after` pseudo-element with `filter: blur()` and a
  `mask-composite: exclude` ring mask for the inset variant. PHP
  `BoxShadowResolver` + `BoxShadowEmitter` mirror the TS pair
  byte-for-byte so editor canvas, saved markup, and Blade-rendered
  output stay in lockstep. New scope class `ve-bs-<id>` persisted on
  `attributes.style.shadow._shadowScopeId`. Front-end Blade rendering
  goes through a new `BoxShadowCssAccumulator` +
  `BlockSupports::pushBoxShadow()` + auto-stamping in
  `BlockSupports::compile()`, so every block already routed through
  the supports compiler picks up shadow rendering with zero
  per-template changes. The supports-extension filter also strips the
  native WordPress `supports.shadow` on opted-in blocks to keep the
  two systems from fighting over the `style.shadow` attribute slot.
  Mirrors the architecture established by gradient borders (#490).
  **Known limitation:** outer gradient shadows on blocks with
  `overflow: hidden` (e.g. Cover) are visually clipped at the wrapper
  edge — gradient shadows need a `::before` pseudo-element because
  the native `box-shadow` property doesn't accept gradient fills, and
  pseudo-elements (unlike box-shadow) are clipped by their host's
  overflow. Solid shadows and preset shadows are unaffected. See
  [`docs/box-shadows.md`](docs/box-shadows.md) for the full authoring
  guide and workarounds.

- **Per-post layout overrides on the Query Loop via post-variants
  (#591).** New `artisanpack/post-variant` block, child of
  `artisanpack/post-template`, declares an override template that
  swaps in for posts matching its `matcher` attribute. Four matcher
  kinds ship in V1: `position` (`first` / `last` / `nth:<n>` /
  `range:<from>-<to>`), `pattern` (`odd` / `even` /
  `every-nth:<step>[:start:<offset>]`), `meta` (`sticky`, `featured`,
  `has-featured-image`, `author:<id>`, `taxonomy:<tax>:<slug>`), and
  `custom` (`callback:<name>` → `apve_query_variant_match_<name>`
  filter hook). A new "Post Variants" panel in the query inspector
  lists, adds, reorders, and deletes variants. Static rules
  (position / pattern) precompile to a 0-based `position →
  variantOrder` map stored on the parent post-template as
  `_compiledVariantMap` for O(1) lookup; dynamic rules (`meta`,
  `custom`) resolve at render time via the new
  `ArtisanPackUI\VisualEditor\Resources\VariantResolver`. Precedence
  is fixed: instance > position > pattern > meta > custom > base,
  with `priority` ascending as the tie-breaker. All three renderers
  (Blade, React, Vue) consume the same inlined tree — variants are
  stripped server-side by `QueryInliner`, so existing query loops
  with no variants render identically to before. Items rendered via
  a variant carry an extra `is-variant` class on their
  `core/post-template-item` wrapper for downstream styling.
- **Native flex layout panel on group / column / columns / grid-item
  (#595).** New `Flex Layout` + `Flex Item` inspector panels expose
  every CSS flexbox property — direction, wrap, justify, align-items,
  align-content, place-content, row/column gap, plus per-item
  align-self, grow, shrink, basis, order — each per-breakpoint via the
  existing `<ViewportSwitcher />`. Replaces WordPress core's narrow
  Flex layout variation on `artisanpack/group` (suppressed via filter)
  and layers on top of the default `artisanpack/columns` distribution.
  Class output mirrors Tailwind's utility convention (`ap-flex`,
  `md:ap-justify-between`, `ap-gap-x-[16px]`, …) and is asserted
  byte-identical across the Blade, React, and Vue renderers via a
  shared fixture set. Legacy `layout.type === 'flex'` content on
  `artisanpack/group` migrates automatically on first edit. See
  [[blocks/Flex Layout]] for the full surface.

## [1.1.1] — 2026-06-15

### Fixed

- **Icon registration no longer collides with `owenvoke/blade-fontawesome`
  (#587).** In consumer apps that pull both `artisanpack-ui/visual-editor`
  and `owenvoke/blade-fontawesome` (the default path via
  `livewire-ui-components`), the visual-editor's `fas` / `far` / `fab`
  icon sets collided with the prefixes blade-fontawesome registers,
  causing `BladeUI\Icons\Exceptions\CannotRegisterIconSet` on every
  `<x-...>` render and breaking Blade-rendered routes and Livewire tests.
  `FontAwesomeFreeIconSets::register()` now detects the blade-fontawesome
  service provider and stops publishing the FA Free sets through
  `ap.icons.register-icon-sets`, so the icons-package no longer forwards
  the conflicting prefixes to `BladeUI\Icons\Factory::add()`. The
  visual-editor's own `IconSvgResolver` seeds its FA Free path map
  directly from `FontAwesomeFreeIconSets::discover()`, so the icon
  picker preview and the rendered Icon Block still resolve the bundled
  SVGs.

## [1.1.0] — 2026-06-14

The 1.1.0 release ships the full `artisanpack/icon` block (Phases 1–7),
a wave of new first-party `artisanpack/*` blocks, block bindings for
parent post/page/CPT data, block animations, border-gradient borders,
an auto-injected custom-block CSS pipeline for the editor canvas
iframe, a `BreadcrumbsResolver`, and a set of Cover block fixes. See
the new [[blocks/Icon Block]] page for the icon-block surface and the
per-block docs under `docs/blocks/` for the new block families.

### Added

- **Block bindings — connect block attrs to parent post/page/CPT
  data (#504).** Block attributes can now bind to fields on the
  surrounding post/page/CPT record, so editor placeholders render the
  live value and front-end output stays in sync without hand-rolled
  `render_callback` wiring.
- **Block animations — entrance / hover / continuous + custom
  keyframes (#489).** New animation panel on every supported block
  with entrance, hover, and continuous animation types plus a custom
  keyframe escape hatch. Animations are emitted as standard CSS on
  the wrapper so they survive both the editor canvas and the
  rendered front end.
- **Border gradients — linear / radial / conic borders + tabbed
  color/gradient picker (#490).** Border controls now accept a
  gradient as well as a solid color. Linear, radial, and conic
  gradient types are supported and exposed through a tabbed picker
  that shares its color/gradient surface with the existing palette
  controls.
- **Auto-inject custom block CSS into the editor canvas iframe
  (#566).** Custom CSS registered against a block via the block API
  is now mirrored into the editor canvas iframe so the canvas
  matches the rendered front end without the host app having to
  enqueue editor styles by hand.
- **`BreadcrumbsResolver` for `artisanpack/breadcrumbs` (#565).**
  The breadcrumbs block now resolves its trail through a dedicated
  resolver, decoupling the trail computation from the block's
  server renderer so host apps can override how a trail is built
  for custom post types and routes.
- **New first-party `artisanpack/*` block families (#495).** The
  block library grows with:
  - **`artisanpack/breadcrumbs`** (#496).
  - **`artisanpack/accordion` + `artisanpack/tabs`** families
    (#497).
  - **`artisanpack/grid` + `artisanpack/grid-item`** families
    (#498).
  - **`artisanpack/next-post` + `artisanpack/previous-post`**
    container blocks (#499).
  - **Site-chrome blocks — `artisanpack/copyright`,
    `artisanpack/marquee`, `artisanpack/comments-number`** (#500).
  - **Single-post content cluster — `artisanpack/single-content`,
    `artisanpack/related-posts`, `artisanpack/author-social-icons`,
    `artisanpack/social-share-content`** (#501).
  - **Search cluster** (#502).
  - **`artisanpack/skills-slider`** (#503).
  All new blocks ship under the `artisanpack/*` namespace as
  first-party blocks; the inserter icons and categories were
  restyled and recategorised in the same wave (#495).
- **Icon block — full Phase 1–7 surface (#552, #554, #555, #556,
  #557, #558).** The `artisanpack/icon` block lands across seven
  phases:
  - **Phase 1 — block scaffold** with server render + SVG
    sanitizer (#552).
  - **Phase 2 — bundled FA Free SVGs** (Solid, Regular, Brands)
    auto-registered against the icons package via the
    `ap.icons.register-icon-sets` filter, with inline-rendered
    SVGs on the front end (#554).
  - **Phase 4 — picker UI** with search, set chips, a recent
    tray, and a paginated grid (#555).
  - **Phase 5 — custom SVG paste / upload** for one-off icons
    sanitized through the same SVG sanitizer (#556).
  - **Phase 6 — admin icon-sets settings** + zip-upload pipeline
    for registering whole icon families through the admin UI
    (#557).
  - **Phase 7 — end-to-end coverage and docs (#558).** Cross-
  phase Pest tests now stitch the registration filter, catalog, picker
  endpoints, admin uploader, sanitizer, and block renderer together so
  regressions that only surface end-to-end are caught. New Vitest
  coverage exercises the WP-style envelope plumbing and the
  width/height override path. Docs add a dedicated [[blocks/Icon Block]]
  page covering the block usage walkthrough, the developer recipe for
  `ap.icons.register-icon-sets`, the admin upload walkthrough, and FA
  Pro guidance (BYO SVGs, no token storage).
- **Icon block — independent width/height overrides.** The
  `artisanpack/icon` block now supports per-axis `width` and `height`
  attributes that override the uniform `size`. The inspector ships a
  `Dimensions` panel with a `NumberControl`-backed size and width/
  height `UnitControl`s (`px` / `em` / `rem` / `%` / `vw` / `vh`). All
  three controls emit changes on every keystroke so the canvas
  updates live.
- **Icon block — dedicated Icon color field.** The standard WordPress
  text-color control is replaced by a `Sidebar → Color → Icon` picker
  that writes to a new `iconColor` attribute and is applied directly
  to the body span as `color`. The bundled SVGs ship with
  `fill: currentcolor`, so the picked color flows through to the
  icon's fill. Mirrors the ndiego reference Icon Block split.

### Fixed

- **Icon block — WP style controls now apply on the canvas and front
  end.** Background, border, padding, and margin set via the
  inspector's standard controls now reach the rendered block. The
  block previously declared `__experimentalSkipSerialization: true`
  for every support and then never read the styles back, so author
  selections silently no-op'd. The block now lets WordPress serialize
  background/border/spacing onto the wrapper via `useBlockProps()`,
  and the server renderer applies the same envelope to the wrapper
  div (with the legacy top-level `backgroundColor` attribute kept as
  a fallback for posts saved before the fix). Palette-color slugs
  resolve through the standard `has-{slug}-background-color` /
  `has-{slug}-border-color` classes.
- **Icon block — decorative + linked icons now produce labeled
  anchors.** When `isDecorative`, `link`, and `ariaLabel` are all set,
  the supplied `ariaLabel` is now promoted onto the `<a>` itself
  rather than dropped. The body span remains `aria-hidden="true"`
  (the SVG is the decorative element), but the anchor finally has an
  accessible name. The editor-side `hasDecorativeLinkConflict()`
  warning still fires when no `ariaLabel` is supplied, which is the
  scenario the warning was always meant to flag.
- **Cover block — background classes now route to the overlay span
  (#583).** Palette-color background classes on the Cover block now
  reach the overlay span where the legacy markup expects them,
  instead of being applied to the wrapper and producing a flat,
  unblended fill.
- **Cover block — unfreeze editor on overlay color pick + media
  select (#578).** Picking an overlay color or a media item in the
  Cover block no longer hangs the editor. Stale refinement results
  are guarded against and the block's effect chain no longer
  reschedules itself in a tight loop.

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
