<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;

it( 'seeds all five entity kinds into the database', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )
		->expectsOutputToContain( 'Seeded sample content from' )
		->assertSuccessful();

	expect( VisualEditorTemplate::count() )->toBe( 4 );
	expect( VisualEditorTemplatePart::count() )->toBe( 3 );
	expect( VisualEditorNavigation::count() )->toBe( 3 );
	expect( VisualEditorPattern::count() )->toBe( 3 );
	expect( VisualEditorGlobalStyles::count() )->toBe( 1 );
} );

it( 'round-trips template fields correctly', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$index = VisualEditorTemplate::where( 'slug', 'index' )->first();

	expect( $index )->not->toBeNull();
	expect( $index->title )->toBe( 'Index' );
	expect( $index->theme )->toBe( 'artisanpack-base' );
	expect( $index->source )->toBe( 'theme' );
	expect( $index->origin )->toBe( 'theme' );
	expect( $index->status )->toBe( 'publish' );
	expect( $index->getBlocks() )->not->toBeEmpty();
	expect( $index->getRawContent() )->not->toBe( '' );
} );

it( 'round-trips template-part fields correctly', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$header = VisualEditorTemplatePart::where( 'slug', 'header' )->first();

	expect( $header )->not->toBeNull();
	expect( $header->title )->toBe( 'Header' );
	expect( $header->area )->toBe( 'header' );
	expect( $header->theme )->toBe( 'artisanpack-base' );
	expect( $header->getBlocks() )->not->toBeEmpty();
	expect( $header->getRawContent() )->not->toBe( '' );
} );

it( 'seeds all four templates with non-empty block trees', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	foreach ( [ 'index', 'single', 'page', '404' ] as $slug ) {
		$template = VisualEditorTemplate::where( 'slug', $slug )->first();

		expect( $template )->not->toBeNull( "Template '{$slug}' should exist" );
		expect( $template->getBlocks() )->not->toBeEmpty( "Template '{$slug}' should have blocks" );
	}
} );

it( 'round-trips navigation fields correctly', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$nav = VisualEditorNavigation::where( 'slug', 'primary-nav' )->first();

	expect( $nav )->not->toBeNull();
	expect( $nav->title )->toBe( 'Primary' );
	expect( $nav->status )->toBe( 'publish' );
	expect( $nav->menu_order )->toBe( 0 );
	expect( $nav->getBlocks() )->not->toBeEmpty();
} );

it( 'seeds the nested navigation with a submenu block', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$nested = VisualEditorNavigation::where( 'slug', 'nested-nav' )->first();

	expect( $nested )->not->toBeNull();

	$submenuBlocks = array_filter(
		$nested->getBlocks(),
		static fn ( array $block ): bool => 'core/navigation-submenu' === ( $block['name'] ?? null )
	);

	expect( $submenuBlocks )->not->toBeEmpty();
} );

it( 'round-trips pattern fields and syncs categories', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$hero = VisualEditorPattern::where( 'slug', 'hero' )->first();

	expect( $hero )->not->toBeNull();
	expect( $hero->title )->toBe( 'Hero' );
	expect( $hero->synced )->toBeTrue();
	expect( $hero->status )->toBe( 'publish' );
	expect( $hero->getBlocks() )->not->toBeEmpty();
	expect( $hero->categories )->toHaveCount( 1 );
	expect( $hero->categories->first()->slug )->toBe( 'featured' );
} );

it( 'seeds unsynced patterns correctly', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$callout = VisualEditorPattern::where( 'slug', 'call-out-pair' )->first();

	expect( $callout )->not->toBeNull();
	expect( $callout->synced )->toBeFalse();
} );

it( 'round-trips global-styles fields correctly', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	$gs = VisualEditorGlobalStyles::where( 'theme', 'artisanpack-base' )->first();

	expect( $gs )->not->toBeNull();
	expect( $gs->version )->toBe( 3 );
	expect( $gs->settings['color']['palette'] )->not->toBeEmpty();
	expect( $gs->styles['blocks']['core/button'] )->not->toBeEmpty();
} );

it( 'is idempotent across repeated runs', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();
	$this->artisan( 'visual-editor:seed-sample-content' )->assertSuccessful();

	expect( VisualEditorTemplate::count() )->toBe( 4 );
	expect( VisualEditorTemplatePart::count() )->toBe( 3 );
	expect( VisualEditorNavigation::count() )->toBe( 3 );
	expect( VisualEditorPattern::count() )->toBe( 3 );
	expect( VisualEditorGlobalStyles::count() )->toBe( 1 );
} );

it( 'fails fast when the fixtures directory is missing', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content', [ '--path' => '/nonexistent/path' ] )
		->expectsOutputToContain( 'Sample-content fixtures directory not found' )
		->assertFailed();
} );

it( 'rejects fixture files whose top-level JSON is a list rather than an object', function (): void {
	$fixturesDir = sys_get_temp_dir() . '/ve-seed-test-' . uniqid();
	mkdir( $fixturesDir . '/templates', 0o777, true );

	file_put_contents(
		$fixturesDir . '/templates/list.json',
		json_encode( [ [ 'id' => 1, 'slug' => 'wrapped' ] ] )
	);

	$this->artisan( 'visual-editor:seed-sample-content', [ '--path' => $fixturesDir ] )
		->expectsOutputToContain( 'must decode to a JSON object' )
		->assertFailed();

	unlink( $fixturesDir . '/templates/list.json' );
	rmdir( $fixturesDir . '/templates' );
	rmdir( $fixturesDir );
} );

it( 'surfaces per-kind row counts in the command output', function (): void {
	$this->artisan( 'visual-editor:seed-sample-content' )
		->expectsOutputToContain( 'templates' )
		->expectsOutputToContain( 'template-parts' )
		->expectsOutputToContain( 'navigation' )
		->expectsOutputToContain( 'patterns' )
		->expectsOutputToContain( 'global-styles' )
		->assertSuccessful();
} );
