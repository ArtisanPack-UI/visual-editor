<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fixtures\TestBlockContentModel;
use Tests\TestUser;

it( 'throws 404 for an unknown resource slug', function (): void {
	$resolver = new ResourceResolver( [
		'posts' => TestBlockContentModel::class,
	] );

	$resolver->modelClassFor( 'orders' );
} )->throws( NotFoundHttpException::class );

it( 'returns the configured class for a known slug', function (): void {
	$resolver = new ResourceResolver( [
		'posts' => TestBlockContentModel::class,
	] );

	expect( $resolver->modelClassFor( 'posts' ) )->toBe( TestBlockContentModel::class );
} );

it( 'throws RuntimeException when the configured class is missing', function (): void {
	$resolver = new ResourceResolver( [
		'ghosts' => 'App\\Models\\NonexistentModel',
	] );

	$resolver->modelClassFor( 'ghosts' );
} )->throws( RuntimeException::class );

it( 'does not validate HasBlockContent at construction', function (): void {
	// TestUser is a real Eloquent model but does not use HasBlockContent.
	// Constructor must accept it without throwing — validation is deferred
	// to first resolve so a contributor's standalone install never trips
	// host boot.
	$resolver = new ResourceResolver( [
		'users' => TestUser::class,
	] );

	expect( $resolver )->toBeInstanceOf( ResourceResolver::class );
} );

it( 'throws InvalidArgumentException with the prescribed message on first resolve of a non-HasBlockContent class', function (): void {
	$resolver = new ResourceResolver( [
		'users' => TestUser::class,
	] );

	$resolver->resolve( 'users', 1 );
} )->throws(
	InvalidArgumentException::class,
	'Resource [users] resolves to [' . TestUser::class . '] which does not use HasBlockContent.',
);

it( 'reports whether a slug is registered, including entries with an invalid class', function (): void {
	$resolver = new ResourceResolver( [
		'posts'  => TestBlockContentModel::class,
		'broken' => 'App\\Models\\DoesNotExist',
	] );

	expect( $resolver->has( 'posts' ) )->toBeTrue()
		->and( $resolver->has( 'broken' ) )->toBeTrue()
		->and( $resolver->has( 'imaginary' ) )->toBeFalse();
} );

it( 'honours a subclass that resolves slugs dynamically in has()', function (): void {
	$resolver = new class extends ResourceResolver {
		public function modelClassFor( string $resource ): string
		{
			if ( 'portfolio' === $resource ) {
				return TestBlockContentModel::class;
			}

			throw new NotFoundHttpException();
		}
	};

	expect( $resolver->has( 'portfolio' ) )->toBeTrue()
		->and( $resolver->has( 'imaginary' ) )->toBeFalse();
} );
