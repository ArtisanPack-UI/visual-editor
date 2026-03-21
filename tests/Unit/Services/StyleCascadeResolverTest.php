<?php

/**
 * StyleCascadeResolver Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\ColorPaletteManager;
use ArtisanPackUI\VisualEditor\Services\SpacingScaleManager;
use ArtisanPackUI\VisualEditor\Services\StyleCascadeResolver;
use ArtisanPackUI\VisualEditor\Services\TypographyPresetsManager;

/**
 * Helper to create a fresh resolver instance with default managers.
 */
function createResolver(): StyleCascadeResolver
{
	return new StyleCascadeResolver(
		new ColorPaletteManager(),
		new TypographyPresetsManager(),
		new SpacingScaleManager(),
	);
}

// ─── Global Styles ──────────────────────────────────────────────────

test( 'getGlobalStyles returns colors typography and spacing', function (): void {
	$resolver = createResolver();
	$global   = $resolver->getGlobalStyles();

	expect( $global )->toHaveKeys( [ 'colors', 'typography', 'spacing' ] )
		->and( $global['colors'] )->toHaveKey( 'primary' )
		->and( $global['typography'] )->toHaveKeys( [ 'fontFamilies', 'elements' ] )
		->and( $global['spacing'] )->toHaveKeys( [ 'scale', 'blockGap' ] );
} );

test( 'getGlobalStyles colors are hex values', function (): void {
	$resolver = createResolver();
	$colors   = $resolver->getGlobalStyles()['colors'];

	foreach ( $colors as $slug => $value ) {
		expect( $value )->toBeString()
			->and( $value )->toMatch( '/^#[0-9a-fA-F]{6}$/' );
	}
} );

test( 'getGlobalStyles typography includes font families', function (): void {
	$resolver   = createResolver();
	$typography = $resolver->getGlobalStyles()['typography'];

	expect( $typography['fontFamilies'] )->toHaveKeys( [ 'heading', 'body', 'mono' ] );
} );

test( 'getGlobalStyles spacing includes standard steps', function (): void {
	$resolver = createResolver();
	$spacing  = $resolver->getGlobalStyles()['spacing'];

	expect( $spacing['scale'] )->toHaveKey( 'md' );
} );

// ─── Three-Level Cascade Resolution ────────────────────────────────

test( 'resolve with empty overrides returns global styles', function (): void {
	$resolver = createResolver();
	$global   = $resolver->getGlobalStyles();
	$resolved = $resolver->resolve();

	expect( $resolved )->toEqual( $global );
} );

test( 'resolve with template styles overrides global colors', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'colors' => [
			'primary' => '#ff0000',
		],
	];

	$resolved = $resolver->resolve( [], $templateStyles );

	expect( $resolved['colors']['primary'] )->toBe( '#ff0000' )
		->and( $resolved['colors'] )->toHaveKey( 'secondary' );
} );

test( 'resolve with block styles overrides template and global', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'colors' => [
			'primary' => '#ff0000',
		],
	];
	$blockStyles = [
		'colors' => [
			'primary' => '#00ff00',
		],
	];

	$resolved = $resolver->resolve( $blockStyles, $templateStyles );

	expect( $resolved['colors']['primary'] )->toBe( '#00ff00' );
} );

test( 'resolve preserves unoverridden values from each level', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'colors' => [
			'primary' => '#ff0000',
		],
	];
	$blockStyles = [
		'typography' => [
			'fontFamilies' => [
				'heading' => 'Georgia, serif',
			],
		],
	];

	$resolved = $resolver->resolve( $blockStyles, $templateStyles );

	// Block override for typography
	expect( $resolved['typography']['fontFamilies']['heading'] )->toBe( 'Georgia, serif' );
	// Template override for colors
	expect( $resolved['colors']['primary'] )->toBe( '#ff0000' );
	// Global default preserved
	expect( $resolved['colors'] )->toHaveKey( 'secondary' );
	expect( $resolved['typography']['fontFamilies'] )->toHaveKey( 'body' );
} );

test( 'resolve with nested typography elements merges correctly', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'typography' => [
			'elements' => [
				'h1' => [
					'fontSize' => '3rem',
				],
			],
		],
	];

	$resolved = $resolver->resolve( [], $templateStyles );

	// h1 fontSize overridden by template
	expect( $resolved['typography']['elements']['h1']['fontSize'] )->toBe( '3rem' );
	// h1 fontWeight should still exist from global
	expect( $resolved['typography']['elements']['h1'] )->toHaveKey( 'fontWeight' );
} );

// ─── resolveInherited ───────────────────────────────────────────────

test( 'resolveInherited returns global merged with template', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'colors' => [
			'primary' => '#ff0000',
		],
	];

	$inherited = $resolver->resolveInherited( $templateStyles );

	expect( $inherited['colors']['primary'] )->toBe( '#ff0000' )
		->and( $inherited['colors'] )->toHaveKey( 'secondary' );
} );

test( 'resolveInherited with empty template returns global', function (): void {
	$resolver  = createResolver();
	$global    = $resolver->getGlobalStyles();
	$inherited = $resolver->resolveInherited();

	expect( $inherited )->toEqual( $global );
} );

// ─── Source Detection ───────────────────────────────────────────────

test( 'getSource returns global when no overrides', function (): void {
	$resolver = createResolver();

	expect( $resolver->getSource( 'colors.primary' ) )->toBe( StyleCascadeResolver::SOURCE_GLOBAL );
} );

