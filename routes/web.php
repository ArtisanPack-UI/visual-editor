<?php

/**
 * Visual Editor Site Editor Web Routes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Routes
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Route;

$prefix     = (string) config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );
$middleware = (array) config( 'artisanpack.visual-editor.site_editor.middleware', [ 'web', 'auth' ] );
$hubPage    = (string) config( 'artisanpack.visual-editor.site_editor.hub_page', \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage::class );

$prefix = veApplyFilters( 've.site-editor.route-prefix', $prefix );

Route::prefix( $prefix )
	->middleware( $middleware )
	->group( function () use ( $hubPage ): void {
		Route::get( '/', $hubPage )
			->name( 'visual-editor.site-editor' );
	} );
