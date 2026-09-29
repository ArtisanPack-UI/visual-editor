<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
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

it( 'appends the in-effect spacing presets after the emitter output (#814)', function (): void {
	fakeGlobalStylesEmitter( ":root {\n\t--wp--preset--color--primary: #000;\n}\n" );

	$css = ( new GlobalStylesEmissionResolver() )->emit();

	expect( $css )
		->toStartWith( ":root {\n\t--wp--preset--color--primary: #000;\n}\n\n:root {" )
		->toContain( '--wp--preset--spacing--40: 1.5rem;' );
} );

it( 'emits nothing when the emitter has no output, keeping the #434 contract (#814)', function (): void {
	fakeGlobalStylesEmitter( '' );

	expect( ( new GlobalStylesEmissionResolver() )->emit() )->toBe( '' );
} );
