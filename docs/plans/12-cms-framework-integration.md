# Visual Editor — cms-framework Integration Plan

**Package:** `artisanpack-ui/visual-editor` (companion: `artisanpack-ui/cms-framework`)
**Version Target:** 1.0.0
**Created:** April 26, 2026
**Status:** Planning
**Relates to:** #309 (umbrella), #395 (shim resolution), [`11-v1-expansion.md`](11-v1-expansion.md) Phase F.
**Supersedes scope of:** `01-comprehensive-plan.md` §3.2 ("CMS Framework Integration") and §9.1 ("CMS Framework Permissions") — those sections were written for the abandoned Phase 2 from-scratch React rebuild and pre-date the Gutenberg adoption pivot. Their hook names and APIs are still referenced where current.

---

## 1. Why this plan exists

The umbrella (#309) parks the real `core-data` backend in `artisanpack-ui/cms-framework` as post-V1: *"Real `@wordpress/core-data` backend — ships via `artisanpack-ui/cms-framework`, replaces shim. Tracked in that package's repo."* That deferral was correct for the Gutenberg-adoption alpha (M0–M14) and remains correct for the *full* backend swap (templates, parts, patterns, global styles, navigation moving from visual-editor's first-party tables into cms-framework).

But V1.0.0 still cannot ship "a usable Laravel CMS" (the explicit goal of [`11-v1-expansion.md`](11-v1-expansion.md) §1) without two narrower integrations:

1. **The visual editor must be able to edit cms-framework's `Post` and `Page` content.** Today, cms-framework stores `content` as longText (HTML/markdown). The editor has no way to round-trip a block tree against it.
2. **`core/site-*` blocks need a real source for site title / tagline / logo / URL.** Today they read from `config/visual-editor.php` defaults.

This plan covers exactly those two integrations plus the block re-enablement they unlock. It does **not** move site-editor entities (templates/parts/patterns/global-styles/navigation) into cms-framework — that stays on the post-V1 trajectory.

---

## 2. V1 integration scope (final)

### 2.1 Loose coupling via hooks

Neither package adds the other to `composer.json`. Detection is `class_exists()` plus filter participation, mirroring `artisanpack-ui/icons` (`addFilter('ap.icons.register-icon-sets', …)`) and the `artisanpack-ui/hooks` patterns already used across the ecosystem.

cms-framework's service-provider boot phase wraps its registrations in `class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)` so cms-framework remains usable standalone (its own admin / API works without the editor). visual-editor's service provider does not need to know about cms-framework — it only consumes filter results.

### 2.2 Editable cms-framework content (G1)

- `Post` and `Page` adopt `HasBlockContent` with `protected $blockContentColumn = 'block_content';`
- Migrations add a separate `block_content json nullable` column on `posts` and `pages`. The original `content` longText column is preserved for legacy text/excerpt/search compatibility.
- cms-framework registers its models into visual-editor's resource map via the new `ap.visual-editor.resources` filter when both packages are installed.
- `ContentTypeManager`-registered custom types are auto-picked up if their model uses `HasBlockContent`.

### 2.3 Site-meta bridge (G2)

- cms-framework registers default settings via `SettingsManager::registerSetting()`: `site.title`, `site.tagline`, `site.url`, `site.logo_id`, `site.icon_id`.
- New REST endpoint: `/api/settings/site` returning the WP-shape `/wp/v2/settings` envelope (title, description, url, site_logo, site_icon).
- visual-editor's `core/site-*` block resolvers (Blade, React, Vue renderers) read from `apGetSetting()` when the helper exists; fall back to `config('artisanpack.visual-editor.site_meta')` defaults otherwise.

### 2.4 Editor entity adapter (G3)

- visual-editor adds WP-shape resource transformers for `Post` and `Page` under `Http/Resources/Adapters/CmsFramework/`.
- The shim's editor bootstrap registers two new entities: `postType:post` → `/visual-editor/api/posts` and `postType:page` → `/visual-editor/api/pages`. These wrap the cms-framework models behind visual-editor's existing `/visual-editor/api/` prefix (auth/middleware stays consistent with other editor traffic).
- Inspector sidebar surfaces document settings (status, slug, author, taxonomies, featured image, excerpt, parent/order for pages).

### 2.5 Block deny-list cleanup (G4)

Re-enable everything except `core/latest-comments`:

- `core/post-*` — full enablement against G3 entities.
- `core/archives`, `core/categories`, `core/tag-cloud` — wire to existing cms-framework term endpoints (`/api/post-categories`, `/api/post-tags`, `/api/page-categories`, `/api/page-tags`).
- `core/query` + `core/query-loop` — loop runtime in Blade/React/Vue renderers using `BlogManager::getArchiveQuery` and an equivalent pages query.

### 2.6 Permissions registration (G5, no enforcement yet)

- cms-framework registers `visual_editor.*` permissions into its RBAC tables when visual-editor is detected (G5 only seeds; visual-editor's policies remain "any authenticated user" baseline).
- Actual policy delegation lands in V1.1 once the permission set is exercised in real apps. Defining the schema in V1 means apps that adopt the editor can build admin UI against the permission names without waiting.

### 2.7 Out of scope for V1

Explicitly deferred:

- Templates, template-parts, patterns, global-styles, navigation moving to cms-framework — V2 (#309's "Real `@wordpress/core-data` backend ships via cms-framework, replaces shim").
- `core/latest-comments` — V1.1, requires building a Comments module in cms-framework from scratch (model, migration, moderation states, REST API, nested replies, spam handling, comment block renderers across Blade/React/Vue).
- Permissions delegation in policies — V1.1.
- Forking core blocks into `artisanpack/*` (#331) — V2.
- Custom-fields surface in the editor inspector sidebar — V1.1.

---

## 3. Phase ordering

```
Phase G — cms-framework integration (V1)

  Prerequisites
    G0   core-data shim auto-resolution (#395).
         Independent of cms-framework but unblocks G3/G4.
         Lives in visual-editor.

  Resource bridge (sequential)
    G1a  cms-framework: Post + Page adopt HasBlockContent
         + block_content migration on posts and pages tables.
    G1b  visual-editor: ap.visual-editor.resources filter — resources
         config becomes (static config) ⊕ (filter results).
    G1b' cms-framework: register Post / Page into the filter under
         class_exists(VisualEditor::class).
    G1c  cms-framework: ContentTypeManager → resource auto-registration
         for any registered type whose model uses HasBlockContent.

  Site-meta bridge (parallel with G1)
    G2a  cms-framework: register site.* settings + /api/settings/site
         REST endpoint in WP /wp/v2/settings shape.
    G2b  visual-editor: core/site-* block resolvers read from
         apGetSetting() with config fallback. Wired in all three
         renderer packages (Blade, React, Vue).

  Editor surface (after G1)
    G3   visual-editor: Post + Page WP-shape resources + addEntities
         at editor bootstrap. Inspector sidebar document settings.

  Block re-enablement (after G3)
    G4a  Re-enable core/post-* against G3 entities. Update enabled_blocks
         and the JS-side mirror in site-editor-app.tsx.
    G4b  Re-enable core/archives, core/categories, core/tag-cloud.
         Renderer wiring against existing cms-framework term endpoints.
    G4c  Loop runtime: core/query + core/query-loop. Blade/React/Vue
         renderer wiring against a new cms-framework QueryRuntime
         service that wraps BlogManager + the equivalent pages query.

  Permissions seed (sequential, after G1)
    G5   cms-framework: register visual_editor.* permissions when
         visual-editor is detected.

  Ship
    G6   Plan/README/dev-app updates. Smoke flow: install both
         packages, edit a post, save, render.
```

**Rough duration:** 6–8 weeks. Critical path is G0 → G1 → G3 → G4. G2 and G5 parallelize.

---

## 4. Architecture details

### 4.1 Resource filter contract

**Filter name:** `ap.visual-editor.resources`

**Input:** the static-config map from `config('artisanpack.visual-editor.resources')` (typically empty in the package default; populated by host apps that want to register their own `App\Models\*`).

**Output:** a `Map<string, class-string>` where keys are URL slugs and values are FQCN of Eloquent models that use `HasBlockContent`.

**Application site:** `VisualEditorServiceProvider::boot()` replaces the static read with `applyFilters('ap.visual-editor.resources', config('artisanpack.visual-editor.resources'))` and passes the merged map to `ResourceResolver`. Static config wins on key collision (host app overrides cms-framework defaults).

**Validation:** `ResourceResolver` throws if a registered slug → class entry's class doesn't use `HasBlockContent`. Errors surface in the dev-app on first request, not at boot, so cms-framework standalone install never trips them.

**cms-framework registration site (G1b'):** `CMSFrameworkServiceProvider::boot()`:

```php
if ( class_exists( \ArtisanPackUI\VisualEditor\VisualEditor::class ) ) {
    addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
        return array_merge( [
            'posts' => \ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post::class,
            'pages' => \ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page::class,
        ], $resources );
    } );
}
```

### 4.2 Block content storage

**Migration shape (G1a, on cms-framework's `posts` and `pages` tables):**

```php
Schema::table( 'posts', function ( Blueprint $table ): void {
    $table->json( 'block_content' )->nullable()->after( 'content' );
} );
```

(Same for `pages`.) Reverse migration drops the column. No data migration — apps with existing `content` keep their HTML/markdown there; new edits write block trees to `block_content`.

**Model adoption:**

```php
use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;

class Post extends Model {
    use HasBlockContent;

    protected string $blockContentColumn = 'block_content';
    // ...
}
```

`HasBlockContent::initializeHasBlockContent()` registers the `array` cast automatically. No additional `$casts` entry required.

**Search:** cms-framework's existing search queries against `content` keep working. visual-editor's Scout extractor reads `block_content` via `toBlockContentSearchableArray()`. Apps that want both indexed do `array_merge( $this->only( ['title', 'excerpt', 'content'] ), $this->toBlockContentSearchableArray() )` in their `toSearchableArray()`.

**Backwards compatibility:** Apps upgrading to V1 with existing `Post::content` data are unaffected. Editing a legacy post in the visual editor populates `block_content` on first save; the legacy `content` column is left untouched. Document this dual-state in the cms-framework upgrade guide so apps know to either (a) keep both columns indefinitely, or (b) run a one-time migration to convert legacy HTML → blocks (`@wordpress/blocks` `rawHandler`) and clear the legacy column.

### 4.3 Site-meta REST shape

**Endpoint:** `GET /api/settings/site` (registered in cms-framework's settings module).

**Response (matches `/wp/v2/settings`):**

```json
{
    "title": "ArtisanPack UI Demo",
    "description": "A Laravel CMS",
    "url": "https://example.test",
    "site_logo": 42,
    "site_icon": 17
}
```

Mutating endpoints (`PUT /api/settings/site`) update the matching `Setting` rows via `SettingsManager::updateSetting('site.title', $value)` etc. Existing `Setting` resource transformers don't need to change.

**Default registration (G2a, in cms-framework):**

```php
$settings->registerSetting( 'site.title',    config( 'app.name', '' ),  'sanitizeText',  SettingType::String );
$settings->registerSetting( 'site.tagline',  '',                         'sanitizeText',  SettingType::String );
$settings->registerSetting( 'site.url',      config( 'app.url', '' ),    'sanitizeUrl',   SettingType::String );
$settings->registerSetting( 'site.logo_id',  null,                       'sanitizeInt',   SettingType::Integer );
$settings->registerSetting( 'site.icon_id',  null,                       'sanitizeInt',   SettingType::Integer );
```

### 4.4 Editor entity adapter

**Endpoints (visual-editor side, G3):**

- `GET    /visual-editor/api/posts` / `{id}`
- `POST   /visual-editor/api/posts`
- `PUT    /visual-editor/api/posts/{id}`
- `DELETE /visual-editor/api/posts/{id}`

Same shape for `/visual-editor/api/pages`. These sit alongside cms-framework's public `/api/posts` — the visual-editor variants emit the WP-shape envelope; the cms-framework public variants stay flat for the public REST consumers.

**Single-record shape (Post):**

```json
{
    "id": 1,
    "slug": "hello-world",
    "title":   { "rendered": "Hello, world", "raw": "Hello, world" },
    "excerpt": { "rendered": "Intro...",     "raw": "Intro..." },
    "content": { "raw": "<!-- wp:paragraph -->...", "blocks": [/*...*/] },
    "status": "publish",
    "type": "post",
    "author": 3,
    "featured_media": 42,
    "date": "2026-04-20T10:00:00Z",
    "categories": [1, 2],
    "tags": [7]
}
```

`Page` adds `parent`, `menu_order`, `template`. List response uses the same `{ data, meta }` envelope visual-editor's site-editor entities use.

**Shim registration (editor bootstrap, JS):**

```ts
dispatch( 'core' ).addEntities( [
    { kind: 'postType', name: 'post', baseURL: '/posts', key: 'id' },
    { kind: 'postType', name: 'page', baseURL: '/pages', key: 'id' },
] );
```

`apiBase` stays `/visual-editor/api`. With G0 (#395) shipped, `core/post-content`, `core/post-title`, etc. read these entities through `useEntityRecord` / `useEntityRecords` and resolve automatically.

### 4.5 Loop runtime (G4c)

**The problem:** `core/query` attributes are large (postType, perPage, pages, offset, postIn, postNotIn, parents, orderBy, order, author, sticky, exclude, taxQuery, search, ...). Three renderers (Blade, React, Vue) means three implementations. Without consolidation, query semantics drift.

**Decision:** cms-framework grows a `QueryRuntime` service that takes a normalized query-attrs payload and returns Eloquent results. All three renderers call into it via the same contract:

- **Blade renderer** — direct call to `app( QueryRuntime::class )->resolve( $attrs )` since both run in PHP.
- **React/Vue server-side renderers** — same direct call when running in Laravel; client-side rendering goes through a new `POST /visual-editor/api/query/resolve` endpoint that wraps `QueryRuntime`.
- **Editor preview** (the iframe canvas) — uses the same REST endpoint via `useEntityRecords` with a synthetic entity registered for query-loop previews.

**Service contract (cms-framework):**

```php
namespace ArtisanPackUI\CMSFramework\Modules\Blog\Services;

class QueryRuntime
{
    public function resolve( array $attributes ): LengthAwarePaginator
    {
        // Routes by attributes['postType']:
        //   'post' → BlogManager::getArchiveQuery( $filters )
        //   'page' → PageManager::getArchiveQuery( $filters )
        //   custom → ContentTypeManager::resolve( $type )::getArchiveQuery( $filters )
    }
}
```

**Renderer responsibility:** translate the resolved paginator's items into the inner `core/post-template` render context (current post id) and walk the inner blocks. The runtime owns the SQL; the renderer owns the block-tree walk.

### 4.6 Permissions seed (G5)

**Registration site (cms-framework `boot()` under `class_exists` guard):**

```php
$permissions = [
    'visual_editor.access',
    'visual_editor.posts.edit',
    'visual_editor.pages.edit',
    'visual_editor.templates.edit',
    'visual_editor.template-parts.edit',
    'visual_editor.patterns.edit',
    'visual_editor.global-styles.edit',
    'visual_editor.navigation.edit',
];

foreach ( $permissions as $name ) {
    \ArtisanPackUI\CMSFramework\Modules\Users\Facades\Permissions::register( $name );
}
```

Permissions are seeded but visual-editor policies don't yet check them — that flips in V1.1 behind `artisanpack.visual-editor.authorization.delegate_to_cms_framework` (default off in V1.1, planned default on in V1.2).

---

## 5. Risks of record

### 5.1 Dual content columns confuse search

Apps with existing `Post::content` (HTML) get a second `block_content` (JSON) column on first edit. Search-on-content needs to read both. Mitigation: document the dual-state in cms-framework's upgrade guide; ship a one-shot Artisan command (`cms:migrate-content-to-blocks`) in V1.1 that converts legacy HTML to blocks via `@wordpress/blocks` `rawHandler` and clears the legacy column.

### 5.2 Loop runtime divergence

`core/query` semantics live in three renderers. Without `QueryRuntime` as a single source of truth, query results diverge between server-rendered Blade and client-rendered React/Vue. **Decision:** all three renderers go through the same service (direct call in PHP, REST in JS). G4c lands this contract before any renderer-specific work begins.

### 5.3 Custom content types without `HasBlockContent`

`ContentTypeManager` registers types with arbitrary models. If a user's custom type's model doesn't use `HasBlockContent`, the resource filter silently skips it. Mitigation: cms-framework logs a warning when a registered type appears editable but its model lacks the trait. Surface the warning in the dev-app's editor route boot.

### 5.4 cms-framework standalone install must keep working

Every cms-framework registration that touches visual-editor goes behind `class_exists`. Add an explicit test in cms-framework's CI that boots the package without visual-editor in `composer.json` and asserts no errors, no missing classes, no failed hooks.

### 5.5 cms-framework version coupling

V1.0.0 of visual-editor depends on V?.?.? of cms-framework for the `block_content` migration, `site.*` settings, and `QueryRuntime`. Pin a minimum cms-framework version in visual-editor's README and document the matching tag. cms-framework's V1.x release that ships these features must be tagged before visual-editor's V1.0.0.

---

## 6. Branching + release strategy

- Same `release/1.0` integration branch on visual-editor.
- cms-framework work lands on its own `release/1.x` (cms-framework owns its versioning cadence).
- visual-editor V1.0.0 does not tag until cms-framework's matching minimum-required version is tagged + published. Document the version pair in the V1.0.0 release notes.
- `main` remains release-only for both packages.

---

## 7. Issue tracking approach

Per [`11-v1-expansion.md`](11-v1-expansion.md) §6: create milestone-level tracking issues one phase at a time, as each kicks off.

- **G0** is already filed (#395 in visual-editor).
- **G1a / G1b' / G1c / G2a / G5** belong in cms-framework's repo.
- **G1b / G2b / G3 / G4a / G4b / G4c / G6** belong in visual-editor's repo.
- Update the `#309` umbrella to add a "Phase G — cms-framework integration" section linking both repos' tracking issues once cut.

---

## 8. Open questions to answer at phase kickoff

Not blocking this plan, but worth naming so they don't surface mid-implementation:

- **G1a column name** — `block_content` (chosen) vs. `editor_content`. Settled: `block_content`.
- **G1c custom-type criteria** — auto-pickup if `HasBlockContent` present, or also require an explicit `supports => ['editor']` flag in the type registration? Leaning purely trait-based; the trait *is* the opt-in.
- **G2a `site.url` default** — use `config('app.url')` as default, or require explicit set during cms-framework install? Leaning: default to `config('app.url')`, override via admin UI.
- **G3 inspector sidebar fields** — beyond status / slug / author / taxonomies / featured-image: include excerpt, parent (pages), menu-order (pages), template (pages). Custom fields defer to V1.1.
- **G4c renderer/runtime contract** — REST-only call from all renderers (uniform) or direct manager call from Blade (faster, but two seams)? Leaning: direct call from Blade because the in-process win is real and the contract stays the same shape; document both code paths.
- **G5 permission list completeness** — confirmed list above covers core entities. Add per-action granularity (`visual_editor.posts.publish`) at G5 kickoff if RBAC users want it; otherwise keep the coarser list.
- **Site-meta read on the public front-end** — visual-editor's renderers use `apGetSetting()` when present, but what does the dev-app *itself* use for the site title in its layout chrome? Should be consistent. Decide at G2 kickoff.
