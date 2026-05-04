<?php

/**
 * Visual Editor API routes.
 *
 * JSON endpoints consumed by the React editor. Loaded by the service provider
 * under the `/visual-editor/api` prefix with the middleware stack configured
 * at `artisanpack.visual-editor.api.middleware`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Controllers\Adapters\CmsFramework\PageController;
use ArtisanPackUI\VisualEditor\Http\Controllers\Adapters\CmsFramework\PostController;
use ArtisanPackUI\VisualEditor\Http\Controllers\AttachmentController;
use ArtisanPackUI\VisualEditor\Http\Controllers\BlockPreviewController;
use ArtisanPackUI\VisualEditor\Http\Controllers\EntitySearchController;
use ArtisanPackUI\VisualEditor\Http\Controllers\GlobalStylesController;
use ArtisanPackUI\VisualEditor\Http\Controllers\MenuLocationsController;
use ArtisanPackUI\VisualEditor\Http\Controllers\NavigationController;
use ArtisanPackUI\VisualEditor\Http\Controllers\PatternController;
use ArtisanPackUI\VisualEditor\Http\Controllers\QueryResolveController;
use ArtisanPackUI\VisualEditor\Http\Controllers\ResourceContentController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\TemplateController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\TemplatePartController;
use ArtisanPackUI\VisualEditor\Http\Controllers\VisualEditorBlocksController;
use Illuminate\Support\Facades\Route;

// Generic resource content endpoints (M3). Any model registered in
// `artisanpack.visual-editor.resources` is editable through these routes.
Route::get( '{resource}/{id}/content', [ ResourceContentController::class, 'show' ] )
	->where( 'resource', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.resources.content.show' );

Route::put( '{resource}/{id}/content', [ ResourceContentController::class, 'update' ] )
	->where( 'resource', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.resources.content.update' );

Route::post( 'blocks/preview', [ BlockPreviewController::class, 'preview' ] )
	->name( 'visual-editor.api.blocks.preview' );

// G4c-2 — `core/query` block resolution. Wraps cms-framework's
// `QueryRuntime` (or any host-bound `QueryResolverContract`
// implementation) and returns paginated WP-shape results so the editor
// canvas + the React/Vue front-end renderers can consume the same
// envelope.
Route::post( 'query/resolve', [ QueryResolveController::class, 'resolve' ] )
	->name( 'visual-editor.api.query.resolve' );

Route::get( 'blocks', [ VisualEditorBlocksController::class, 'index' ] )
	->name( 'visual-editor.api.blocks.index' );

// H6 `wp_template` REST surface — see docs/plans/14-cms-framework-site-editor-integration.md §4.5.
// Slug-keyed (visual-editor's resolver is scoped to the active theme); reads
// come through H5's resolver, writes pass through to cms-framework's
// Template model under a class_exists + binding guard.
Route::get( 'templates', [ TemplateController::class, 'index' ] )
	->name( 'visual-editor.api.templates.index' );

Route::post( 'templates', [ TemplateController::class, 'store' ] )
	->name( 'visual-editor.api.templates.store' );

Route::get( 'templates/{slug}', [ TemplateController::class, 'show' ] )
	->where( 'slug', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.templates.show' );

Route::put( 'templates/{slug}', [ TemplateController::class, 'update' ] )
	->where( 'slug', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.templates.update' );

Route::delete( 'templates/{slug}', [ TemplateController::class, 'destroy' ] )
	->where( 'slug', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.templates.destroy' );

// H6 `wp_template_part` REST surface.
Route::get( 'template-parts', [ TemplatePartController::class, 'index' ] )
	->name( 'visual-editor.api.template-parts.index' );

Route::post( 'template-parts', [ TemplatePartController::class, 'store' ] )
	->name( 'visual-editor.api.template-parts.store' );

Route::get( 'template-parts/{slug}', [ TemplatePartController::class, 'show' ] )
	->where( 'slug', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.template-parts.show' );

Route::put( 'template-parts/{slug}', [ TemplatePartController::class, 'update' ] )
	->where( 'slug', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.template-parts.update' );

Route::delete( 'template-parts/{slug}', [ TemplatePartController::class, 'destroy' ] )
	->where( 'slug', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.template-parts.destroy' );

// C3 `globalStyles` REST surface — see docs/core-data-shim.md §Global styles.
// Order matters: the static `lookup` and `base` routes must be declared
// before `{globalStyle}` so they are not swallowed by the wildcard
// model-binding segment.
Route::get( 'global-styles/lookup', [ GlobalStylesController::class, 'lookup' ] )
	->name( 'visual-editor.api.global-styles.lookup' );

Route::get( 'global-styles/base', [ GlobalStylesController::class, 'base' ] )
	->name( 'visual-editor.api.global-styles.base' );

Route::get( 'global-styles/{globalStyle}', [ GlobalStylesController::class, 'show' ] )
	->name( 'visual-editor.api.global-styles.show' );

Route::put( 'global-styles/{globalStyle}', [ GlobalStylesController::class, 'update' ] )
	->name( 'visual-editor.api.global-styles.update' );

// C5 `wp_block` REST surface — see docs/core-data-shim.md §Patterns.
Route::get( 'patterns', [ PatternController::class, 'index' ] )
	->name( 'visual-editor.api.patterns.index' );

Route::post( 'patterns', [ PatternController::class, 'store' ] )
	->name( 'visual-editor.api.patterns.store' );

Route::get( 'patterns/{pattern}', [ PatternController::class, 'show' ] )
	->name( 'visual-editor.api.patterns.show' );

Route::put( 'patterns/{pattern}', [ PatternController::class, 'update' ] )
	->name( 'visual-editor.api.patterns.update' );

Route::delete( 'patterns/{pattern}', [ PatternController::class, 'destroy' ] )
	->name( 'visual-editor.api.patterns.destroy' );

// C4 `wp_navigation` REST surface — see docs/core-data-shim.md §Navigation.
Route::get( 'navigation', [ NavigationController::class, 'index' ] )
	->name( 'visual-editor.api.navigation.index' );

Route::post( 'navigation', [ NavigationController::class, 'store' ] )
	->name( 'visual-editor.api.navigation.store' );

Route::get( 'navigation/{navigation}', [ NavigationController::class, 'show' ] )
	->name( 'visual-editor.api.navigation.show' );

Route::put( 'navigation/{navigation}', [ NavigationController::class, 'update' ] )
	->name( 'visual-editor.api.navigation.update' );

Route::delete( 'navigation/{navigation}', [ NavigationController::class, 'destroy' ] )
	->name( 'visual-editor.api.navigation.destroy' );

// D4 menu-location read surface — locations are config-driven (V1 plan §8) so
// the editor only reads them; assignment writes live on the navigation
// record's `location` field via the regular `PUT /navigation/{id}` route.
Route::get( 'menu-locations', [ MenuLocationsController::class, 'index' ] )
	->name( 'visual-editor.api.menu-locations.index' );

// D4 entity search — backs the link-control picker in the menu tree
// editor. Sources: registered `resources` config + templates +
// template parts. Read-only.
Route::get( 'search', [ EntitySearchController::class, 'index' ] )
	->name( 'visual-editor.api.search.index' );

// G3 cms-framework Post + Page entity adapters — see plan 12 §4.4.
// Both controllers resolve their model through `ResourceResolver`, so
// the host's `posts` / `pages` slugs (registered statically or via
// the `ap.visual-editor.resources` filter) determine the underlying
// Eloquent class. The legacy `posts/{post}` routes that bound to
// `VisualEditorPost` were removed at this point in the M3→G3
// migration — host apps that still reference that model directly
// should migrate to a `HasBlockContent` model registered in the
// resource map.
Route::get( 'posts', [ PostController::class, 'index' ] )
	->name( 'visual-editor.api.posts.index' );

Route::post( 'posts', [ PostController::class, 'store' ] )
	->name( 'visual-editor.api.posts.store' );

Route::get( 'posts/{id}', [ PostController::class, 'show' ] )
	->name( 'visual-editor.api.posts.show' );

Route::put( 'posts/{id}', [ PostController::class, 'update' ] )
	->name( 'visual-editor.api.posts.update' );

Route::delete( 'posts/{id}', [ PostController::class, 'destroy' ] )
	->name( 'visual-editor.api.posts.destroy' );

Route::get( 'pages', [ PageController::class, 'index' ] )
	->name( 'visual-editor.api.pages.index' );

Route::post( 'pages', [ PageController::class, 'store' ] )
	->name( 'visual-editor.api.pages.store' );

Route::get( 'pages/{id}', [ PageController::class, 'show' ] )
	->name( 'visual-editor.api.pages.show' );

Route::put( 'pages/{id}', [ PageController::class, 'update' ] )
	->name( 'visual-editor.api.pages.update' );

Route::delete( 'pages/{id}', [ PageController::class, 'destroy' ] )
	->name( 'visual-editor.api.pages.destroy' );

// G4a — WP REST attachment shape for `core/post-featured-image` and
// `core/cover` (featured-image option). Both blocks resolve the saved
// `featured_media` id via `getEntityRecord('postType', 'attachment',
// id)`, so the shim's attachment entity registration points here.
// Read-only for V1; uploads still flow through the host's media-library
// picker via the M4 media bridge.
Route::get( 'attachments/{id}', [ AttachmentController::class, 'show' ] )
	->where( 'id', '[0-9]+' )
	->name( 'visual-editor.api.attachments.show' );
