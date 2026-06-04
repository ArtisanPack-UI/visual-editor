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

Full inventory: [`docs/core-data-shim.md`](core-data-shim.md).

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
   [Global styles §8](global-styles.md#8-handling-a-future-schema-bump).
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
5. Update [Global styles](global-styles.md#1-pinned-schema-version).

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
- **Dark mode.** Toggled by DaisyUI's `data-theme` attribute on a parent
  element. The editor inherits the host page's theme; flipping themes
  inside the editor without a remount produces inconsistent paint
  states until the next interaction.
- **Avoid `@artisanpack-ui/react` Popover and Dropdown inside the
  editor.** Both have known interactions with the editor's portal that
  freeze the canvas. Use inline manual patterns or
  `@wordpress/components` Popover / Dropdown directly.

Full theme contract: [Theming](theming.md).

---

## 6. "Unknown resource" on save

`PUT /visual-editor/api/{resource}/{id}/content` returns 404 with
"Unknown resource '{slug}'". The slug isn't in
`config('artisanpack.visual-editor.resources')` and no
`ap.visual-editor.resources` filter contributor added it.

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
  [Content model §3](content-model.md#3-policies-and-authorization)).
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

## See also

- [Getting started](getting-started.md)
- [Core-data shim](core-data-shim.md)
- [Global styles](global-styles.md)
- [Theming](theming.md)
