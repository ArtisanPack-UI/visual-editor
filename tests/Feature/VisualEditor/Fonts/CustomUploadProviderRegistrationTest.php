<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Providers\CustomUploadProvider;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;

it( 'registers the custom upload provider on the font source registry by default', function (): void {
	$registry = app( FontSourceRegistry::class );

	expect( $registry->has( 'custom' ) )->toBeTrue()
		->and( $registry->get( 'custom' ) )->toBeInstanceOf( CustomUploadProvider::class );
} );

it( 'omits the custom upload provider when it is disabled in config', function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.providers.custom.enabled', false );

	$registry = app( FontSourceRegistry::class );

	expect( $registry->has( 'custom' ) )->toBeFalse();
} );
