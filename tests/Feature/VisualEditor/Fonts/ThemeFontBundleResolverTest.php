<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\ThemeFontBundle;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use ArtisanPackUI\VisualEditor\Fonts\Services\ThemeFontBundleResolver;
use Illuminate\Support\Facades\Storage;

/**
 * A self-hostable fake provider that records the faces it is asked to fetch, so
 * a test can prove whether a resolve reached out to the network. `failAll`
 * makes every fetch throw to exercise the provider-failure branch.
 *
 * @param  array<string, mixed>  $options
 */
function fakeBundleFontProvider( array $options = [] ): FontProvider
{
	return new class( $options ) implements FontProvider {
		/** @var array<int, string> */
		public array $fetched = [];

		/**
		 * @param  array<string, mixed>  $options
		 */
		public function __construct( public array $options )
		{
		}

		public function key(): string
		{
			return $this->options['key'] ?? 'fake';
		}

		public function label(): string
		{
			return 'Fake Fonts';
		}

		public function isSelfHostable(): bool
		{
			return true;
		}

		public function searchCatalog( string $query, int $page = 1 ): array
		{
			return [ 'families' => [], 'page' => $page, 'has_more' => false ];
		}

		public function getFamily( string $slug ): ?array
		{
			return [
				'slug'        => $slug,
				'family'      => $this->options['family'] ?? 'Inter',
				'is_variable' => false,
				'license'     => null,
				'faces'       => [ [ 'weight' => 400, 'style' => 'normal' ] ],
				'axes'        => [],
			];
		}

		public function fetchFace( string $slug, string $weight, string $style ): string
		{
			$this->fetched[] = $weight . ':' . $style;

			if ( true === ( $this->options['failAll'] ?? false ) ) {
				throw new \RuntimeException( 'Simulated provider failure.' );
			}

			return 'wOF2' . $weight . $style;
		}
	};
}

beforeEach( function (): void {
	Storage::fake( 'public' );
} );

it( 'parses and normalizes the theme.json fonts block', function (): void {
	$declarations = app( ThemeFontBundleResolver::class )->parse( [
		'fonts' => [
			[
				'provider' => 'Google',
				'family'   => 'Open Sans',
				'faces'    => [
					[ 'weight' => 400, 'style' => 'Normal' ],
					[ 'weight' => 400, 'style' => 'normal' ],
					[ 'weight' => 700, 'style' => 'italic' ],
				],
			],
			[ 'provider' => 'bunny', 'family' => 'Roboto' ],
			[ 'provider' => 'google' ],
			[ 'family' => 'No Provider' ],
			'not-an-array',
		],
	] );

	expect( $declarations )->toHaveCount( 2 );

	expect( $declarations[0] )->toMatchArray( [
		'provider' => 'google',
		'family'   => 'Open Sans',
		'slug'     => 'open-sans',
	] );
	expect( $declarations[0]['faces'] )->toEqual( [
		[ 'weight' => 400, 'style' => 'normal' ],
		[ 'weight' => 700, 'style' => 'italic' ],
	] );

	expect( $declarations[1] )->toMatchArray( [
		'provider' => 'bunny',
		'family'   => 'Roboto',
		'slug'     => 'roboto',
	] );
	expect( $declarations[1]['faces'] )->toEqual( [ [ 'weight' => 400, 'style' => 'normal' ] ] );
} );

it( 'clamps out-of-range face weights to the 1–1000 CSS range', function (): void {
	// A malformed or adversarial manifest weight would otherwise flow into the
	// unsignedSmallInteger weight column and fail the insert on strict drivers.
	$declarations = app( ThemeFontBundleResolver::class )->parse( [
		'fonts' => [
			[
				'provider' => 'google',
				'family'   => 'Open Sans',
				'faces'    => [
					[ 'weight' => 100000, 'style' => 'normal' ],
					[ 'weight' => -5, 'style' => 'italic' ],
					[ 'weight' => 400, 'style' => 'normal' ],
				],
			],
		],
	] );

	expect( $declarations[0]['faces'] )->toEqual( [
		[ 'weight' => 1000, 'style' => 'normal' ],
		[ 'weight' => 1, 'style' => 'italic' ],
		[ 'weight' => 400, 'style' => 'normal' ],
	] );
} );

