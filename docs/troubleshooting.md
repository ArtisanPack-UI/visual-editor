# Troubleshooting

Common problems and the underlying constraints they surface, in rough
order of how often they come up.

---

## 1. Editor doesn't appear

The Blade component rendered, but the React app didn't boot. Most likely
causes, in order:

1. **Vite bundle not built.** Run `npm run build` (or `npm run dev` in
   development). The editor mounts from the package's compiled JS bundle,
   which Vite emits to `public/build/`.
2. **Peer dependencies missing.** Tailwind CSS v4 + DaisyUI v5 must be
   loaded at the page level. The editor inherits them — without them,
   the React tree renders but is invisible.
3. **JS console errors.** Open the browser console. Missing `core-data`
   shim (the package aliases `@wordpress/core-data` to its own shim in
   `vite.config.ts` — confirm your Vite config doesn't override the
   alias).
4. **The mount point is hidden by CSS.** `[data-ap-visual-editor]` needs
   visible dimensions — wrap it in a layout that gives it height.

---

## 2. Core-data shim entities and missing data

The package ships its own `@wordpress/core-data` shim under
`resources/js/visual-editor/vendor/core-data-shim.ts`. The shim
implements a curated subset of selectors that Gutenberg expects:
`getEntityRecord`, `getEntityRecords`, `getEditedEntityRecord`,
`canUser`, `getCurrentUser`, `getMedia`, plus the resolver machinery
(`hasFinishedResolution`, `isResolving`).

Symptoms when the shim doesn't cover a code path:

- A block renders empty in the canvas with no error.
- The console shows `Cannot read properties of undefined` from a
  Gutenberg internal.
- A core-data selector returns `null` and the block infinite-loops on
  `isResolving`.

**Diagnosing.** Add a debugger to the shim's `getEntityRecord` and see
what `{ kind, name, id }` the block is asking for. If `kind:name` is
unregistered, the shim returns `null` — register the entity in
`core-data-shim.ts` or add a back-compat stub.

**Fixing.** New entities go into the `entities` array; new selectors go
into the `selectors` map. Both files are tested by `vitest run` —
adding a fixture for the new entity and asserting the shim returns it
catches regressions.

---

## 3. WordPress package upgrades

The package pins `@wordpress/*` to the versions listed in `package.json`.
Renovate / Dependabot is **paused** for these packages during V1.x —
mid-stream minor bumps invalidate weeks of UI work.

### Upgrade procedure

1. **Decide to upgrade deliberately.** Read the upstream `CHANGELOG.md`
   for every `@wordpress/*` package being bumped. Note schema-version
   bumps, removed selectors, renamed components.
2. **Update `package.json` pins together.** All `@wordpress/*` packages
   should move in lockstep — they share internal contracts and skewing
   them cross-version causes subtle runtime breakage.
3. **Re-run the parity check.** `npm run verify:parity` renders fixture
   trees through Blade + React + Vue renderers and diffs the output.
   Drift means a block fork's markup changed upstream.
4. **Re-run upstream-diff.** `npm run upstream-diff` compares each
   forked block's `register()` call to a fresh upstream registration
   and surfaces drift in attributes / supports / variations.
5. **Decide on each drift.** Adopt upstream change → update the fork +
   bump its `upstream-state.json`. Reject the change → annotate the
   `upstream-state.json` with the rejection reason.
