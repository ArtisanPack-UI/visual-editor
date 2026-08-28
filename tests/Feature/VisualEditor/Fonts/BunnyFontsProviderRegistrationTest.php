<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Providers\BunnyFontsProvider;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;

it( 'registers the Bunny Fonts provider on the font source registry by default', function () {
	$registry = app( FontSourceRegistry::class );

	expect( $registry->has( 'bunny' ) )->toBeTrue()
		->and( $registry->get( 'bunny' ) )->toBeInstanceOf( BunnyFontsProvider::class );
} );

it( 'omits the Bunny Fonts provider when it is disabled in config', function () {
	config()->set( 'artisanpack.visual-editor.fonts.providers.bunny.enabled', false );

	$registry = app( FontSourceRegistry::class );

	expect( $registry->has( 'bunny' ) )->toBeFalse();
} );
