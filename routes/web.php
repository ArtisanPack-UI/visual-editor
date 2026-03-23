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
$permission = (string) config( 'artisanpack.visual-editor.site_editor.permission', 'visual-editor.access-site-editor' );
$gates      = (array) config( 'artisanpack.visual-editor.site_editor.gates', [] );
$hubPage    = (string) config( 'artisanpack.visual-editor.site_editor.hub_page', \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage::class );

$prefix = veApplyFilters( 've.site-editor.route-prefix', $prefix );

if ( '' !== $permission ) {
	$middleware[] = 've.gate:' . $permission;
}

Route::prefix( $prefix )
	->middleware( $middleware )
	->group( function () use ( $hubPage, $gates ): void {
		Route::get( '/', $hubPage )
			->name( 'visual-editor.site-editor' );

		Route::get( '/global-styles', \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\GlobalStylesPage::class )
			->middleware( veGateMiddleware( $gates['styles'] ?? '' ) )
			->name( 'visual-editor.global-styles' );

		Route::get( '/templates', \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage::class )
			->middleware( veGateMiddleware( $gates['templates'] ?? '' ) )
			->name( 'visual-editor.templates' );

		Route::get( '/parts', \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplatePartListingPage::class )
			->middleware( veGateMiddleware( $gates['parts'] ?? '' ) )
			->name( 'visual-editor.template-parts' );

		Route::get( '/patterns', \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternListingPage::class )
			->middleware( veGateMiddleware( $gates['patterns'] ?? '' ) )
			->name( 'visual-editor.patterns' );
	} );
