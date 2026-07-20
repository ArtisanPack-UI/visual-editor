<?php

/**
 * Phase 7 (#558) — cross-phase integration coverage for the Icon Block.
 *
 * Phases 1–6 each ship deep unit tests on their slice. This file is
 * deliberately wider: each scenario stitches together the registration
 * filter, the catalog, the picker endpoints, the admin uploader, the
 * sanitizer, and the block renderer so a regression that only shows up
 * when the pieces are wired together gets caught here.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Icon\IconBlock;
use ArtisanPackUI\VisualEditor\Services\Icon\IconCatalog;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSet;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestUser;

function e2eIconBase(): string
{
	if ( ! isset( test()->iconBase ) ) {
		test()->iconBase = sys_get_temp_dir() . '/icon-e2e-' . bin2hex( random_bytes( 4 ) );
		mkdir( test()->iconBase, 0o755, true );
	}
	return test()->iconBase;
}

function & e2eZipPaths(): array
{
	static $paths = [];
	return $paths;
}

function e2eMakeZip( array $entries ): UploadedFile
{
	$path    = sys_get_temp_dir() . '/icon-e2e-' . bin2hex( random_bytes( 4 ) ) . '.zip';
	$archive = new ZipArchive();
	$status  = $archive->open( $path, ZipArchive::CREATE );
	if ( true !== $status ) {
		throw new RuntimeException( "Failed to create zip {$path}: status {$status}" );
	}
	foreach ( $entries as $name => $contents ) {
		$archive->addFromString( $name, $contents );
	}
	$archive->close();

	$paths   = & e2eZipPaths();
	$paths[] = $path;

	return new UploadedFile( $path, basename( $path ), 'application/zip', null, true );
}

function e2eActAsAdmin(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Icon E2E Admin',
		'email'    => 'icon-e2e+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );
	test()->actingAs( $user );
	return $user;
}

function e2eBindAllowingGate(): void
{
	app()->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate
		{
			public function check( Request $request ): ?Response
			{
				return null;
			}
		};
	} );
}

function e2eRegisterBundledSet( string $prefix, array $icons ): string
{
	$base = e2eIconBase() . '/' . $prefix;
	if ( ! is_dir( $base ) ) {
		mkdir( $base, 0o755, true );
	}
	foreach ( $icons as $name => $svg ) {
		file_put_contents( $base . '/' . $name . '.svg', $svg );
	}
	return $base;
}

function e2eBindCatalogFixture( array $sets, array $icons ): void
{
	app()->instance(
		IconCatalog::class,
		new IconCatalog( static fn (): array => [ 'sets' => $sets, 'icons' => $icons ] ),
	);
}

beforeEach( function (): void {
	$paths = & e2eZipPaths();
	$paths = [];

	// Stand the uploaded-set registry on a temp dir per-test so each
	// scenario starts from a clean slate without touching the others'
	// fixtures.
	$uploadBase = e2eIconBase() . '/uploaded';
	mkdir( $uploadBase, 0o755, true );
	app()->instance( UploadedIconSetRegistry::class, new UploadedIconSetRegistry( $uploadBase ) );
	app()->forgetInstance( \ArtisanPackUI\VisualEditor\Services\Icon\IconSetUploader::class );

	e2eBindAllowingGate();
} );

afterEach( function (): void {
	$rrm = static function ( string $dir ) use ( &$rrm ): void {
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			is_dir( $path ) ? $rrm( $path ) : @unlink( $path );
		}
		@rmdir( $dir );
	};

	if ( isset( test()->iconBase ) && is_dir( test()->iconBase ) ) {
		$rrm( test()->iconBase );
		test()->iconBase = null;
	}

	foreach ( e2eZipPaths() as $zip ) {
		if ( is_file( $zip ) ) {
			@unlink( $zip );
		}
	}
} );

// -------------------------------------------------------------------
// Block registration end-to-end
// -------------------------------------------------------------------

it( 'registers the artisanpack/icon dynamic block end-to-end', function (): void {
	// The service provider wires the dynamic block in `boot()`; reach
	// into the container to confirm the block is bound under the right
	// name and resolves an instance with the production dependencies.
	$block = app( IconBlock::class );

	expect( $block->name() )->toBe( 'artisanpack/icon' );

	// And the block should be able to render a placeholder out of the
	// box (no iconRef, no customSvg) without the IconSvgResolver having
	// to know about any registered sets.
	$html = $block->render( [] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' );
} );

// -------------------------------------------------------------------
// Picker search across multiple registered sets
// -------------------------------------------------------------------

it( 'searches across both bundled and admin-uploaded sets', function (): void {
	e2eActAsAdmin();

	// Pretend an admin previously uploaded a "brand" set.
	app( UploadedIconSetRegistry::class )->register(
		new UploadedIconSet( 'brand', 'Brand', '2026-01-01T00:00:00Z' ),
	);

	// And bind a catalog that surfaces both the FA Free `fas` set and
	// the uploaded `brand` set, with icons coming from each.
	e2eBindCatalogFixture(
		sets: [
			[ 'prefix' => 'fas',   'label' => 'Solid', 'source' => 'solid' ],
			[ 'prefix' => 'brand', 'label' => 'Brand', 'source' => 'uploaded' ],
		],
		icons: [
			[ 'name' => 'house',  'set' => 'fas',   'label' => 'House',  'terms' => [ 'home' ] ],
			[ 'name' => 'rocket', 'set' => 'brand', 'label' => 'Rocket', 'terms' => [ 'launch' ] ],
		],
	);

	$this->getJson( '/visual-editor/api/icons/search?q=launch' )
		->assertOk()
		->assertJsonPath( 'total', 1 )
		->assertJsonPath( 'data.0.name', 'rocket' )
		->assertJsonPath( 'data.0.set', 'brand' );

	$this->getJson( '/visual-editor/api/icons/search' )
		->assertOk()
		->assertJsonPath( 'total', 2 );
} );

// -------------------------------------------------------------------
// SVG sanitization integration (block + admin upload)
// -------------------------------------------------------------------

it( 'sanitizes a malicious customSvg pasted into the block before render', function (): void {
	$block = app( IconBlock::class );

	// The `<a>` tag isn't in the SVG allowlist, so the sanitizer
	// removes that entire subtree (along with its `javascript:` href).
	// The `<path>` here sits at the top level so it survives, while
	// `<script>` and the `onload` handler are both stripped.
	$payload = <<<'SVG'
		<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">
			<script>alert('xss')</script>
			<a href="javascript:alert(1)"></a>
			<path d="M0 0"/>
		</svg>
		SVG;

	$html = $block->render( [ 'customSvg' => $payload ] );

	expect( $html )->toContain( '<path' )
		->and( $html )->not->toContain( '<script' )
		->and( $html )->not->toContain( 'onload' )
		->and( $html )->not->toContain( 'javascript:' );
} );

it( 'sanitizes admin-uploaded SVGs before persisting them to disk', function (): void {
	e2eActAsAdmin();

	$zip = e2eMakeZip( [
		'logo.svg'  => '<svg xmlns="http://www.w3.org/2000/svg" onclick="evil()"><path d="M0 0"/></svg>',
		'evil.svg'  => '<script>alert(1)</script>',
	] );

	$response = $this->post( '/visual-editor/api/admin/icon-sets', [
		'prefix' => 'mybrand',
		'label'  => 'My Brand',
		'zip'    => $zip,
	] );

	$response->assertStatus( Response::HTTP_CREATED );

	// `logo.svg` is sanitized + persisted; `evil.svg` is rejected outright.
	$registry  = app( UploadedIconSetRegistry::class );
	$storedDir = $registry->pathFor( 'mybrand' );

	$logo = file_get_contents( $storedDir . '/logo.svg' );
	expect( $logo )->toContain( '<path' )
		->and( $logo )->not->toContain( 'onclick' );

	expect( file_exists( $storedDir . '/evil.svg' ) )->toBeFalse();
} );

// -------------------------------------------------------------------
// Admin upload happy path
// -------------------------------------------------------------------

it( 'uploads an icon set, registers it, and makes it pickable end-to-end', function (): void {
	e2eActAsAdmin();

	$zip = e2eMakeZip( [
		'arrow.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
	] );

	$response = $this->post( '/visual-editor/api/admin/icon-sets', [
		'prefix' => 'uploaded',
		'label'  => 'Uploaded',
		'zip'    => $zip,
	] );

	$response->assertStatus( Response::HTTP_CREATED );

	expect( app( UploadedIconSetRegistry::class )->has( 'uploaded' ) )->toBeTrue();

	// Wire a catalog that mirrors what `ap.icons.registerIconSets`
	// would expose for the newly-uploaded set, then drive the picker
	// search endpoint and confirm the icon is discoverable.
	e2eBindCatalogFixture(
		sets:  [ [ 'prefix' => 'uploaded', 'label' => 'Uploaded', 'source' => 'uploaded' ] ],
		icons: [ [ 'name' => 'arrow', 'set' => 'uploaded', 'label' => 'Arrow', 'terms' => [ 'pointer' ] ] ],
	);

	$this->getJson( '/visual-editor/api/icons/search?set=uploaded&q=pointer' )
		->assertOk()
		->assertJsonPath( 'total', 1 )
		->assertJsonPath( 'data.0.set', 'uploaded' )
		->assertJsonPath( 'data.0.name', 'arrow' );
} );

// -------------------------------------------------------------------
// Prefix-collision integration
// -------------------------------------------------------------------

it( 'rejects an upload whose prefix collides with an already-registered set', function (): void {
	e2eActAsAdmin();

	// Seed an existing registration so the second upload hits the
	// prefix-collision branch.
	app( UploadedIconSetRegistry::class )->register(
		new UploadedIconSet( 'taken', 'Taken', '2026-01-01T00:00:00Z' ),
	);

	$zip = e2eMakeZip( [
		'thing.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	] );

	$response = $this->post( '/visual-editor/api/admin/icon-sets', [
		'prefix' => 'taken',
		'label'  => 'Taken',
		'zip'    => $zip,
	] );

	$response->assertStatus( Response::HTTP_CONFLICT )
		->assertJsonPath( 'error', 'prefix_collision' )
		->assertJsonPath( 'prefix', 'taken' );
} );

it( 'replaces a previously-registered set on a same-prefix call to register()', function (): void {
	// The registry itself does not throw on collision — it overwrites
	// the existing entry. The collision policy lives one layer up in
	// `IconSetUploader::store()` / the admin controller, exercised in
	// the HTTP test above. Verifying the registry's actual overwrite
	// semantics keeps the layering documented.
	$registry = app( UploadedIconSetRegistry::class );
	$registry->register( new UploadedIconSet( 'dup', 'First', '2026-01-01T00:00:00Z' ) );
	$registry->register( new UploadedIconSet( 'dup', 'Second', '2026-01-02T00:00:00Z' ) );

	$entries = $registry->all();
	expect( $entries )->toHaveCount( 1 )
		->and( $entries[0]->label )->toBe( 'Second' );
} );

// -------------------------------------------------------------------
// Deleted-set fallback: block renders placeholder for a vanished set
// -------------------------------------------------------------------

it( 'renders a placeholder when the referenced icon set has been deleted', function (): void {
	$base = e2eRegisterBundledSet( 'gone', [
		'pin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
	] );

	$resolver = new IconSvgResolver( [ 'gone' => $base ] );
	$block    = new IconBlock( new SvgSanitizer(), $resolver );

	// Sanity: while the set exists, the resolver inlines the SVG.
	$html = $block->render( [ 'iconRef' => [ 'set' => 'gone', 'name' => 'pin' ] ] );
	expect( $html )->toContain( '<svg' )
		->and( $html )->toContain( 'data-icon-set="gone"' );

	// Now tear the set down behind the resolver's back, mimicking an
	// admin DELETE that happened after the post was authored.
	@unlink( $base . '/pin.svg' );
	rmdir( $base );

	$html = $block->render( [ 'iconRef' => [ 'set' => 'gone', 'name' => 'pin' ] ] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->not->toContain( '<svg' )
		// The data-* carriers are kept so a future re-upload of the
		// same prefix re-resolves the icon without losing the
		// reference.
		->and( $html )->toContain( 'data-icon-set="gone"' )
		->and( $html )->toContain( 'data-icon-name="pin"' );
} );

it( 'renders a placeholder when no resolver knows the requested set at all', function (): void {
	$block = new IconBlock( new SvgSanitizer() );

	$html = $block->render( [ 'iconRef' => [ 'set' => 'never-registered', 'name' => 'whatever' ] ] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->not->toContain( '<svg' );
} );

// -------------------------------------------------------------------
// Decorative + link a11y combinations
// -------------------------------------------------------------------

it( 'wraps a non-decorative linked icon with the rendered anchor', function (): void {
	$base     = e2eRegisterBundledSet( 'fab', [
		'github' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
	] );
	$resolver = new IconSvgResolver( [ 'fab' => $base ] );
	$block    = new IconBlock( new SvgSanitizer(), $resolver );

	$html = $block->render( [
		'iconRef'    => [ 'set' => 'fab', 'name' => 'github' ],
		'link'       => 'https://example.com',
		'linkTarget' => '_blank',
		'linkRel'    => 'nofollow',
		'ariaLabel'  => 'Visit on GitHub',
	] );

	expect( $html )->toContain( '<a href="https://example.com"' )
		->and( $html )->toContain( 'target="_blank"' )
		->and( $html )->toContain( 'noopener' )
		->and( $html )->toContain( 'noreferrer' )
		->and( $html )->toContain( 'nofollow' )
		->and( $html )->toContain( 'aria-label="Visit on GitHub"' );
} );

it( 'still emits aria-hidden on a decorative linked icon — the editor surfaces the conflict warning', function (): void {
	$base     = e2eRegisterBundledSet( 'fab', [
		'github' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
	] );
	$resolver = new IconSvgResolver( [ 'fab' => $base ] );
	$block    = new IconBlock( new SvgSanitizer(), $resolver );

	// Decorative + linked + no aria-label is a known a11y conflict —
	// `edit.tsx` surfaces an inline warning so the author fixes it
	// before save. The server still emits exactly what was saved so
	// the editor warning doesn't go away post-render. The `<a>` has
	// no accessible name in this scenario — which is precisely what
	// the warning exists to call out.
	$html = $block->render( [
		'iconRef'      => [ 'set' => 'fab', 'name' => 'github' ],
		'link'         => 'https://example.com',
		'isDecorative' => true,
	] );

	expect( $html )->toContain( '<a href="https://example.com"' )
		->and( $html )->toContain( 'aria-hidden="true"' )
		->and( $html )->not->toContain( 'aria-label=' );
} );

it( 'lets a decorative + linked combo recover when an aria-label is supplied', function (): void {
	$base     = e2eRegisterBundledSet( 'fab', [
		'github' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
	] );
	$resolver = new IconSvgResolver( [ 'fab' => $base ] );
	$block    = new IconBlock( new SvgSanitizer(), $resolver );

	$html = $block->render( [
		'iconRef'      => [ 'set' => 'fab', 'name' => 'github' ],
		'link'         => 'https://example.com',
		'isDecorative' => true,
		'ariaLabel'    => 'Visit on GitHub',
	] );

	// `aria-hidden` stays on the body span (the SVG is decorative), but
	// the supplied ariaLabel is promoted onto the `<a>` so the link
	// itself has an accessible name. Without that promotion the link
	// would still read as unlabeled to screen readers — which is the
	// scenario the editor warning is supposed to prevent.
	expect( $html )->toMatch( '/<a href="https:\/\/example\.com"[^>]*aria-label="Visit on GitHub"/' )
		->and( $html )->toContain( 'aria-hidden="true"' );
} );

// -------------------------------------------------------------------
// `ap.icons.registerIconSets` filter wires bundled sets at boot
// -------------------------------------------------------------------

it( 'collects bundled FA Free sets via the ap.icons.registerIconSets filter', function (): void {
	$registrationClass = 'ArtisanPackUI\\Icons\\Registries\\IconSetRegistration';

	// The icons package is a `suggest`-level dependency. When it's not
	// installed in the test env, the service provider's class_exists()
	// gate cleanly skips registration — so do the same here.
	if ( ! class_exists( $registrationClass ) || ! function_exists( 'applyFilters' ) ) {
		test()->markTestSkipped( 'artisanpack-ui/icons is not installed in this test environment' );
	}

	// The FA Free SVGs are mirrored into `resources/icons/font-awesome/`
	// by `scripts/sync-fa-icons.mjs` as the `npm run build` prebuild
	// step. CI's PHP job doesn't run that step (the SVGs ship only in
	// the published package or after `npm run build`), so
	// `FontAwesomeFreeIconSets::discover()` returns an empty array
	// there and the filter callback no-ops. Skip in that case rather
	// than failing on a sync that lives downstream of `composer
	// install`.
	$faFreeDir = __DIR__ . '/../../../resources/icons/font-awesome';
	if ( ! is_dir( $faFreeDir . '/fas' ) ) {
		test()->markTestSkipped( 'FA Free SVGs not synced — run `npm run sync:fa-icons` first' );
	}

	$registry = applyFilters( 'ap.icons.registerIconSets', new $registrationClass() );

	$sets = $registry->getSets();

	expect( $sets )->toHaveKeys( [ 'fas', 'far', 'fab' ] );
} );
