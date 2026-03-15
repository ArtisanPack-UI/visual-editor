<?php

/**
 * Visual Editor API Routes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Routes
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Controllers\EmbedController;
use Illuminate\Support\Facades\Route;

Route::prefix( 'api/visual-editor' )
	->middleware( [ 'api', 'throttle:30,1' ] )
	->group( function (): void {
		Route::post( 'embed/resolve', [ EmbedController::class, 'resolve' ] )
			->name( 'visual-editor.embed.resolve' );

		Route::get( 'geocode', [ EmbedController::class, 'geocode' ] )
			->name( 'visual-editor.geocode' );
	} );
