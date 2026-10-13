<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\PostCoreSource;
use Tests\Fixtures\TestBindingsModel;
use Tests\TestUser;

it( 'returns null when no model is in scope', function (): void {
	$source = new PostCoreSource();

	expect( $source->resolve( new BindingContext(), [ 'key' => 'title' ] ) )->toBeNull();
} );

it( 'reads the title off the parent model', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Welcome',
		'status'  => 'published',
		'content' => [],
	] );

	$source = new PostCoreSource();

	expect( $source->resolve( new BindingContext( $model ), [ 'key' => 'title' ] ) )->toBe( 'Welcome' );
} );

it( 'prefers a draft value over the saved column', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Saved Title',
		'status'  => 'published',
		'content' => [],
	] );

	$source = new PostCoreSource();

	$ctx = new BindingContext( $model, [ 'title' => 'Editor Draft' ] );

	expect( $source->resolve( $ctx, [ 'key' => 'title' ] ) )->toBe( 'Editor Draft' );
} );

it( 'resolves the author name via the belongsTo relation', function (): void {
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

it( 'declares the author relation for eager-loading when author_name is bound', function (): void {
	$source = new PostCoreSource();

	expect( $source->eagerLoadRelations( [ [ 'key' => 'author_name' ] ] ) )->toBe( [ 'author' ] );
} );

it( 'declares no relations when only title or excerpt is bound', function (): void {
	$source = new PostCoreSource();

	expect( $source->eagerLoadRelations( [ [ 'key' => 'title' ], [ 'key' => 'excerpt' ] ] ) )->toBe( [] );
} );

it( 'returns null when the key is empty', function (): void {
	$source = new PostCoreSource();
	$model  = new TestBindingsModel( [ 'title' => 'X' ] );

	expect( $source->resolve( new BindingContext( $model ), [] ) )->toBeNull()
		->and( $source->resolve( new BindingContext( $model ), [ 'key' => '' ] ) )->toBeNull();
} );

it( 'refuses to resolve a key that is not in the supported whitelist', function (): void {
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

it( 'refuses to read a supported field the model hides from serialization (#848)', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Welcome',
		'status'  => 'published',
		'excerpt' => 'Internal summary',
		'content' => [],
	] );
	$model->makeHidden( 'excerpt' );

	$source = new PostCoreSource();
	$ctx    = new BindingContext( $model );

	expect( $source->resolve( $ctx, [ 'key' => 'excerpt' ] ) )->toBeNull()
		->and( $source->resolve( $ctx, [ 'key' => 'title' ] ) )->toBe( 'Welcome' );
} );

it( 'only reads supported fields listed in a non-empty $visible (#848)', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Welcome',
		'status'  => 'published',
		'excerpt' => 'Internal summary',
		'content' => [],
	] );
	$model->setVisible( [ 'title' ] );

	$source = new PostCoreSource();
	$ctx    = new BindingContext( $model );

	expect( $source->resolve( $ctx, [ 'key' => 'excerpt' ] ) )->toBeNull()
		->and( $source->resolve( $ctx, [ 'key' => 'title' ] ) )->toBe( 'Welcome' );
} );

it( 'does not resolve author_name through a hidden author name (#848)', function (): void {
	$author = TestUser::query()->create( [
		'name'     => 'Grace Hopper',
		'email'    => 'grace+' . uniqid() . '@example.com',
		'password' => 'secret',
	] );
	$author->makeHidden( 'name' );

	$model = TestBindingsModel::query()->create( [
		'title'     => 'X',
		'status'    => 'published',
		'author_id' => $author->getKey(),
		'content'   => [],
	] );
	$model->setRelation( 'author', $author );

	expect( ( new PostCoreSource() )->resolve( new BindingContext( $model ), [ 'key' => 'author_name' ] ) )->toBeNull();
} );
