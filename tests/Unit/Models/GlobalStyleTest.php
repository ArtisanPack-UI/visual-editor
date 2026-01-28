<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\GlobalStyle;

test( 'global style has correct table name', function (): void {
	$style = new GlobalStyle();

	expect( $style->getTable() )->toBe( 've_global_styles' );
} );

test( 'global style has correct fillable attributes', function (): void {
	$style = new GlobalStyle();

	expect( $style->getFillable() )->toContain( 'key' )
		->and( $style->getFillable() )->toContain( 'value' )
		->and( $style->getFillable() )->toContain( 'theme_default' )
		->and( $style->getFillable() )->toContain( 'is_customized' );
} );

test( 'global style casts json fields correctly', function (): void {
	$style = GlobalStyle::create( [
		'key'           => 'colors',
		'value'         => [ 'primary' => '#3b82f6' ],
		'theme_default' => [ 'primary' => '#3b82f6' ],
	] );

	$style->refresh();

	expect( $style->value )->toBeArray()
		->and( $style->theme_default )->toBeArray();
} );

test( 'global style casts boolean fields correctly', function (): void {
	$style = GlobalStyle::create( [
		'key'           => 'typography',
		'value'         => [ 'font' => 'sans-serif' ],
		'is_customized' => true,
	] );

	$style->refresh();

	expect( $style->is_customized )->toBeTrue();
} );

test( 'global style customized scope filters correctly', function (): void {
	GlobalStyle::create( [
		'key'           => 'colors-custom',
		'value'         => [ 'primary' => '#ff0000' ],
		'is_customized' => true,
	] );

	GlobalStyle::create( [
		'key'           => 'colors-default',
		'value'         => [ 'primary' => '#3b82f6' ],
		'is_customized' => false,
	] );

	expect( GlobalStyle::customized()->count() )->toBe( 1 );
} );

test( 'global style find by key works', function (): void {
	GlobalStyle::create( [
		'key'   => 'spacing',
		'value' => [ 'base' => '1rem' ],
	] );

	$found   = GlobalStyle::findByKey( 'spacing' );
	$missing = GlobalStyle::findByKey( 'nonexistent' );

	expect( $found )->not->toBeNull()
		->and( $found->key )->toBe( 'spacing' )
		->and( $missing )->toBeNull();
} );
