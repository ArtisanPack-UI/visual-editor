<?php

/**
 * Config Component Resolution Tests.
 *
 * Verifies that the config-based class swap mechanism works correctly
 * for site editor page components.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Contracts\SiteEditorListing;
use ArtisanPackUI\VisualEditor\Contracts\SiteEditorPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\GlobalStylesPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PartEditorPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternEditorPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternListingPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateEditorPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplatePartListingPage;

test( 'HubPage implements SiteEditorPage', function (): void {
	expect( new HubPage() )->toBeInstanceOf( SiteEditorPage::class );
} );

test( 'GlobalStylesPage implements SiteEditorPage', function (): void {
	expect( new GlobalStylesPage() )->toBeInstanceOf( SiteEditorPage::class );
} );

test( 'PartEditorPage implements SiteEditorPage', function (): void {
	expect( new PartEditorPage() )->toBeInstanceOf( SiteEditorPage::class );
} );

test( 'PatternEditorPage implements SiteEditorPage', function (): void {
	expect( new PatternEditorPage() )->toBeInstanceOf( SiteEditorPage::class );
} );

test( 'TemplateEditorPage implements SiteEditorPage', function (): void {
	expect( new TemplateEditorPage() )->toBeInstanceOf( SiteEditorPage::class );
} );

test( 'TemplateListingPage implements SiteEditorListing', function (): void {
	expect( new TemplateListingPage() )->toBeInstanceOf( SiteEditorListing::class );
} );

test( 'TemplatePartListingPage implements SiteEditorListing', function (): void {
	expect( new TemplatePartListingPage() )->toBeInstanceOf( SiteEditorListing::class );
} );

test( 'PatternListingPage implements SiteEditorListing', function (): void {
	expect( new PatternListingPage() )->toBeInstanceOf( SiteEditorListing::class );
} );

test( 'SiteEditorListing extends SiteEditorPage', function (): void {
	expect( is_subclass_of( SiteEditorListing::class, SiteEditorPage::class ) )->toBeTrue();
} );

test( 'config contains default hub_page class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['hub_page'] )->toBe( HubPage::class );
} );

test( 'config contains default template_listing class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['template_listing'] )->toBe( TemplateListingPage::class );
} );

test( 'config contains default part_listing class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['part_listing'] )->toBe( TemplatePartListingPage::class );
} );

test( 'config contains default pattern_listing class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['pattern_listing'] )->toBe( PatternListingPage::class );
} );

test( 'config contains default global_styles_page class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['global_styles_page'] )->toBe( GlobalStylesPage::class );
} );

test( 'config contains default part_editor class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['part_editor'] )->toBe( PartEditorPage::class );
} );

test( 'config contains default pattern_editor class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['pattern_editor'] )->toBe( PatternEditorPage::class );
} );

test( 'config contains default template_editor class', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['template_editor'] )->toBe( TemplateEditorPage::class );
} );

test( 'routes resolve hub page from config', function (): void {
	$components = config( 'artisanpack.visual-editor.site_editor.components' );
	$hubClass   = $components['hub_page'] ?? HubPage::class;

	expect( class_exists( $hubClass ) )->toBeTrue();
	expect( is_subclass_of( $hubClass, SiteEditorPage::class ) )->toBeTrue();
} );

test( 'custom config class overrides default', function (): void {
	config( [
		'artisanpack.visual-editor.site_editor.components.hub_page' => 'App\\Custom\\HubPage',
	] );

	$components = config( 'artisanpack.visual-editor.site_editor.components' );

	expect( $components['hub_page'] )->toBe( 'App\\Custom\\HubPage' );
} );
