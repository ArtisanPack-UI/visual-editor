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
use ArtisanPackUI\VisualEditor\Http\Controllers\Icon\IconSearchController;
use ArtisanPackUI\VisualEditor\Http\Controllers\Icon\IconSetsController;
use ArtisanPackUI\VisualEditor\Http\Controllers\Icon\IconSvgController;
use ArtisanPackUI\VisualEditor\Http\Controllers\Icon\IconSvgSanitizeController;
use ArtisanPackUI\VisualEditor\Http\Controllers\MenuLocationsController;
use ArtisanPackUI\VisualEditor\Http\Controllers\QueryResolveController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\GlobalStylesController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\MenuController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\MenuItemController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\PatternController;
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

// H6 `__unstableBase` REST surface — see docs/plans/14-cms-framework-site-editor-integration.md §4.5.
// Singleton-per-theme. The `lookup` and `base` static routes must be
// declared before `{id}` so they are not swallowed by the wildcard.
Route::get( 'global-styles/lookup', [ GlobalStylesController::class, 'lookup' ] )
	->name( 'visual-editor.api.global-styles.lookup' );

Route::get( 'global-styles/base', [ GlobalStylesController::class, 'base' ] )
	->name( 'visual-editor.api.global-styles.base' );

Route::get( 'global-styles/css', [ GlobalStylesController::class, 'css' ] )
	->name( 'visual-editor.api.global-styles.css' );

Route::get( 'global-styles/{id}', [ GlobalStylesController::class, 'show' ] )
	->where( 'id', '[A-Za-z0-9_]+' )
	->name( 'visual-editor.api.global-styles.show' );

Route::put( 'global-styles/{id}', [ GlobalStylesController::class, 'update' ] )
	->where( 'id', '[A-Za-z0-9_]+' )
	->name( 'visual-editor.api.global-styles.update' );

// H6 `wp_block` REST surface. Slug regex allows `user/<slug>` so
// cms-framework's user-source slug prefix rides through the URL.
Route::get( 'patterns', [ PatternController::class, 'index' ] )
	->name( 'visual-editor.api.patterns.index' );

Route::post( 'patterns', [ PatternController::class, 'store' ] )
	->name( 'visual-editor.api.patterns.store' );

Route::get( 'patterns/{slug}', [ PatternController::class, 'show' ] )
	->where( 'slug', '.+' )
	->name( 'visual-editor.api.patterns.show' );

Route::put( 'patterns/{slug}', [ PatternController::class, 'update' ] )
	->where( 'slug', '.+' )
	->name( 'visual-editor.api.patterns.update' );

Route::delete( 'patterns/{slug}', [ PatternController::class, 'destroy' ] )
	->where( 'slug', '.+' )
	->name( 'visual-editor.api.patterns.destroy' );

// H6 `wp_navigation` REST surface — see docs/plans/14-cms-framework-site-editor-integration.md §4.5.
// Id-keyed (cms-framework's `menus.id` is the canonical identifier);
// reads bypass H5's location-keyed resolver and hit the model directly,
// since `wp_navigation` REST expects id-based addressing over the full
// menu set (not just menus assigned to a location).
Route::get( 'menus', [ MenuController::class, 'index' ] )
	->name( 'visual-editor.api.menus.index' );

Route::post( 'menus', [ MenuController::class, 'store' ] )
	->name( 'visual-editor.api.menus.store' );

Route::get( 'menus/{id}', [ MenuController::class, 'show' ] )
	->whereNumber( 'id' )
	->name( 'visual-editor.api.menus.show' );

Route::put( 'menus/{id}', [ MenuController::class, 'update' ] )
	->whereNumber( 'id' )
	->name( 'visual-editor.api.menus.update' );

Route::delete( 'menus/{id}', [ MenuController::class, 'destroy' ] )
	->whereNumber( 'id' )
	->name( 'visual-editor.api.menus.destroy' );

// H6 `wp_navigation_link` REST surface. Items belong to a menu; index
// requires `?menu_id=...` so the editor scopes its fetch to a single
// menu's tree without paginating across the table.
Route::get( 'menu-items', [ MenuItemController::class, 'index' ] )
	->name( 'visual-editor.api.menu-items.index' );

Route::post( 'menu-items', [ MenuItemController::class, 'store' ] )
	->name( 'visual-editor.api.menu-items.store' );

Route::get( 'menu-items/{id}', [ MenuItemController::class, 'show' ] )
	->whereNumber( 'id' )
	->name( 'visual-editor.api.menu-items.show' );

Route::put( 'menu-items/{id}', [ MenuItemController::class, 'update' ] )
	->whereNumber( 'id' )
	->name( 'visual-editor.api.menu-items.update' );

Route::delete( 'menu-items/{id}', [ MenuItemController::class, 'destroy' ] )
	->whereNumber( 'id' )
	->name( 'visual-editor.api.menu-items.destroy' );

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

// Icon Block Phase 4 (#555) — picker search + set-family chips.
// Both routes are read-only; the catalog is backed by the bundled
// `index.json` manifest and exposes paginated results so the editor
// never has to ship the full FA Free term index to the browser.
Route::get( 'icons/sets', [ IconSetsController::class, 'index' ] )
	->name( 'visual-editor.api.icons.sets' );

Route::get( 'icons/search', [ IconSearchController::class, 'index' ] )
	->name( 'visual-editor.api.icons.search' );

Route::get( 'icons/svg', [ IconSvgController::class, 'show' ] )
	->name( 'visual-editor.api.icons.svg' );

// Icon Block Phase 5 (#556) — custom SVG paste/upload sanitization. The
// editor POSTs the pasted/uploaded markup, gets back the SvgSanitizer
// output + warnings, and persists the sanitized result into the block's
// `customSvg` attribute. Authoritative sanitization still runs at render
// time inside IconBlock; this endpoint is what lets the editor surface
// warnings inline before save.
Route::post( 'icons/svg/sanitize', [ IconSvgSanitizeController::class, 'store' ] )
	->name( 'visual-editor.api.icons.svg.sanitize' );

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

// #481 — singleton site-meta envelope consumed by the editor's
// `artisanpack/site-*` block previews. The shim's URL builder always
// appends an id segment to a single-record fetch, so the route accepts
// any short alphanumeric token and treats it as a sentinel — the
// controller always returns the same record.
Route::get( 'site/{id}', [ SiteController::class, 'show' ] )
	->where( 'id', '[A-Za-z0-9_-]+' )
	->name( 'visual-editor.api.site.show' );
