<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\PostCoreSource;
use Tests\Fixtures\TestBindingsModel;
use Tests\TestUser;

it( 'returns null when no model is in scope', function () {
	$source = new PostCoreSource();

	expect( $source->resolve( new BindingContext(), [ 'key' => 'title' ] ) )->toBeNull();
} );

it( 'reads the title off the parent model', function () {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Welcome',
		'status'  => 'published',
		'content' => [],
	] );

	$source = new PostCoreSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'key' => 'title' ] ) )->toBe( 'Welcome' );
} );

it( 'prefers a draft value over the saved column', function () {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Saved Title',
		'status'  => 'published',
		'content' => [],
	] );

	$source = new PostCoreSource();

	$ctx = new BindingContext( $model, [ 'title' => 'Editor Draft' ] );

	expect( $source->resolve( $ctx, [ 'key' => 'title' ] ) )->toBe( 'Editor Draft' );
} );

it( 'resolves the author name via the belongsTo relation', function () {
	$author = TestUser::query()->create( [
		'name'     => 'Ada Lovelace',
		'email'    => 'ada+' . uniqid() . '@example.com',
		'password' => 'secret',
	] );

	$model = TestBindingsModel::query()->create( [
		'title'     => 'X',
		'status'    => 'published',
		'author_id' => $author->getKey(),
		'content'   => [],
	] );

	$source = new PostCoreSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'key' => 'author_name' ] ) )
		->toBe( 'Ada Lovelace' );
} );

it( 'declares the author relation for eager-loading when author_name is bound', function () {
	$source = new PostCoreSource();

	expect( $source->eagerLoadRelations( [ [ 'key' => 'author_name' ] ] ) )->toBe( [ 'author' ] );
} );

it( 'declares no relations when only title or excerpt is bound', function () {
	$source = new PostCoreSource();

	expect( $source->eagerLoadRelations( [ [ 'key' => 'title' ], [ 'key' => 'excerpt' ] ] ) )->toBe( [] );
} );

it( 'returns null when the key is empty', function () {
	$source = new PostCoreSource();
	$model  = new TestBindingsModel( [ 'title' => 'X' ] );

	expect( $source->resolve( new BindingContext( $model ), [] ) )->toBeNull()
		->and( $source->resolve( new BindingContext( $model ), [ 'key' => '' ] ) )->toBeNull();
} );

it( 'refuses to resolve a key that is not in the supported whitelist', function () {
	$model = TestBindingsModel::query()->create( [
		'title'     => 'X',
		'status'    => 'published',
		'author_id' => 999,
		'content'   => [],
	] );

	$source = new PostCoreSource();

	// `author_id` is a real column on the model but is not in
	// SUPPORTED_FIELDS — the source should refuse to expose it via the
	// binding layer.
	expect( $source->resolve( new BindingContext( $model ), [ 'key' => 'author_id' ] ) )->toBeNull();
} );
