<?php

/**
 * Hook Filter Tests.
 *
 * Verifies that filter hooks modify data correctly for site editor
 * customization: hub cards, listing columns, listing actions, nav items,
 * and route prefix.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

test( 've.hub.cards filter modifies hub cards', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not installed.' );
	}

	addFilter( 've.hub.cards', function ( array $cards ): array {
		$cards[] = [
			'slug'        => 'custom-section',
			'label'       => 'Custom Section',
			'description' => 'A custom hub card.',
			'icon'        => '<svg></svg>',
			'url'         => '/custom',
			'count'       => null,
			'permission'  => null,
		];

		return $cards;
	} );

	$hubPage = new ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage();
	$cards   = $hubPage->getCards();

	$slugs = array_column( $cards, 'slug' );

	expect( $slugs )->toContain( 'custom-section' );

	// Clean up.
	removeAllFilters( 've.hub.cards' );
} );

test( 've.listing.columns filter modifies template listing columns', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not installed.' );
	}

	addFilter( 've.listing.columns', function ( array $columns, string $type ): array {
		if ( 'template' === $type ) {
			$columns[] = [ 'key' => 'custom_field', 'label' => 'Custom', 'sortable' => false ];
		}

		return $columns;
	} );

	$listing = new ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage();
	$columns = $listing->getColumns();

	$keys = array_column( $columns, 'key' );

	expect( $keys )->toContain( 'custom_field' );

	// Clean up.
	removeAllFilters( 've.listing.columns' );
} );

test( 've.listing.actions filter adds custom row actions', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not installed.' );
	}

	addFilter( 've.listing.actions', function ( array $actions, string $type ): array {
		$actions[] = [ 'label' => 'Export', 'event' => 've-export', 'icon' => 'download' ];

		return $actions;
	} );

	$listing = new ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage();
	$actions = $listing->getRowActions( (object) [ 'id' => 1, 'name' => 'Test' ] );

	expect( $actions )->toHaveCount( 1 );
	expect( $actions[0]['label'] )->toBe( 'Export' );

	// Clean up.
	removeAllFilters( 've.listing.actions' );
} );

test( 've.site-editor.route-prefix filter modifies route prefix', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not installed.' );
	}

	addFilter( 've.site-editor.route-prefix', function ( string $prefix ): string {
		return 'admin/editor';
	} );

	$result = veApplyFilters( 've.site-editor.route-prefix', 'site-editor' );

	expect( $result )->toBe( 'admin/editor' );

	// Clean up.
	removeAllFilters( 've.site-editor.route-prefix' );
} );

test( 'veApplyFilters returns value unchanged when no hooks registered', function (): void {
	$result = veApplyFilters( 've.test.nonexistent-filter', 'original' );

	expect( $result )->toBe( 'original' );
} );

test( 've.listing.actions returns empty array when no hooks registered', function (): void {
	$listing = new ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage();
	$actions = $listing->getRowActions( (object) [ 'id' => 1 ] );

	expect( $actions )->toBeArray();
	expect( $actions )->toBeEmpty();
} );
