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

The map can also be extended at runtime via the `ap.visual-editor.resources` filter (cms-framework registers `Post` and `Page` this way). Static config wins on key collision.

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

⚠️ **POST-vs-GET caveat for `logout_route`:** the block always emits a plain `<a>`, but Breeze / Jetstream / Fortify register `logout` as POST + CSRF — clicking the rendered link will hit a 405 unless the host either (a) registers a GET-side logout endpoint and points `logout_route` / `logout_path` at it, or (b) rewrites the resolved envelope through the `ap.visual-editor.loginout.envelope` filter.

For fully custom URL resolution (per-tenant routes, SSO, etc.) override the resolved envelope through the `ap.visual-editor.loginout.envelope` filter hook.

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

Named breakpoints the editor's viewport switcher and responsive value resolver use. Resolved in priority order:

1. Active theme's `theme.json` → `settings.custom.artisanpack.breakpoints`
2. This config array (host-app overrides)
3. `BreakpointRegistry::DEFAULTS` (Tailwind v4 mins)

```php
'breakpoints' => [
    'sm'  => '640px',
    'md'  => '768px',
    'lg'  => '1024px',
    'xl'  => '1280px',
    '2xl' => '1536px',
],
```

Merging is by key, so an entry here for `sm` resizes the default `sm` breakpoint without affecting the others; a new key like `3xl` adds a breakpoint. Values may be integer pixels (`640`) or CSS length strings (`'640px'`).

The implicit `base` slot (no min-width, applies everywhere) is reserved and cannot be redefined.

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

Static-config entry points for the five site-editor entity types. Each key is also a filter slug — packages like cms-framework register their entities at runtime through `addFilter('ap.visual-editor.{type}', ...)`. Static config wins on key collision.

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

## Configuration filter hooks

Several configuration keys can also be extended via filter hooks at runtime — useful for package contributions that shouldn't require host-app config edits.

| Key | Filter | Behaviour |
|-----|--------|-----------|
| `resources` | `ap.visual-editor.resources` | Merge slug → model class entries |
| `site-editor.templates` | `ap.visual-editor.templates` | Merge template entries |
| `site-editor.template-parts` | `ap.visual-editor.template-parts` | Merge template-part entries |
| `site-editor.patterns` | `ap.visual-editor.patterns` | Merge pattern entries |
| `site-editor.navigation` | `ap.visual-editor.navigation` | Merge menu entries |
| `breakpoints` | (theme.json) | Replace/merge breakpoints from active theme |
| `states` | (theme.json) | Replace/merge states from active theme |

Static config always wins on key collision. See [[Hooks and Events]] for the full filter / action reference.

---

## See also

- [[Installation Guide]] — Initial setup
- [[Content Model]] — Resource map and authorization
- [[Hooks and Events]] — Filter / action reference for extending the editor
