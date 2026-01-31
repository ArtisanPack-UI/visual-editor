<?php

declare( strict_types=1 );

/**
 * Visual Editor API Routes
 *
 * Defines the API routes for the visual editor package,
 * including content save, publish, and autosave endpoints.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Http\Controllers\ContentApiController;
use Illuminate\Support\Facades\Route;

$guard = config( 'artisanpack.visual-editor.api.auth_guard', 'sanctum' );

Route::middleware( [ 'api', "auth:{$guard}" ] )
	->prefix( 'api/visual-editor' )
	->group( function (): void {
		Route::post( '/content/{content}/save', [ ContentApiController::class, 'save' ] )
			->name( 'visual-editor.api.save' );

		Route::post( '/content/{content}/autosave', [ ContentApiController::class, 'autosave' ] )
			->name( 'visual-editor.api.autosave' );

		Route::post( '/content/{content}/publish', [ ContentApiController::class, 'publish' ] )
			->name( 'visual-editor.api.publish' );

		Route::post( '/content/{content}/unpublish', [ ContentApiController::class, 'unpublish' ] )
			->name( 'visual-editor.api.unpublish' );

		Route::post( '/content/{content}/schedule', [ ContentApiController::class, 'schedule' ] )
			->name( 'visual-editor.api.schedule' );
	} );
