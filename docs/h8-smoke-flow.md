# H8 smoke flow

Manual QA checklist that exercises the full Phase H pipeline end-to-end.
Run it on every release candidate before tagging visual-editor v1.x and
on every cms-framework v1.x release that pairs with it.

Not automated — Phase H spans two Composer packages, a real database,
the site-editor SPA, and admin-side UI; reproducing that in Testbench
hides more failure modes than it catches. The smoke flow stays a manual
regression net by design; see [`plans/14`](plans/14-cms-framework-site-editor-integration.md) §H8.

## Test surface

The [`themes/dev-sample/`](../themes/dev-sample/) theme is the smallest
fixture that touches every Phase H concept — templates, template parts,
patterns (synced + unsynced), global styles, menu locations. Activate
it, run the steps below, hit every assertion.

A real consumer (e.g. Keystone CMS with `jmwd-default`) covers the same
surface in dogfooding, but `dev-sample` keeps the regression net inside
this repo so a visual-editor breaking change shows up in this checklist
before it reaches downstream consumers.

## Prerequisites

- Composer-installable Laravel host with PHP 8.4+.
- Both `artisanpack-ui/visual-editor` and `artisanpack-ui/cms-framework`
  installed and version-paired (see
  [`README.md` § Version compatibility](../README.md#version-compatibility)).
- The host's auth + admin chrome up (Keystone CMS works; any
  Testbench-style app with users + a `role:admin` middleware also works).

## Steps

### A. Install + activate

1. Run package migrations: `php artisan migrate`. Confirm
   `template_parts`, `menus`, `menu_items`, `menu_location_assignments`,
   `visual_editor_templates`, `visual_editor_template_parts`,
   `visual_editor_patterns`, `visual_editor_global_styles` all exist.
2. Place `themes/dev-sample/` in the host's themes directory (the path
   resolved by `config('cms.themes.directory')`, default `themes/`).
3. Activate the theme via the host's theme-management UI or by setting
   `themes.activeTheme` directly via `apUpdateSetting`.
4. Confirm `themes.activeTheme` is `dev-sample` and that the host's
   theme view path is registered (the host's `index` template should
   resolve to `themes/dev-sample/templates/index.html` or a host-defined
   wrapper).

**Assert:** the public front-end serves `/` with the dev-sample content
(site title, the unsynced hero copy if it was seeded into a page, footer
separator + copyright).

### B. Open the site editor

1. Navigate to the host's `/admin/site-editor` route (or wherever the
   consuming app mounts the visual-editor SPA).
2. The shell loads with the navigator open by default. Templates +
   Template Parts sections list theme files.

**Assert:** Templates section shows `index` (source: theme file).
Template Parts section shows `header` and `footer` (source: theme file).

### C. File → DB authority flip (templates)

1. From the navigator, click `index` to open it in the canvas.
2. Edit the heading copy — change `Build with blocks` to
   `Build with blocks — edited via the site editor`.
3. Save (top-right button or `⌘S`).
4. Refresh `/admin/site-editor` to reload the template list.

**Assert:**
- `visual_editor_templates` now has a row for `index` with the edited
  block tree.
- The Templates row in the navigator surfaces a `has_theme_file: true`
  + a "Revert to theme" action.
- The public front-end at `/` renders the edited copy, not the theme
  file's original.

### D. Revert flip

1. Click the "Revert to theme" action on the `index` row.

**Assert:**
- `visual_editor_templates` row for `index` is deleted.
- Template list shows `index` again with `source: theme file`.
- Public front-end serves the theme-file copy again.

### E. Template-part edit (same flip on a different entity)

1. Repeat C/D against the `header` template part — edit the site title
   block, save, verify the override row appears in
   `visual_editor_template_parts`, revert, verify the row is gone.

### F. Patterns — synced + unsynced

1. Open `index` in the canvas.
2. Click the top-bar "+" (the real Inserter — #439). Switch to the
   Patterns tab.
3. Confirm both dev-sample patterns appear:
   - `Hero with CTA` (unsynced)
   - `Footer credits (synced)` (synced)
4. Insert the unsynced pattern into the canvas.

**Assert:** a copy of the pattern's block tree is inserted as plain
blocks. Editing it doesn't modify the source pattern.

5. Insert the synced pattern.

**Assert:** a single `core/block` reference is inserted, not a copy.
Editing it routes through the synced-pattern edit modal; saving updates
the pattern record, which propagates to every render site-wide.

### G. Global styles

1. Open the Styles section.
2. Adjust one preset color value (e.g. accent).
3. Save.

**Assert:**
- cms-framework's `global_styles` table carries the edited payload
  keyed on `dev-sample` (slice 5 retired the orphan
  `visual_editor_global_styles` table — emission now flows through
  cms-framework's `GlobalStylesEmitter`).
- `GET /visual-editor/api/global-styles/css` returns the new value in
  the `--wp--preset--color--accent` declaration.
- Public front-end renders the new color (after cache-bust if the
  consumer caches the compiled CSS).

### G2. Canvas ↔ front-end style parity (Keystone #47)

1. Without making any further edits, open a page that includes the
   `core/heading` and `core/button` blocks in the site editor.
2. Compare the canvas rendering against the same page on the public
   front-end (open in two browser tabs).

**Assert:**
- Heading typography, link color, button background, and palette
  preset usage match between the two surfaces. The canvas pulls the
  same compiled CSS the front-end emits (via
  `/visual-editor/api/global-styles/css`), so divergence here means
  the canvas's `BlockEditorBoundary` stopped appending it to
  `editorSettings.styles`.

### H. Menus + menu locations

1. Open the Navigation section.
2. Create a new menu, add 2–3 items, assign it to the `primary` location.
3. Save.

**Assert:**
- `menus` + `menu_items` + `menu_location_assignments` carry the new
  records.
- The `header` template part's `core/navigation` block renders the
  assigned menu on the public front-end.
- The `footer` location stays declared-but-unassigned without breaking
  anything (orphaned-location regression).

## When something fails

- **Front-end renders theme file even after override:** check
  `TemplateResolver::resolve()` is returning the DB row when present.
  Likely #434 territory if file-authority is reasserting.
- **Patterns tab shows "Failed to load patterns" with empty DB:** check
  `listPatterns()` returns a flat array (no `{ data, meta }` envelope).
  Regression covered by `editor/__tests__/inserter-patterns-panel.test.tsx`'s
  empty-state case.
- **Editor canvas blank on seeded content:** seeded blocks need
  `clientId` + `isValid: true` and non-empty `attributes` objects.
  Regression covered by Keystone's `ThemeSeedApplier`.

## Logging

Each release that runs this flow should record:

- Date + git SHA of visual-editor + cms-framework
- Pass / fail per step
- Any deviations from the assertions (and the issue ID filed for the
  deviation)

Keep the log in the release PR description or `docs/releases/` —
wherever the release-engineering process lives.
