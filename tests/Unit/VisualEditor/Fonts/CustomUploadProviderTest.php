<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;
use ArtisanPackUI\VisualEditor\Fonts\Providers\CustomUploadProvider;

beforeEach( function (): void {
	$this->provider = new CustomUploadProvider();
} );

it( 'identifies itself as the self-hostable custom source', function (): void {
	expect( $this->provider->key() )->toBe( 'custom' )
		->and( $this->provider->label() )->not->toBe( '' )
		->and( $this->provider->isSelfHostable() )->toBeTrue();
} );

it( 'exposes an empty catalog and echoes the requested page', function (): void {
	$page = $this->provider->searchCatalog( 'anything', 3 );

	expect( $page )->toBe( [
		'families' => [],
		'page'     => 3,
		'has_more' => false,
	] );
} );

it( 'clamps a non-positive catalog page to one', function (): void {
	expect( $this->provider->searchCatalog( '', 0 )['page'] )->toBe( 1 );
} );

it( 'resolves no family by slug', function (): void {
	expect( $this->provider->getFamily( 'anything' ) )->toBeNull();
} );

it( 'refuses to fetch a face — uploads have no remote origin', function (): void {
	expect( fn () => $this->provider->fetchFace( 'x', '400', 'normal' ) )
		->toThrow( FontProviderException::class );
} );
