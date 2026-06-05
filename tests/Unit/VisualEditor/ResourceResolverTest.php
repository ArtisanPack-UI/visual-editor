<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fixtures\TestBlockContentModel;
use Tests\TestUser;

it( 'throws 404 for an unknown resource slug', function () {
	$resolver = new ResourceResolver( [
		'posts' => TestBlockContentModel::class,
	] );

	$resolver->modelClassFor( 'orders' );
} )->throws( NotFoundHttpException::class );

it( 'returns the configured class for a known slug', function () {
	$resolver = new ResourceResolver( [
		'posts' => TestBlockContentModel::class,
	] );

	expect( $resolver->modelClassFor( 'posts' ) )->toBe( TestBlockContentModel::class );
} );

it( 'throws RuntimeException when the configured class is missing', function () {
	$resolver = new ResourceResolver( [
		'ghosts' => 'App\\Models\\NonexistentModel',
	] );

	$resolver->modelClassFor( 'ghosts' );
} )->throws( RuntimeException::class );

it( 'does not validate HasBlockContent at construction', function () {
	// TestUser is a real Eloquent model but does not use HasBlockContent.
	// Constructor must accept it without throwing — validation is deferred
	// to first resolve so a contributor's standalone install never trips
	// host boot.
	$resolver = new ResourceResolver( [
		'users' => TestUser::class,
	] );

	expect( $resolver )->toBeInstanceOf( ResourceResolver::class );
} );

it( 'throws InvalidArgumentException with the prescribed message on first resolve of a non-HasBlockContent class', function () {
	$resolver = new ResourceResolver( [
		'users' => TestUser::class,
	] );

	$resolver->resolve( 'users', 1 );
} )->throws(
	InvalidArgumentException::class,
	'Resource [users] resolves to [' . TestUser::class . '] which does not use HasBlockContent.'
);
