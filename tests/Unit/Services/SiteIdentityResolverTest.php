<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\SiteIdentityResolver;

test( 'get title returns app name by default', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.title', null );
	config()->set( 'app.name', 'Test App' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getTitle() )->toBe( 'Test App' );
} );

test( 'get title returns config override when set', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.title', 'Custom Title' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getTitle() )->toBe( 'Custom Title' );
} );

test( 'get title falls back to Laravel when config is empty string', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.title', '' );
	config()->set( 'app.name', 'Fallback App' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getTitle() )->toBe( 'Fallback App' );
} );

test( 'get tagline returns config value', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.tagline', 'A great site' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getTagline() )->toBe( 'A great site' );
} );

test( 'get tagline returns empty string by default', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.tagline', '' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getTagline() )->toBe( '' );
} );

test( 'get logo url returns config value', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.logo_url', '/images/logo.png' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getLogoUrl() )->toBe( '/images/logo.png' );
} );

test( 'get logo url returns empty string by default', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.logo_url', '' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getLogoUrl() )->toBe( '' );
} );

test( 'get home url returns app url by default', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.home_url', null );
	config()->set( 'app.url', 'https://example.com' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getHomeUrl() )->toBe( 'https://example.com' );
} );

test( 'get home url returns config override when set', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.home_url', 'https://custom.com' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getHomeUrl() )->toBe( 'https://custom.com' );
} );

test( 'get logo alt falls back to site title', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.logo_alt', null );
	config()->set( 'artisanpack.visual-editor.site_identity.title', null );
	config()->set( 'app.name', 'My App' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getLogoAlt() )->toBe( 'My App' );
} );

test( 'get logo alt returns config override when set', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.logo_alt', 'Custom Alt' );

	$resolver = new SiteIdentityResolver();

	expect( $resolver->getLogoAlt() )->toBe( 'Custom Alt' );
} );

test( 'to array returns all identity values', function (): void {
	config()->set( 'artisanpack.visual-editor.site_identity.title', 'Site Name' );
	config()->set( 'artisanpack.visual-editor.site_identity.tagline', 'Site Tagline' );
	config()->set( 'artisanpack.visual-editor.site_identity.logo_url', '/logo.png' );
	config()->set( 'artisanpack.visual-editor.site_identity.logo_alt', 'Logo Alt' );
	config()->set( 'artisanpack.visual-editor.site_identity.home_url', 'https://home.com' );

	$resolver = new SiteIdentityResolver();
	$array    = $resolver->toArray();

	expect( $array )->toHaveKeys( [ 'title', 'tagline', 'logoUrl', 'logoAlt', 'homeUrl' ] )
		->and( $array['title'] )->toBe( 'Site Name' )
		->and( $array['tagline'] )->toBe( 'Site Tagline' )
		->and( $array['logoUrl'] )->toBe( '/logo.png' )
		->and( $array['logoAlt'] )->toBe( 'Logo Alt' )
		->and( $array['homeUrl'] )->toBe( 'https://home.com' );
} );
