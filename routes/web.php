<?php

declare( strict_types=1 );

/**
 * Visual Editor Routes
 *
 * Defines the web routes for the visual editor package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Models\Content;
use Illuminate\Support\Facades\Route;

Route::middleware( [ 'web', 'auth' ] )->group( function (): void {
	Route::get( '/visual-editor/{content}', function ( Content $content ) {
		return view( 'visual-editor::editor-page', [ 'content' => $content ] );
	} )->name( 'visual-editor.edit' );
} );
