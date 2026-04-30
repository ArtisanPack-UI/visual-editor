<?php

/**
 * Visual editor package configuration.
 *
 * Merged into the host application's `artisanpack.visual-editor` key by
 * VisualEditorServiceProvider. Applications override any of these values by
 * publishing this file to `config/artisanpack/visual-editor.php`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

return [

	/*
	|--------------------------------------------------------------------------
	| Resources
	|--------------------------------------------------------------------------
	|
	| Maps a URL-friendly slug to the Eloquent model class that backs it. The
	| editor's REST routes resolve `/visual-editor/api/{resource}/{id}/content`
	| through this map, so adding a new editable content type is a config
	| change — no per-model controllers required. Every listed model must use
	| the `HasBlockContent` trait.
	|
	*/

	'resources' => [
		// 'posts' => App\Models\Post::class,
		// 'pages' => App\Models\Page::class,
	],

	/*
	|--------------------------------------------------------------------------
	| Site meta
	|--------------------------------------------------------------------------
	|
	| Fallback values for the `core/site-title`, `core/site-tagline`, and
	| `core/site-logo` block resolvers. The Blade renderer reads these only
	| when `apGetSetting()` (cms-framework's settings helper) is unavailable;
	| the React/Vue renderers consume them via the `siteMeta` prop or the
	| bootstrap-time `setDefaultSiteMeta()` API. See plan 12 §4.3 for the
	| full G2 site-meta bridge contract.
	|
	| `logo_id` and `icon_id` are media-library media ids; the resolver
	| converts them to URLs via `apGetMediaUrl()` when present.
	|
	*/

	'site_meta' => [
		'title'       => null,
		'description' => null,
		'url'         => null,
		'logo_id'     => null,
		'icon_id'     => null,
	],

	/*
	|--------------------------------------------------------------------------
	| Block registry filters
	|--------------------------------------------------------------------------
	|
	| `enabled_blocks` acts as an allow-list: when non-empty, only the listed
	| block names are exposed to the inserter. `disabled_blocks` is an
	| always-applied deny-list. The deny-list wins when both are set. Use
	| fully-qualified block names (e.g. `core/paragraph`, `core/query`).
	|
	| The V1 defaults follow the M5 block-library audit
	| (see docs/block-library-audit.md), updated by E4 (#381) and G4b
	| (#401). The allow-list now includes the entity-scoped blocks that
	| B1's expanded `core-data` shim plus the C1–C5 REST surface can
	| round-trip (`core/template-part`, `core/post-*`, `core/site-*`,
	| `core/navigation`) and the taxonomy/feed widgets that G4b wires to
	| cms-framework's term + post APIs (`core/categories`,
	| `core/tag-cloud`, `core/archives`). The deny-list still removes
	| the loop runtime (`core/query`, `core/query-loop` — V1 G4c) and
	| the comments widgets (`core/latest-comments` — V1.1+, requires a
	| Comments module in cms-framework). Keep the JS-side mirror in
	| `resources/js/visual-editor/site-editor/site-editor-app.tsx`
	| (`D2_DISABLED_BLOCKS`) in sync with the deny-list — the two lists
	| want to agree.
	|
	*/

	'enabled_blocks' => [
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/quote',
		'core/code',
		'core/preformatted',
		'core/pullquote',
		'core/verse',
		'core/table',
		'core/image',
		'core/gallery',
		'core/video',
		'core/audio',
		'core/file',
		'core/embed',
		'core/cover',
		'core/media-text',
		'core/columns',
		'core/group',
		'core/row',
		'core/stack',
		'core/buttons',
		'core/separator',
		'core/spacer',
		'core/details',
		'core/search',
		'core/latest-posts',
		// E4 — re-enabled on the back of B1's expanded core-data shim
		// and the C1–C5 REST surface. Each block has a renderer in
		// every renderer package (Blade / React / Vue) and round-trips
		// against the empty-state shim without crashing. See
		// docs/block-library-audit.md for the per-block notes.
		'core/template-part',
		'core/post-title',
		'core/post-content',
		'core/post-excerpt',
		'core/post-date',
		'core/post-author',
		'core/post-featured-image',
		'core/site-title',
		'core/site-tagline',
		'core/site-logo',
		'core/navigation',
		// G4b (#401) — taxonomy/feed widgets backed by cms-framework's
		// term + post APIs through the dynamic-block registry. Hosts
		// without cms-framework leave them registered client-side but
		// the server-side renderer falls back to the unknown-block
		// shell since no DynamicBlock is registered.
		'core/categories',
		'core/tag-cloud',
		'core/archives',
		'artisanpack/callout',
	],

	'disabled_blocks' => [
		// `core/navigation` was enabled by D4 and stays enabled in E4.
		// `core/template-part`, `core/post-*`, and `core/site-*` are
		// promoted to the allow-list above by E4 (#381). G4b (#401)
		// promotes `core/categories`, `core/tag-cloud`, and
		// `core/archives`. The blocks listed here remain deliberately
		// deferred:
		//
		//  - core/query / core/query-loop need a real loop runtime
		//    (V1 G4c — `cms-framework` `QueryRuntime` service).
		//  - core/latest-comments needs a Comments module in
		//    cms-framework that does not exist yet (V1.1+).
		//
		// The JS-side mirror in site-editor-app.tsx is updated
		// alongside this entry.
		'core/query',
		'core/query-loop',
		'core/latest-comments',
	],

	/*
	|--------------------------------------------------------------------------
	| Media bridge
	|--------------------------------------------------------------------------
	|
	| The editor's media picker and upload plumbing route to whatever media
	| library the host application provides. The default integration is
	| `artisanpack-ui/media-library`: the host calls `registerMediaBridge`
	| with `MediaModal` and `uploadMedia` before `bootVisualEditor`. Any
	| library that exposes an equivalent picker component (props:
	| `open`, `onClose`, `onSelect`, `multiSelect`, `allowedTypes`,
	| `context`, `title`) and upload function (`(file, metadata?) =>
	| Promise<{ data: Media } | Media>`) can be swapped in — the
	| `media.bridge` key below records the active choice so server-side
	| code (for example the Featured Image hydration path) can pick the
	| matching PHP adapter from the container.
	|
	| Server-side record conversion is delegated to
	| `ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter`.
	| Rebind that class in the container to override the Gutenberg shape
	| emitted by `toGutenberg()`; the default implementation duck-types
	| the `artisanpack-ui/media-library` Media model.
	|
	*/

	'media' => [
		'bridge'  => 'artisanpack-ui/media-library',
		'adapter' => \ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter::class,
	],

	/*
	|--------------------------------------------------------------------------
	| API routes
	|--------------------------------------------------------------------------
	|
	| Middleware stack applied to the auto-registered `/visual-editor/api/*`
	| routes. The defaults cover a session-authenticated web app; API-only or
	| stateless apps can swap in `auth:sanctum`, `auth:api`, etc.
	|
	*/

	'api' => [
		'middleware' => [ 'api', 'auth' ],
	],

	/*
	|--------------------------------------------------------------------------
	| Authorization
	|--------------------------------------------------------------------------
	|
	| Controls how the default policy for the legacy VisualEditorPost model
	| gates access. Resource models (via `HasBlockContent`) delegate to their
	| own Laravel policies and ignore this flag.
	|
	*/

	'authorization' => [
		'restrict_by_owner' => false,
	],

	/*
	|--------------------------------------------------------------------------
	| Sample content
	|--------------------------------------------------------------------------
	|
	| The `visual-editor:seed-sample-content` Artisan command loads fixtures
	| for the five B1 shim entities (templates, template parts, navigation,
	| patterns, global styles) and writes them to a storage disk so the dev
	| app has something to render against before Phase C endpoints land.
	|
	| `fixtures_path` is an absolute directory path. When null, the command
	| falls back to the package's own `tests/Fixtures/sample-content/`
	| directory (only available while the package is installed from source,
	| e.g. a path repository or cloned checkout). Host apps that want to
	| vendor their own fixtures should point this at their own directory.
	|
	*/

	'sample_content' => [
		'fixtures_path' => null,
	],

	/*
	|--------------------------------------------------------------------------
	| Global styles
	|--------------------------------------------------------------------------
	|
	| Configures the `globalStyles` entity the site editor customizes.
	| `theme` scopes the singleton lookup — each installed theme gets its
	| own global-styles record. `schema_version` pins the theme.json
	| schema the package accepts on `PUT` requests; see
	| `docs/global-styles.md` for the contract and how we handle future
	| upgrades. `base_path` is an absolute path to the PHP file returning
	| the default `base` payload (the theme.json defaults the site-editor
	| compares user overrides against); leave null to use the package's
	| bundled defaults.
	|
	*/

	'global_styles' => [
		'theme'          => 'artisanpack-base',
		'schema_version' => 3,
		'base_path'      => null,
	],

	/*
	|--------------------------------------------------------------------------
	| Navigation
	|--------------------------------------------------------------------------
	|
	| `locations` maps a theme-exposed menu-location slug to a navigation
	| record id (by primary key) along with a human-readable label for the
	| site editor UI. When `primary_id` is null or points at a missing
	| record, the `MenuLocationResolver` falls back to the first published
	| nav in `menu_order`. Menu-location admin CRUD is deferred to 1.1+;
	| V1 is intentionally config-driven (see the V1 plan doc §8).
	|
	| Each entry:
	|   - `slug`       (string)  Location identifier used by theme blocks.
	|   - `label`      (string)  Human label shown in the site editor.
	|   - `primary_id` (int|null) VisualEditorNavigation id, or null to
	|                            always use the fallback.
	|
	*/

	'navigation' => [
		'locations' => [
			// 'primary' => [
			//     'slug'       => 'primary',
			//     'label'      => 'Primary Menu',
			//     'primary_id' => null,
			// ],
		],
	],

];
