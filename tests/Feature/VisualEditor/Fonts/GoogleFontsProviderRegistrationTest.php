<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Providers\GoogleFontsProvider;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;

it( 'registers the Google Fonts provider on the font source registry by default', function () {
	$registry = app( FontSourceRegistry::class );

	expect( $registry->has( 'google' ) )->toBeTrue()
		->and( $registry->get( 'google' ) )->toBeInstanceOf( GoogleFontsProvider::class );
} );

it( 'omits the Google Fonts provider when it is disabled in config', function () {
	config()->set( 'artisanpack.visual-editor.fonts.providers.google.enabled', false );

	$registry = app( FontSourceRegistry::class );

	expect( $registry->has( 'google' ) )->toBeFalse();
} );
