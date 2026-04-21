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
		'artisanpack/callout',
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

];
