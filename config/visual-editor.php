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
	*/

	'enabled_blocks' => [],

	'disabled_blocks' => [],

	/*
	|--------------------------------------------------------------------------
	| Media adapter
	|--------------------------------------------------------------------------
	|
	| Identifier the editor uses to resolve the media-upload implementation.
	| M3 only ships the `null` adapter (media insertion is unavailable); the
	| real `artisanpack-ui/media-library` adapter lands in a later milestone.
	| Consuming apps can bind their own adapter by registering a container
	| binding on the same key and updating this value.
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
