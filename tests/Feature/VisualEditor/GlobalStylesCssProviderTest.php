<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesCssProvider;
use Illuminate\Support\Facades\Cache;

beforeEach( function (): void {
	Cache::flush();
	$this->provider = $this->app->make( GlobalStylesCssProvider::class );
} );

it( 'falls back to the bundled base payload when no record exists', function (): void {
	$css = $this->provider->css();

	// Defaults from resources/theme-json/default-base.php should round-trip
	// through the compiler so a brand-new install is never unstyled.
	expect( $css )->toContain( ':root' )
		->toContain( '--wp--preset--color--primary' )
		->toContain( 'body {' );
} );

it( 'compiles the active record when present', function (): void {
	VisualEditorGlobalStyles::create( [
		'theme'    => 'artisanpack-base',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'brand', 'name' => 'Brand', 'color' => '#abcdef' ],
				],
			],
		],
		'styles'   => [],
	] );

	$css = $this->provider->css();

	expect( $css )->toContain( '--wp--preset--color--brand: #abcdef' );
} );

it( 'caches the compiled CSS — second call hits the cache', function (): void {
	$record = VisualEditorGlobalStyles::create( [
		'theme'    => 'artisanpack-base',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [ [ 'slug' => 'one', 'name' => 'One', 'color' => '#111111' ] ],
			],
		],
		'styles'   => [],
	] );

	$first = $this->provider->css();

	// Mutating the model in memory without saving should not affect the
	// cached output — proves the second call read from the cache.
	$record->settings = [
		'color' => [
			'palette' => [ [ 'slug' => 'two', 'name' => 'Two', 'color' => '#222222' ] ],
		],
	];

	$second = $this->provider->css();

	expect( $second )->toBe( $first )
		->toContain( '--wp--preset--color--one' )
		->not->toContain( '--wp--preset--color--two' );
} );

it( 'invalidates the cache when the record is saved', function (): void {
	$record = VisualEditorGlobalStyles::create( [
		'theme'    => 'artisanpack-base',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [ [ 'slug' => 'before', 'name' => 'Before', 'color' => '#000000' ] ],
			],
		],
		'styles'   => [],
	] );

	$initial = $this->provider->css();

	expect( $initial )->toContain( '--wp--preset--color--before' );

	// Sleep a second so updated_at advances — sqlite's CURRENT_TIMESTAMP
	// has 1s resolution and the cache key includes the timestamp.
	sleep( 1 );

	$record->settings = [
		'color' => [
			'palette' => [ [ 'slug' => 'after', 'name' => 'After', 'color' => '#ffffff' ] ],
		],
	];
	$record->save();

	$updated = $this->provider->css();

	expect( $updated )->toContain( '--wp--preset--color--after' )
		->not->toContain( '--wp--preset--color--before' );
} );

it( 'scopes records by theme so a non-default theme query returns its own CSS', function (): void {
	VisualEditorGlobalStyles::create( [
		'theme'    => 'artisanpack-base',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [ [ 'slug' => 'base', 'name' => 'Base', 'color' => '#aaaaaa' ] ],
			],
		],
		'styles'   => [],
	] );

	VisualEditorGlobalStyles::create( [
		'theme'    => 'other-theme',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [ [ 'slug' => 'other', 'name' => 'Other', 'color' => '#bbbbbb' ] ],
			],
		],
		'styles'   => [],
	] );

	$baseCss  = $this->provider->css();
	$otherCss = $this->provider->css( 'other-theme' );

	expect( $baseCss )->toContain( '--wp--preset--color--base' )
		->not->toContain( '--wp--preset--color--other' );
	expect( $otherCss )->toContain( '--wp--preset--color--other' )
		->not->toContain( '--wp--preset--color--base' );
} );
