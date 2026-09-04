<?php

/**
 * H6 PatternController + GlobalStylesController standalone-install
 * tests — exercises behavior when cms-framework is autoloaded but its
 * provider has NOT been booted (the moral equivalent of a host app
 * without `composer require artisanpack-ui/cms-framework`).
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Standalone tester',
		'email'    => 'standalone-patterns+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );
} );

it( 'returns the built-in seed patterns when no contributors are registered', function (): void {
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	// #639 — the visual-editor ships a `page/blank` starter regardless
	// of whether cms-framework is integrated, so the modal has an entry
	// to render even on standalone installs.
	// #764 — a `page/location` starter is seeded alongside it, composing
	// the business-info blocks + reviews as a landing-page starting point.
	$response = $this->getJson( '/visual-editor/api/patterns' )
		->assertOk()
		->assertJsonCount( 2 );

	$slugs = array_column( $response->json(), 'slug' );

	expect( $slugs )->toContain( 'page/blank' )
		->and( $slugs )->toContain( 'page/location' );

	$location = collect( $response->json() )->firstWhere( 'slug', 'page/location' );

	expect( $location['source'] )->toBe( 'theme' )
		->and( $location['categories'] )->toContain( 'page' )
		->and( $location['post_types'] )->toBe( [ 'page' ] )
		->and( $location['content']['raw'] )->toContain( 'wp:artisanpack/business-address' )
		->and( $location['content']['raw'] )->toContain( 'wp:artisanpack/business-hours' )
		->and( $location['content']['raw'] )->toContain( 'wp:artisanpack/business-phone' )
		->and( $location['content']['raw'] )->toContain( 'wp:artisanpack/business-email' )
		->and( $location['content']['raw'] )->toContain( 'wp:artisanpack/reviews' );
} );

it( 'returns 404 on POST patterns when cms-framework is not integrated', function (): void {
	$this->postJson( '/visual-editor/api/patterns', [
		'slug'  => 'cta',
		'title' => 'CTA',
	] )
		->assertNotFound()
		->assertJsonPath( 'message', 'The site editor requires artisanpack-ui/cms-framework.' );
} );

it( 'returns 404 on PUT patterns when cms-framework is not integrated', function (): void {
	$this->putJson( '/visual-editor/api/patterns/user/cta', [
		'title' => 'Renamed',
	] )->assertNotFound();
} );

it( 'returns 404 on DELETE patterns when cms-framework is not integrated', function (): void {
	$this->deleteJson( '/visual-editor/api/patterns/user/cta' )->assertNotFound();
} );

it( 'returns __base__ as the global-styles lookup id when no resolver entry exists', function (): void {
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( '/visual-editor/api/global-styles/lookup' )
		->assertOk()
		->assertJsonPath( 'id', '__base__' );
} );

it( 'returns 404 on PUT global-styles when cms-framework is not integrated', function (): void {
	$this->putJson( '/visual-editor/api/global-styles/__base__', [
		'theme'  => 'digital-shopfront',
		'styles' => [],
	] )->assertNotFound();
} );
