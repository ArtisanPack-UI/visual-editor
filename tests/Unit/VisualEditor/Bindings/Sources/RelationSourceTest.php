<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\RelationSource;
use Tests\Fixtures\TestBindingsModel;
use Tests\TestUser;

it( 'returns null when no model is in scope', function () {
	$source = new RelationSource();

	expect( $source->resolve( new BindingContext(), [ 'path' => 'author.name' ] ) )->toBeNull();
} );

it( 'returns null when path is missing or empty', function () {
	$source = new RelationSource();
	$model  = new TestBindingsModel( [ 'title' => 'X' ] );

	expect( $source->resolve( new BindingContext( $model ), [] ) )->toBeNull()
		->and( $source->resolve( new BindingContext( $model ), [ 'path' => '   ' ] ) )->toBeNull();
} );

it( 'walks a single-segment path to a column', function () {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Welcome',
		'status'  => 'published',
		'content' => [],
	] );

	$source = new RelationSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'path' => 'title' ] ) )->toBe( 'Welcome' );
} );

it( 'walks a two-segment path across a belongsTo relation', function () {
	$author = TestUser::query()->create( [
		'name'     => 'Grace Hopper',
		'email'    => 'grace+' . uniqid() . '@example.com',
		'password' => 'secret',
	] );

	$model = TestBindingsModel::query()->create( [
		'title'     => 'X',
		'status'    => 'published',
		'author_id' => $author->getKey(),
		'content'   => [],
	] );

	$source = new RelationSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'path' => 'author.name' ] ) )
		->toBe( 'Grace Hopper' );
} );

it( 'returns null when a non-leaf segment dead-ends', function () {
	$model = TestBindingsModel::query()->create( [
		'title'     => 'Orphan',
		'status'    => 'published',
		'author_id' => null,
		'content'   => [],
	] );

	$source = new RelationSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'path' => 'author.name' ] ) )->toBeNull();
} );

it( 'declares the relation chain for eager-loading (drops the leaf segment)', function () {
	$source = new RelationSource();

	expect( $source->eagerLoadRelations( [ [ 'path' => 'author.name' ] ] ) )->toBe( [ 'author' ] );
} );

it( 'declares the nested relation chain', function () {
	$source = new RelationSource();

	expect( $source->eagerLoadRelations( [ [ 'path' => 'author.profile.display_name' ] ] ) )
		->toBe( [ 'author.profile' ] );
} );

it( 'declares no relation when the path has only one segment', function () {
	$source = new RelationSource();

	expect( $source->eagerLoadRelations( [ [ 'path' => 'title' ] ] ) )->toBe( [] );
} );

it( 'stops at a numeric segment when computing eager-loads', function () {
	$source = new RelationSource();

	expect( $source->eagerLoadRelations( [ [ 'path' => 'categories.0.name' ] ] ) )
		->toBe( [ 'categories' ] );
} );

it( 'returns a value from an array on the model', function () {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Y',
		'status'  => 'published',
		'content' => [ 'meta' => [ 'tone' => 'casual' ] ],
	] );

	$source = new RelationSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'path' => 'content.meta' ] ) )
		->toBe( [ 'tone' => 'casual' ] );
} );

it( 'deduplicates eager-load entries across many bindings on the same chain', function () {
	$source = new RelationSource();

	$paths = [
		[ 'path' => 'author.name' ],
		[ 'path' => 'author.email' ],
		[ 'path' => 'author.profile.bio' ],
	];

	expect( $source->eagerLoadRelations( $paths ) )->toBe( [ 'author', 'author.profile' ] );
} );
