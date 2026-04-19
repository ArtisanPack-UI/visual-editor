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
	| Block registry filters
	|--------------------------------------------------------------------------
	|
	| `enabled_blocks` acts as an allow-list: when non-empty, only the listed
	| block names are exposed to the inserter. `disabled_blocks` is an
	| always-applied deny-list. The deny-list wins when both are set. Use
	| fully-qualified block names (e.g. `core/paragraph`, `core/query`).
	|
	| The frozen V1 defaults follow the M5 block-library audit
	| (see docs/block-library-audit.md). Only blocks that render correctly
	| against the empty-state @wordpress/core-data shim are enabled; every
	| block that needs a real Laravel-backed core store — navigation, query,
	| post-*, site-*, template-part, taxonomy widgets — is disabled until
	| the artisanpack-ui/cms-framework package replaces the shim.
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
	],

	'disabled_blocks' => [
		'core/navigation',
		'core/query',
		'core/query-loop',
		'core/post-content',
		'core/post-title',
		'core/post-excerpt',
		'core/post-date',
		'core/post-author',
		'core/post-featured-image',
		'core/site-logo',
		'core/site-title',
		'core/site-tagline',
		'core/template-part',
		'core/latest-comments',
		'core/archives',
		'core/categories',
		'core/tag-cloud',
	],

	/*
	|--------------------------------------------------------------------------
	| Media adapter
	|--------------------------------------------------------------------------
	|
	| Reserved for a future server-side adapter binding. The M4 media bridge
	| (#314) wires `artisanpack-ui/media-library` into the editor entirely on
	| the client: the host app calls `registerMediaBridge(...)` from
	| `@artisanpack-ui/visual-editor` with `MediaModal` and `uploadMedia`
	| before `bootVisualEditor()`. Leaving this key in place for apps that
	| bind their own adapter via a container binding keyed on this value.
	|
	*/

	'media' => [
		'adapter' => 'null',
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

];
