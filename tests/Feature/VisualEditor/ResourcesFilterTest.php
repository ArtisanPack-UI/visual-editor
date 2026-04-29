<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestBlockContentPageModel;

afterEach( function () {
	removeAllFilters( 'ap.visual-editor.resources' );
} );

function rebuildResourceResolver(): ResourceResolver
{
	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	return app( ResourceResolver::class );
}

it( 'registers resources contributed via the ap.visual-editor.resources filter', function () {
	addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
		return array_merge( [
			'posts' => TestBlockContentModel::class,
		], $resources );
	} );

	$resolver = rebuildResourceResolver();

	expect( $resolver->modelClassFor( 'posts' ) )->toBe( TestBlockContentModel::class );
} );

it( 'lets static config win over filter contributions on key collision', function () {
	config()->set( 'artisanpack.visual-editor.resources', [
		'posts' => TestBlockContentPageModel::class, // host override
	] );

	addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
		// Filter contributor naively merges its default; the host config
		// in the static config map should still win.
		return array_merge( [
			'posts' => TestBlockContentModel::class,
		], $resources );
	} );

	$resolver = rebuildResourceResolver();

	expect( $resolver->modelClassFor( 'posts' ) )->toBe( TestBlockContentPageModel::class );
} );

it( 'merges static config and filter contributions when slugs do not collide', function () {
	config()->set( 'artisanpack.visual-editor.resources', [
		'pages' => TestBlockContentPageModel::class,
	] );

	addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
		return array_merge( [
			'posts' => TestBlockContentModel::class,
		], $resources );
	} );

	$resolver = rebuildResourceResolver();

	expect( $resolver->modelClassFor( 'pages' ) )->toBe( TestBlockContentPageModel::class );
	expect( $resolver->modelClassFor( 'posts' ) )->toBe( TestBlockContentModel::class );
} );

it( 'does not throw at boot when a filter contributes an invalid class — error surfaces on first request', function () {
	addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
		return array_merge( [
			'invalid' => 'App\\Models\\DoesNotExist',
		], $resources );
	} );

	// Boot-equivalent rebuild must succeed even though the contributed
	// class doesn't exist.
	$resolver = rebuildResourceResolver();

	expect( $resolver )->toBeInstanceOf( ResourceResolver::class );

	// The error only surfaces when a request actually tries to resolve
	// the slug.
	expect( fn () => $resolver->resolve( 'invalid', 1 ) )
		->toThrow( RuntimeException::class );
} );