it( 'returns no declarations when the manifest has no fonts block', function (): void {
	expect( app( ThemeFontBundleResolver::class )->parse( [] ) )->toBe( [] );
	expect( app( ThemeFontBundleResolver::class )->parse( [ 'fonts' => 'nope' ] ) )->toBe( [] );
} );

it( 'links an already-installed font without fetching from the provider', function (): void {
	$provider = fakeBundleFontProvider( [ 'key' => 'fake', 'family' => 'Inter' ] );
	app( FontSourceRegistry::class )->register( $provider );

	$font = Font::factory()->create( [
		'provider' => 'fake',
		'family'   => 'Inter',
		'slug'     => 'inter',
	] );

	$result = app( ThemeFontBundleResolver::class )->resolve( 'my-theme', [
		'fonts' => [
			[
				'provider' => 'fake',
				'family'   => 'Inter',
				'faces'    => [ [ 'weight' => 400, 'style' => 'normal' ], [ 'weight' => 700, 'style' => 'normal' ] ],
			],
		],
	] );

	expect( $provider->fetched )->toBe( [] );
	expect( $result['linked'] )->toHaveCount( 1 );
	expect( $result['installed'] )->toHaveCount( 0 );
	expect( Font::query()->count() )->toBe( 1 );

	$bundle = ThemeFontBundle::query()->where( 'theme_slug', 'my-theme' )->sole();
	expect( $bundle->font_id )->toBe( $font->id );
	expect( $bundle->faces )->toEqual( [
		[ 'weight' => 400, 'style' => 'normal' ],
		[ 'weight' => 700, 'style' => 'normal' ],
	] );
} );

it( 'installs a missing font when network installs are confirmed', function (): void {
	$provider = fakeBundleFontProvider( [ 'key' => 'fake', 'family' => 'Inter' ] );
	app( FontSourceRegistry::class )->register( $provider );

	$result = app( ThemeFontBundleResolver::class )->resolve( 'my-theme', [
		'fonts' => [
			[ 'provider' => 'fake', 'family' => 'Inter', 'faces' => [ [ 'weight' => 400, 'style' => 'normal' ] ] ],
		],
	], installMissing: true );

	expect( $provider->fetched )->toBe( [ '400:normal' ] );
	expect( $result['installed'] )->toHaveCount( 1 );
	expect( $result['linked'] )->toHaveCount( 0 );

	$font = Font::query()->where( 'provider', 'fake' )->where( 'slug', 'inter' )->sole();
	expect( $font->faces )->toHaveCount( 1 );

	$bundle = ThemeFontBundle::query()->where( 'theme_slug', 'my-theme' )->sole();
	expect( $bundle->font_id )->toBe( $font->id );
} );

it( 'skips a missing font when installs are not confirmed', function (): void {
	$provider = fakeBundleFontProvider( [ 'key' => 'fake' ] );
	app( FontSourceRegistry::class )->register( $provider );

	$result = app( ThemeFontBundleResolver::class )->resolve( 'my-theme', [
		'fonts' => [
			[ 'provider' => 'fake', 'family' => 'Inter', 'faces' => [ [ 'weight' => 400, 'style' => 'normal' ] ] ],
		],
	] );

	expect( $provider->fetched )->toBe( [] );
	expect( $result['skipped'] )->toHaveCount( 1 );
	expect( Font::query()->count() )->toBe( 0 );
	expect( ThemeFontBundle::query()->count() )->toBe( 0 );
} );

it( 'records a provider failure and still links the other fonts', function (): void {
	app( FontSourceRegistry::class )->register( fakeBundleFontProvider( [ 'key' => 'boom', 'failAll' => true ] ) );

	$present = Font::factory()->create( [
		'provider' => 'fake',
		'family'   => 'Inter',
		'slug'     => 'inter',
	] );
	app( FontSourceRegistry::class )->register( fakeBundleFontProvider( [ 'key' => 'fake', 'family' => 'Inter' ] ) );

	$result = app( ThemeFontBundleResolver::class )->resolve( 'my-theme', [
		'fonts' => [
			[ 'provider' => 'boom', 'family' => 'Broken', 'faces' => [ [ 'weight' => 400, 'style' => 'normal' ] ] ],
			[ 'provider' => 'fake', 'family' => 'Inter', 'faces' => [ [ 'weight' => 400, 'style' => 'normal' ] ] ],
		],
	], installMissing: true );

	expect( $result['failed'] )->toHaveCount( 1 );
	expect( $result['failed'][0]['declaration']['provider'] )->toBe( 'boom' );
	expect( $result['linked'] )->toHaveCount( 1 );

	expect( Font::query()->where( 'provider', 'boom' )->exists() )->toBeFalse();
	$bundle = ThemeFontBundle::query()->sole();
	expect( $bundle->font_id )->toBe( $present->id );
} );

