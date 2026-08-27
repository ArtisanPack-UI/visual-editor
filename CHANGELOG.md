# ArtisanPack UI Visual Editor — Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Font Library** (#627) — a site-editor front door to font management,
  reachable from the **"Manage fonts…"** entry point on every Typography
  control. The modal browses **Google Fonts** and **Bunny Fonts** through
  keyless catalog endpoints, previews families same-origin, and installs the
  weights and styles you choose. Admins can also upload their own `.woff2`,
  `.woff`, `.ttf`, and `.otf` faces on the Custom Upload tab. Every installed
  family becomes a font-family preset available in each picker, resolved
  through a generated `fonts.css` on both the canvas and the public site.
  Installs are **self-hosted by design**: face files are fetched server-side
  once and served from your own domain, so visitors never hit a provider CDN —
  keeping the site GDPR-compliant. Third parties can add their own catalog by
  implementing the `FontProvider` contract and registering it through the
  `ap.visualEditor.registerFontSources` filter (#629); the REST surface (#634),
  modal (#635), font-family picker (#636), and same-origin previews (#741)
  round out the feature. New documentation: [Fonts](docs/fonts.md) (user guide,
  including the self-hosting/GDPR notes), [Font Providers](docs/font-providers.md)
  (developer guide with a worked example), plus the
  `ap.visualEditor.registerFontSources` reference in
  [Hooks and Events](docs/Hooks-and-Events.md) and a Font Library entry point
  in [Site Editor](docs/site-editor.md).

### Changed

- **The `post-comments-form` block fires `ap.cmsFramework.comments.form.action`**
  (cms-framework#245) — this block is the only fire site in the ecosystem for
  the comment form's action filter, but it emitted the un-prefixed legacy name
  `comments.form.action`. The CMS Framework's 2.5.0 hook-rename wave listed that
  filter as moved under `ap.cmsFramework.*` and its CHANGELOG said so, but the
  alias never shipped there, so the documented new name resolved to nothing and
  hosts had to stay on the old one. cms-framework 2.8.0 lands the missing alias
  and this release moves the fire site onto the canonical name to match. The
  filter keeps a cms-framework namespace rather than gaining a visual-editor one
  because comments are that package's domain and its `POST /api/v1/comments`
  endpoint is this filter's default value — the same emitter-is-not-owner split
  as `ap.rbac.roleRegistered`. **No action is required:** `comments.form.action`
  is registered as a deprecation alias here as well as in cms-framework, so a
  subscriber on either name still fires, in either direction. The alias is
  declared on this side deliberately — cms-framework is not a dependency of this
  package, so a host rendering this block without it would otherwise have no
  alias registered at all. Hosts should migrate to
  `ap.cmsFramework.comments.form.action`; the old name logs a deprecation notice
  the first time it resolves per request.
- **Hosts that published this package's block views must re-publish to pick the
  new hook name up** — `resources/views/vendor/visual-editor-renderer-blade/`
  shadows the package's own partials, so a published copy of
  `blocks/artisanpack/post-comments-form.blade.php` keeps firing the old name
  until it is refreshed with
  `php artisan vendor:publish --tag=visual-editor-blade-views --force`.
  Behavior is unchanged either way thanks to the alias; this only affects which
  name is emitted and therefore whether a deprecation notice is logged.

### Fixed

- **The renderer-blade suite registers `HooksServiceProvider`, so hook aliases
  actually resolve under test** — its `TestCase` listed only the visual-editor
  and renderer providers. `HookDeprecations` is bound as a singleton by the
  hooks provider, so without it every `app( HookDeprecations::class )` call
  resolved a *fresh* instance: the aliases registered in
  `VisualEditorServiceProvider::boot()` were written to an object nothing else
  ever read, and no legacy hook name resolved. The failure was silent rather
  than loud — the canonical name still fired normally, so only a test
  specifically asserting an *old* name would catch it. The root suite's
  `TestCase` has always registered the provider; the two are now consistent.

## [1.6.0] - 2026-08-07

### Added

- **Composed view: edit your content inside the template it will render in**
  (#618) — the post-editor canvas showed content on a bare ground, so the only
  way to see a heading under the real site header, or to judge the spacing where
  the content ends and the footer begins, was to open Preview in another tab and
  lose the editing context. A **View with template** switch in the top bar now
  renders the resolved template's chrome above and below the block canvas,
  inside the same iframe, so it picks up the theme's compiled CSS with no
  server-side render round-trip. The chrome is a genuine read-only preview: each
  region mounts through Gutenberg's block-preview provider in its own isolated
  block-editor store, so it is non-selectable and non-editable rather than
  merely `lock`ed — and the content editor's own block tree is never swapped, so
  selection, undo history and unsaved changes all survive a toggle, which the
  earlier composed-tree approach could not manage without freezing the editor.
  The template is cut at its `post-content` slot into header and footer chrome,
  cloning any layout wrapper the slot is nested inside onto both sides so the
  surrounding layout still reads correctly; `post-title`, `post-author`,
  `post-date` and `post-featured-image` blocks in the chrome bind against the
  post being edited rather than the template's sample data. A sticky in-canvas
  ribbon names the resolved template and carries an **Edit template ↗** CTA
  that deep-links the site editor by slug (`?entity=template&slug=…`, resolved
  to an entity id on mount and rewritten to the canonical URL) in a new tab —
  new tab on purpose, since navigating a post editor holding unsaved content
  away to edit chrome would be a data-loss trap. Backing it is a new
  `GET {resource}/{id}/applied-template` endpoint
  (`ResourceAppliedTemplateController`), gated on the same `view` authorization
  as the content endpoints, resolving the template and its referenced template
  parts. Its `?template=` parameter overrides the slug persisted on the model so
  a preview taken right after a template change does not race the debounced
  save, and a blank value is meaningful — it says the selection was *cleared*.
  A miss returns 200 with a discriminated `{status: "missing", reason}` body
  rather than a 404, so the routine "no template chosen" case does not litter
  devtools; the client turns it into a built-in default template (post title,
  featured image, content slot) plus a toast *and* a standing notice, so the
  reason is still on screen a minute later when the toast has auto-dismissed.
  Documented in `docs/post-editor/Composed-View.md`.
- **Blade-vs-JS markup parity check** (#704) — nothing guarded against the three
  renderers drifting on markup: `scripts/verify-renderer-parity.mjs` compares
  block *names* only, and `parity.test.ts` compares React against Vue with Blade
  left out of both. That is why the Blade-only #700 fix could land green while
  making Blade disagree with the JS renderers, undetected until #702. A shared
  fixture set (`packages/renderer-markup-parity/fixtures.json`) is now rendered
  through all three renderers: the Pest suite writes canonicalized golden files
  from `<x-ve-blocks>`, and the vitest suite asserts the React and Vue SSR output
  matches them, so drift in any renderer fails CI at the point it is introduced.
  Fixtures cover every layout-supporting wrapper — `group` (flow / constrained /
  flex / grid, plus the Flex Layout panel and Photo Grid cases), `row`, `stack`,
  `columns`, `buttons`, `post-content` with and without a stored layout, and
  `post-template` across all three saved layout shapes and its `columns`
  bounds. Canonicalization is narrow and documented: whitespace, attribute order
  and CSS declaration separators are normalized; element names, class tokens and
  attribute values are not. Known divergences are declared explicitly in the
  fixture manifest with a tracking issue rather than papered over with loose
  matching. The two canonicalizers are held to the same definition of
  whitespace — JavaScript's `\s` matches U+00A0 while PCRE's does not, so a
  fixture containing `&nbsp;` would otherwise have produced a golden the JS side
  could never match — and a `dropClassTokensMatching` pattern that fails to
  compile now throws rather than being silently read as "no match", which would
  have written the token into the golden while the JS side dropped it.

### Fixed

- **`core/html` blocks now render their saved markup** (#690) — the block
  shipped neither a `block.json` manifest nor a Blade partial, so a
  `<!-- wp:html -->` block in a theme template or `post_content` fell through
  to the unknown-block fallback and emitted an empty
  `data-ve-unknown-block` wrapper with its content dropped. An
  `artisanpack/html` manifest now declares `content` with `source: "raw"` —
  activating the matcher `BlockAttributeSourceResolver` already implemented
  but no bundled manifest used — and the paired `core/html` /
  `artisanpack/html` partials emit the recovered markup verbatim, with no
  wrapper element, matching Gutenberg's `<RawHTML>` save. The markup is
  deliberately not run through `kses()`: the partial sits inside the trust
  boundary documented on `BlockMarkupHydrator`, and sanitizing here would
  mangle the `<script>` / `<iframe>` / `<svg>` payloads the block exists to
  carry. Registration is server-side only — the editor bundle does not yet
  register a client-side HTML block. **Advisory for hosts:** unlike WordPress
  there is no `unfiltered_html`-style per-user capability gate, so a site that
  grants low-trust authors the block editor should sanitize `core/html` content
  on save.
- **Site-editor global styles no longer leak onto the admin chrome** (#679) —
  the theme global-styles emitter declares theme.json root spacing and the
  `--wp--preset--*` tokens on `:root`, which is right for the front end and for
  a canvas rendered inside a `<BlockCanvas>` iframe. The site editor mounts
  **inline** (#418), so `:root` resolved to the host document's `<html>` and the
  theme's root padding pushed the entire editor shell — top bar, navigator,
  inspector and canvas — in by that amount, showing up as a visible border
  around the whole editor on any host that paints `<body>`.
  `scope-global-styles-css.ts` now rewrites `:root` to `.editor-styles-wrapper`
  before the CSS is injected, including inside `@media` and `@supports` blocks
  and skipping comments, strings and `url()` contents. The rewrite is a strict
  narrowing at identical specificity (both selectors weigh 0,1,0), so the tokens
  land on the canvas surface instead of the document root, every block inside
  still inherits them, and theme root spacing applies where a theme author
  expects it. Fixed here rather than in cms-framework's `GlobalStylesEmitter`,
  whose `:root` output is correct for every other consumer.
- **`artisanpack/marquee` renders its saved content again** (#691) — it was the
  only one of the 105 bundled `block.json` manifests declaring a `source`
  definition while being absent from both registration lists in
  `VisualEditorServiceProvider` (`$forkedBlocks` and `$referenceBlocks`).
  `BlockMarkupHydrator::recoverAttributes()` looks the block up in
  `BlockTypeRegistry`, found nothing, and returned `[]`, so `marqueeContent` was
  never recovered from saved markup and `marquee.blade.php` rendered an empty
  marquee.
- **Container bindings resolve without a leading backslash** (#692) — Laravel's
  container does not normalise FQCN keys, so `'\Foo\Bar'` and `Foo\Bar::class`
  are two distinct entries. `EntitySearchController::searchCmsFrameworkEntities()`
  built the template and template-part resolver class strings *with* a leading
  backslash, so `app( $resolverClass )` missed any host- or test-registered
  binding and silently constructed a fresh resolver instead — a host that
  rebound `TemplateResolver::class` was ignored with no error. `class_exists()`
  behaves identically either way, which is exactly why the guard above it hid
  the problem. The backslash is dropped there and everywhere else in the package
  that built a container key the same way.
- **Post-editor canvas now honours the theme's `theme.json` colors** (#695) —
  the canvas body is painted through two package-owned custom properties
  (`--ap-editor-canvas-bg` / `--ap-editor-canvas-fg`) that nothing ever
  assigned, so both fell back to their light-mode defaults. Because the rule
  that reads them is an element+class compound (`body.editor-styles-wrapper`,
  specificity 0,1,1) it out-specifies a theme's bare `.editor-styles-wrapper`
  rule regardless of source order, so no theme sheet could correct it: a dark
  theme rendered a white canvas with dark body text. The canvas now derives
  both properties from the resolved theme.json's `styles.color` and injects
  them as a `:root` rule. The same defect applied one level down —
  `DEFAULT_CANVAS_STYLES` hardcoded `#111827` for `h1`..`h6` at the same
  specificity, which on a dark ground rendered headings near-invisible — so
  the heading baseline now chains through a matching
  `--ap-editor-canvas-heading-fg`, sourced from `styles.elements.heading` (or
  `h1`..`h6` when every declared level agrees) and falling back to the canvas
  foreground. Themes declaring no colors keep the previous light defaults, and
  a host rule on `body` / `.editor-styles-wrapper` still overrides the theme.
  A theme that declares a background without a paired text color gets a legible
  foreground derived for it via the package's WCAG helpers rather than half a
  pair, and WordPress's `var:preset|color|slug` shorthand is accepted alongside
  the `var(--wp--preset--color--slug)` CSS form. That derivation covers every
  opaque CSS color syntax a `theme.json` may carry — `rgb()`, `hsl()`, named
  colors, and alpha-bearing hex — not just the plain hex the WCAG helpers parse
  natively, so an unpaired dark background written as `rgb(17 24 39)` or `black`
  is no longer emitted with an unreadable foreground left on the default. Values
  with no fixed opaque color (`var()` references, translucent colors) are left
  alone rather than guessed at, and the theme's own syntax is what reaches the
  stylesheet — normalisation is used only to measure contrast. The site-editor
  canvas is unaffected.
- **Blade renderer front-end assets no longer 404 on a fresh install** (#699) —
  every stylesheet `<x-ve-blocks-styles />` links under
  `/vendor/visual-editor-renderer-blade/` returned Laravel's 404 page until the
  consumer discovered and ran
  `vendor:publish --tag=visual-editor-renderer-blade-assets`, leaving the front
  end with no block-gap between paragraphs, no content-size containment on
  constrained groups, and `core/columns` collapsed to a vertical stack.
  `visual-editor-renderer-blade` now registers a route that serves the bundled
  block-library and `frontend/*` assets straight from the package, so the
  styles resolve with no install step and package upgrades take effect
  immediately. Publishing still works and still wins — the web server serves
  the static file before the route is reached — but it is now an optional
  performance choice rather than a silent requirement.
- **Per-block layout classes in the Blade renderer** (#700) — layout-supporting
  partials emitted only the shared `is-layout-{type}` modifier, so the shipped
  block-library rules that key on the per-block compound
  (`.wp-block-group.wp-block-group-is-layout-constrained`,
  `.wp-block-post-template-is-layout-flow > li > .aligncenter`, …) never
  matched. `group`, `row`, `stack`, `columns`, `buttons`, `post-content`, and
  `post-template` now emit both classes through the new
  `Support\LayoutSupport` helper. `post-content` additionally honours its
  stored `layout.type` for the first time — without that class the
  content-size containment rules `ThemeJsonTokensCompiler` emits for
  `.wp-block-post-content.is-layout-constrained` could never apply — and
  `columns` gained the `is-layout-flex` pair it was missing entirely.
- **Constrained groups now constrain their children** (#700) —
  `ThemeJsonTokensCompiler` emitted content-size containment only for
  `.wp-block-post-content`, so a `<article class="wp-block-group
  is-layout-constrained">` let its title, meta, and featured image run
  edge-to-edge while its post body behaved. It now also emits the
  `> :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright))`
  cap plus the `alignwide` / `alignfull` overrides for constrained groups,
  mirroring the per-instance rules WordPress generates. Keyed on the
  per-block `wp-block-group-is-layout-constrained` compound, so host markup
  that hand-writes the shared `is-layout-constrained` modifier and styles
  those children itself is not retroactively constrained.
- **React and Vue `core/group` honour the Flex Layout panel** (#711) — the
  Blade partial flips a group's layout class to `is-layout-flex` when the #595
  Flex Layout panel emits the unprefixed `ap-flex` class and no explicit
  `layout` is stored, so the flow baseline's
  `is-layout-flow > * + * { margin-block-start: gap }` rule stops pushing flex
  children apart along the cross axis. Both JS renderers called
  `layoutClass()` unconditionally and emitted
  `is-layout-flow wp-block-group-is-layout-flow` for the same tree — exactly
  the bug #595 fixed for Blade, still live on React and Vue hosts. They now
  mirror the Blade rule byte for byte: only the base-breakpoint `ap-flex`
  triggers it (a `md:ap-flex` means "flex from md up" and must not flip the
  base class), and only when the stored layout type is empty. Three parity
  fixtures pin all three cases.
- **`core/post-template` agrees across renderers on layout and column bounds**
  (#704) — `QueryInliner::postTemplateLayoutIsGrid()` accepts three saved
  shapes when it stamps `_resolvedGridSpan` onto each item: a plain `layout`
  string, an upstream mirror's object-form `layout.type`, and Gutenberg's
  sibling `layoutType`. The renderers did not agree with it or with each
  other — Blade read only the plain string, React and Vue read the string and
  `layoutType` — so a tree the inliner treated as a grid could render its
  wrapper as flow, leaving the stamped span classes with no grid to lay out.
  All three renderers now accept all three shapes. Blade additionally clamps
  `columns` to the `[1, 12]` range the stylesheet actually ships `columns-N`
  rules for, and falls back to the default for an unparseable value, matching
  the JS renderers; previously it could emit `columns-0` or `columns-99`.
- **Composed view announces a repeated template failure** (#618) — the
  fallback toast's latch only cleared when leaving composed mode, so broken
  template A → working B → broken A again produced no second toast. A
  successful resolution now clears it. The applied-template cache also holds a
  `Map` rather than a single slot, so it keeps the "cached per
  `(resource, id, template)` triple for the lifetime of the editor mount"
  contract its documentation states — flipping A → B → A previously refetched
  A once B had resolved.
- **Site-editor routing no longer rewrites navigation silently** (#618) — a
  host `routeBase` that fails the same-origin-absolute-path test is replaced
  with the package's own mount path. That substitution is right for the ribbon
  CTA's `<a href>`, where an absolute or `javascript:` URL would be an off-site
  link, but as silent behaviour for the SPA's own routing it made every
  in-editor navigation push URLs the host does not serve, with a 404 on
  reload and nothing in the console to explain it. It now warns once, naming
  the rejected value, and the fallback is documented on the `routeBase` prop.
  Separately, the deep-link "user has already navigated" guard compared the
  pathname only, so navigating away and back inside the SPA while a slug
  lookup was in flight defeated it and the late resolution teleported the
  author; it now compares the query string too, which every in-SPA navigation
  clears and none restores.

## [1.5.5] - 2026-08-02

### Added

- **`BlockMarkupHydrator`** (`Support`) — the supported server-side path
  from a raw WP block-markup string (a block theme's `templates/*.html`
  or `parts/*.html`, a `.php` pattern, a persisted `post_content`) to the
  editor-shape tree the renderers consume. `hydrate( string $markup )`
  parses the delimiters via cms-framework's `BlockMarkupParser` — resolved
  by name, so cms-framework stays a non-dependency and the method degrades
  to an empty tree when it is absent — while `hydrateTree( array $parsed )`
  takes an already-parsed tree and keeps working either way.
  `canParseMarkup()` lets hosts that would rather fail loudly gate on
  parser availability. Carries a `MAX_DEPTH` recursion cap (128, above the
  parser's own 64) so a hand-built or imported payload cannot blow the PHP
  stack, and falls back across the `core/` ↔ `artisanpack/` namespace pair
  so theme files written against WP core's block names resolve against the
  forks this package registers.
- **`BlockAttributeSourceResolver`** (`Support`) — server-side port of
  Gutenberg's save-shape attribute matchers over `DOMDocument` + XPath,
  covering `attribute`/`property`, `html` (including `multiline`),
  `rich-text`, `text`, `tag`, `query`, and `raw`. Recovery is driven
  entirely by the block-type registry's `block.json` definitions rather
  than a hand-maintained per-block table, so a block that ships a new
  sourced attribute is picked up with no change here.
- **`CssSelectorToXPath`** (`Support`) — hand-rolled translator for the
  narrow CSS subset `block.json` `selector` fields actually use (tag,
  class, id, attribute presence/equality, `:not()` over a single simple
  predicate, descendant and child combinators, comma groups), so the
  package gains no new Composer dependency for this. Selectors outside the
  subset throw `UnsupportedSelectorException`, which callers degrade to
  "attribute not recovered" and report once per process rather than
  silently matching the wrong node.
- **`BlockRenderer::renderMarkup( string $markup, ?string $defaultTheme )`**
  (`visual-editor-renderer-blade`) — markup in, HTML out. Hydrates, then
  inlines `template-part` and synced-pattern references before the walk,
  so a standalone theme template renders its parts as real content rather
  than empty wrappers. Blocks that need an entity in scope (`post-*`,
  `core/query`, comments, breadcrumbs) still require the full
  `<x-ve-blocks :tree="…" :post="…" />` pipeline.

### Fixed

- **Block-theme markup no longer renders textless server-side (#688).**
  There was no path from WP-serialized block markup to rendered HTML
  outside the editor, and the gap was not merely a shape mismatch between
  cms-framework's `{blockName, attrs, innerHTML}` and the editor's
  `{name, attributes}`. Gutenberg persists most block text in the **saved
  HTML**, not in the delimiter JSON — zero of the `artisanpack-ui` theme's
  130 paragraph/heading blocks serialize a `content` attribute — so a
  key-rename conversion produced a structurally-correct but completely
  textless page. Hydration now replays each registered block type's
  attribute definitions back over the saved HTML, recovering paragraph and
  heading `content`, button text and href, image `src`/`alt`/`caption`,
  list values, quote and citation, table cells, and everything else
  declared with a `source`.
- **`.html`-sourced template parts now inline with content (#688).**
  `TemplatePartInliner` resolved a block theme's `parts/header.html` to an
  entity with `blocks` empty and the raw markup in `rawContent` —
  cms-framework's resolver does not parse theme files — so the part
  inlined as an empty wrapper. The inliner now hydrates that raw markup
  when `blocks` comes back empty, accepting either `rawContent`
  (visual-editor's `ResolvedTemplatePart`) or `raw` (cms-framework's
  `ResolvedEntity`) so it works whichever value object a host's resolver
  hands back. A theme's own parts render without first being opened and
  re-saved in the site editor.
- **`TemplatePartInliner` now honors container overrides of the
  cms-framework resolver.** `RESOLVER_CLASS` carried a leading backslash,
  which does not match the key Laravel's container stores
  `TemplatePartResolver::class` under; `app()` treated it as a distinct
  binding and silently built a fresh instance, ignoring any host- or
  test-supplied override.

### Security

- `BlockMarkupHydrator` documents an explicit trust boundary. Recovered
  `rich-text` / `html` attributes are HTML fragments and block partials
  emit them unescaped (`{!! $content !!}`) — that is what makes a
  paragraph's `<strong>` survive the round trip. Hydration is therefore
  only safe over markup you already trust to render: theme files on disk,
  patterns, and editor-authored content that passed the post editor's
  authorization. It is **not** a sanitizer, and passing visitor-submitted
  markup to `hydrate()` turns it into stored XSS. This is the same
  boundary Gutenberg draws around block markup — neither widened nor
  narrowed. Hosts rendering untrusted markup should run it through their
  own sanitizer (e.g. `kses()` from `artisanpack-ui/security`) first.

## [1.5.4] - 2026-07-28

### Fixed

- **Site editor per-size and per-state style overrides now scope
  correctly (#700).** The site-editor bootstrap in
  `site-editor/site-editor-app.tsx` never called the five `register*()`
  functions that wire the responsive + state pipeline —
  `registerResponsiveAttribute`, `registerResponsiveAttributesFilter`,
  `registerStateAttribute`, `registerStateAttributesFilter`, and
  `registerStateStylesFilters` — so opted-in blocks never got the
  auto-injected `responsive` / `states` storage keys, the
  `editor.BlockEdit` HOCs never wrapped `setAttributes` to route writes
  into `responsive.<bp>.<path>` or `states.<path>.<activeState>`, and
  the per-state `<style>` scope class was never injected into the
  canvas. Every panel write landed on the base attribute, so a change
  on Hover state or Desktop viewport applied to every state / screen
  size. All five functions are now registered before
  `registerArtisanPackBlocks()`, mirroring
  `editor/editor-app.tsx:211-219`. The shared `BlockEditorProvider` in
  `site-editor/block-editor-boundary.tsx` also mounts
  `<StateWriteInterceptor />` and `<StateInspectorSync />` (missed when
  the provider was hoisted in #436) so apiVersion-3 color / border /
  shadow panels, which dispatch `updateBlockAttributes` directly and
  bypass the HOC chain, still route through the state bag.
- **Button block now opts into responsive spacing / dimensions
  (#700).** The singular `artisanpack/button` block declared
  `artisanpackStates` but not `artisanpackResponsive`, so
  per-breakpoint padding / margin writes had no `responsive` storage
  key to persist into and applied globally. Added
  `supports.artisanpackResponsive.attributes: ["spacing", "dimensions"]`
  in `resources/js/visual-editor/blocks/button/block.json`, matching the
  convention on the `buttons`, `columns`, `group`, and `column` blocks.

## [1.5.3] - 2026-07-27

### Fixed

- **Release workflow no longer races Packagist's version-immutability
  policy (#683).** The workflow shipped in #678 triggered on
  `push: tags: 'v*'` and used a `build-dist` job to force-update the
  tag mid-run so the composer tarball would carry `dist/editor/` +
  `dist/lib/`. Packagist's crawler observes new stable tags within
  seconds and freezes their SHA permanently — the workflow lost that
  race on v1.5.2, so Packagist ended up pinned at the source-only
  commit while GitHub's tag advanced to the dist-baked one. Composer
  consumers of v1.5.2 still got the source-only tarball, same broken
  state as v1.5.1. The workflow now triggers on `workflow_dispatch`
  with a `version` input. The maintainer merges the release PR into
  main, then invokes `gh workflow run release.yml -f version=X.Y.Z`.
  The workflow guards against re-shipping an existing tag, verifies
  the version input matches `composer.json` / `package.json`, builds
  `dist/`, commits it onto a fresh commit, creates the tag on that
  commit with `git tag -a` (annotated, not `-f`), and pushes it
  exactly ONCE with `git push origin refs/tags/vX.Y.Z` (no
  `--force`). Packagist observes the tag exactly once, at the
  correct SHA. `tests/Feature/Ci/ReleaseWorkflowTest.php` was
  rewritten to lock the new invariants and explicitly banned
  `git tag -f` / `git push --force` on version tags so the race
  can't recur silently.

### Notes

- **v1.5.3 is the first release that actually delivers Pattern B
  from `docs/Installation-Guide.md` end-to-end.** v1.5.2 attempted
  the same dist-baking contract described in #678 but Packagist
  froze it at the source-only tarball (see #683 above). Consumers
  who pinned to v1.5.2 for the pre-built `dist/editor/` tree should
  upgrade to v1.5.3 — pin `^1.5.3` if you need Pattern B. The
  `v1.5.2` GitHub tag still points at the dist-baked commit
  (`49e6728`) for anyone doing `git clone && git checkout v1.5.2`,
  but Composer consumers get `442ef80` (source-only) from Packagist
  regardless; this cosmetic tag/Packagist mismatch was left as-is
  because GitHub's tag-protection rule blocks a CLI restore and the
  practical impact is zero for Composer installs.

## [1.5.2] - 2026-07-27

> **This release did not fully ship its promised dist-baking on
> Packagist.** #678's release-workflow force-push raced Packagist's
> version-immutability policy and lost — Packagist froze `v1.5.2`
> at the source-only commit (`442ef80`) while GitHub's tag advanced
> to the dist-baked commit (`49e6728`). Composer consumers who
> pinned to `^1.5.2` for Pattern B still get the source-only
> tarball. Upgrade to v1.5.3 (fixed in #683) for the actual
> pre-built bundles.

### Fixed

- **Site editor now exposes the media-bridge registration surface
  (#677).** The site-editor bundle shipped without installing the
  `editor.MediaUpload` slot-fill filter and without re-exporting
  `registerMediaBridge` / `registerArtisanpackMediaBridge` from
  `site-editor/main.tsx`, so the `core/image` block placeholder in
  template parts hid the **Media Library** button and clicking
  **Upload** silently no-op'd — the fallback uploader surfaced an
  "Uploads are unavailable until a media bridge is registered"
  snackbar because no host could actually register one on the
  site-editor entry. `site-editor/main.tsx` now mirrors the
  post-editor registration surface: the bridge exports ride through
  the ESM entry, `window.ApSiteEditor` + `window.ApSiteEditorBoot`
  are installed to match `window.ApVisualEditor`, and
  `ensureMediaBridgeFilter()` is called at module load so the slot
  fill is in place even when the host registers the bridge
  asynchronously.
- **Composer tarball now ships pre-built editor bundles under
  `dist/editor/` and `dist/lib/` (#678).** Previous releases treated
  `dist/` as a purely local artefact — `.gitignore` excluded it, the
  release workflow built it in an ephemeral runner and threw it
  away, and hosts installing via Composer's GitHub-tarball resolution
  ended up with no `vendor/artisanpack-ui/visual-editor/dist/editor/`
  directory at all. Downstream consequence: Keystone CMS's
  `VisualEditorAssetController` (which serves the bundles straight
  from the vendor tree so hosts don't need Node in their asset
  pipeline) 500'd on every request. The release workflow now runs a
  `build-and-tag` job that rebuilds both bundles, verifies the
  required outputs exist, force-adds `dist/editor/` and `dist/lib/`
  onto the release commit, and re-points the version tag at that
  commit so Composer resolves the built tarball. Sourcemaps are
  stripped from the tarball (would ~triple its size) and attached to
  the GitHub Release as a separate `dist-sourcemaps-vX.Y.Z.tar.gz`
  archive.

### Changed

- **`docs/Installation-Guide.md` now documents both consumption
  patterns.** Pattern A (host runs Vite, existing) and Pattern B
  (host serves pre-built bundles from `vendor/`, new) are called
  out explicitly so CMS integrators know which they can rely on.
- **Tarball size increases by ~18 MB** (uncompressed) to carry the
  pre-built editor and library bundles. Well within Packagist norms
  but worth calling out for consumers with strict install-size
  budgets.

## [1.5.1] - 2026-07-26

### Fixed

- **`<!-- wp:template-part /-->` references inside theme templates now
  render inline in the site-editor canvas (#674).** Three-layered
  failure: `TemplatePartController::index()` ignored the `?theme=&slug=`
  filter the core-data shim sends for composite-id fallback, so the
  shim silently picked an arbitrary sibling (or the missing-record
  placeholder). `TemplateAdapter` shipped `content.blocks: []` for
  theme-file sources because cms-framework's filter contributor only
  populates `raw_content`, and the shim's raw-fallback parser is
  scoped to nav-link / nav-submenu blocks. Since the I7 cutover
  (#415) removed `registerCoreBlocks()`, `core/template-part` blocks
  land unregistered and get dropped; the `artisanpack/template-part`
  fork's edit delegated to `core/template-part` via
  `createForkedEntityEdit`, which fell back to an empty `<div>`
  because the core lookup returned undefined. Fixed at all three
  layers — controller now filters by `theme` + `slug`; adapter parses
  `raw` server-side via cms-framework 2.5+'s `BlockMarkupParser`
  (guarded with `class_exists` so older versions degrade gracefully)
  and rewrites `core/template-part` to the fork in both `raw` and
  `blocks`; the fork ships a real edit that composes the composite id
  from `slug` + `theme`, hands off to the shim's
  `useEntityBlockEditor`, and mounts the resolved tree through
  `useInnerBlocksProps` with `templateLock: 'all'`. The composite
  call is gated behind a subcomponent so a legacy reference missing
  `theme` can't leak through the shim's ambient-id fallback and mount
  the parent template recursively.
- **Site-editor canvas no longer paints a `padding: 24px` inset + a
  hardcoded `background: #fff` over the theme's own background
  (#674).** Templates and template-parts span the full viewport on
  the front-end, so the inset made full-bleed sections cap short of
  the shell edges and dark themes read as a pale-rimmed rectangle.
  Scoped to `.ap-site-editor__entity-canvas-surface`; the pattern
  editor uses `.ap-pattern-canvas__surface` and is unaffected.

## [1.5.0] - 2026-07-21

### Added

- **Six new lifecycle hooks for editor and block integrations (#665).**
  Fills in high-value extension points across block registration,
  rendering, post persistence, editor config assembly, and pattern
  rendering. All hooks live under the canonical `ap.visualEditor.*`
  namespace introduced in #664:

    - `ap.visualEditor.blockRegistered` — action, fires at the end of
      block registration with `(string $name, array $config)`. Fires
      through `BlockTypeRegistry::register()` so both `block.json`
      registration and programmatic `registerBlockType()` calls emit.
    - `ap.visualEditor.beforeRender` — filter on `(array $attributes,
      string $name)` that runs inside `BlockRenderer::renderBlock()`
      after site-meta / loginout stamping. Non-array returns are
      discarded so a misbehaving callback can't blank the block.
    - `ap.visualEditor.postSaved` — action fired from
      `WpEntityController` after every POST/PUT persistence with
      `(int|string $postId, array $blocks)`.
    - `ap.visualEditor.postPublished` — action fired from the same
      site whenever the current save transitions status into the
      WP-canonical `publish` value (create-with-publish or
      non-publish → publish on update). Same payload as `postSaved`.
    - `ap.visualEditor.editorConfig` — filter applied by
      `VisualEditorComponent` on the assembled config array with
      `(array $config, string $screen)`; `$screen` is `'post'` for
      the current component. Filtered values are re-hydrated onto
      the component's typed props, so misbehaving returns can't
      poison a `?string` prop.
    - `ap.visualEditor.patternRender` — filter applied by
      `PatternAdapter::toArray()` on the rendered raw content of a
      pattern with `(string $html, string $slug, array $context)`.
      Context carries source, synced flag, categories, block_types,
      and post_types so callbacks can gate per pattern shape.

### Changed

- **Bumped `artisanpack-ui/hooks` requirement to `^1.3` (#665).** The
  hook fire sites added in #665 assume the helper globals are always
  available, so the previous `^1.2` constraint's `function_exists()`
  guards have been dropped in favour of the newer floor.

### Changed (BREAKING)

- **Renamed every visual-editor hook to camelCase (#664).** The 13 PHP
  hooks the package fires or subscribes to, plus the
  `visual_editor.pre_publish_checks` cross-package hook, the
  `ap.icons.register-icon-sets` bridge hook, and three JS-only hooks
  (`background-controls`, `canvas-styles`, `document-panels`), now use
  camelCase — matching the ArtisanPack UI ecosystem's naming convention.
  Old names remain functional via `deprecateHook()` aliases registered by
  `ArtisanPackUI\VisualEditor\Support\HookAliases` on the PHP side and a
  bidirectional `@wordpress/hooks` shim in
  `resources/js/visual-editor/support/hook-aliases.ts` on the JS side;
  the JS shim runs at `Number.MIN_SAFE_INTEGER` priority so real
  subscribers on the paired name are surfaced before priority-sorted
  dispatch of the applied name's callbacks. A deprecation notice is
  logged the first time an alias resolves per process. Rename table:

    - `ap.visual-editor.resources` → `ap.visualEditor.resources`
    - `ap.visual-editor.templates` → `ap.visualEditor.templates`
    - `ap.visual-editor.template-parts` → `ap.visualEditor.templateParts`
    - `ap.visual-editor.patterns` → `ap.visualEditor.patterns`
    - `ap.visual-editor.global-styles` → `ap.visualEditor.globalStyles`
    - `ap.visual-editor.navigation` → `ap.visualEditor.navigation`
    - `ap.visual-editor.visibility.register-rules` → `ap.visualEditor.visibility.registerRules`
    - `ap.visual-editor.visibility.evaluated` → `ap.visualEditor.visibility.evaluated`
    - `ap.visual-editor.visibility.user-search-results` → `ap.visualEditor.visibility.userSearchResults`
    - `ap.visual-editor.rendered-block` → `ap.visualEditor.renderedBlock`
    - `ap.visual-editor.breadcrumbs.trail` → `ap.visualEditor.breadcrumbs.trail`
    - `ap.visual-editor.loginout.envelope` → `ap.visualEditor.loginout.envelope`
    - `ap.visual-editor.loginout.login-form` → `ap.visualEditor.loginout.loginForm`
    - `visual_editor.pre_publish_checks` → `ap.visualEditor.prePublishChecks`
    - `ap.icons.register-icon-sets` → `ap.icons.registerIconSets`
    - `ap.visual-editor.background-controls` → `ap.visualEditor.backgroundControls`
    - `ap.visual-editor.canvas-styles` → `ap.visualEditor.canvasStyles`
    - `ap.visual-editor.document-panels` → `ap.visualEditor.documentPanels`

  See `src/Support/HookAliases.php` for the full canonical list. Bumps
  the `artisanpack-ui/hooks` Composer requirement to `^1.2` (v1.3.x in
  practice) to pick up the `deprecateHook()` helper (v1.3.0).

- **Site-editor map resolvers key by the entry's own identifier, not the
  raw filter key.** Previously, `find( $rawKey )` succeeded when a
  contributor supplied a filter map whose keys diverged from the entries'
  own `slug` / `location`. That path is gone: `find()` now only resolves
  by the value object's identifier. Contributors that already stamp the
  matching `slug` / `location` on their entries (all first-party
  contributors, including cms-framework) are unaffected.
- **Canvas CSS endpoint delegates to cms-framework's `ThemeStylesheetReader`.**
  `GET /visual-editor/api/global-styles/css` now delegates the theme-file
  read to `ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeStylesheetReader`
  when it's available (cms-framework ≥ 2.5). The endpoint now concatenates
  emitter output + `themes/{slug}/style.css` + `themes/{slug}/editor.css`
  (the last is new — closes the canvas half of cms-framework #199, giving
  the site editor a WordPress `add_editor_style()` analog). Section banners
  switch from `/* === theme stylesheet === */` to per-file banners
  (`/* === style.css === */`, `/* === editor.css === */`) so devtools
  inspection matches cms-framework's endpoint. Falls back to the inline
  `readThemeStylesheet()` helper for cms-framework < 2.5 — the fallback
  preserves the pre-change behavior (style.css only, historical banner
  text) so themes on older cms-framework releases keep the canvas parity
  the endpoint already delivered.

### Fixed

- **Site-editor resolvers accept numeric-string map keys (cms-framework #203).**
  WordPress template hierarchy filenames like `404.html` / `500.html` double
  as slugs. PHP auto-coerces numeric-string array keys to `int` at insertion
  time and `array_merge` renumbers int-keyed entries sequentially, so
  contributors of `ap.visualEditor.{templates,templateParts,patterns,navigation}`
  cannot preserve the intended string key from upstream. `AbstractMapResolver`
  now accepts `int` keys and re-keys the normalized output map by each value
  object's own identifier via a new `identifierOf()` hook (`slug` for
  templates/parts/patterns, `location` for menus). Empty identifiers and
  identifier collisions across entries now throw
  `SiteEditorRegistrationException` at first read instead of silently
  producing an unaddressable / clobbered entry.

## [1.4.0] - 2026-07-16

### Added

- **Dynamic content editor UX (#650).** cms-framework Dynamic Content
  integration inside the visual editor: new `dynamic_content`
  block-binding source, batched `POST /dynamic-content/resolve` +
  `GET /dynamic-content/sources` REST endpoints (gated by the
  `SiteEditorAccessGate` and throttled at `60,1`), `{{`
  autocomplete + token chip decoration + a Token Inserter modal, a
  Dynamic Content tab on the link picker, and an Image block DC
  binding UI. Ships two new blocks: `artisanpack/snippet` (reusable
  block with cycle-guarded CRUD + admin surface at
  `/visual-editor/snippets`) and `artisanpack/dynamic-loop` (iterates
  a collection source and re-renders inner blocks per record). SSR
  wires binding resolution into the Blade renderer via an optional
  `BindingResolver` dependency, and a new `WantsInnerBlocks` marker
  lets dynamic blocks that need the inner tree at render time (loop)
  receive it. cms-framework is a soft dependency — everything
  degrades cleanly when it's not installed. New `docs/dynamic-content.md`
  covers tokens, block bindings, snippets, the loop block, and the
  render pipeline; `docs/block-bindings.md` reconciled to cover the
  bindings sidecar, `fallback` policy, and corrected endpoint paths.
- **Block Visibility (#491, #492, #493).** Per-block runtime
  visibility rules that evaluate **server-side** so hidden blocks
  never emit markup. A single **Visibility** inspector panel exposes
  three rule families: **contextual** (master Hide, screen size,
  query string, referrer, browser/OS/device), **user & auth**
  (login state, user role, specific user via
  `/visual-editor/users/search` with `SiteEditorAccessGate`
  authorization + LIKE escaping), and **scheduling** (single
  date/time window, recurring weekly schedule, per-rule timezone
  override, overnight window support). The editor canvas dims
  hidden blocks so authors can still see and select them while
  they're toggled off; the Blade, React, and Vue renderers each
  filter the tree through `filterVisibleBlocks()` +
  `stampVisibilityScopes()` and emit `<style data-ve-visibility>`
  media queries per hidden breakpoint. Configurable via
  `config('artisanpack.visual-editor.visibility')` — set
  `enabled => false` to bypass every rule site-wide during incident
  response. New `docs/visibility.md`.
- **Responsive preview device sizes (#617).** Extended the
  ViewportSwitcher into a unified device-preview + edit-scope
  control. Selecting a preset atomically resizes the canvas iframe
  to the breakpoint's `previewWidthPx` and scopes subsequent style
  edits to that key. `Breakpoint` gains optional `label` and
  `previewWidthPx` fields (both TS + PHP registries); PHP config
  accepts either the legacy scalar `'sm' => '640px'` shape or the
  new object shape
  `'sm' => ['minWidthPx' => 640, 'previewWidthPx' => 375, 'label' => 'iPhone']`.
  Ship defaults: Mobile (sm/375px), Tablet (md/768px), Desktop
  (lg/1440px), xl+ (1280px), 2xl+ (1536px). Both editor shells now
  stamp `data-breakpoints` with the merged registry snapshot so the
  React shell can hydrate labels and preview widths without a
  round-trip. `docs/blocks/Responsive-Design-Tools.md` updated with
  the object-form config and shipped defaults table.
- **Page pattern inserter modal (#639).** WordPress-style "Choose a
  pattern" modal that auto-opens when the editor loads a
  never-saved record with no content, and can be re-opened at any
  time from a new top-bar button next to the `+` inserter.
  Patterns are grouped by category in a responsive grid, and — for
  the `pages` resource — an optional template selector renders
  site-editor templates (`/visual-editor/api/templates`) alongside
  the pattern grid; picking one writes through the existing
  `template` field on the page. Auto-open is gated by a
  self-contained "never saved" heuristic (empty content plus
  `created_at === updated_at`), so any save — even a blank canvas
  — permanently suppresses the auto-open. Zero patterns for the
  current post-type context suppresses both auto-open and the
  toolbar button; a Blank starter (`page/blank`) ships as the
  seed so the modal always has at least one entry. The modal
  fetches with `?source=theme` so user-created snippet patterns
  (saved via "Convert to pattern" in the sidebar inserter) don't
  leak into the whole-page picker — those still surface in the
  block inserter panel where they belong. Client-side the modal
  further tightens the fetch to patterns whose `post_types` array
  explicitly includes the current context (with a carve-out that
  always keeps the built-in `page/blank` seed) — the whole-page
  picker is meant for starter layouts a developer or theme
  deliberately declared, not the general pattern library.
- **Pattern registration `post_types` scope (#639).** Every
  contributor entry to the `ap.visual-editor.patterns` filter may
  now carry an optional `post_types: string[]` array (Gutenberg
  convention). Patterns without a scope stay available in every
  post-type context; patterns with a scope surface only when the
  requested slug matches. The scope reaches the client via the
  new `post_types` field on `PatternAdapter`'s WP-shape output,
  and the `PatternController` index accepts a new
  `?post_type={slug}` query param that applies the scope filter
  server-side.
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
  applied but no ancestor is positioned. Per-breakpoint authoring
  follows the top-bar viewport switcher (via the shared
  active-breakpoint store) — no separate control lives in the panel,
  matching every sibling responsive-aware inspector panel.
- **Enabled `supports.position: true` on 82 top-level artisanpack
  blocks (#645).** Every `resources/js/visual-editor/blocks/*/block.json`
  that doesn't declare a `parent` or `ancestor` restriction now
  opts in. Child-only blocks (accordion internals, list items,
  columns, buttons children, query pagination, etc.) are
  deliberately skipped — positioning a child that a container
  relies on for layout breaks the container's contract.
  `artisanpack/group` already carried Gutenberg's
  `supports.position: { sticky: true }` object shape, which
  `positionEnabled()` also recognizes; no change needed.
- **Position support on core containers (#646).** Functionally
  subsumed by #645 given the I7 (#415) cutover — this repo no
  longer calls `registerCoreBlocks()`, so no `core/*` block enters
  the editor. Every container listed in the parent issue
  (`core/group`, `core/columns`, `core/column`, `core/cover`,
  `core/row`, `core/stack`, `core/grid`, `core/buttons`,
  `core/image`) has an `artisanpack/*` fork covered by #645.
- **Integration + e2e test coverage (#647).** Round-trip
  integration tests exercising resolver + emitter for a full
  base + per-breakpoint payload, legacy sticky no-churn, and the
  static-toggle-preserves-orphan-fields flow. Playwright e2e
  spec (`tests/E2E/positioning.spec.ts`) documents the runtime
  contract for sticky groups, absolute covers, ancestor
  warnings, per-breakpoint viewport switching, and legacy
  sticky — commented pending the Playwright runner (matches
  the `animations.spec.ts` precedent).
- **Docs (#648).** New `docs/position.md` covering opt-in,
  attribute shape, position values, offset units, per-breakpoint
  inheritance, the ancestor warning, the two frontend emission
  channels (inline + `<style data-ve-position>`), and a
  troubleshooting section. Linked from `docs/home.md`.
- **`ap.visual-editor.background-controls` filter (#649).** New
  JS filter that lets third-party packages contribute panels to
  the background/appearance area of any block that opts into a
  background support — no per-package `editor.BlockEdit` HOC
  required. The visual editor now owns the target-block decision
  via a single HOC that gates on `supports.background` and
  `supports.color.background`, then renders filter-registered
  controls sorted by priority (default 10, lower first) and
  deduped by id (last-wins, mirroring `@wordpress/hooks`). The
  HOC runs innermost so `context.attributes` is the
  breakpoint-merged / state-resolved view and
  `context.setAttributes` routes through the responsive/state
  wrappers. Wired into both the post-editor and site-editor
  bootstraps.
- **`ap.visual-editor.rendered-block` filter (PHP).** Fires in
  `BlockRenderer::renderBlock` after each block (static or
  dynamic) finishes rendering. Callbacks receive the HTML, the
  block name, and the normalized attributes, and must return an
  HTML string. Lets cross-cutting effects (frosted glass,
  contrast overlays, motion wrappers) post-process a block's
  markup without every host having to modify the renderer.
- **`ap.visual-editor.canvas-styles` filter (JS).** Filters the
  ordered `CanvasStyle[]` handed to `BlockCanvas`'s
  `__unstableEditorStyles` prop. Callbacks return a
  `CanvasStyle[]`; non-array returns fall through to the base
  list and non-object entries are dropped. Lets packages push
  their stylesheets into the Gutenberg iframe (which is
  sandboxed from the parent document's Vite-injected styles)
  without touching `canvas-styles.ts` for every new integration.

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
