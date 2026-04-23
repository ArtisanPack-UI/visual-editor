<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use ArtisanPackUI\VisualEditor\Services\MenuLocationResolver;

function makeNavigation( array $overrides = [] ): VisualEditorNavigation
{
	return VisualEditorNavigation::create( array_merge( [
		'slug'       => 'primary-nav',
		'title'      => 'Primary',
		'content'    => [
			'raw'    => '',
			'blocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
			],
		],
		'status'     => 'publish',
		'menu_order' => 0,
	], $overrides ) );
}

function setLocations( array $locations ): void
{
	config( [ 'artisanpack.visual-editor.navigation.locations' => $locations ] );
}

it( 'returns the configured primary nav when the location has an assignment', function () {
	$primary = makeNavigation( [ 'slug' => 'primary-nav', 'menu_order' => 5 ] );
	$footer  = makeNavigation( [ 'slug' => 'footer-nav', 'menu_order' => 10 ] );

	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => $primary->id,
		],
	] );

	$resolver = app( MenuLocationResolver::class );

	expect( $resolver->forLocation( 'primary' ) )->not->toBeNull()
		->and( $resolver->forLocation( 'primary' )->id )->toBe( $primary->id );
} );

it( 'falls back to the first published nav when the location has no assignment', function () {
	$first  = makeNavigation( [ 'slug' => 'primary-nav', 'menu_order' => 0 ] );
	$second = makeNavigation( [ 'slug' => 'footer-nav', 'menu_order' => 5 ] );

	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => null,
		],
	] );

	$resolver = app( MenuLocationResolver::class );

	expect( $resolver->forLocation( 'primary' ) )->not->toBeNull()
		->and( $resolver->forLocation( 'primary' )->id )->toBe( $first->id );
} );

it( 'falls back when the configured primary id points at a missing record', function () {
	$nav = makeNavigation( [ 'slug' => 'primary-nav' ] );

	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => 9999,
		],
	] );

	$resolver = app( MenuLocationResolver::class );

	expect( $resolver->forLocation( 'primary' ) )->not->toBeNull()
		->and( $resolver->forLocation( 'primary' )->id )->toBe( $nav->id );
} );

it( 'falls back when the configured primary id points at an empty record', function () {
	// `$other` has the lower menu_order so it wins the fallback ordering;
	// `$empty` is what the location points at and what we expect to be
	// bypassed because its block tree is empty.
	$other = makeNavigation( [ 'slug' => 'primary-nav', 'menu_order' => 0 ] );
	$empty = makeNavigation( [
		'slug'       => 'empty-nav',
		'content'    => [ 'raw' => '', 'blocks' => [] ],
		'menu_order' => 5,
	] );

	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => $empty->id,
		],
	] );

	$resolver = app( MenuLocationResolver::class );

	expect( $resolver->forLocation( 'primary' ) )->not->toBeNull()
		->and( $resolver->forLocation( 'primary' )->id )->toBe( $other->id );
} );

it( 'returns null when the database has no published navs', function () {
	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => null,
		],
	] );

	$resolver = app( MenuLocationResolver::class );

	expect( $resolver->forLocation( 'primary' ) )->toBeNull();
} );

it( 'ignores draft navs when falling back', function () {
	makeNavigation( [ 'slug' => 'draft-nav', 'status' => 'draft', 'menu_order' => 0 ] );
	$published = makeNavigation( [ 'slug' => 'primary-nav', 'status' => 'publish', 'menu_order' => 5 ] );

	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => null,
		],
	] );

	$resolver = app( MenuLocationResolver::class );

	expect( $resolver->forLocation( 'primary' ) )->not->toBeNull()
		->and( $resolver->forLocation( 'primary' )->id )->toBe( $published->id );
} );

it( 'returns the fallback for an unknown location slug', function () {
	$nav = makeNavigation( [ 'slug' => 'primary-nav' ] );

	setLocations( [] );

	$resolver = app( MenuLocationResolver::class );

	expect( $resolver->forLocation( 'does-not-exist' ) )->not->toBeNull()
		->and( $resolver->forLocation( 'does-not-exist' )->id )->toBe( $nav->id );
} );

it( 'exposes locations() as a keyed map with normalized entries', function () {
	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => 42,
		],
		'footer'  => [
			'slug'       => 'footer',
			'label'      => 'Footer Menu',
			'primary_id' => null,
		],
	] );

	$resolver  = app( MenuLocationResolver::class );
	$locations = $resolver->locations();

	expect( $locations )
		->toHaveKey( 'primary' )
		->toHaveKey( 'footer' )
		->and( $locations['primary'] )->toEqual( [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => 42,
		] )
		->and( $locations['footer']['primary_id'] )->toBeNull();
} );

it( 'drops malformed location entries silently', function () {
	setLocations( [
		'primary' => [
			'slug'       => 'primary',
			'label'      => 'Primary Menu',
			'primary_id' => 1,
		],
		'broken'  => 'not-an-array',
		''        => [
			'slug'  => '',
			'label' => 'Empty slug',
		],
	] );

	$resolver  = app( MenuLocationResolver::class );
	$locations = $resolver->locations();

	expect( $locations )
		->toHaveCount( 1 )
		->toHaveKey( 'primary' );
} );
