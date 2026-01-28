<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\TemplatePart;

test( 'template part has correct table name', function (): void {
	$part = new TemplatePart();

	expect( $part->getTable() )->toBe( 've_template_parts' );
} );

test( 'template part has correct fillable attributes', function (): void {
	$part = new TemplatePart();

	expect( $part->getFillable() )->toContain( 'slug' )
		->and( $part->getFillable() )->toContain( 'name' )
		->and( $part->getFillable() )->toContain( 'area' )
		->and( $part->getFillable() )->toContain( 'blocks' )
		->and( $part->getFillable() )->toContain( 'is_active' );
} );

test( 'template part casts json fields correctly', function (): void {
	$part = TemplatePart::create( [
		'slug'   => 'test-header',
		'name'   => 'Test Header',
		'area'   => 'header',
		'blocks' => [ [ 'type' => 'navigation' ] ],
		'styles' => [ 'background' => '#ffffff' ],
	] );

	$part->refresh();

	expect( $part->blocks )->toBeArray()
		->and( $part->styles )->toBeArray();
} );

test( 'template part uses slug as route key', function (): void {
	$part = new TemplatePart();

	expect( $part->getRouteKeyName() )->toBe( 'slug' );
} );

test( 'template part active scope filters correctly', function (): void {
	TemplatePart::create( [
		'slug'      => 'active-header',
		'name'      => 'Active Header',
		'blocks'    => [],
		'is_active' => true,
	] );

	TemplatePart::create( [
		'slug'      => 'inactive-header',
		'name'      => 'Inactive Header',
		'blocks'    => [],
		'is_active' => false,
	] );

	expect( TemplatePart::active()->count() )->toBe( 1 );
} );

test( 'template part for area scope filters correctly', function (): void {
	TemplatePart::create( [
		'slug'   => 'header-part',
		'name'   => 'Header',
		'area'   => 'header',
		'blocks' => [],
	] );

	TemplatePart::create( [
		'slug'   => 'footer-part',
		'name'   => 'Footer',
		'area'   => 'footer',
		'blocks' => [],
	] );

	expect( TemplatePart::forArea( 'header' )->count() )->toBe( 1 )
		->and( TemplatePart::forArea( 'footer' )->count() )->toBe( 1 );
} );
