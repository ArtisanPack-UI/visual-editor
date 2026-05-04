# Visual Editor — cms-framework Site-Editor Integration Plan

**Package:** `artisanpack-ui/visual-editor` (companion: `artisanpack-ui/cms-framework`)
**Version Target:** visual-editor 1.0.0 / cms-framework 1.x
**Created:** April 27, 2026
**Status:** In rollout — H0–H6 shipped (#100, #108, #110, #112, #114, #407, #431); H7 + H8 pending
**Relates to:** #309 (umbrella), [`11-v1-expansion.md`](11-v1-expansion.md), [`12-cms-framework-integration.md`](12-cms-framework-integration.md).
**Supersedes scope of:** [`11-v1-expansion.md`](11-v1-expansion.md) Phase C (templates / template-parts / patterns / global-styles / navigation backends) and the corresponding Phase D backend wiring — those phases assumed visual-editor first-party tables. This plan moves ownership of those entities into cms-framework. Phase D's UI work in visual-editor remains, retargeted at cms-framework endpoints.

---

## 1. Why this plan exists

[`11-v1-expansion.md`](11-v1-expansion.md) §2.2 plans the site editor with templates / template-parts / patterns / global-styles / navigation as visual-editor first-party tables. [`12-cms-framework-integration.md`](12-cms-framework-integration.md) §2.7 explicitly defers moving those entities into cms-framework to V2: *"Templates, template-parts, patterns, global-styles, navigation moving to cms-framework — V2."*

That deferral is wrong for what the packages are actually for. cms-framework already ships a Themes module (`src/Modules/Themes/`) with `theme.json` manifest discovery and a WordPress-style template hierarchy. Themes are how cms-framework apps will ultimately control templates, parts, patterns, global styles, and menus — same model WordPress uses. Putting first-party tables for those entities into visual-editor would either duplicate that responsibility or trap real apps with cms-framework's themes unable to author the things they're supposed to author.

This plan inverts the V1 split for those five entity types:

- **visual-editor V1** ships only the editor UI + JS shim entities. It carries no first-party tables for templates, parts, patterns, global styles, or navigation.
- **cms-framework V1.x** owns the data model, REST API, theme integration, and CSS emission for all five.
- **The site editor route in visual-editor requires cms-framework.** Without cms-framework installed, `/visual-editor/site-editor` returns a "cms-framework required" install page rather than a broken editor. The post-content editor (`/visual-editor/edit/{type}/{id}`) keeps working standalone.

Phase G ([`12-cms-framework-integration.md`](12-cms-framework-integration.md)) keeps its own cadence — Post and Page integration is independent of this plan. Phase H builds on the same `class_exists` / filter patterns established there.

---

## 2. V1 integration scope (final)

### 2.1 Coupling model

Different from Phase G's loose coupling. Phase G keeps both packages independent because Posts and Pages are content cms-framework owns regardless of editor — they have their own admin and public REST. Templates / parts / patterns / global-styles / navigation are theme-owned, and themes are a cms-framework concept. There is no scenario where visual-editor authors these standalone. Hence the harder dependency: site editor → cms-framework.

cms-framework still does not get visual-editor in `composer.json`. Its theme system, settings, and (new) menus module work without the editor. The dependency is one-directional: visual-editor's site-editor surface checks for cms-framework via `class_exists( \ArtisanPackUI\CMSFramework\CMSFramework::class )`, not the other way around.

### 2.2 Storage model — WP-style hybrid (H1, H2, H3, H4)

Theme-shipped entities (templates, template parts, patterns, global styles, navigation menus) live as files inside the active theme directory. User edits create database rows that override the theme file at resolve time. The theme file is left untouched.

Resolution order at render: **active-theme child theme file → active-theme parent theme file → DB row** is *not* the order. WordPress's order is **DB row → theme file**, and we follow that: a DB row, when present, wins over the file. This preserves the "edit in IDE" workflow until a user customizes via the admin, then customization wins.

A "revert to theme" action in the editor deletes the DB row. The theme file resumes authority on the next render.

Theme switching: DB rows survive theme switches but are scoped by entity slug (e.g. `single`, `single-post`, `header`). Switching themes effectively re-resolves: the new theme's files are checked for matching slugs, DB rows for unmatched slugs become orphaned (kept, not displayed). A V1.1 admin tool surfaces orphaned overrides.

### 2.3 cms-framework V1.x deliverables

- **H0 — theme.json schema extension.** Adopt WP `theme.json` schema (settings, styles, custom templates, template parts, patterns) inside cms-framework's existing `theme.json`. Backwards-compatible: existing manifest fields continue to validate. Schema version pinned to whichever WP core version `@wordpress/*` are pinned to in visual-editor (TBD at H0 kickoff per [`11-v1-expansion.md`](11-v1-expansion.md) §4.2).
- **H1 — Templates + template parts module.** New `src/Modules/SiteEditor/` (or expand Themes module). Models: `Template`, `TemplatePart`. Migrations. REST API in WP `/wp/v2/templates` + `/wp/v2/template-parts` shape. Resolution service that returns merged file/DB content per the §2.2 rules. Adopts `HasBlockContent` for the DB-row content column.
- **H2 — Patterns module.** Single `BlockPattern` model with `source: 'theme' | 'user'` and `synced: bool`. Theme-shipped patterns from `theme.json` `patterns` array + filesystem `patterns/` directory. User patterns from the admin. REST API in WP `/wp/v2/blocks` shape (synced) + `/wp/v2/block-patterns/patterns` shape (unsynced).
- **H3 — Global styles module.** `GlobalStyles` model + migration (`json` column for the styles object). Theme defaults from `theme.json` `settings` + `styles`. DB row appears on first user customization. CSS emission service translates the resolved styles object into CSS custom properties for front-end rendering. Variations from `theme.json` `styles.variations`.
- **H4 — Menus module.** New `src/Modules/Menus/` (cms-framework currently has no menu module). Models: `Menu`, `MenuItem`. Migrations. Menu locations registered by apps via `config('cms.menus.locations')` (default registration site) with theme overrides via `theme.json` `menus.locations`. REST API in WP `/wp/v2/menus` + `/wp/v2/menu-items` shape. `core/navigation` block resolution.

### 2.4 visual-editor V1 deliverables

- **H5 — Resource filters.** Five new filters: `ap.visual-editor.templates`, `ap.visual-editor.template-parts`, `ap.visual-editor.patterns`, `ap.visual-editor.global-styles`, `ap.visual-editor.navigation`. cms-framework registers under `class_exists` guards. visual-editor uses filter results to populate its `addEntities` configuration at editor bootstrap.
- **H6 — Editor entity adapters + sidebars.** WP-shape resource transformers in `Http/Resources/Adapters/CmsFramework/SiteEditor/` for each entity. `addEntities` registration in the shim editor bootstrap. Inspector sidebars surface entity-specific document settings (template name + area for parts, pattern category + sync state, global-styles variation picker, menu location assignment).
- **H7 — Site-editor shell wiring.** [`11-v1-expansion.md`](11-v1-expansion.md) Phase D's UI work (D1 shell, D2 templates, D3 global styles, D4 nav, D5 patterns) targets cms-framework endpoints. Shell entry-point checks `class_exists` on the cms-framework facade; renders an install gate if missing.
- **H8 — Dev-app sample theme.** Ship a `digital-shopfront`-style sample theme under `themes/dev-sample/` in the dev-app, demonstrating templates, parts, patterns, global styles, and menu locations. Smoke flow: install both packages, activate the sample theme, edit a template via the site editor, verify the override flips authority from file to DB.

### 2.5 Out of scope for V1

- Pattern directory / remote pattern import — V2.
- Per-template RBAC delegation — V1.1 (uses Phase G's V1.1 permissions delegation hook).
- Multi-site / per-environment global styles — V2.
- Menu location nesting / hierarchical regions — V1.1.
- Theme update / version migration tooling for orphaned DB overrides — V1.1.
- Forking core blocks into `artisanpack/*` (#331) — V2.

---

## 3. Phase ordering

```
Phase H — cms-framework site-editor integration (V1)

  Schema foundation (sequential, gates everything else)
    H0   cms-framework: theme.json schema extension to cover WP
         theme.json (settings, styles, customTemplates, templateParts,
         patterns, menus). Pin schema version to match @wordpress/*
         pin in visual-editor.

  Backends (parallelizable after H0)
    H1   cms-framework: Templates + template-parts module.
         Models, migrations, REST, file/DB resolution.
    H2   cms-framework: Patterns module.
         Single BlockPattern model, source flag, sync flag, REST.
    H3   cms-framework: Global styles module.
         Model, migration, CSS emission service, variations.
    H4   cms-framework: Menus module.
         Models, migrations, locations (config + theme override), REST.

  Editor surface (after H1-H4)
    H5   visual-editor: five resource filters + cms-framework
         registers behind class_exists guards.
    H6   visual-editor: WP-shape adapters, addEntities, inspector
         sidebars per entity.

  Shell wiring (after H5/H6)
    H7   visual-editor: rescope 11-plan Phase D onto cms-framework
         endpoints. Install-gate when cms-framework missing.

  Ship
    H8   Dev-app sample theme + smoke flow. Plan/README updates.
         Document the visual-editor ↔ cms-framework version pair.
```

**Rough duration:** 8–10 weeks. Critical path is H0 → H1 → H6 → H7. H2/H3/H4 parallelize against H1.

---

## 4. Architecture details

### 4.1 theme.json extension (H0)

cms-framework's current `theme.json` carries: name, slug, version, description, author, screenshot. Extension adds the WP `theme.json` top-level keys:

```json
{
    "name": "Digital Shopfront",
    "slug": "digital-shopfront",
    "version": "1.0.0",
    "$schema": "https://schemas.wp.org/wp/X.Y/theme.json",

    "settings": { ... },
    "styles":   { ... },
    "customTemplates":  [ ... ],
    "templateParts":    [ ... ],
    "patterns":         [ ... ],
    "menus": {
        "locations": {
            "primary":   "Primary Menu",
            "footer":    "Footer Menu"
        }
    }
}
```

`menus.locations` is a cms-framework extension (WP doesn't put menu locations in `theme.json`). It overrides keys registered in `config('cms.menus.locations')` — the app provides the default location set, the theme replaces specific entries by key.

Schema validation lives in `ThemeManager::validateTheme()` extended via a `WpThemeJsonValidator` service. Invalid `theme.json` rejects the theme at discovery time with a logged warning, same as today.

### 4.2 Storage + resolution (H1, H2, H3, H4)

All four backends share a resolver pattern:

```php
namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

interface EntityResolver
{
    /**
     * Returns the authoritative entity for a slug, merging theme files
     * and DB overrides. DB row wins when present.
     *
     * @return ResolvedEntity{source: 'db'|'theme', content: array, ...}
     */
    public function resolve( string $slug ): ?ResolvedEntity;

    public function revert( string $slug ): void;  // Deletes DB override.
}
```

Concrete resolvers per entity type:

- `TemplateResolver` — checks `templates` DB table, falls back to `themes/{active}/templates/{slug}.html`.
- `TemplatePartResolver` — checks `template_parts` table, falls back to `themes/{active}/parts/{slug}.html`.
- `PatternResolver` — checks `block_patterns` table for `source = 'user'` always, theme files in `themes/{active}/patterns/{slug}.php` for `source = 'theme'`. User patterns aren't theme-overridable; theme patterns aren't user-editable (admin can clone-to-user, which creates a DB row).
- `GlobalStylesResolver` — singleton resolution. DB row in `global_styles` table (one row per theme), falls back to `themes/{active}/theme.json` `settings` + `styles`.
- `MenuResolver` — DB-only (menus are user-authored from day one; theme provides location names, not menu content). Theme files don't contain menu definitions.

### 4.3 Block-content storage parity with Phase G

Templates, template-parts, and patterns all carry block content. They use `HasBlockContent` (introduced in Phase G):

```php
class Template extends Model
{
    use HasBlockContent;

    protected string $blockContentColumn = 'content';
}
```

Migrations follow the Phase G shape: `json` column, nullable, with a separate `slug` (URL key), `theme` (which theme this override belongs to), and `area` (for template parts: header / footer / sidebar / general).

### 4.4 Resource-filter contracts (H5)

Five new filters mirroring `ap.visual-editor.resources` from Phase G. Each takes the static config (typically empty) and returns a normalized map:

- `ap.visual-editor.templates` → `array<string, ResolvedTemplate>` keyed by slug.
- `ap.visual-editor.template-parts` → `array<string, ResolvedTemplatePart>` keyed by slug.
- `ap.visual-editor.patterns` → `array<string, ResolvedPattern>` keyed by slug.
- `ap.visual-editor.global-styles` → `?ResolvedGlobalStyles` (singleton).
- `ap.visual-editor.navigation` → `array<string, ResolvedMenu>` keyed by location.

**Implementation reference (H5 shipped):**

- Resolvers + value objects: `src/SiteEditor/Resolution/{TemplateResolver, TemplatePartResolver, PatternResolver, GlobalStylesResolver, MenuResolver, ResolvedTemplate, ResolvedTemplatePart, ResolvedPattern, ResolvedGlobalStyles, ResolvedMenu}.php`
- Lazy-validation exception: `src/SiteEditor/Exceptions/SiteEditorRegistrationException.php`
- Filter wiring: `VisualEditorServiceProvider::registerSiteEditorResolvers()` (called from the `$this->app->booted(...)` callback alongside `registerResourceResolver()`).
- Static config: `config/visual-editor.php` `site-editor.{templates, template-parts, patterns, global-styles, navigation}`. Static keys override filter-supplied keys on collision; for the singleton global-styles, a non-null static config wins outright.
- Tests: `tests/Feature/VisualEditor/SiteEditorFiltersTest.php` covers empty/single-source/colliding/malformed filter-return scenarios.

cms-framework registration site (H5):

```php
if ( class_exists( \ArtisanPackUI\VisualEditor\VisualEditor::class ) ) {
    addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
        return array_merge(
            app( TemplateResolver::class )->all(),
            $existing,  // Static-config wins on collision.
        );
    } );

    // Same shape for template-parts, patterns, global-styles, navigation.
}
```

### 4.5 Editor entity adapter (H6)

Endpoints (visual-editor side):

- `GET / POST / PUT / DELETE   /visual-editor/api/templates` / `{id}`
- `GET / POST / PUT / DELETE   /visual-editor/api/template-parts` / `{id}`
- `GET / POST / PUT / DELETE   /visual-editor/api/patterns` / `{id}`
- `GET / PUT                   /visual-editor/api/global-styles`
- `GET / POST / PUT / DELETE   /visual-editor/api/menus` / `{id}`
- `GET / POST / PUT / DELETE   /visual-editor/api/menu-items` / `{id}`

These wrap cms-framework's resolvers behind visual-editor's existing `/visual-editor/api` prefix (auth/middleware consistent with other editor traffic). The cms-framework public REST stays separate and admin-facing.

Single-record shape mirrors WP REST. Example for `Template`:

```json
{
    "id": 1,
    "slug": "single",
    "type": "wp_template",
    "source": "db",
    "origin": "theme",
    "title":   { "rendered": "Single Post", "raw": "Single Post" },
    "content": { "raw": "<!-- wp:post-title /-->", "blocks": [/*...*/] },
    "theme": "digital-shopfront",
    "has_theme_file": true
}
```

`source: 'db'` indicates the DB override is authoritative. `has_theme_file: true` lets the editor surface a "revert to theme" action.

Shim registration at editor bootstrap (JS):

```ts
dispatch( 'core' ).addEntities( [
    { kind: 'postType', name: 'wp_template',         baseURL: '/templates',         key: 'id' },
    { kind: 'postType', name: 'wp_template_part',    baseURL: '/template-parts',    key: 'id' },
    { kind: 'postType', name: 'wp_block',            baseURL: '/patterns',          key: 'id' },
    { kind: 'postType', name: 'wp_navigation',       baseURL: '/menus',             key: 'id' },
    { kind: 'postType', name: 'wp_navigation_link',  baseURL: '/menu-items',        key: 'id' },
    { kind: 'root',     name: 'globalStyles',        baseURL: '/global-styles',     key: 'id' },
] );
```

#### 4.5.1 Implementation status — H6 shipped

The H6 surface ships across six commits on the `feature/431-site-editor-h6-adapters` branch (PR #431):

| Sub-task | Files | Tests |
|---|---|---|
| WP-shape adapters | `src/Http/Resources/Adapters/CmsFramework/SiteEditor/{Template,TemplatePart,Pattern,GlobalStyles,Menu,MenuItem}Adapter.php` | `tests/Unit/VisualEditor/SiteEditor/Adapters/*Test.php` |
| Template + TemplatePart controllers | `src/Http/Controllers/SiteEditor/{Template,TemplatePart}Controller.php`, form requests under `src/Http/Requests/SiteEditor/` | `tests/Feature/SiteEditor/{Template,TemplatePart}ControllerTest.php` + `TemplateControllerStandaloneTest.php` |
| Pattern + GlobalStyles controllers | `src/Http/Controllers/SiteEditor/{Pattern,GlobalStyles}Controller.php`, form requests | `tests/Feature/SiteEditor/{Pattern,GlobalStyles}ControllerTest.php` + `PatternControllerStandaloneTest.php` |
| Menu + MenuItem controllers | `src/Http/Controllers/SiteEditor/{Menu,MenuItem}Controller.php`, form requests | `tests/Feature/SiteEditor/{Menu,MenuItem}ControllerTest.php` + `MenuControllerStandaloneTest.php` |
| `addEntities` registration | `resources/js/visual-editor/site-editor/register-entities.ts`, updated `vendor/core-data-shim.ts` `DEFAULT_ENTITIES` | `resources/js/visual-editor/site-editor/__tests__/register-entities.test.ts` |
| Inspector sidebars (minimal) | `resources/js/visual-editor/site-editor/sidebars/{template,template-part,pattern,global-styles,menu}-sidebar.tsx` + `sidebar-frame.tsx` | `resources/js/visual-editor/site-editor/sidebars/__tests__/sidebars.test.tsx` |

**Notable departures from this section's example:**

- **`globalStyles` (not `__unstableBase`)** — visual-editor's core-data shim already registered the singleton entity under `name: 'globalStyles'` long before H6. Renaming would ripple through every consumer that reads global styles, so the H6 work kept the existing name. Functionally equivalent; the shim's `addEntities` lookup is keyed by `(kind, name)`.
- **`/menu-items` is an entity, not a sub-resource** — registered as `wp_navigation_link` so the editor can do incremental item edits via `useEntityRecord('postType', 'wp_navigation_link', id)` without re-saving the whole menu. Mirrors the WP REST split.
- **Sidebars are read-only minimal panels** — H6 ships the bootstrap-registration evidence (the sidebars actually fetch and render their entity record). H7's UI rescope expands them to editable Gutenberg-style document settings panels alongside the existing block-selection inspector.
- **Write coupling via direct model import** — controllers gate cms-framework writes behind `class_exists(\ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\X::class)` + `app()->bound(<resolver-class>)`. Standalone install (no cms-framework provider booted) reads return empty, writes return 404 with `{ "message": "The site editor requires artisanpack-ui/cms-framework." }`. Plan 14 §2.1's hard-coupling model — explicit and testable.
- **Menu reads bypass the resolver** — `MenuController` reads cms-framework's `Menu` model directly (not through H5's `MenuResolver`) because the resolver is location-keyed and only surfaces *assigned* menus, while WP REST `wp_navigation` expects id-based addressing over the full menu set. The resolver continues to power `core/navigation` block resolution at render time as a separate code path.
- **DELETE writes scope by `(theme, slug)`** — Templates and parts require `?theme=...` so a multi-theme override doesn't get collateral-deleted. Pattern and Menu deletes scope by id (single global unique).

**Deferred to follow-up #434:**

Plan 11 Phase D legacy code cleanup — `VisualEditor{Template,TemplatePart,Pattern,GlobalStyles,Navigation}` models + their migrations + factories + policies; the legacy `Resources/{TemplateResolver,TemplatePartInliner,PatternInliner}` classes; the `Services/GlobalStylesCssProvider` + `GlobalStylesCacheInvalidator` (cms-framework's `GlobalStylesEmitter` per §4.6 supersedes); `EntitySearchController` rewrite to consume cms-framework's resource map; `MenuLocationsController` rewrite to consume cms-framework's `MenuLocationAssignment`. These are still alive at the model layer because their consumers (search picker, location config endpoint, CSS emission) remain wired to the old DB; #434 spells out the per-system cutover order.

### 4.6 CSS emission (H3)

`GlobalStylesEmitter` service translates the resolved global-styles object into CSS custom properties + per-block class rules. Output is cached on a content hash; cache busts on any DB write to `global_styles` or theme switch.

Front-end integration: a Blade directive (`@cmsGlobalStyles`) renders the emitted CSS in the document head. Themes opt in by including the directive in their root layout. The dev-app sample theme demonstrates this.

The same emitter runs in the editor's iframe canvas via a `/visual-editor/api/global-styles/css` endpoint that returns the same emitted CSS. The site-editor canvas injects it for live preview.

### 4.7 Menu locations (H4)

Locations resolve in this order:

1. App-level: `config('cms.menus.locations')` returns `['primary' => 'Primary', 'footer' => 'Footer']`.
2. Theme override: active theme's `theme.json` `menus.locations` — keys present override the app default by key.

Apps assign menus to locations via the admin or programmatically: `Menus::assign( 'primary', $menuId )`. The `core/navigation` block resolver reads the assigned menu by location at render time.

Themes that need a location no app has registered can declare it in `theme.json` and the location appears with the theme-provided label.

---

## 5. Risks of record

### 5.1 Site-editor unavailable when cms-framework missing

The hard dependency is intentional but breaks the "install visual-editor and try the editor" demo path for the site-editor surface. Mitigation: install gate in visual-editor's site-editor route renders a clear "Install `artisanpack-ui/cms-framework` to enable the site editor" page with the composer command. Post-content editor remains usable standalone.

### 5.2 Theme.json schema drift

WP iterates `theme.json` schema across versions. Pinning to WP version X means a theme authored against WP version Y may use unsupported keys. Mitigation: H0 pins the schema version in `cms-framework` config + docs; validation rejects unknown top-level keys with a logged warning. Version bumps are explicit work, like [`11-v1-expansion.md`](11-v1-expansion.md) §4.2 commits to.

### 5.3 Resolution-order surprises

WP's "DB row wins over theme file" model surprises authors who expect the file system to be authoritative. Mitigation: every editor surface that shows a DB override exposes `has_theme_file` + a "revert" action. The dev-app smoke flow exercises both directions (override + revert). Documentation for theme authors explains the model upfront.

### 5.4 Menu-location collision

App and theme can both register the same location key with different labels. Decision: theme wins on collision (themes are closer to the user-visible behavior). Log a warning when override happens so app authors aren't confused.

### 5.5 Orphaned DB overrides on theme switch

Switching themes leaves DB overrides for templates/parts whose slugs don't exist in the new theme. They are kept (data preservation) but invisible. V1.1 admin tool surfaces them. Mitigation for V1: log them at theme-switch time so they're discoverable via logs.

### 5.6 Pattern source confusion

A theme pattern and a user pattern with the same slug must not collide. Decision: user patterns have an auto-prefix (`user/{slug}`) at storage time so theme `single` and user `single` are distinct. REST `slug` field shows the user-facing slug; storage carries the prefix.

### 5.7 cms-framework version coupling tightens

Phase G already created a version coupling between visual-editor V1.0.0 and cms-framework V1.x ([`12-cms-framework-integration.md`](12-cms-framework-integration.md) §5.5). Phase H tightens it: visual-editor V1.0.0 minimum required cms-framework version is whatever ships H0–H4. Document the version pair in V1.0.0 release notes alongside Phase G's pair.

---

## 6. Branching + release strategy

- visual-editor changes land on `release/1.0` (existing integration branch).
- cms-framework changes land on `release/1.x` (existing integration branch).
- visual-editor V1.0.0 does not tag until the matching cms-framework V1.x version (covering H0–H4) is tagged + published.
- `main` remains release-only for both packages.

---

## 7. Issue tracking approach

Same cadence as Phase G ([`12-cms-framework-integration.md`](12-cms-framework-integration.md) §7) and [`11-v1-expansion.md`](11-v1-expansion.md) §6: milestone-level tracking issues created one phase at a time as each kicks off.

- **H0** filed in cms-framework at this plan's creation. The kickoff.
- **H1 / H2 / H3 / H4** belong in cms-framework's repo. Cut as H0 lands.
- **H5 / H6 / H7 / H8** belong in visual-editor's repo. Cut after the cms-framework backends are in flight (H5 needs H1–H4 contracts to register against).
- Update the visual-editor `#309` umbrella to add a "Phase H — cms-framework site-editor integration" section linking both repos' tracking issues as they're cut.

Every issue gets:

- **Issue type** (`Task` / `Feature` / `Documentation` / `Maintenance` per org definitions): set via GraphQL `updateIssueIssueType`.
- **Project assignment**: `ArtisanPack UI Overview` (project 3) per existing convention, plus `Visual Editor v1.0` (project 2) so Priority + Size fields can be set.
- **Priority** (P0 / P1 / P2): set via `updateProjectV2ItemFieldValue` on the v1.0 project item.
- **Size** (XS / S / M / L / XL): set via `updateProjectV2ItemFieldValue` on the v1.0 project item.

---

## 8. Open questions to answer at phase kickoff

Not blocking this plan, but worth naming so they don't surface mid-implementation:

- **H0 schema version pin** — confirm the WP `@wordpress/*` version pin from [`11-v1-expansion.md`](11-v1-expansion.md) §4.1 before naming the theme.json schema URL. Settle at H0 kickoff.
- **H1 template area for parts** — restrict to `header` / `footer` / `sidebar` / `general` (WP defaults), or open-ended user-defined areas? Leaning closed list for V1; open in V1.1.
- **H2 user-pattern slug strategy** — `user/{slug}` prefix at storage (decision in §5.6) or separate `user_patterns` table? Leaning prefix for one model, one table.
- **H3 variation registration** — theme-only (declared in `theme.json` `styles.variations`) or also runtime-registerable by apps? Leaning theme-only for V1.
- **H4 menu-item link types** — `core/navigation-link` only, or also `core/navigation-submenu` and `core/page-list`? All three needed for parity; budget extra time at H4 kickoff.
- **H6 install-gate copy** — exact wording for the "cms-framework required" page. Decide at H6 kickoff.
- **H7 entity-route lazy-loading** — load all five entity-list views eagerly at site-editor boot (faster nav, larger initial bundle), or per-route lazy (smaller boot, per-nav cost)? Leaning lazy.
- **H8 sample theme name + scope** — fork cms-framework's `digital-shopfront` reference theme into the dev-app, or build a minimal `dev-sample` theme just for the smoke flow? Decide at H8 kickoff.
