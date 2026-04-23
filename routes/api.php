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

use ArtisanPackUI\VisualEditor\Http\Controllers\BlockPreviewController;
use ArtisanPackUI\VisualEditor\Http\Controllers\GlobalStylesController;
use ArtisanPackUI\VisualEditor\Http\Controllers\ResourceContentController;
use ArtisanPackUI\VisualEditor\Http\Controllers\TemplateController;
use ArtisanPackUI\VisualEditor\Http\Controllers\TemplatePartController;
use ArtisanPackUI\VisualEditor\Http\Controllers\VisualEditorBlocksController;
use ArtisanPackUI\VisualEditor\Http\Controllers\VisualEditorPostsController;
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

Route::get( 'blocks', [ VisualEditorBlocksController::class, 'index' ] )
	->name( 'visual-editor.api.blocks.index' );

// C1 `wp_template` REST surface — see docs/core-data-shim.md §Templates.
Route::get( 'templates', [ TemplateController::class, 'index' ] )
	->name( 'visual-editor.api.templates.index' );

Route::post( 'templates', [ TemplateController::class, 'store' ] )
	->name( 'visual-editor.api.templates.store' );

Route::get( 'templates/{template}', [ TemplateController::class, 'show' ] )
	->name( 'visual-editor.api.templates.show' );

Route::put( 'templates/{template}', [ TemplateController::class, 'update' ] )
	->name( 'visual-editor.api.templates.update' );

Route::delete( 'templates/{template}', [ TemplateController::class, 'destroy' ] )
	->name( 'visual-editor.api.templates.destroy' );

// C2 `wp_template_part` REST surface — see docs/core-data-shim.md §Template parts.
Route::get( 'template-parts', [ TemplatePartController::class, 'index' ] )
	->name( 'visual-editor.api.template-parts.index' );

Route::post( 'template-parts', [ TemplatePartController::class, 'store' ] )
	->name( 'visual-editor.api.template-parts.store' );

Route::get( 'template-parts/{templatePart}', [ TemplatePartController::class, 'show' ] )
	->name( 'visual-editor.api.template-parts.show' );

Route::put( 'template-parts/{templatePart}', [ TemplatePartController::class, 'update' ] )
	->name( 'visual-editor.api.template-parts.update' );

Route::delete( 'template-parts/{templatePart}', [ TemplatePartController::class, 'destroy' ] )
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

// Legacy ve_contents routes retained for the existing editor tests and the
// `VisualEditorPost` model. Deprecated in M3 in favor of the resource routes
// above; removed once the dev app migrates off the seeded post fixture.
Route::get( 'posts/{post}', [ VisualEditorPostsController::class, 'show' ] )
	->name( 'visual-editor.api.posts.show' );

Route::put( 'posts/{post}', [ VisualEditorPostsController::class, 'update' ] )
	->name( 'visual-editor.api.posts.update' );