test( 'getSource returns template when template overrides', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'colors' => [
			'primary' => '#ff0000',
		],
	];

	expect( $resolver->getSource( 'colors.primary', [], $templateStyles ) )
		->toBe( StyleCascadeResolver::SOURCE_TEMPLATE );
} );

test( 'getSource returns block when block overrides', function (): void {
	$resolver    = createResolver();
	$blockStyles = [
		'colors' => [
			'primary' => '#00ff00',
		],
	];
	$templateStyles = [
		'colors' => [
			'primary' => '#ff0000',
		],
	];

	expect( $resolver->getSource( 'colors.primary', $blockStyles, $templateStyles ) )
		->toBe( StyleCascadeResolver::SOURCE_BLOCK );
} );

test( 'getSource checks block before template', function (): void {
	$resolver = createResolver();

	$source = $resolver->getSource(
		'colors.primary',
		[ 'colors' => [ 'primary' => '#111111' ] ],
		[ 'colors' => [ 'primary' => '#222222' ] ],
	);

	expect( $source )->toBe( StyleCascadeResolver::SOURCE_BLOCK );
} );

test( 'getSource with nested path works correctly', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'typography' => [
			'elements' => [
				'h1' => [
					'fontSize' => '3rem',
				],
			],
		],
	];

	expect( $resolver->getSource( 'typography.elements.h1.fontSize', [], $templateStyles ) )
		->toBe( StyleCascadeResolver::SOURCE_TEMPLATE );
} );

// ─── Source Map ─────────────────────────────────────────────────────

test( 'getSourceMap returns map for all leaf properties', function (): void {
	$resolver = createResolver();
	$map      = $resolver->getSourceMap();

	expect( $map )->toBeArray()
		->and( $map )->toHaveKey( 'colors.primary' )
		->and( $map['colors.primary'] )->toBe( StyleCascadeResolver::SOURCE_GLOBAL );
} );

test( 'getSourceMap reflects overrides correctly', function (): void {
	$resolver = createResolver();
	$map      = $resolver->getSourceMap(
		[ 'colors' => [ 'primary' => '#00ff00' ] ],
		[ 'colors' => [ 'secondary' => '#ff0000' ] ],
	);

	expect( $map['colors.primary'] )->toBe( StyleCascadeResolver::SOURCE_BLOCK )
		->and( $map['colors.secondary'] )->toBe( StyleCascadeResolver::SOURCE_TEMPLATE );
} );

// ─── Inherited Value ────────────────────────────────────────────────

test( 'getInheritedValue returns global value when no template override', function (): void {
	$resolver = createResolver();
	$global   = $resolver->getGlobalStyles();

	expect( $resolver->getInheritedValue( 'colors.primary' ) )
		->toBe( $global['colors']['primary'] );
} );

test( 'getInheritedValue returns template value when template overrides', function (): void {
	$resolver = createResolver();

	expect( $resolver->getInheritedValue( 'colors.primary', [ 'colors' => [ 'primary' => '#ff0000' ] ] ) )
		->toBe( '#ff0000' );
} );

test( 'getInheritedValue returns null for non-existent path', function (): void {
	$resolver = createResolver();

	expect( $resolver->getInheritedValue( 'nonexistent.path' ) )->toBeNull();
} );

// ─── Override Checks ────────────────────────────────────────────────

test( 'isBlockOverride returns true when block has the property', function (): void {
	$resolver = createResolver();

	expect( $resolver->isBlockOverride( 'colors.primary', [ 'colors' => [ 'primary' => '#00ff00' ] ] ) )
		->toBeTrue();
} );

test( 'isBlockOverride returns false when block does not have the property', function (): void {
	$resolver = createResolver();

	expect( $resolver->isBlockOverride( 'colors.primary', [] ) )
		->toBeFalse();
} );

test( 'isTemplateOverride returns true when template has the property', function (): void {
	$resolver = createResolver();

	expect( $resolver->isTemplateOverride( 'colors.primary', [ 'colors' => [ 'primary' => '#ff0000' ] ] ) )
		->toBeTrue();
} );

test( 'isTemplateOverride returns false when template does not have the property', function (): void {
	$resolver = createResolver();

	expect( $resolver->isTemplateOverride( 'colors.primary', [] ) )
		->toBeFalse();
} );

// ─── Edge Cases ─────────────────────────────────────────────────────

test( 'resolve handles empty arrays at all levels', function (): void {
	$resolver = createResolver();
	$resolved = $resolver->resolve( [], [] );

	expect( $resolved )->toBeArray()
		->and( $resolved['colors'] )->not->toBeEmpty();
} );

test( 'resolve handles spacing scale override', function (): void {
	$resolver       = createResolver();
	$templateStyles = [
		'spacing' => [
			'scale' => [
				'md' => '2rem',
			],
		],
	];

	$resolved = $resolver->resolve( [], $templateStyles );

	expect( $resolved['spacing']['scale']['md'] )->toBe( '2rem' );
} );

test( 'resolve handles blockGap override', function (): void {
	$resolver    = createResolver();
	$blockStyles = [
		'spacing' => [
			'blockGap' => 'lg',
		],
	];

	$resolved = $resolver->resolve( $blockStyles );

	expect( $resolved['spacing']['blockGap'] )->toBe( 'lg' );
} );

test( 'constants have correct values', function (): void {
	expect( StyleCascadeResolver::SOURCE_GLOBAL )->toBe( 'global' )
		->and( StyleCascadeResolver::SOURCE_TEMPLATE )->toBe( 'template' )
		->and( StyleCascadeResolver::SOURCE_BLOCK )->toBe( 'block' );
} );
