<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\MenuResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;

/**
 * #1019 — the resource and site-editor resolvers are bound lazily, so their
 * filters only run when something resolves them, once per request scope.
 */
dataset( 'lazy resolvers', [
	'resources'      => [ 'ap.visualEditor.resources', ResourceResolver::class ],
	'templates'      => [ 'ap.visualEditor.templates', TemplateResolver::class ],
	'template parts' => [ 'ap.visualEditor.templateParts', TemplatePartResolver::class ],
	'patterns'       => [ 'ap.visualEditor.patterns', PatternResolver::class ],
	'navigation'     => [ 'ap.visualEditor.navigation', MenuResolver::class ],
] );

function rebindLazyResolvers(): void
{
	$provider = new VisualEditorServiceProvider( app() );
	$provider->registerResourceResolver();
	$provider->registerSiteEditorResolvers();
}

afterEach( function (): void {
	foreach ( [
		'ap.visualEditor.resources',
		'ap.visualEditor.templates',
		'ap.visualEditor.templateParts',
		'ap.visualEditor.patterns',
		'ap.visualEditor.navigation',
	] as $filter ) {
		removeAllFilters( $filter, 99 );
	}

	rebindLazyResolvers();
} );

it( 'does not run the filter until the resolver is resolved', function ( string $filter, string $resolver ): void {
	$calls = 0;

	addFilter( $filter, function ( array $existing ) use ( &$calls ): array {
		$calls++;

		return $existing;
	}, 99 );

	rebindLazyResolvers();

	expect( $calls )->toBe( 0 );

	app( $resolver );

	expect( $calls )->toBe( 1 );
} )->with( 'lazy resolvers' );

it( 'runs the filter once per request scope', function ( string $filter, string $resolver ): void {
	$calls = 0;

	addFilter( $filter, function ( array $existing ) use ( &$calls ): array {
		$calls++;

		return $existing;
	}, 99 );

	rebindLazyResolvers();

	$first = app( $resolver );

	expect( app( $resolver ) )->toBe( $first )
		->and( $calls )->toBe( 1 );

	app()->forgetScopedInstances();

	expect( app( $resolver ) )->not->toBe( $first )
		->and( $calls )->toBe( 2 );
} )->with( 'lazy resolvers' );

it( 'sees filter callbacks added after binding but before first resolve', function (): void {
	rebindLazyResolvers();

	addFilter( 'ap.visualEditor.templates', function ( array $existing ): array {
		return array_merge( [
			'late' => [ 'slug' => 'late', 'theme' => 'test', 'title' => 'Late', 'source' => 'theme' ],
		], $existing );
	}, 99 );

	expect( app( TemplateResolver::class )->find( 'late' ) )->not->toBeNull();
} );

it( 'does not build any resolver while booting the application', function (): void {
	foreach ( [
		ResourceResolver::class,
		TemplateResolver::class,
		TemplatePartResolver::class,
		PatternResolver::class,
		MenuResolver::class,
	] as $resolver ) {
		expect( app()->resolved( $resolver ) )->toBeFalse( "{$resolver} was built during boot" );
	}
} );
