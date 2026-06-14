<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\CustomFieldSource;
use Tests\Fixtures\TestBindingsModel;

it( 'returns null when no model is in context', function () {
	$source = new CustomFieldSource();

	expect( $source->resolve( new BindingContext(), [ 'key' => 'featured_icon' ] ) )->toBeNull();
} );

it( 'returns null when the args key is missing or blank', function () {
	$source = new CustomFieldSource();
	$model  = new TestBindingsModel( [ 'title' => 'X' ] );
	$ctx    = new BindingContext( $model );

	expect( $source->resolve( $ctx, [] ) )->toBeNull()
		->and( $source->resolve( $ctx, [ 'key' => '' ] ) )->toBeNull();
} );

it( 'reads a column off the parent model', function () {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Hello',
		'status'  => 'published',
		'excerpt' => 'Short summary',
		'content' => [],
	] );

	$source = new CustomFieldSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'key' => 'excerpt' ] ) )
		->toBe( 'Short summary' );
} );

it( 'prefers the draft value over the saved column', function () {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Saved',
		'status'  => 'published',
		'content' => [],
	] );

	$source = new CustomFieldSource();

	$ctx = new BindingContext( $model, [ 'title' => 'Unsaved Draft' ] );

	expect( $source->resolve( $ctx, [ 'key' => 'title' ] ) )->toBe( 'Unsaved Draft' );
} );

it( 'returns null when the column does not exist on the model', function () {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'X',
		'status'  => 'published',
		'content' => [],
	] );

	$source = new CustomFieldSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'key' => 'no_such_column' ] ) )->toBeNull();
} );

it( 'declares no eager-load relations', function () {
	expect( ( new CustomFieldSource() )->eagerLoadRelations( [ [ 'key' => 'whatever' ] ] ) )->toBe( [] );
} );
