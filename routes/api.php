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
use ArtisanPackUI\VisualEditor\Http\Controllers\ResourceContentController;
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

// Legacy ve_contents routes retained for the existing editor tests and the
// `VisualEditorPost` model. Deprecated in M3 in favor of the resource routes
// above; removed once the dev app migrates off the seeded post fixture.
Route::get( 'posts/{post}', [ VisualEditorPostsController::class, 'show' ] )
	->name( 'visual-editor.api.posts.show' );

Route::put( 'posts/{post}', [ VisualEditorPostsController::class, 'update' ] )
	->name( 'visual-editor.api.posts.update' );