6. **Schema-version check.** If theme.json schema bumped, follow
   [Global styles §8](site-editor/Global-Styles.md#8-handling-a-future-schema-bump).
7. **Run the full test suite.** `./vendor/bin/pest && npm test`.

### Symptoms of an unaudited upgrade

- New block variations appearing in the inserter that the team didn't
  agree to ship.
- Removed selectors causing core-data shim warnings on selector lookup.
- theme.json `version` validation errors on `PUT /global-styles/{id}`.
- `@wordpress/components` style regressions (the package overrides
  Gutenberg button/modal styling — restyling work needs to be redone
  against the new component internals).

---

## 4. theme.json schema upgrades

The pinned schema version (currently 3) is enforced by
`UpdateGlobalStylesRequest` — every PUT must include
`version === 3`. When the upstream schema bumps:

1. Audit the new schema's added/removed top-level keys.
2. Update `artisanpack.visual-editor.global_styles.schema_version` to
   the new version.
3. Update `resources/theme-json/default-base.php` defaults.
4. Ship a migration that either rewrites existing user records into the
   new schema or documents a manual upgrade path for customized records.
5. Update [Global styles](site-editor/Global-Styles.md#1-pinned-schema-version).

The pin exists precisely so this is conscious work — not silent drift on
`npm update`.

---

## 5. Theming quirks

The editor restyles `@wordpress/components` (Button, Modal, Notice, etc.)
to DaisyUI via the
[`@artisanpack-ui/react`](https://www.npmjs.com/package/@artisanpack-ui/react)
component pack. Restyling is best-effort — some quirks:

- **Modal layering.** Gutenberg's modals use a portal at document root.
  If the host page has its own portal root with a higher `z-index`, the
  Gutenberg modal can render behind it. Bump the host portal to
  `z-index: 60` or lower.
- **Color tokens.** The editor reads palette tokens from the active
  global-styles record. If colors look wrong, check `/global-styles/css`
  in DevTools to confirm the expected variables are emitted.
- **Palette slugs named `text`, `link` or `border`.** Their color class
  shares a name with the `has-text-color` / `has-link-color` /
  `has-border-color` markers. Before v1.14.1 (cms-framework 2.14.1) the
  slug's color was forced onto every block with a custom color, so a
  button with a palette text color showed the theme's `text` color on the
  front end. Upgrade both packages; the utilities are now scoped to real
  picks of the slug.
- **Dark mode.** Toggled by DaisyUI's `data-theme` attribute on a parent
  element. The editor inherits the host page's theme; flipping themes
  inside the editor without a remount produces inconsistent paint
  states until the next interaction.
- **Avoid `@artisanpack-ui/react` Popover and Dropdown inside the
  editor.** Both have known interactions with the editor's portal that
  freeze the canvas. Use inline manual patterns or
  `@wordpress/components` Popover / Dropdown directly.
- **Theme `style.css` wins over theme.json after upgrading to 1.15.**
  Since v1.15.0 the compiled global styles print in `<head>`, before the
  theme's own stylesheet, as they do in the editor canvas and WordPress.
  A `style.css` rule now beats the theme.json rule it overrides on the
  front end, where before the body-printed global styles won. Mount
  `<x-ve-blocks-styles>` before the `<link>` to your theme stylesheet,
  and move any rule that should lose into theme.json. To get the 1.14
  placement back, set `global_styles_in_head` to `false` (see
  [Configuration](Configuration.md#global_styles_in_head)).
- **Global styles missing from mail or `Blade::render()` output.** When
  blocks render inside an `@extends` section or a component slot before
  the head, the global styles are queued on the `ve-global-styles` stack
  for `<x-ve-blocks-styles>`. On HTML responses the renderer's
  middleware injects anything left queued into `<head>`, but output that
  never becomes an HTTP response (a mailable, a string from
  `Blade::render()` or `view()->render()`) gets no injection. Set
  `global_styles_in_head` to `false` for those renders, or print
  `@stack('ve-global-styles')` in their layout's head.
- **Hover glow changed color after upgrading to 1.15.** The `glow`
  hover animation no longer hard-codes indigo. Without a per-block
  **Glow color**, it uses the theme's
  `settings.custom.animation.glowColor`, then the `primary` palette
  color at 35%. The package default palette always declares `primary`,
  so existing glows change color. Set
  `settings.custom.animation.glowColor` in theme.json to pin one color
  site-wide, or pick a Glow color on the block. See
  [Animations](animations.md).

Full theme contract: [Theming](post-editor/Theming.md).

---

## 6. "Unknown resource" on save

`PUT /visual-editor/api/{resource}/{id}/content` returns 404 with
"Unknown resource '{slug}'". The slug isn't in
`config('artisanpack.visual-editor.resources')` and no
`ap.visualEditor.resources` filter contributor added it.

Check:

- `php artisan config:clear` if you recently edited the config.
- The Blade component's `resource="..."` attribute matches a registered
  slug.
- If you're relying on cms-framework auto-registration, confirm
  cms-framework is installed and the version pair is compatible.

---

## 7. Saves succeed but content disappears on reload

The model is using `HasBlockContent` but `$blockContentColumn` doesn't
match an actual column on the model, or the column isn't cast to JSON.

Check:

- The migration added a JSON column with the expected name (default:
  `content`).
- If the model already had a `content` column with a non-JSON cast
  (e.g. `string`), set `$blockContentColumn` to a different name or
  override the cast in `casts()`.

---

## 8. Site editor returns 403

The default `SiteEditorAccessGate` is `DenyByDefaultGate` — fail-closed.
Either:

- Bind a permissive gate in your app service provider (see
  [Content model §3](content-model.md#3-policies-and-authorization) and
  [Access Gate](site-editor/Access-Gate.md)).
- Install cms-framework, which binds `CmsFrameworkInstallGate`
  automatically.

---

## 9. Renderer parity drift

`npm run verify:parity` fails after editing a block. The Blade, React,
and Vue renderers produce different HTML for the same block tree.

Common causes:

- Edited the `save.tsx` markup but didn't update the corresponding
  Blade partial / React component / Vue component.
- Static block has different default attribute coercion in two
  renderers (e.g. one falls back to `''`, another to `null`).
- Dynamic block's `render()` output drifted from the React renderer's
  `<DynamicBlock>` fallback expectations.

Fix the renderer that disagrees with `edit.tsx` / `save.tsx`. The
parity script is the source of truth — if a fixture produces three
different HTML strings, the fixture is wrong or one of the renderers is
wrong, never the script.

---

## 10. Dev sandbox specifics

The `/ve-sandbox` route (`npm run dev:sandbox`) is a standalone editor
playground that doesn't talk to the API. Useful for block authoring in
isolation; will be removed post-V1.

If the sandbox crashes but the real editor works, you've probably hit a
sandbox-only regression — file under `area:sandbox` rather than
debugging the production editor.

---

## 11. Theme templates render empty (or structure without text)

*Fixed in v1.5.5 (#688).*

**Symptom.** A block theme's `templates/*.html` renders as the right boxes
and wrappers with no words in them, or a `template-part` reference inlines as
an empty wrapper.

**Cause.** Gutenberg persists most block text in the block's saved HTML, not
in the delimiter's JSON — across the `artisanpack-ui` theme, zero of its 130
paragraph/heading blocks serialize a `content` attribute. Converting the
parser's `{blockName, attrs, innerHTML}` shape to the renderer's
`{name, attributes}` shape by renaming keys therefore drops every string on
the page. Separately, cms-framework's `TemplatePartResolver` does not parse
theme files, so `.html`-sourced parts arrived with `blocks` empty and their
markup sitting unread in `rawContent`.

**Fix.** Upgrade to v1.5.5 or later, which hydrates markup through
`BlockMarkupHydrator` — replaying each block type's `block.json` attribute
definitions back over the saved HTML — and hydrates `.html`-sourced template
parts from their raw markup when `blocks` comes back empty. If you are
starting from a markup string rather than a tree, call
`BlockRenderer::renderMarkup()`; see
[Rendering raw block markup](renderers.md#rendering-raw-block-markup).

**Still empty after upgrading?** Hydration needs cms-framework's markup
parser. Check `BlockMarkupHydrator::canParseMarkup()` — when it returns
`false`, `artisanpack-ui/cms-framework` is not installed and `renderMarkup()`
returns an empty string by design.

---

## 12. Custom CSS missing, rejected or stripped

The **Custom CSS** field in Styles → Effects (since v1.15.0) is guarded
by the deny-by-default `visual-editor.edit-block-css` ability.

**The field doesn't appear.** The user lacks the
`effects.custom_css_capability` capability (default `edit_block_css`).
Grant it to the role, or redefine the gate. A permission backend that
throws for an unknown permission (Spatie, when `edit_block_css` was
never created) denies rather than erroring. Vue and Inertia hosts must
pass `canEditBlockCss` to the editor mount themselves; without it the
field stays hidden. See
[Access Gate](site-editor/Access-Gate.md#custom-css-ability-visual-editoredit-block-css).

**The CSS is saved but nothing prints.** The whole value is rejected,
not partly printed, when it:

- uses an unquoted URL: write `url("image.png")` or `url('image.png')`,
  not `url(image.png)`;
- has braces, parentheses or brackets that don't nest correctly (each
  `{`, `(` and `[` must close in order; `color: rgb(` or `a)` is
  rejected), outside strings and comments;
- loads a resource (`url(`, `image-set(`, `image(`, `src(`) while
  `effects.custom_css_allow_urls` is `false`.

The editor marks the field invalid and says why. Content saved by 1.15
development builds with unquoted URLs needs re-saving with quoted URLs.
In React and Vue, pass `customCssAllowUrls={false}` to `<BlockTree>`
when the config is `false`, or those renderers print what Blade drops.

**The CSS disappears on save.** For a user without the ability, every
save keeps only the Custom CSS already stored on that record. Code that
imports or seeds block content with Custom CSS while a user is logged
in (a web-triggered import, a test using `actingAs()`) hits the same
rule; wrap the write in
`app( CustomCssModelGuard::class )->trusted( fn () => … )`. Console
runs with no logged-in user aren't affected.

---

## 13. Heading anchor links changed after upgrading to 1.15

Since v1.15.0, automatic heading anchors are reduced to the characters
every renderer keeps in an `id` (`[A-Za-z0-9_:.-]`, runs of `-`
collapsed) before duplicates get their `-1`, `-2` suffix. That fixes
Table of Contents links to ids that weren't printed, and duplicate ids
on non-Latin headings, but it changes the auto anchor of headings with
non-ASCII letters or symbols: `creme--x` becomes `creme-x`, and a
heading whose letters all drop out (`日本語`) becomes `heading`.

Author-set anchors and anchors the editor already saved on the block
don't change. To keep an old link working, set the heading's **HTML
anchor** to the old value in the editor.

In React and Vue, anchors are unique within one `<BlockTree>`. Two trees
on one page (for example a header tree and a content tree) can repeat
an id; render them as one tree or set author anchors on headings that
could collide.

---

## 14. Bindings or block previews return 403 in the editor

Since v1.15.0, `POST /visual-editor/api/bindings/resolve` and
`POST /visual-editor/api/blocks/preview` check the `view` ability on the
record being edited when its model has a registered policy. An editor
who can open the editor but whose policy denies `view` on that record
gets `403`: the canvas shows the blocks' static values instead of the
bound ones, and server-rendered block previews for that record show the
request's error message instead of the preview. Allow `view` in the policy for those users. Models
without a policy are unaffected.

Since v1.17.0 the editor sends both as batches, so in DevTools the
request itself answers `200`: look in its `results` for items with
`"error": "forbidden"` (bindings) or `"status": 403` (block previews). See
[Block Bindings](block-bindings.md#record-access-check).

---

## See also

- [Quick Start](Quick-Start.md)
- [Global styles](site-editor/Global-Styles.md)
- [Theming](post-editor/Theming.md)
