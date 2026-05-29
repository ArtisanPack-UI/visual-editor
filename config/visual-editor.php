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
		// Content cluster — forked to artisanpack/* (I0 #408, I1 #409).
		// The core/* counterparts stay registered (so existing content
		// deserializes and the from:core/* transforms keep working) but are
		// dropped from this allow-list, so only the forks surface in the
		// inserter.
		'artisanpack/paragraph',
		'artisanpack/heading',
		'artisanpack/list',
		'artisanpack/quote',
		'artisanpack/code',
		'artisanpack/preformatted',
		'artisanpack/pullquote',
		'artisanpack/verse',
		'artisanpack/table',
		// Media cluster — forked to artisanpack/* (I2 #410).
		'artisanpack/image',
		'artisanpack/gallery',
		'artisanpack/video',
		'artisanpack/audio',
		'artisanpack/file',
		'artisanpack/embed',
		'artisanpack/cover',
		'artisanpack/media-text',
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
		// G4c-2 (#402) — `core/query` + its inner `core/post-template`
		// are pre-resolved server-side by `QueryInliner` against
		// cms-framework's `QueryRuntime`. The editor preview hits the
		// `/visual-editor/api/query/resolve` endpoint via a custom
		// `useQueryPreview` hook. Each renderer package (Blade, React,
		// Vue) ships its own thin `core/query` renderer that walks the
		// pre-expanded inner blocks.
		'core/query',
		'core/post-template',
		'artisanpack/callout',
		// Layout cluster — forked to artisanpack/* (I3 #411).
		'artisanpack/group',
		'artisanpack/row',
		'artisanpack/stack',
		'artisanpack/columns',
		'artisanpack/column',
		'artisanpack/buttons',
		'artisanpack/button',
		'artisanpack/separator',
		'artisanpack/spacer',
		'artisanpack/details',
		// Widgets cluster — forked to artisanpack/* (I4 #412).
		'artisanpack/search',
		'artisanpack/latest-posts',
	],

	'disabled_blocks' => [
		// `core/navigation` was enabled by D4 and stays enabled in E4.
		// `core/template-part`, `core/post-*`, and `core/site-*` are
		// promoted to the allow-list above by E4 (#381). G4b (#401)
		// promotes `core/categories`, `core/tag-cloud`, and
		// `core/archives`. G4c-2 (#402) promotes `core/query` and
		// `core/post-template`. The blocks listed here remain
		// deliberately deferred:
		//
		//  - core/query-loop is the deprecated alias for `core/query`;
		//    upstream registers it but no `Edit` ships any longer, so
		//    it stays in the deny-list to keep it out of the inserter.
		//  - core/latest-comments needs a Comments module in
		//    cms-framework that does not exist yet (V1.1+).
		//
		// The JS-side mirror in site-editor-app.tsx is updated
		// alongside this entry.
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
	| Site editor (H5)
	|--------------------------------------------------------------------------
	|
	| Static-config entry points for the five site-editor entity types. Each
	| key is also a filter slug — packages like cms-framework register their
	| entities at runtime through `addFilter('ap.visual-editor.{type}', ...)`.
	|
	| Static config wins on key collision: host-app entries listed here take
	| precedence over filter-supplied entries with the same key.
	|
	| Standalone visual-editor installs (no cms-framework, no host
	| registrations) leave these empty and the editor's site-editor surface
	| boots cleanly with no entities. See plan 14 §4.4 for the full filter
	| contract and the ResolvedX value-object shapes consumed by H6.
	|
	*/

	'site-editor' => [
		// array<string, array> keyed by template slug.
		// Each entry: { slug, theme, title, status, source, content: { raw, blocks }, has_theme_file, is_custom, wp_id?, ... }
		'templates' => [],

		// array<string, array> keyed by template-part slug.
		// Each entry adds: { area: 'header'|'footer'|'sidebar'|'general' }
		'template-parts' => [],

		// array<string, array> keyed by pattern slug.
		// Each entry: { slug, title, source: 'theme'|'user', synced, content: { raw, blocks }, categories?, block_types? }
		'patterns' => [],

		// array<string, mixed>|null — singleton, not a map.
		// { theme, settings, styles, variations? }
		'global-styles' => null,

		// array<string, array> keyed by theme-declared menu location.
		// Each entry: { location, name, items: [...] }
		'navigation' => [],
	],

];
