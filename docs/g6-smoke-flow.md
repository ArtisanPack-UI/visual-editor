# G6 smoke flow

Manual QA checklist that exercises the cms-framework integration
(Phase G) end-to-end. Run it on every release candidate before tagging
visual-editor v1.0.x and on every cms-framework v1.x release that pairs
with it.

Not automated — Phase G spans two Composer packages, a real database,
the editor SPA, public-route rendering across the Blade renderer, and
cms-framework's admin / settings UI. Reproducing that in Testbench
hides more failure modes than it catches. The smoke flow stays a manual
regression net by design; see
[`plans/12-cms-framework-integration.md`](plans/12-cms-framework-integration.md) §3 (Phase G6).

This flow is complementary to [`h8-smoke-flow.md`](h8-smoke-flow.md):
G6 verifies post / page editing, site-meta, and query-loop blocks
against cms-framework's content stores; H8 verifies the site-editor
SPA (templates, parts, patterns, global styles, navigation). Run G6
before H8 on a release candidate so a content-side regression is
caught before the site-editor pass.

## Test surface

The `~/Herd/artisanpack-ui` dev app is the reference fixture. Both
packages are wired in via path repositories, so source changes in
either package land in the dev app on the next refresh.

## Prerequisites

- Composer-installable Laravel host with PHP 8.4+.
- Both `artisanpack-ui/visual-editor` and `artisanpack-ui/cms-framework`
  installed and version-paired (see
  [`README.md` § Version compatibility](../README.md#version-compatibility)).
- A user with permission to access the editor — the dev app's default
  admin user works; on a host with RBAC wired, give the user the
  `visual_editor.access` and `visual_editor.posts.edit` permissions.

## Steps

### A. Install + migrate

1. From the dev app root, run package migrations: `php artisan migrate`.
2. Confirm the cms-framework content tables — `posts`, `pages` —
   carry both the legacy `content` longText column and the new
   `block_content` json column. The dual-state is intentional (plan
   12 §4.2); the legacy column stays untouched for backwards
   compatibility.
3. Confirm cms-framework's `settings` table has rows for
   `site.title`, `site.tagline`, `site.url`, `site.logo_id`,
   `site.icon_id`. Defaults seed from `config('app.name')` /
   `config('app.url')` on install.

**Assert:** `php artisan tinker` → `apGetSetting('site.title')` returns
the dev app's configured app name, not `null`.

### B. Create a post

1. Create a `Post` via cms-framework's admin (or via factory in
   tinker: `Post::factory()->create(['title' => 'G6 smoke post'])`).
2. Note the post id.

**Assert:** the post exists with `block_content` set to `null` and
`content` carrying whatever the factory / admin emitted.

### C. Open the post in the visual editor

1. Navigate to `/admin/visual-editor/posts/{id}` (or the route the
   host app mounts the post editor on — the visual-editor entity for
   `postType:post` resolves through `/visual-editor/api/posts/{id}`).
2. The editor shell loads with the post's existing content rendered as
   blocks.

**Assert:**
- The inspector sidebar's document panel surfaces status, slug,
  author, taxonomies, featured image, and excerpt (G3 sidebar fields).
- No console errors about missing entities. `dispatch('core').addEntities`
  has registered `postType:post` and `postType:page` against
  `/visual-editor/api/posts` and `/visual-editor/api/pages`.

### D. Add and save site-context blocks

1. From the inserter, add a `core/site-title` block.
2. Add a `core/site-tagline` below it.
3. Add a `core/post-title` and a `core/post-excerpt` block.
4. Save the post (top-right Save button or `⌘S`).

**Assert:**
- The post row's `block_content` column carries the saved block tree
  (JSON with `core/site-title`, `core/site-tagline`, `core/post-title`,
  `core/post-excerpt`).
- The legacy `content` column is unchanged from step B.

### E. Add a query block

1. From the inserter, add a `core/query` block scoped to `postType:post`.
2. Leave the inner `core/post-template` defaults in place (post title,
   excerpt).
3. Save.

**Assert:**
- `block_content` now carries the query block with normalized attrs
  (postType, perPage, etc.) and the inner template.
- No console errors from the editor preview — the query block resolves
  through `POST /visual-editor/api/query/resolve` which proxies to
  cms-framework's `QueryRuntime::resolve()` (plan 12 §4.5).

### F. Render on the public front-end

1. Visit the public route for the post (the dev app exposes it under
   whatever pattern cms-framework's `BlogManager` is wired to —
   typically `/blog/{slug}` or `/posts/{slug}`).
2. Inspect the rendered page.

**Assert:**
- `core/site-title` renders the value from `apGetSetting('site.title')`.
- `core/site-tagline` renders `apGetSetting('site.tagline')` (empty
  string acceptable if the dev app hasn't set a tagline).
- `core/post-title` renders the post's title.
- `core/post-excerpt` renders the post's excerpt.
- `core/query` resolves the configured post type and renders the
  inner template once per result (limited by `perPage`).
- The Blade renderer is calling `QueryRuntime::resolve()` directly
  in-process — confirm with a query-log tap (`DB::listen(…)`) that the
  SQL hits run on the same request cycle, no HTTP round-trip.

### G. Site-meta parity (editor canvas ↔ front-end)

1. From cms-framework's admin settings UI (or via tinker:
   `apUpdateSetting('site.title', 'G6 parity check')`), change
   `site.title`.
2. Reload the post in the editor canvas.
3. Reload the public post page.

**Assert:**
- Both surfaces render the updated title without a cache clear.
- The editor's `core/site-title` block reads the new value through
  `GET /api/settings/site` (cms-framework's WP-shape settings endpoint,
  plan 12 §4.3).
- The Blade renderer reads it through `apGetSetting('site.title')`
  via `SiteMetaResolver` (`packages/visual-editor-renderer-blade/
  src/Resolvers/SiteMetaResolver.php`).

### H. Standalone cms-framework still boots

1. In a scratch directory, install cms-framework without
   visual-editor (`composer require artisanpack-ui/cms-framework`).
2. Run `php artisan migrate`.
3. Boot a tinker session.

**Assert:**
- No errors at boot. cms-framework's `class_exists` guards keep its
  editor-side hooks dormant.
- `apGetSetting('site.title')` works (settings registration is not
  guarded — it's a cms-framework primitive).
- `addFilter('ap.visual-editor.resources', …)` registration silently
  no-ops because visual-editor's filter dispatcher isn't loaded.

## When something fails

- **Editor canvas blank on the saved post:** check
  `/visual-editor/api/posts/{id}` returns a WP-shape envelope with
  `content.blocks` populated. The Phase G3 transformer in
  `src/Http/Resources/Adapters/CmsFramework/` is responsible for
  parsing `block_content` into the `content` field.
- **`core/site-*` blocks render the config fallback instead of the
  setting value:** confirm `apGetSetting()` is reachable from the
  renderer (`function_exists('apGetSetting')` in `SiteMetaResolver`).
  If cms-framework's settings module didn't boot, the helper won't be
  registered and the resolver falls back to
  `config('artisanpack.visual-editor.site_meta')`.
- **`core/query` returns an empty list:** confirm cms-framework's
  `QueryRuntime::resolve()` is dispatching to the right manager for
  the requested `postType`. The dispatch table lives in
  `ArtisanPackUI\CMSFramework\Modules\Blog\Services\QueryRuntime`.
- **Permissions show up in the editor but the user can't save:**
  expected in V1.0 — visual-editor's policies still use the "any
  authenticated user" baseline. Permission delegation lands in V1.1
  behind the
  `artisanpack.visual-editor.authorization.delegate_to_cms_framework`
  flag (plan 12 §4.6).

## Logging

Each release that runs this flow should record:

- Date + git SHA of visual-editor + cms-framework
- Pass / fail per step
- Any deviations from the assertions (and the issue ID filed for the
  deviation)

Keep the log in the release PR description or `docs/releases/` —
wherever the release-engineering process lives.
