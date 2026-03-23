<?php

/**
 * GlobalStylesState Component Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\GlobalStylesState;

test( 'global styles state can be instantiated with defaults', function (): void {
	$component = new GlobalStylesState();

	expect( $component->paletteEntries )->toBeArray()
		->and( $component->paletteEntries )->not->toBeEmpty()
		->and( $component->typographyData )->toBeArray()
		->and( $component->spacingData )->toBeArray();
} );

test( 'global styles state accepts custom palette', function (): void {
	$customPalette = [
		[ 'name' => 'Brand', 'slug' => 'brand', 'color' => '#ff0000' ],
	];

	$component = new GlobalStylesState( palette: $customPalette );

	expect( $component->paletteEntries )->toHaveCount( 1 )
		->and( $component->paletteEntries[0]['slug'] )->toBe( 'brand' );
} );

test( 'global styles state accepts custom typography', function (): void {
	$customTypography = [
		'fontFamilies' => [ 'heading' => 'Georgia, serif' ],
		'elements'     => [],
	];

	$component = new GlobalStylesState( typography: $customTypography );

	expect( $component->typographyData['fontFamilies']['heading'] )->toBe( 'Georgia, serif' );
} );

test( 'global styles state accepts custom spacing', function (): void {
	$customSpacing = [
		'scale'       => [ [ 'name' => 'Small', 'slug' => 'sm', 'value' => '0.5rem' ] ],
		'blockGap'    => 'sm',
		'customSteps' => [],
	];

	$component = new GlobalStylesState( spacing: $customSpacing );

	expect( $component->spacingData['scale'] )->toHaveCount( 1 )
		->and( $component->spacingData['blockGap'] )->toBe( 'sm' );
} );

test( 'global styles state renders', function (): void {
	$view = $this->blade( '<x-ve-global-styles-state />' );

	expect( $view )->not->toBeNull();
} );

test( 'global styles state renders as hidden element', function (): void {
	$this->blade( '<x-ve-global-styles-state />' )
		->assertSee( 'aria-hidden="true"', false );
} );

test( 'global styles state initializes globalStyles Alpine store', function (): void {
	$this->blade( '<x-ve-global-styles-state />' )
		->assertSee( "Alpine.store( 'globalStyles'", false );
} );
