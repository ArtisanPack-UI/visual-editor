<?php

/**
 * Publish Views Command Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Commands
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\File;

/**
 * Get the publish destination base path.
 *
 * @return string
 */
function getPublishBase(): string
{
	return resource_path( 'views/vendor/visual-editor' );
}

beforeEach( function (): void {
	if ( File::isDirectory( getPublishBase() ) ) {
		File::deleteDirectory( getPublishBase() );
	}
} );

afterEach( function (): void {
	if ( File::isDirectory( getPublishBase() ) ) {
		File::deleteDirectory( getPublishBase() );
	}
} );

test( 've:publish fails without --views flag', function (): void {
	$this->artisan( 've:publish' )
		->assertFailed();
} );

test( 've:publish --views publishes all views', function (): void {
	$this->artisan( 've:publish', [ '--views' => true ] )
		->assertSuccessful();

	$base = getPublishBase();

	expect( File::isDirectory( $base . '/livewire/site-editor' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/hub.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/template-listing.blade.php' ) )->toBeTrue();
} );

test( 've:publish --views --tag=site-editor publishes only site editor views', function (): void {
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'site-editor' ] )
		->assertSuccessful();

	$base = getPublishBase();

	expect( File::exists( $base . '/livewire/site-editor/hub.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/layouts/site-editor.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/template-listing.blade.php' ) )->toBeFalse();
} );

test( 've:publish --views --tag=listings publishes only listing views', function (): void {
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'listings' ] )
		->assertSuccessful();

	$base = getPublishBase();

	expect( File::exists( $base . '/livewire/site-editor/template-listing.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/part-listing.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/pattern-listing.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/hub.blade.php' ) )->toBeFalse();
} );

test( 've:publish --views --tag=editors publishes only editor views', function (): void {
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'editors' ] )
		->assertSuccessful();

	$base = getPublishBase();

	expect( File::exists( $base . '/livewire/site-editor/part-editor.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/pattern-editor.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/hub.blade.php' ) )->toBeFalse();
} );

test( 've:publish --views --tag=styles publishes only styles views', function (): void {
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'styles' ] )
		->assertSuccessful();

	$base = getPublishBase();

	expect( File::exists( $base . '/livewire/site-editor/global-styles-page.blade.php' ) )->toBeTrue();
	expect( File::exists( $base . '/livewire/site-editor/hub.blade.php' ) )->toBeFalse();
} );

test( 've:publish fails with unknown tag', function (): void {
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'nonexistent' ] )
		->assertFailed();
} );

test( 've:publish does not overwrite existing files without --force', function (): void {
	$base = getPublishBase();

	// Publish first time.
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'site-editor' ] )
		->assertSuccessful();

	$hubPath = $base . '/livewire/site-editor/hub.blade.php';

	expect( File::exists( $hubPath ) )->toBeTrue();

	// Write custom content.
	File::put( $hubPath, '<!-- custom -->' );

	// Publish again without --force.
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'site-editor' ] )
		->assertSuccessful();

	// Custom content should be preserved.
	expect( File::get( $hubPath ) )->toBe( '<!-- custom -->' );
} );

test( 've:publish --force overwrites existing files', function (): void {
	$base = getPublishBase();

	// Publish first time.
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'site-editor' ] )
		->assertSuccessful();

	$hubPath = $base . '/livewire/site-editor/hub.blade.php';

	// Write custom content.
	File::put( $hubPath, '<!-- custom -->' );

	// Publish again with --force.
	$this->artisan( 've:publish', [ '--views' => true, '--tag' => 'site-editor', '--force' => true ] )
		->assertSuccessful();

	// Custom content should be overwritten.
	expect( File::get( $hubPath ) )->not->toBe( '<!-- custom -->' );
} );
