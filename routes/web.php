<?php

/**
 * Visual Editor Site Editor Web Routes.
 *
 * All page component classes are resolved from config to allow class swaps.
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
$components = (array) config( 'artisanpack.visual-editor.site_editor.components', [] );

$hubPage          = (string) ( $components['hub_page'] ?? \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage::class );
$globalStylesPage = (string) ( $components['global_styles_page'] ?? \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\GlobalStylesPage::class );
$templateListing  = (string) ( $components['template_listing'] ?? \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage::class );
$partListing      = (string) ( $components['part_listing'] ?? \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplatePartListingPage::class );
$partEditor       = (string) ( $components['part_editor'] ?? \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PartEditorPage::class );
$patternListing   = (string) ( $components['pattern_listing'] ?? \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternListingPage::class );
$patternEditor    = (string) ( $components['pattern_editor'] ?? \ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternEditorPage::class );

$prefix = veApplyFilters( 've.site-editor.route-prefix', $prefix );

if ( '' !== $permission ) {
	$middleware[] = 've.gate:' . $permission;
}

Route::prefix( $prefix )
	->middleware( $middleware )
	->group( function () use ( $hubPage, $globalStylesPage, $templateListing, $partListing, $partEditor, $patternListing, $patternEditor, $gates ): void {
		Route::get( '/', $hubPage )
			->name( 'visual-editor.site-editor' );

		Route::get( '/global-styles', $globalStylesPage )
			->middleware( veGateMiddleware( $gates['styles'] ?? '' ) )
			->name( 'visual-editor.global-styles' );

		Route::get( '/templates', $templateListing )
			->middleware( veGateMiddleware( $gates['templates'] ?? '' ) )
			->name( 'visual-editor.templates' );

		Route::get( '/parts', $partListing )
			->middleware( veGateMiddleware( $gates['parts'] ?? '' ) )
			->name( 'visual-editor.template-parts' );

		Route::get( '/parts/create', $partEditor )
			->middleware( veGateMiddleware( $gates['parts'] ?? '' ) )
			->name( 'visual-editor.template-parts.create' );

		Route::get( '/parts/{slug}/edit', $partEditor )
			->middleware( veGateMiddleware( $gates['parts'] ?? '' ) )
			->name( 'visual-editor.template-parts.edit' );

		Route::get( '/patterns', $patternListing )
			->middleware( veGateMiddleware( $gates['patterns'] ?? '' ) )
			->name( 'visual-editor.patterns' );

		Route::get( '/patterns/create', $patternEditor )
			->middleware( veGateMiddleware( $gates['patterns'] ?? '' ) )
			->name( 'visual-editor.patterns.create' );

		Route::get( '/patterns/{slug}/edit', $patternEditor )
			->middleware( veGateMiddleware( $gates['patterns'] ?? '' ) )
			->name( 'visual-editor.patterns.edit' );
	} );
