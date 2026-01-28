<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Template;

test( 'template has correct table name', function (): void {
	$template = new Template();

	expect( $template->getTable() )->toBe( 've_templates' );
} );

test( 'template has correct fillable attributes', function (): void {
	$template = new Template();

	expect( $template->getFillable() )->toContain( 'slug' )
		->and( $template->getFillable() )->toContain( 'name' )
		->and( $template->getFillable() )->toContain( 'template_parts' )
		->and( $template->getFillable() )->toContain( 'content_area_settings' )
		->and( $template->getFillable() )->toContain( 'is_active' );
} );

test( 'template casts json fields correctly', function (): void {
	$template = Template::create( [
		'slug'                  => 'test-template',
		'name'                  => 'Test Template',
		'template_parts'        => [ 'header', 'footer' ],
		'content_area_settings' => [ 'max_width' => '1200px' ],
		'styles'                => [ 'color' => 'blue' ],
	] );

	$template->refresh();

	expect( $template->template_parts )->toBeArray()
		->and( $template->content_area_settings )->toBeArray()
		->and( $template->styles )->toBeArray();
} );

test( 'template casts boolean fields correctly', function (): void {
	$template = Template::create( [
		'slug'                  => 'bool-test',
		'name'                  => 'Bool Test',
		'template_parts'        => [],
		'content_area_settings' => [],
		'is_custom'             => true,
		'is_active'             => false,
	] );

	$template->refresh();

	expect( $template->is_custom )->toBeTrue()
		->and( $template->is_active )->toBeFalse();
} );

test( 'template uses slug as route key', function (): void {
	$template = new Template();

	expect( $template->getRouteKeyName() )->toBe( 'slug' );
} );

test( 'template active scope filters correctly', function (): void {
	Template::create( [
		'slug'                  => 'active-template',
		'name'                  => 'Active',
		'template_parts'        => [],
		'content_area_settings' => [],
		'is_active'             => true,
	] );

	Template::create( [
		'slug'                  => 'inactive-template',
		'name'                  => 'Inactive',
		'template_parts'        => [],
		'content_area_settings' => [],
		'is_active'             => false,
	] );

	expect( Template::active()->count() )->toBe( 1 );
} );

test( 'template custom scope filters correctly', function (): void {
	Template::create( [
		'slug'                  => 'custom-template',
		'name'                  => 'Custom',
		'template_parts'        => [],
		'content_area_settings' => [],
		'is_custom'             => true,
	] );

	Template::create( [
		'slug'                  => 'builtin-template',
		'name'                  => 'Built-in',
		'template_parts'        => [],
		'content_area_settings' => [],
		'is_custom'             => false,
	] );

	expect( Template::custom()->count() )->toBe( 1 );
} );

test( 'template of type scope filters correctly', function (): void {
	Template::create( [
		'slug'                  => 'page-template',
		'name'                  => 'Page',
		'template_parts'        => [],
		'content_area_settings' => [],
		'type'                  => 'page',
	] );

	Template::create( [
		'slug'                  => 'post-template',
		'name'                  => 'Post',
		'template_parts'        => [],
		'content_area_settings' => [],
		'type'                  => 'post',
	] );

	expect( Template::ofType( 'page' )->count() )->toBe( 1 );
} );
