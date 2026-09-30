<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.presets', [] );
} );

/**
 * The resolver resolves the emitter by its leading-backslash FQCN, which
 * the container treats as a distinct key from `GlobalStylesEmitter::class`.
 */
function fakeGlobalStylesEmitter( string $css ): void
{
	$emitter = Mockery::mock( GlobalStylesEmitter::class );
	$emitter->shouldReceive( 'emit' )->andReturn( $css );

	app()->instance( GlobalStylesEmitter::class, $emitter );
	app()->instance( '\\' . GlobalStylesEmitter::class, $emitter );
}

/**
 * Bind a cms-framework resolver whose resolved settings (theme.json +
 * style variation + user row, already merged) carry the given spacing
 * sizes — the source the fallback block is built from.
 *
 * @param  array<int, array<string, string>>|null  $spacingSizes
 */
function fakeResolvedSpacingSizes( ?array $spacingSizes ): void
{
	$settings = null === $spacingSizes ? [] : [ 'spacing' => [ 'spacingSizes' => $spacingSizes ] ];
	$resolver = Mockery::mock( GlobalStylesResolver::class );
	$resolver->shouldReceive( 'resolve' )->andReturn( new ResolvedGlobalStyles(
		theme               : 'demo',
		settings            : $settings,
		styles              : [],
		variation           : 'roomy',
		hasUserCustomization: true,
		model               : null,
	) );

	app()->instance( GlobalStylesResolver::class, $resolver );
}

it( 'prepends the in-effect spacing presets before the emitter output (#814)', function (): void {
	fakeGlobalStylesEmitter( ":root {\n\t--wp--preset--color--primary: #000;\n}\n" );
	fakeResolvedSpacingSizes( null );

	$css = ( new GlobalStylesEmissionResolver() )->emit();

	expect( $css )
		->toStartWith( ":root {\n\t--wp--preset--spacing--20: 0.5rem;" )
		->toEndWith( ":root {\n\t--wp--preset--color--primary: #000;\n}" )
		->toContain( '--wp--preset--spacing--40: 1.5rem;' );
} );

it( 'builds the fallback from the resolved settings so variation sizes beat the package defaults (#814)', function (): void {
	fakeGlobalStylesEmitter( ":root {\n\t--wp--preset--spacing--40: 2.5rem;\n}\n" );
	fakeResolvedSpacingSizes( [ [ 'slug' => '40', 'size' => '2.5rem' ] ] );

	$css = ( new GlobalStylesEmissionResolver() )->emit();

	expect( $css )
		->not->toContain( '--wp--preset--spacing--40: 1.5rem;' )
		->not->toContain( '--wp--preset--spacing--20: 0.5rem;' );
} );

it( 'keeps the resolved value last in the cascade when a host preset collides (#814)', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.spacing_sizes', [ [ 'slug' => 'sm', 'size' => '6px' ] ] );
	fakeGlobalStylesEmitter( ":root {\n\t--wp--preset--spacing--sm: 12px;\n}\n" );
	fakeResolvedSpacingSizes( [ [ 'slug' => 'sm', 'size' => '12px' ] ] );

	$css = ( new GlobalStylesEmissionResolver() )->emit();

	expect( strrpos( $css, '--wp--preset--spacing--sm: 12px;' ) )
		->toBeGreaterThan( (int) strpos( $css, '--wp--preset--spacing--sm: 6px;' ) );
} );

it( 'emits nothing when the emitter has no output, keeping the #434 contract (#814)', function (): void {
	fakeGlobalStylesEmitter( '' );

	expect( ( new GlobalStylesEmissionResolver() )->emit() )->toBe( '' );
} );
