<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontFileWriteException;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontInstallationException;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;
use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A self-hostable fake provider whose behavior is driven by an options array so
 * a single helper covers the happy path, refusals, and mid-install failure.
 *
 * @param  array<string, mixed>  $options
 */
function fakeInstallerFontProvider( array $options = [] ): FontProvider
{
	return new class( $options ) implements FontProvider {
		/** @var array<int, string> */
		public array $fetched = [];

		/**
		 * @param  array<string, mixed>  $options
		 */
		public function __construct( private array $options )
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
			return $this->options['selfHostable'] ?? true;
		}

		public function searchCatalog( string $query, int $page = 1 ): array
		{
			return [ 'families' => [], 'page' => $page, 'has_more' => false ];
		}

		public function getFamily( string $slug ): ?array
		{
			if ( true === ( $this->options['unknownFamily'] ?? false ) ) {
				return null;
			}

			return [
				'slug'        => $slug,
				'family'      => $this->options['family'] ?? (string) Str::of( $slug )->headline(),
				'is_variable' => $this->options['is_variable'] ?? false,
				'license'     => $this->options['license'] ?? null,
				'faces'       => [ [ 'weight' => 400, 'style' => 'normal' ] ],
				'axes'        => $this->options['axes'] ?? [],
			];
		}

		public function fetchFace( string $slug, string $weight, string $style ): string
		{
			$this->fetched[] = $weight . ':' . $style;

			if ( ( $this->options['failOnWeight'] ?? null ) === $weight ) {
				throw new FontProviderException( 'Simulated fetch failure.' );
			}

			return 'wOF2' . $weight . $style;
		}
	};
}

beforeEach( function (): void {
	Storage::fake( 'public' );
} );

it( 'installs a font end to end: files, rows, and bundle', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake', 'family' => 'Inter' ] ) );

	$font = app( FontInstaller::class )->install( 'fake', 'inter', [
		[ 'weight' => 400, 'style' => 'normal' ],
		[ 'weight' => 700, 'style' => 'normal' ],
	] );

	expect( $font->provider )->toBe( 'fake' )
		->and( $font->family )->toBe( 'Inter' )
		->and( $font->installed_at )->not->toBeNull()
		->and( $font->faces )->toHaveCount( 2 );

	Storage::disk( 'public' )->assertExists( 'visual-editor/fonts/fake/inter/400-normal.woff2' );
	Storage::disk( 'public' )->assertExists( 'visual-editor/fonts/fake/inter/700-normal.woff2' );
	Storage::disk( 'public' )->assertExists( 'visual-editor/fonts/fonts.css' );

	expect( app( FontsCssGenerator::class )->read() )->toContain( 'font-family: "Inter"' );
} );

it( 'de-duplicates requested faces', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	$font = app( FontInstaller::class )->install( 'fake', 'inter', [
		[ 'weight' => 400, 'style' => 'normal' ],
		[ 'weight' => 400, 'style' => 'normal' ],
	] );

	expect( $font->faces )->toHaveCount( 1 );
} );

it( 'treats a whitespace-padded provider key as the same font', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	$installer = app( FontInstaller::class );
	$installer->install( ' fake ', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );
	$font = $installer->install( 'fake', 'inter', [ [ 'weight' => 700, 'style' => 'normal' ] ] );

	expect( Font::query()->count() )->toBe( 1 )
		->and( $font->provider )->toBe( 'fake' )
		->and( $font->faces()->count() )->toBe( 2 );
} );

it( 'throws for an unregistered provider', function (): void {
	expect( fn () => app( FontInstaller::class )->install( 'nope', 'inter', [ [ 'weight' => 400 ] ] ) )
		->toThrow( FontInstallationException::class );
} );

it( 'refuses a provider that is not self-hostable', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'cdn', 'selfHostable' => false ] ) );

	expect( fn () => app( FontInstaller::class )->install( 'cdn', 'inter', [ [ 'weight' => 400 ] ] ) )
		->toThrow( FontInstallationException::class );
} );

it( 'throws for an unknown family', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake', 'unknownFamily' => true ] ) );

	expect( fn () => app( FontInstaller::class )->install( 'fake', 'missing', [ [ 'weight' => 400 ] ] ) )
		->toThrow( FontInstallationException::class );
} );

it( 'throws when no valid faces are selected', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	expect( fn () => app( FontInstaller::class )->install( 'fake', 'inter', [ [ 'style' => 'normal' ] ] ) )
		->toThrow( FontInstallationException::class );
} );

