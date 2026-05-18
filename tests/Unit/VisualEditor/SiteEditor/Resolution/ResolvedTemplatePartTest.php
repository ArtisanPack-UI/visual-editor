<?php

/**
 * Tests for {@see \ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart}.
 *
 * Focus is on the legacy-area back-compat surface that Keystone #55
 * added. Resolution / coercion of other fields is exercised by the
 * higher-level adapter and feature tests; these unit cases stay
 * narrow.
 *
 * @since 1.1.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Exceptions\SiteEditorRegistrationException;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;

it( 'maps the legacy `general` area onto `uncategorized` so pre-#55 rows still resolve (Keystone #55)', function (): void {
	$part = ResolvedTemplatePart::fromArray( [
		'slug'  => 'sidebar-old',
		'area'  => 'general',
		'theme' => 'jmwd-default',
	] );

	expect( $part->area )->toBe( 'uncategorized' );
} );

it( 'accepts `navigation-overlay` so Gutenberg\'s Create Overlay flow resolves cleanly (Keystone #55)', function (): void {
	$part = ResolvedTemplatePart::fromArray( [
		'slug'  => 'navigation-overlay',
		'area'  => 'navigation-overlay',
		'theme' => 'jmwd-default',
	] );

	expect( $part->area )->toBe( 'navigation-overlay' );
} );

it( 'still rejects an unknown area that isn\'t the legacy `general` alias', function (): void {
	$threw = false;

	try {
		ResolvedTemplatePart::fromArray( [
			'slug'  => 'whatever',
			'area'  => 'menu-bar',
			'theme' => 'jmwd-default',
		] );
	} catch ( SiteEditorRegistrationException $e ) {
		$threw = true;
		expect( $e->getMessage() )->toContain( 'uncategorized' )
			->and( $e->getMessage() )->toContain( 'navigation-overlay' );
	}

	expect( $threw )->toBeTrue();
} );
