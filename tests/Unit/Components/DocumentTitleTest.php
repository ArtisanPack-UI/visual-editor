<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DocumentTitle;

test( 'document title can be instantiated with defaults', function (): void {
	$component = new DocumentTitle();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->metaKey )->toBe( 'title' );
	expect( $component->label )->toBeNull();
	expect( $component->placeholder )->toBeNull();
	expect( $component->autoSlug )->toBeFalse();
	expect( $component->slugKey )->toBe( 'slug' );
} );

test( 'document title accepts custom props', function (): void {
	$component = new DocumentTitle(
		id: 'doc-title',
		metaKey: 'heading',
		label: 'Page Title',
		placeholder: 'Enter title',
		autoSlug: true,
		slugKey: 'permalink',
	);

	expect( $component->uuid )->toContain( 'doc-title' );
	expect( $component->metaKey )->toBe( 'heading' );
	expect( $component->label )->toBe( 'Page Title' );
	expect( $component->placeholder )->toBe( 'Enter title' );
	expect( $component->autoSlug )->toBeTrue();
	expect( $component->slugKey )->toBe( 'permalink' );
} );

test( 'document title renders', function (): void {
	$view = $this->blade( '<x-ve-document-title />' );
	expect( $view )->not->toBeNull();
} );

test( 'document title renders label', function (): void {
	$this->blade( '<x-ve-document-title />' )
		->assertSee( 'Title' );
} );

test( 'document title renders custom label', function (): void {
	$this->blade( '<x-ve-document-title label="Page Title" />' )
		->assertSee( 'Page Title' );
} );

test( 'document title renders input', function (): void {
	$this->blade( '<x-ve-document-title />' )
		->assertSee( '<input', false );
} );

test( 'document title renders placeholder', function (): void {
	$this->blade( '<x-ve-document-title />' )
		->assertSee( 'Add title' );
} );

test( 'document title renders custom placeholder', function (): void {
	$this->blade( '<x-ve-document-title placeholder="Enter heading" />' )
		->assertSee( 'Enter heading' );
} );

test( 'document title renders meta key binding', function (): void {
	$view = $this->blade( '<x-ve-document-title />' );

	$view->assertSee( 'getMeta', false );
} );

test( 'document title renders custom meta key', function (): void {
	$view = $this->blade( '<x-ve-document-title meta-key="heading" />' );

	$view->assertSee( 'heading', false );
} );
