<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SampleContent\SampleContentRepository;
use Illuminate\Support\Facades\Storage;

beforeEach( function (): void {
	Storage::fake( 'local' );
} );

it( 'seeds every B1-shim entity from the packaged fixtures directory', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )
		->expectsOutputToContain( 'Seeded sample content from' )
		->assertSuccessful();

	$disk = Storage::disk( 'local' );

	// One record per fixture file committed under tests/Fixtures/sample-content/.
	expect( $disk->files( 'visual-editor/sample-content/postType/wp_template' ) )->toHaveCount( 4 );
	expect( $disk->files( 'visual-editor/sample-content/postType/wp_template_part' ) )->toHaveCount( 3 );
	expect( $disk->files( 'visual-editor/sample-content/postType/wp_navigation' ) )->toHaveCount( 3 );
	expect( $disk->files( 'visual-editor/sample-content/postType/wp_block' ) )->toHaveCount( 3 );
	expect( $disk->files( 'visual-editor/sample-content/root/globalStyles' ) )->toHaveCount( 1 );
} );

it( 'covers the acceptance criteria for each entity kind', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$repository = app( SampleContentRepository::class );
	$disk       = Storage::disk( 'local' );

	// Templates — four of them, each with a non-empty block tree.
	foreach ( [ 1, 2, 3, 4 ] as $id ) {
		$record = $repository->readRecord( $disk, 'templates', $id );

		expect( $record )->not->toBeNull();
		expect( $record['type'] )->toBe( 'wp_template' );
		expect( $record['content']['blocks'] )->not->toBeEmpty();
	}

	// Header template part is referenced by every template fixture.
	$header = $repository->readRecord( $disk, 'template-parts', 10 );
	expect( $header )->not->toBeNull();
	expect( $header['area'] )->toBe( 'header' );

	// Nested navigation exercises the submenu shape for Phase D4.
	$nested = $repository->readRecord( $disk, 'navigation', 22 );
	expect( $nested )->not->toBeNull();
	$submenuBlocks = array_filter(
		$nested['content']['blocks'],
		static fn ( array $block ): bool => 'core/navigation-submenu' === ( $block['name'] ?? null )
	);
	expect( $submenuBlocks )->not->toBeEmpty();

	// Patterns cover both synced and unsynced variants.
	$synced   = $repository->readRecord( $disk, 'patterns', 30 );
	$unsynced = $repository->readRecord( $disk, 'patterns', 31 );
	expect( $synced['synced'] )->toBeTrue();
	expect( $unsynced['synced'] )->toBeFalse();

	// Global styles is the singleton theme.json record.
	$globalStyles = $repository->readRecord( $disk, 'global-styles', 40 );
	expect( $globalStyles )->not->toBeNull();
	expect( $globalStyles['version'] )->toBe( 3 );
	expect( $globalStyles['settings']['color']['palette'] )->not->toBeEmpty();
	expect( $globalStyles['styles']['blocks']['core/button'] )->not->toBeEmpty();
} );

it( 'is idempotent across repeated runs', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$disk           = Storage::disk( 'local' );
	$firstSnapshot  = collect( $disk->allFiles( 'visual-editor/sample-content' ) )
		->mapWithKeys( fn ( string $file ): array => [ $file => $disk->get( $file ) ] )
		->all();

	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$secondSnapshot = collect( $disk->allFiles( 'visual-editor/sample-content' ) )
		->mapWithKeys( fn ( string $file ): array => [ $file => $disk->get( $file ) ] )
		->all();

	expect( $secondSnapshot )->toEqual( $firstSnapshot );
} );

it( 'prunes records removed from the fixtures directory on re-seed', function (): void {
	$fixturesDir = sys_get_temp_dir() . '/visual-editor-b2-fixtures-' . uniqid();
	mkdir( $fixturesDir . '/templates', 0o777, true );

	file_put_contents(
		$fixturesDir . '/templates/temp.json',
		json_encode( [ 'id' => 999, 'slug' => 'temp', 'type' => 'wp_template' ] )
	);

	$this->artisan( 'visual-editor:seed-sample-content', [ '--path' => $fixturesDir ] )
		->assertSuccessful();

	expect( Storage::disk( 'local' )->exists( 'visual-editor/sample-content/postType/wp_template/999.json' ) )
		->toBeTrue();

	unlink( $fixturesDir . '/templates/temp.json' );

	$this->artisan( 'visual-editor:seed-sample-content', [ '--path' => $fixturesDir ] )
		->assertSuccessful();

	expect( Storage::disk( 'local' )->exists( 'visual-editor/sample-content/postType/wp_template/999.json' ) )
		->toBeFalse();

	rmdir( $fixturesDir . '/templates' );
	rmdir( $fixturesDir );
} );

it( 'fails fast when the fixtures directory is missing', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content', [ '--path' => '/nonexistent/path' ] )
		->expectsOutputToContain( 'Sample-content fixtures directory not found' )
		->assertFailed();
} );

it( 'respects a custom fixtures path via the --path option', function (): void {
	$fixturesDir = sys_get_temp_dir() . '/visual-editor-b2-fixtures-' . uniqid();
	mkdir( $fixturesDir . '/patterns', 0o777, true );

	file_put_contents(
		$fixturesDir . '/patterns/custom.json',
		json_encode( [
			'id'      => 500,
			'slug'    => 'custom',
			'type'    => 'wp_block',
			'synced'  => false,
			'content' => [ 'raw' => '', 'blocks' => [] ],
		] )
	);

	$this->artisan( 'visual-editor:seed-sample-content', [ '--path' => $fixturesDir ] )
		->assertSuccessful();

	expect( Storage::disk( 'local' )->exists( 'visual-editor/sample-content/postType/wp_block/500.json' ) )
		->toBeTrue();

	unlink( $fixturesDir . '/patterns/custom.json' );
	rmdir( $fixturesDir . '/patterns' );
	rmdir( $fixturesDir );
} );

it( 'rejects fixtures that are missing a primary key', function (): void {
	$fixturesDir = sys_get_temp_dir() . '/visual-editor-b2-fixtures-' . uniqid();
	mkdir( $fixturesDir . '/templates', 0o777, true );

	file_put_contents(
		$fixturesDir . '/templates/broken.json',
		json_encode( [ 'slug' => 'broken', 'title' => [ 'rendered' => 'Broken' ] ] )
	);

	$this->artisan( 'visual-editor:seed-sample-content', [ '--path' => $fixturesDir ] )
		->expectsOutputToContain( 'missing a primary key' )
		->assertFailed();

	unlink( $fixturesDir . '/templates/broken.json' );
	rmdir( $fixturesDir . '/templates' );
	rmdir( $fixturesDir );
} );