it( 're-syncs bundles on re-activation, dropping removed declarations', function (): void {
	app( FontSourceRegistry::class )->register( fakeBundleFontProvider( [ 'key' => 'fake' ] ) );

	$inter = Font::factory()->create( [ 'provider' => 'fake', 'family' => 'Inter', 'slug' => 'inter' ] );
	$roboto = Font::factory()->create( [ 'provider' => 'fake', 'family' => 'Roboto', 'slug' => 'roboto' ] );

	$resolver = app( ThemeFontBundleResolver::class );

	$resolver->resolve( 'my-theme', [
		'fonts' => [
			[ 'provider' => 'fake', 'family' => 'Inter' ],
			[ 'provider' => 'fake', 'family' => 'Roboto' ],
		],
	] );

	expect( ThemeFontBundle::query()->where( 'theme_slug', 'my-theme' )->count() )->toBe( 2 );

	$resolver->resolve( 'my-theme', [
		'fonts' => [
			[ 'provider' => 'fake', 'family' => 'Inter' ],
		],
	] );

	$bundles = ThemeFontBundle::query()->where( 'theme_slug', 'my-theme' )->get();
	expect( $bundles )->toHaveCount( 1 );
	expect( $bundles->first()->font_id )->toBe( $inter->id );

	// The dropped declaration's library font stays installed.
	expect( Font::query()->whereKey( $roboto->id )->exists() )->toBeTrue();
} );

it( 'forgetTheme removes the theme bundles but leaves the library and other themes intact', function (): void {
	$font = Font::factory()->create( [ 'provider' => 'fake', 'family' => 'Inter', 'slug' => 'inter' ] );

	ThemeFontBundle::factory()->create( [ 'theme_slug' => 'theme-a', 'font_id' => $font->id ] );
	ThemeFontBundle::factory()->create( [ 'theme_slug' => 'theme-b', 'font_id' => $font->id ] );

	$removed = app( ThemeFontBundleResolver::class )->forgetTheme( 'theme-a' );

	expect( $removed )->toBe( 1 );
	expect( ThemeFontBundle::query()->where( 'theme_slug', 'theme-a' )->exists() )->toBeFalse();
	expect( ThemeFontBundle::query()->where( 'theme_slug', 'theme-b' )->exists() )->toBeTrue();
	expect( Font::query()->whereKey( $font->id )->exists() )->toBeTrue();
} );

it( 'plans present and missing declarations without fetching', function (): void {
	Font::factory()->create( [ 'provider' => 'fake', 'family' => 'Inter', 'slug' => 'inter' ] );

	$plan = app( ThemeFontBundleResolver::class )->plan( [
		'fonts' => [
			[ 'provider' => 'fake', 'family' => 'Inter' ],
			[ 'provider' => 'fake', 'family' => 'Roboto' ],
		],
	] );

	expect( $plan['present'] )->toHaveCount( 1 );
	expect( $plan['present'][0]['slug'] )->toBe( 'inter' );
	expect( $plan['missing'] )->toHaveCount( 1 );
	expect( $plan['missing'][0]['slug'] )->toBe( 'roboto' );
} );

it( 'resolves the bundle when the theme.activated action fires', function (): void {
	$font = Font::factory()->create( [ 'provider' => 'fake', 'family' => 'Inter', 'slug' => 'inter' ] );

	doAction( 'ap.cmsFramework.theme.activated', 'hooked-theme', [
		'fonts' => [
			[ 'provider' => 'fake', 'family' => 'Inter', 'faces' => [ [ 'weight' => 400, 'style' => 'normal' ] ] ],
		],
	] );

	$bundle = ThemeFontBundle::query()->where( 'theme_slug', 'hooked-theme' )->sole();
	expect( $bundle->font_id )->toBe( $font->id );
} );
