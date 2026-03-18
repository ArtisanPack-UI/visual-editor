<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DocumentTaxonomies;

test( 'document taxonomies can be instantiated with defaults', function (): void {
	$component = new DocumentTaxonomies();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->metaKey )->toBe( 'taxonomies' );
	expect( $component->taxonomy )->toBe( '' );
	expect( $component->label )->toBeNull();
	expect( $component->options )->toBe( [] );
} );

test( 'document taxonomies accepts custom props', function (): void {
	$options   = [ '1' => 'Category A', '2' => 'Category B' ];
	$component = new DocumentTaxonomies(
		id: 'cats',
		metaKey: 'categories',
		taxonomy: 'category',
		label: 'Categories',
		options: $options,
	);

	expect( $component->uuid )->toContain( 'cats' );
	expect( $component->metaKey )->toBe( 'categories' );
	expect( $component->taxonomy )->toBe( 'category' );
	expect( $component->label )->toBe( 'Categories' );
	expect( $component->options )->toBe( $options );
} );

test( 'document taxonomies renders', function (): void {
	$view = $this->blade( '<x-ve-document-taxonomies />' );
	expect( $view )->not->toBeNull();
} );

test( 'document taxonomies renders label', function (): void {
	$this->blade( '<x-ve-document-taxonomies />' )
		->assertSee( 'Taxonomies' );
} );

test( 'document taxonomies renders custom label', function (): void {
	$this->blade( '<x-ve-document-taxonomies label="Categories" />' )
		->assertSee( 'Categories' );
} );

test( 'document taxonomies renders empty state', function (): void {
	$this->blade( '<x-ve-document-taxonomies />' )
		->assertSee( 'No options available.' );
} );

test( 'document taxonomies renders checkbox inputs', function (): void {
	$this->blade( '<x-ve-document-taxonomies />' )
		->assertSee( 'checkbox', false );
} );

test( 'document taxonomies renders meta key binding', function (): void {
	$view = $this->blade( '<x-ve-document-taxonomies />' );

	$view->assertSee( 'getMeta', false );
} );

test( 'document taxonomies uses taxonomy-scoped meta key', function (): void {
	$view = $this->blade( '<x-ve-document-taxonomies taxonomy="category" />' );

	$view->assertSee( 'taxonomies.category', false );
} );
