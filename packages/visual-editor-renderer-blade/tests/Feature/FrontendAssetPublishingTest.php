<?php

/**
 * Covers fingerprinted static delivery of the renderer's assets (#1033).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendAssetManifest;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendAssetPublisher;
use ArtisanPackUI\VisualEditorRendererBlade\Support\FrontendAssets;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function publishedAssetsDirectory(): string
{
	return public_path( FrontendAssets::PUBLIC_DIRECTORY );
}

function publishedManifest(): array
{
	return json_decode( (string) file_get_contents( FrontendAssetManifest::path() ), true );
}

function renderBlocksStyles( string $attributes = '' ): string
{
	return Blade::render( '<x-ve-blocks-styles ' . $attributes . ' />' );
}

beforeEach( function (): void {
	File::deleteDirectory( publishedAssetsDirectory() );
} );

afterEach( function (): void {
	File::deleteDirectory( publishedAssetsDirectory() );
} );

it( 'publishes every asset under a content-hashed name with a manifest', function (): void {
	$this->artisan( 'visual-editor:publish-assets' )->assertSuccessful();

	$files = publishedManifest()['files'];

	expect( array_keys( $files ) )->toBe( array_keys( FrontendAssets::sources() ) );

	foreach ( $files as $path => $fingerprinted ) {
		expect( $fingerprinted )->toBe( FrontendAssets::fingerprintedPath( $path, FrontendAssets::hash( FrontendAssets::sources()[ $path ] ) ) )
			->and( file_get_contents( publishedAssetsDirectory() . '/' . $fingerprinted ) )
			->toBe( file_get_contents( FrontendAssets::sources()[ $path ] ) );
	}
} );

it( 'writes Apache rules that cache only the hashed files', function (): void {
	$this->artisan( 'visual-editor:publish-assets' );

	expect( file_get_contents( publishedAssetsDirectory() . '/.htaccess' ) )
		->toContain( 'public, max-age=31536000, immutable' );

	preg_match( '/FilesMatch "([^"]+)"/', (string) file_get_contents( publishedAssetsDirectory() . '/.htaccess' ), $matches );

	$pattern = '/' . $matches[1] . '/';

	expect( preg_match( $pattern, 'tabs.3f2a9c1e77ab.css' ) )->toBe( 1 )
		->and( preg_match( $pattern, '7f78ab4ce3025bcf16b8.css' ) )->toBe( 1 )
		->and( preg_match( $pattern, 'tabs.css' ) )->toBe( 0 )
		->and( preg_match( $pattern, 'manifest.json' ) )->toBe( 0 );
} );

it( 'links to the plain paths until the assets are published', function (): void {
	expect( renderBlocksStyles() )
		->toContain( 'href="/vendor/visual-editor-renderer-blade/style.css"' )
		->toContain( 'href="/vendor/visual-editor-renderer-blade/frontend/tabs.css"' )
		->toContain( 'src="/vendor/visual-editor-renderer-blade/frontend/interactivity.js"' );
} );

it( 'links to the fingerprinted files once published', function (): void {
	$this->artisan( 'visual-editor:publish-assets' );

	$files = publishedManifest()['files'];

	expect( renderBlocksStyles() )
		->toContain( 'href="/vendor/visual-editor-renderer-blade/' . $files['style.css'] . '"' )
		->toContain( 'href="/vendor/visual-editor-renderer-blade/' . $files['frontend/tabs.css'] . '"' )
		->toContain( 'src="/vendor/visual-editor-renderer-blade/' . $files['frontend/interactivity.js'] . '"' );
} );

it( 'keeps a custom asset base on the plain paths, since a CDN may not hold the hashed files', function (): void {
	$this->artisan( 'visual-editor:publish-assets' );

	expect( renderBlocksStyles( 'asset-base="https://cdn.example.com/ve"' ) )
		->toContain( 'href="https://cdn.example.com/ve/frontend/tabs.css"' );
} );

it( 'ignores a manifest left over from another package version', function (): void {
	$this->artisan( 'visual-editor:publish-assets' );

	$manifest              = publishedManifest();
	$manifest['signature'] = 'from-an-older-release';

	file_put_contents( FrontendAssetManifest::path(), json_encode( $manifest ) );

	app()->forgetScopedInstances();

	expect( renderBlocksStyles() )->toContain( 'href="/vendor/visual-editor-renderer-blade/frontend/tabs.css"' );
} );

it( 'ignores a manifest once a source changes without changing size', function (): void {
	$this->artisan( 'visual-editor:publish-assets' );

	$source   = FrontendAssets::sources()['frontend/tabs.css'];
	$modified = (int) filemtime( $source );

	try {
		touch( $source, $modified + 60 );
		clearstatcache();

		app()->forgetScopedInstances();

		expect( renderBlocksStyles() )->toContain( 'href="/vendor/visual-editor-renderer-blade/frontend/tabs.css"' );
	} finally {
		touch( $source, $modified );
		clearstatcache();
	}
} );

it( 'ignores a corrupt manifest', function (): void {
	File::ensureDirectoryExists( publishedAssetsDirectory() );
	file_put_contents( FrontendAssetManifest::path(), 'not json' );

	expect( renderBlocksStyles() )->toContain( 'href="/vendor/visual-editor-renderer-blade/style.css"' );
} );

it( 'keeps the previous publish for cached pages and removes older ones', function (): void {
	$directory = publishedAssetsDirectory() . '/frontend';

	File::ensureDirectoryExists( $directory );
	file_put_contents( $directory . '/tabs.aaaaaaaaaaaa.css', 'older' );
	file_put_contents( $directory . '/tabs.bbbbbbbbbbbb.css', 'previous' );
	file_put_contents( FrontendAssetManifest::path(), json_encode( [
		'signature' => 'previous',
		'files'     => [ 'frontend/tabs.css' => 'frontend/tabs.bbbbbbbbbbbb.css' ],
	] ) );

	app( FrontendAssetPublisher::class )->publish();

	expect( $directory . '/tabs.bbbbbbbbbbbb.css' )->toBeFile()
		->and( $directory . '/tabs.aaaaaaaaaaaa.css' )->not->toBeFile();
} );

/**
 * Fires the event Laravel raises after `composer` runs `package:discover`.
 */
