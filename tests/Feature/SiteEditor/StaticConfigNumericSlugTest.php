<?php

/**
 * Static site-editor config keyed by an all-digit slug (#1093).
 *
 * PHP stores a `'404'` key as the integer `404`. The static map merges
 * back over the filter result with `array_replace()`, so the entry is
 * overwritten instead of renumbered and listed twice.
 *
 * Runs without cms-framework's provider: its own contributor renumbers
 * the key too, which cms-framework#447 tracks.
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\PatternController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\TemplateController;
use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\TemplatePartController;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$this->actingAs( TestUser::create( [
		'name'     => 'Static config tester',
		'email'    => 'static-config+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );

	config()->set( 'artisanpack.visual-editor.site-editor.templates', [
		'404' => [
			'slug'    => '404',
			'theme'   => 'digital-shopfront',
			'title'   => 'Not Found',
			'source'  => 'theme',
			'content' => [ 'raw' => '', 'blocks' => [] ],
		],
	] );

	config()->set( 'artisanpack.visual-editor.site-editor.template-parts', [
		'404' => [
			'slug'    => '404',
			'theme'   => 'digital-shopfront',
			'title'   => 'Not Found Notice',
			'area'    => 'general',
			'source'  => 'theme',
			'content' => [ 'raw' => '', 'blocks' => [] ],
		],
	] );

	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
} );

it( 'lists a static entry keyed by an all-digit slug once', function ( string $route ): void {
	$this->getJson( "/visual-editor/api/{$route}" )
		->assertOk()
		->assertJsonCount( 1 )
		->assertJsonPath( '0.slug', '404' )
		->assertJsonPath( '0.id', 'digital-shopfront//404' );
} )->with( [ 'templates', 'template-parts' ] );

it( 'keeps the static entry when a contributor keys its own entry by the same slug', function ( string $route, string $filter ): void {
	addFilter( $filter, function ( array $entries ): array {
		$entries['404'] = array_merge( $entries['404'], [ 'title' => 'From a contributor' ] );

		return $entries;
	} );

	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( "/visual-editor/api/{$route}" )
		->assertOk()
		->assertJsonCount( 1 )
		->assertJsonPath( '0.title.raw', fn ( string $title ): bool => 'From a contributor' !== $title );
} )->with( [
	'templates'      => [ 'templates', 'ap.visualEditor.templates' ],
	'template parts' => [ 'template-parts', 'ap.visualEditor.templateParts' ],
] );

it( 'rebuilds a resolver from static config keyed by an all-digit slug after a write', function ( string $controller, string $resolverClass ): void {
	if ( PatternController::class === $controller ) {
		config()->set( 'artisanpack.visual-editor.site-editor.patterns', [
			'404' => [
				'slug'    => '404',
				'title'   => 'Not Found Pattern',
				'source'  => 'theme',
				'content' => [ 'raw' => '', 'blocks' => [] ],
			],
		] );
	}

	$instance = app( $controller );

	Closure::bind( fn () => $this->refreshResolver(), $instance, $controller )();

	$matches = array_filter( app( $resolverClass )->all(), static fn ( object $entry ): bool => '404' === $entry->slug );

	expect( $matches )->toHaveCount( 1 );
} )->with( [
	'templates'      => [ TemplateController::class, TemplateResolver::class ],
	'template parts' => [ TemplatePartController::class, TemplatePartResolver::class ],
	'patterns'       => [ PatternController::class, PatternResolver::class ],
] );
