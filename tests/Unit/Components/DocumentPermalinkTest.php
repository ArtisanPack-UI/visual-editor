<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DocumentPermalink;

test( 'document permalink can be instantiated with defaults', function (): void {
	$component = new DocumentPermalink();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->metaKey )->toBe( 'slug' );
	expect( $component->baseUrl )->toBe( '/' );
	expect( $component->label )->toBeNull();
} );

test( 'document permalink accepts custom props', function (): void {
	$component = new DocumentPermalink(
		id: 'permalink',
		metaKey: 'url_slug',
		baseUrl: 'https://mysite.com/blog/',
		label: 'URL Slug',
	);

	expect( $component->uuid )->toContain( 'permalink' );
	expect( $component->metaKey )->toBe( 'url_slug' );
	expect( $component->baseUrl )->toBe( 'https://mysite.com/blog/' );
	expect( $component->label )->toBe( 'URL Slug' );
} );

test( 'document permalink renders', function (): void {
	$view = $this->blade( '<x-ve-document-permalink />' );
	expect( $view )->not->toBeNull();
} );

test( 'document permalink renders label', function (): void {
	$this->blade( '<x-ve-document-permalink />' )
		->assertSee( 'Permalink' );
} );

test( 'document permalink renders base url prefix', function (): void {
	$this->blade( '<x-ve-document-permalink base-url="https://mysite.com/blog/" />' )
		->assertSee( 'https://mysite.com/blog/' );
} );

test( 'document permalink renders default base url', function (): void {
	$view = $this->blade( '<x-ve-document-permalink />' );

	$view->assertSee( '/' );
} );

test( 'document permalink renders input', function (): void {
	$this->blade( '<x-ve-document-permalink />' )
		->assertSee( '<input', false );
} );

test( 'document permalink renders meta key binding', function (): void {
	$view = $this->blade( '<x-ve-document-permalink />' );

	$view->assertSee( 'getMeta', false );
} );

test( 'document permalink renders slug placeholder', function (): void {
	$this->blade( '<x-ve-document-permalink />' )
		->assertSee( 'enter-slug' );
} );
