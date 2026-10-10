---
title: Configuration
---

# Configuration

The visual editor reads its configuration from `config/artisanpack/visual-editor.php`. The package's defaults are merged into the host application's config under the `artisanpack.visual-editor.*` key — publish the file to override any of them.

```bash
php artisan vendor:publish --tag=artisanpack-visual-editor-config
```

This page is a reference for every top-level key.

---

## `resources`

Maps a URL-friendly slug to the Eloquent model class that backs it. The editor's REST routes resolve `/visual-editor/api/{resource}/{id}/content` through this map.

```php
'resources' => [
    'posts' => App\Models\Post::class,
    'pages' => App\Models\Page::class,
],
```

Every listed model must use the `ArtisanPackUI\VisualEditor\Concerns\HasBlockContent` trait. Adding a new editable content type is a config change — no per-model controllers required.

The map can also be extended at runtime via the `ap.visualEditor.resources` filter (cms-framework registers `Post` and `Page` this way). Static config wins on key collision.

Full contract: [[Content Model#2-the-resource-map]].

---

## `site_meta`

Fallback values for the `core/site-title`, `core/site-tagline`, and `core/site-logo` block resolvers. Used only when `apGetSetting()` (cms-framework's settings helper) is unavailable.

```php
'site_meta' => [
    'title'       => null,
    'description' => null,
    'url'         => null,
    'logo_id'     => null,
    'icon_id'     => null,
],
```

`logo_id` and `icon_id` are media-library media ids; the resolver converts them to URLs via `apGetMediaUrl()` when present.

---

## `resolver`

Behaviour knobs for the server-side `_resolved*` stamping pipeline.

```php
'resolver' => [
    'adjacency' => [
        'auto_query' => false,
    ],
],
```

`adjacency.auto_query` controls the generic query-fallback used when stamping `post-navigation-link` blocks. When `true`, the resolver issues an Eloquent query against `published_at` to find the adjacent published row if the host's Post model doesn't expose an explicit adjacency accessor. Costs one extra query per `post-navigation-link` render per direction.

---

## `enabled_blocks` / `disabled_blocks`

`enabled_blocks` is an allow-list: when non-empty, only the listed block names are exposed to the inserter. `disabled_blocks` is an always-applied deny-list. The deny-list wins when both are set. Use fully-qualified block names (e.g. `artisanpack/paragraph`).

The package's default `enabled_blocks` covers the full V1 fork: content, media, layout, widget, entity, loop/feed, comments, and query/pagination clusters — all under the `artisanpack/*` namespace.

To narrow the inserter, override `enabled_blocks` with the subset you want. To deny a specific block without rebuilding the allow-list, add it to `disabled_blocks`.

See [[Blocks]] for the block library overview.

---

## `media`

Configures the editor's media picker bridge.

```php
'media' => [
    'bridge'  => 'artisanpack-ui/media-library',
    'adapter' => \ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter::class,
],
```

- `bridge` — Slug recording which client-side bridge is registered. Server-side code uses this to pick the matching PHP adapter from the container.
- `adapter` — Class that converts host media records into Gutenberg-shape `Attachment` objects. Rebind in the container to override the shape emitted by `toGutenberg()`.

See [[Post Editor#5-media-library-integration]] for the bridge contract.

---

## `api`

Middleware stack applied to the auto-registered `/visual-editor/api/*` routes.

```php
'api' => [
    'middleware' => ['api', 'auth'],
],
```

Swap in `auth:sanctum`, `auth:api`, etc. for API-only or stateless apps.

---

## `authorization`

Controls how the default policy for the legacy `VisualEditorPost` model gates access. Resource models (via `HasBlockContent`) delegate to their own Laravel policies and ignore this flag.

```php
'authorization' => [
    'restrict_by_owner' => false,
],
```

---

## `content_access`

**Since v1.13 (#834).** Who may call the post-editor content-authoring endpoints that sit outside any model policy: the icon picker (`icons/sets`, `icons/search`, `icons/svg`), the custom SVG sanitizer (`icons/svg/sanitize`) and the pattern card previews (`patterns/preview`). They check the `visual-editor.edit-content` ability and return a JSON `403` when it fails.

```php
'content_access' => [
    'capability'        => null,
    'sanitize_throttle' => '60,1',
],
```

- `capability` — left `null` (or any non-string value), any authenticated user passes. Set a capability string (e.g. `'edit_content'`) to require it through the user's `hasCapability()` / `hasPermissionTo()` / `hasPermission()`. A user model with none of those falls back to `$user->can( $capability )` and logs a warning. Or define the `visual-editor.edit-content` gate yourself.
- `sanitize_throttle` — per-user rate limit for `icons/svg/sanitize`, as Laravel `throttle` middleware arguments (`"max attempts,minutes"`), counted in its own rate-limit bucket. `null` or `''` uses the default `'60,1'`; `false` turns the throttle off.

The throttle is read when routes are registered, so after changing `sanitize_throttle` on an install with cached routes, run `php artisan route:cache` again.

See [Access Gate](site-editor/Access-Gate.md#content-authoring-ability-visual-editoredit-content).

---

## `loginout`

Configures the `artisanpack/loginout` block's server-side renderer. The package does not ship login / logout routes of its own — the resolver looks up the configured named routes first, falling back to the literal paths when the names are not registered.

```php
'loginout' => [
    'guard'          => '',
    'login_route'    => 'login',
    'login_path'     => '/login',
    'logout_route'   => 'logout',
    'logout_path'    => '/logout',
    'redirect_param' => 'redirect_to',
],
```

⚠️ **POST-vs-GET caveat for `logout_route`:** the block always emits a plain `<a>`, but Breeze / Jetstream / Fortify register `logout` as POST + CSRF — clicking the rendered link will hit a 405 unless the host either (a) registers a GET-side logout endpoint and points `logout_route` / `logout_path` at it, or (b) rewrites the resolved envelope through the `ap.visualEditor.loginout.envelope` filter.

For fully custom URL resolution (per-tenant routes, SSO, etc.) override the resolved envelope through the `ap.visualEditor.loginout.envelope` filter hook.

---

## `global_styles`

Configures the `globalStyles` entity the site editor customizes.

```php
'global_styles' => [
    'theme'          => 'artisanpack-base',
    'schema_version' => 3,
    'base_path'      => null,
],
```

- `theme` — Scopes the singleton lookup. Each installed theme gets its own global-styles record.
- `schema_version` — Pins the `theme.json` schema the package accepts on `PUT` requests. See [[site-editor/Global Styles]] for the contract.
- `base_path` — Absolute path to the PHP file returning the default `base` payload (the `theme.json` defaults the site-editor compares user overrides against). Leave `null` to use the package's bundled defaults.

---

## `breakpoints`

**Desktop-first since v1.12.1 (#820).** Named breakpoints the editor's viewport switcher and the responsive value resolver use. "All sizes" (`base`) is the desktop design; each device breakpoint is an override that applies at its `maxWidthPx` **and below**, and inherits from the next larger device (Mobile inherits from Tablet, then base).

Resolved by merging three layers by key, highest wins:

1. Active theme's `theme.json` → `settings.custom.artisanpack.breakpoints`
2. This config array (host-app overrides)
3. `BreakpointRegistry::DEFAULTS`

The package defaults are:

```php
'tablet' => [ 'maxWidthPx' => 1023, 'previewWidthPx' => 768, 'label' => 'Tablet' ],
'mobile' => [ 'maxWidthPx' => 767,  'previewWidthPx' => 375, 'label' => 'Mobile' ],
```

The published config ships the key empty; override only what you need:

```php
'breakpoints' => [
    'tablet' => [ 'label' => 'iPad' ],
    'mobile' => [ 'maxWidthPx' => 767, 'previewWidthPx' => 390, 'label' => 'Phone' ],
],
```

- **`maxWidthPx`** — the width the override applies at and below, emitted as `@media (max-width: Npx)`.
- **`previewWidthPx`** — the canvas width the viewport switcher previews at. It must not exceed `maxWidthPx`, so the editor preview always matches the front end. Defaults to the entry's width.
- **`label`** — the switcher / inspector label. Defaults to the key.
- **Partial entries** merge into the entry at the same key, so `'tablet' => [ 'label' => 'iPad' ]` keeps the default widths.
- **Scalar values** (`'mobile' => '600px'` or `600`) change an existing entry's width. An inherited preview width that no longer fits is clamped to the new max-width. A scalar for a key that doesn't exist yet creates a legacy `minWidthPx` entry.
- **`null` removes a key**: `'tablet' => null` drops the Tablet override.

**Legacy keys.** The pre-1.12.1 mobile-first keys (`sm` 640, `md` 768, `lg` 1024, `xl` 1280, `2xl` 1536) are still registered with `minWidthPx`, so content saved before 1.12.1 renders exactly as before. They are no longer offered in the viewport switcher; screen-size visibility rules still use them. An entry declares either `maxWidthPx` or `minWidthPx`, never both. Media queries are emitted in registry order — legacy min-width entries ascending, then device max-width entries descending — so smaller-device overrides win the cascade.

**What follows these values.** Per-block CSS (spacing, widths, column counts, position, shadows, gradients, animations) and the Columns / Media & Text / float stacking. The static class-based layout CSS (flex utilities, grid and post-template spans, masonry) and the React / Vue renderers ship rules for the default Tablet (1023px) and Mobile (767px) widths only, so changing those widths or adding devices leaves those layouts on the defaults.

**Validation** runs when the registry is built and throws `InvalidArgumentException` on bad input: keys that aren't letters / numbers / hyphens / underscores, the reserved `base` key, an entry with both or neither of `minWidthPx` / `maxWidthPx`, widths that aren't a positive integer or `Npx` string, empty or non-string labels, duplicate min- or max-widths, and a `previewWidthPx` wider than `maxWidthPx` — see `BreakpointRegistry::validate()`.

The implicit `base` slot is reserved and cannot be redefined.

See [[blocks/Responsive Design Tools]] for the editor + developer workflow.

---

## `states`

Interactive states the InspectorControls state switcher and state value resolver use. Resolved in priority order:

1. Active theme's `theme.json` → `settings.custom.artisanpack.states`
2. This config array (host-app overrides)
3. `StateRegistry::DEFAULTS` (idle, hover, focus, focus-visible, active, disabled)

```php
'states' => [
    'aria-current' => [
        'label'        => 'Current',
        'selector'     => '&[aria-current="page"]',
        'icon'         => 'flag',
        'inheritsFrom' => 'idle',
    ],
],
```

Each state is an associative array with these keys:

| Key | Type | Purpose |
|-----|------|---------|
| `label` | string | Human-readable label shown in the inspector |
| `selector` | string | CSS pseudo or attribute selector. The token `&` is replaced with the block's unique class scope. Reserved `idle` must use `''`. |
| `icon` | string | Optional icon slug for the inspector chip |
| `inheritsFrom` | string | Parent state key for null-fallback. The `idle` slot is the implicit root. |
| `hoverMediaWrap` | bool | When `true`, wraps the rule in `@media (hover: hover)`. Default `false`. |

To remove a built-in state, set its key to `null` — the registry skips it. The reserved `idle` state is the implicit base of every inheritance chain and cannot be removed or aliased.

See [[blocks/State Design Tools]] for the editor + developer workflow.

---

## `site-editor`

Static-config entry points for the five site-editor entity types. Each key is also a filter slug — packages like cms-framework register their entities at runtime through `addFilter('ap.visualEditor.{type}', ...)`. Static config wins on key collision.

```php
'site-editor' => [
    'templates'      => [],   // keyed by template slug
    'template-parts' => [],   // keyed by template-part slug; entries add `area`
    'patterns'       => [],   // keyed by pattern slug
    'global-styles'  => null, // singleton, not a map
    'navigation'     => [],   // keyed by theme-declared menu location
],
```

Standalone visual-editor installs (no cms-framework, no host registrations) leave these empty and the editor's site-editor surface boots cleanly with no entities.

Full shape contracts for each entity are commented inline in the config file. See [[Site Editor]] for the surface tour.

---

## `pattern_previews`

**Since v1.13 (#832).** Rendered pattern card previews in the inserter, the site editor's pattern grid and the page-pattern modal.

```php
'pattern_previews' => [
    'cache_ttl' => 3600,
],
```

- `cache_ttl` — seconds a rendered preview stays cached. The whole cache is also cleared when a pattern is updated or deleted, when a template part, menu or menu item is written through the site-editor API, and when global styles change. The TTL is what picks up other content changes, such as new posts behind a Query loop.

Entries vary by viewer, locale and host. Add more vary data with the `ap.visualEditor.patternPreviewCacheVary` filter. See [Patterns](site-editor/Patterns.md#pattern-previews).

---

## `presets`

**Since v1.9 (#773).** Host-provided palette / font-size / font-family / spacing-size presets, for applications that ship no `theme.json` and no cms-framework.

```php
'presets' => [
    // Bare list — implicit `append` mode.
    'palette' => [
        [ 'slug' => 'brand-navy', 'name' => 'Brand Navy', 'color' => '#0a2540' ],
    ],

    // Wrapper form — `mode` is 'append' (default) or 'replace'.
    'font_sizes' => [
        'mode'    => 'replace',
        'entries' => [
            [ 'slug' => 'body', 'name' => 'Body', 'size' => '1rem' ],
        ],
    ],

    'font_families' => [], // entries: [ 'slug', 'name', 'fontFamily' ]
    'spacing_sizes' => [], // entries: [ 'slug', 'name', 'size' ]
],
```

Entry shapes: `palette` → `slug`, `name`, `color` (hex or any CSS color); `font_sizes` / `spacing_sizes` → `slug`, `name`, `size` (any CSS length); `font_families` → `slug`, `name`, `fontFamily` (a CSS `font-family` stack).

Precedence, lowest to highest: the package defaults, the active theme's `theme.json` presets, then this config. Under `append`, host slugs that collide with a theme or default slug replace that entry in place, and an empty list is a no-op. Under `replace`, the host list wins outright for that preset kind — an empty `entries` array wipes both the theme and the default presets.

Slugs must match `/^[a-z0-9_-]+$/`. Entries with an invalid slug or an empty value are silently dropped, so a typo can't break the picker.

Since v1.12.0 the front end declares every spacing preset the pickers offer, and since v1.12.1 every font-size and palette preset too — see [Theme-less front-end defaults](renderers.md#theme-less-front-end-defaults).

---

## `taxonomies`

**Since v1.9 (#771).** Taxonomies registered here are surfaced in the `artisanpack/post-terms` Settings-sidebar picker and get one inserter variation each. Resolved through `TaxonomyRegistry`; the editor mount stamps them as `data-taxonomies` for both the post and site editors.

Keys are the taxonomy slug stamped onto the block's `term` attribute (matching the slugs `PostResolver::resolvePostTerms()` reads from the post's relations). Each value is either a display label, or an array with `label` and an optional `plural` for richer inserter keywords:

```php
'taxonomies' => [
    'category' => 'Category',   // default
    'post_tag' => 'Tag',        // default
    'genre'    => 'Genre',
    'topic'    => [ 'label' => 'Topic', 'plural' => 'Topics' ],
],
```

The `category` and `post_tag` defaults mirror WordPress core's built-in public taxonomies.

---

## `breadcrumbs`

Configures the `artisanpack/breadcrumbs` block's server-side trail resolver (#565).

```php
'breadcrumbs' => [
    'home_url'   => null, // null → url('/')
    'home_label' => null, // null → the translated "Home" string
],
```

Customize the trail itself (e.g. insert a "Category" hop) through the `ap.visualEditor.breadcrumbs.trail` filter.

---

## `business.google_maps_api_key`

**Since v1.9 (#761).** Optional Google Maps embed API key used by the `artisanpack/business-address` block. Default `null`.

```php
'business' => [
    'google_maps_api_key' => null,
],
```

When set and the block's `mapProvider` attribute is `google`, the block composes a Google Maps `/maps/embed/v1/place` URL; otherwise it falls back to the keyless OpenStreetMap embed around the address `lat`/`lng`. A host-supplied map URL always wins. The business data itself comes from the `ap.visualEditor.businessInfo` filter.

⚠️ The key ships to the browser inside the embed URL, so use an HTTP-referrer-restricted key limited to the **Maps Embed API**. Never use a server-unrestricted key here.

---

## `animations` / `keyframes`

**Since v1.1 (#489).** `animations` adds, overrides, or removes the presets the Animations panel offers, keyed by family (`entrance`, `hover`, `continuous`). Resolved in priority order: the active theme's `settings.custom.artisanpack.animations`, this config, then `AnimationRegistry::DEFAULTS`. Set a key to `null` to remove a built-in.

```php
'animations' => [
    'entrance' => [
        'fade-in-blur' => [
            'label'    => 'Fade in (blur)',
            'keyframe' => 'apFadeInBlur',
            'duration' => 700,
            'easing'   => 'ease-out',
        ],
    ],
],
```

`keyframes` registers named `@keyframes` blocks for the animation dropdowns. They merge with the theme's `settings.custom.artisanpack.keyframes` and editor-authored keyframes saved in Global Styles; built-in names are reserved.

```php
'keyframes' => [
    [
        'name'  => 'confetti',
        'stops' => [
            [ 'at' => '0%',   'transform' => 'translateY(0)' ],
            [ 'at' => '50%',  'transform' => 'translateY(-12px) rotate(10deg)' ],
            [ 'at' => '100%', 'transform' => 'translateY(0)' ],
        ],
    ],
],
```

Both ship empty. See [[Animations]] for the required shape per family.

---

## `default_styles`

**Since v1.12.1 (#821).** Controls the baseline front-end stylesheet `<x-ve-blocks-styles>` emits so a site with no theme renders close to the editor canvas (content typography, a heading scale, and root padding for constrained post content). Every rule has zero specificity and is scoped to block output, so host CSS always wins.

```php
'default_styles' => 'auto',
```

| Value | Behaviour |
|-------|-----------|
| `'auto'` | Emit only when no theme is active (default). |
| `true` | Always emit. |
| `false` | Never emit. |

The preset and layout token defaults (font sizes, palette, content and wide size, block gap) are always declared and a theme overrides them; this flag only controls the baseline stylesheet. The Blade renderer is the only one that reads it. See [Theme-less front-end defaults](renderers.md#theme-less-front-end-defaults).

---

## `ai`

**Since v1.12.1 (#828).** Access and payload bounds for the optional AI features. Every call spends the site's AI credentials, so the `/ai/*` endpoints and the `AiTools` Livewire listeners require the `visual-editor.use-ai` ability (denied by default) and the endpoints are throttled.

```php
'ai' => [
    'payload_limits' => [
        'max_blocks'     => 1000,   // total blocks in a tree, nested included
        'max_depth'      => 12,     // deepest innerBlocks nesting (top level is 1)
        'max_bytes'      => 131072, // size of the block JSON sent to the model
        'max_text_chars' => 20000,  // text length for a content rewrite
    ],
    'capability' => 'use_ai_features',
    'throttle'   => '20,1',
    'alt_text'   => [
        'allowed_hosts' => [],
    ],
],
```

- `payload_limits` — a request over any limit is rejected rather than truncated.
- `capability` — what the default `visual-editor.use-ai` gate requires (checked through the user's `hasCapability()` / `hasPermissionTo()` / `hasPermission()`). Set to `''` to deny everyone, or redefine the gate for your own rule.
- `throttle` — per-user rate limit for the `/ai/*` endpoints, as Laravel `throttle` middleware arguments (`"max attempts,minutes"`). Since v1.13 it's counted in its own rate-limit bucket, so it doesn't share a counter with the font, dynamic-content, SVG-sanitize or pattern-preview throttles. `null` or `''` uses the default; `false` turns the throttle off. Re-run `php artisan route:cache` after changing it.
- `alt_text.allowed_hosts` — extra hosts alt-text image URLs may point at (e.g. a CDN). The `app.url` host is always allowed and server file paths are never accepted. Don't list a host with an open redirect.

See [[AI Features]] for the full feature and access guide.

---

## `visibility`

**Since v1.4 (#491 · #492 · #493).** Site-wide controls for block visibility rules.

```php
'visibility' => [
    'enabled'             => true,
    'user_model'          => null,
    'user_search_columns' => null,
],
```

- `enabled` — kill switch for the whole feature.
- `user_model` — model the "Specific User" rule's `/visual-editor/api/users/search` autocomplete queries. `null` falls back to `auth.providers.users.model`.
- `user_search_columns` — columns that autocomplete searches. `null` uses `email` and `name` when present on the model.

See [[Visibility]].

---

## `fonts`

**Since v1.7 (#627).** Configures the Font Library: where self-hosted fonts are stored, who can manage them, and the remote providers.

```php
'fonts' => [
    'disk'                => env('VE_FONTS_DISK', 'public'),
    'path'                => 'visual-editor/fonts',
    'css_path'            => 'visual-editor/fonts/fonts.css',
    'capability'          => 'manage_fonts',
    'regenerate'          => [ 'queued' => false ],
    'install_max_seconds' => 0,
    'bundles'             => [ 'auto_install' => env('VE_FONTS_BUNDLE_AUTO_INSTALL', false) ],
    'providers'           => [
        'google' => [ 'enabled' => true, /* metadata_url, css_url, user_agent, subset, per_page, cache_ttl, timeout, max_bytes */ ],
        'bunny'  => [ 'enabled' => true, /* list_url, css_url, user_agent, subset, per_page, cache_ttl, timeout, max_bytes */ ],
        'custom' => [ 'enabled' => true ],
    ],
    'upload' => [
        'max_kilobytes'       => 5_120,
        'max_total_kilobytes' => 25_600,
        'extensions'          => [ 'woff2', 'woff', 'ttf', 'otf' ],
    ],
],
```

- `disk` / `path` / `css_path` — where fetched and uploaded face files are self-hosted, and the generated `fonts.css` bundle rebuilt on every install / uninstall.
- `capability` — gates every mutating Font Library action through `FontPolicy`. Users without it can still browse.
- `regenerate.queued` — reserved; v1 always regenerates synchronously.
- `install_max_seconds` — wall-clock budget for a catalog install. `0` derives it from `max_execution_time` (80% of it, or 60s when unlimited).
- `bundles.auto_install` — whether activating a theme installs the fonts its `theme.json` declares but the library lacks (a remote fetch).
- `providers.*.enabled` — drop a source from the Font Library without code changes. Provider defaults: `subset` `latin`, `per_page` 24, `cache_ttl` 86400, `timeout` 10, `max_bytes` 15 MB.
- `upload` — per-file and per-request size caps and accepted extensions for custom uploads.

See [[Fonts]] and [[Font Providers]].

---

## Configuration filter hooks

Several configuration keys can also be extended via filter hooks at runtime — useful for package contributions that shouldn't require host-app config edits.

| Key | Filter | Behaviour |
|-----|--------|-----------|
| `resources` | `ap.visualEditor.resources` | Merge slug → model class entries |
| `site-editor.templates` | `ap.visualEditor.templates` | Merge template entries |
| `site-editor.template-parts` | `ap.visualEditor.templateParts` | Merge template-part entries |
| `site-editor.patterns` | `ap.visualEditor.patterns` | Merge pattern entries |
| `site-editor.navigation` | `ap.visualEditor.navigation` | Merge menu entries |
| `breakpoints` | (theme.json) | Replace/merge breakpoints from active theme |
| `states` | (theme.json) | Replace/merge states from active theme |
| `animations` / `keyframes` | (theme.json) | Replace/merge animations and keyframes from active theme |

Static config always wins on key collision. See [[Hooks and Events]] for the full filter / action reference.

---

## See also

- [[Installation Guide]] — Initial setup
- [[Content Model]] — Resource map and authorization
- [[Hooks and Events]] — Filter / action reference for extending the editor