it( 'rolls back files and rows when a face fetch fails midway', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake', 'failOnWeight' => '700' ] ) );

	expect( fn () => app( FontInstaller::class )->install( 'fake', 'inter', [
		[ 'weight' => 400, 'style' => 'normal' ],
		[ 'weight' => 700, 'style' => 'normal' ],
	] ) )->toThrow( FontProviderException::class );

	expect( Font::query()->count() )->toBe( 0 )
		->and( FontFace::query()->count() )->toBe( 0 );

	// The 400 face was written before the 700 fetch failed; rollback removes it.
	Storage::disk( 'public' )->assertMissing( 'visual-editor/fonts/fake/inter/400-normal.woff2' );
} );

it( 'merges new faces into an existing font on re-install', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	$installer = app( FontInstaller::class );
	$installer->install( 'fake', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );
	$font = $installer->install( 'fake', 'inter', [ [ 'weight' => 700, 'style' => 'normal' ] ] );

	expect( Font::query()->count() )->toBe( 1 )
		->and( $font->faces()->count() )->toBe( 2 );
} );

it( 'stores variable axis metadata on installed faces', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [
		'key'         => 'fake',
		'is_variable' => true,
		'axes'        => [ 'wght' => [ 'min' => 100, 'max' => 900, 'default' => 400 ] ],
	] ) );

	$font = app( FontInstaller::class )->install( 'fake', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );

	expect( $font->is_variable )->toBeTrue()
		->and( $font->faces->first()->axes )->toHaveKey( 'wght' );
} );

it( 'still reports a successful install when bundle regeneration fails', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	// A generator whose post-commit rebuild fails must not undo the persisted
	// install; the atomic write keeps the previous bundle and the failure is
	// logged rather than surfaced as an install failure.
	app()->instance( FontsCssGenerator::class, new class extends FontsCssGenerator {
		public function generate(): string
		{
			throw new FontFileWriteException( 'Simulated regeneration failure.' );
		}
	} );

	Log::spy();

	$font = app( FontInstaller::class )->install( 'fake', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );

	expect( $font->exists )->toBeTrue()
		->and( Font::query()->count() )->toBe( 1 )
		->and( FontFace::query()->count() )->toBe( 1 );

	Log::shouldHaveReceived( 'error' )->once();
} );

it( 'uninstalls a font: removes rows, files, and rebuilds the bundle', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	$installer = app( FontInstaller::class );
	$font      = $installer->install( 'fake', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );
	$path      = $font->faces->first()->path;

	$installer->uninstall( $font );

	expect( Font::query()->count() )->toBe( 0 )
		->and( FontFace::query()->count() )->toBe( 0 );

	Storage::disk( 'public' )->assertMissing( $path );
} );

it( 'deletes face files from the disk recorded at install, not the current config disk', function (): void {
	Storage::fake( 'other' );
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	$installer = app( FontInstaller::class );
	$font      = $installer->install( 'fake', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );
	$path      = $font->faces->first()->path;

	expect( $font->faces->first()->disk )->toBe( 'public' );

	// A same-named file on a different disk must survive; only the face's own
	// recorded disk is touched even after the configured disk is repointed.
	Storage::disk( 'other' )->put( $path, 'decoy' );
	config( [ 'artisanpack.visual-editor.fonts.disk' => 'other' ] );

	$installer->uninstall( $font );

	Storage::disk( 'public' )->assertMissing( $path );
	Storage::disk( 'other' )->assertExists( $path );
} );

it( 'bulk uninstalls multiple fonts by model or id and rebuilds once', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	$installer = app( FontInstaller::class );
	$first     = $installer->install( 'fake', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );
	$second    = $installer->install( 'fake', 'roboto', [ [ 'weight' => 400, 'style' => 'normal' ] ] );

	$removed = $installer->bulkUninstall( [ $first->id, $second ] );

	expect( $removed )->toBe( 2 )
		->and( Font::query()->count() )->toBe( 0 )
		->and( FontFace::query()->count() )->toBe( 0 );
} );

it( 'skips unknown ids during bulk uninstall', function (): void {
	app( FontSourceRegistry::class )->register( fakeInstallerFontProvider( [ 'key' => 'fake' ] ) );

	$installer = app( FontInstaller::class );
	$font      = $installer->install( 'fake', 'inter', [ [ 'weight' => 400, 'style' => 'normal' ] ] );

	$removed = $installer->bulkUninstall( [ $font->id, 99999 ] );

	expect( $removed )->toBe( 1 )
		->and( Font::query()->count() )->toBe( 0 );
} );
