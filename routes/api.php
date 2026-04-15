<?php

/**
 * Visual Editor API routes.
 *
 * JSON endpoints consumed by the React editor. Loaded by the service provider
 * under the `/visual-editor/api` prefix with the `api` + `auth` middleware stack.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Controllers\VisualEditorBlocksController;
use ArtisanPackUI\VisualEditor\Http\Controllers\VisualEditorPostsController;
use Illuminate\Support\Facades\Route;

Route::get( 'posts/{post}', [VisualEditorPostsController::class, 'show'] )
	->name( 'visual-editor.api.posts.show' );

Route::put( 'posts/{post}', [VisualEditorPostsController::class, 'update'] )
	->name( 'visual-editor.api.posts.update' );

Route::get( 'blocks', [VisualEditorBlocksController::class, 'index'] )
	->name( 'visual-editor.api.blocks.index' );