function finishPackageDiscovery(): void
{
	Event::dispatch( new CommandFinished( 'package:discover', new ArrayInput( [] ), new BufferedOutput(), 0 ) );
}

it( 'refreshes published assets after composer runs package discovery', function (): void {
	$this->artisan( 'visual-editor:publish-assets' );

	File::delete( FrontendAssetManifest::path() );
	file_put_contents( FrontendAssetManifest::path(), json_encode( [ 'signature' => 'old', 'files' => [] ] ) );

	finishPackageDiscovery();

	expect( publishedManifest()['signature'] )->toBe( FrontendAssets::signature() );
} );

it( 'writes nothing to public on package discovery for a host that never published', function (): void {
	finishPackageDiscovery();

	expect( publishedAssetsDirectory() )->not->toBeDirectory();
} );

it( 'serves a fingerprinted request as immutable when the hash is current', function (): void {
	$source = FrontendAssets::sources()['frontend/tabs.css'];
	$path   = FrontendAssets::fingerprintedPath( 'frontend/tabs.css', FrontendAssets::hash( $source ) );

	$response = $this->get( '/vendor/visual-editor-renderer-blade/' . $path );

	$response->assertOk();

	expect( $response->headers->get( 'Cache-Control' ) )
		->toContain( 'immutable' )
		->toContain( 'max-age=31536000' )
		->and( $response->streamedContent() )->toBe( file_get_contents( $source ) );
} );

it( 'serves a stale fingerprint for revalidation rather than a 404', function (): void {
	$response = $this->get( '/vendor/visual-editor-renderer-blade/frontend/tabs.000000000000.css' );

	$response->assertOk();

	expect( $response->headers->get( 'Cache-Control' ) )
		->not->toContain( 'immutable' )
		->toContain( 'must-revalidate' );
} );

it( 'keeps plain paths on revalidation', function (): void {
	$response = $this->get( '/vendor/visual-editor-renderer-blade/style.css' );

	expect( $response->headers->get( 'Cache-Control' ) )
		->toContain( 'must-revalidate' )
		->not->toContain( 'immutable' );
} );

it( 'still rejects traversal through a fingerprinted path', function (): void {
	$this->get( '/vendor/visual-editor-renderer-blade/frontend/../../../composer.000000000000.css' )->assertNotFound();
} );
