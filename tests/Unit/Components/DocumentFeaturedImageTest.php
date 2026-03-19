<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DocumentFeaturedImage;

test( 'document featured image can be instantiated with defaults', function (): void {
	$component = new DocumentFeaturedImage();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->metaKey )->toBe( 'featured_image' );
	expect( $component->label )->toBeNull();
	expect( $component->hasMediaLibrary )->toBeBool();
} );

test( 'document featured image accepts custom props', function (): void {
	$component = new DocumentFeaturedImage(
		id: 'hero',
		metaKey: 'hero_image',
		label: 'Hero Image',
	);

	expect( $component->uuid )->toContain( 'hero' );
	expect( $component->metaKey )->toBe( 'hero_image' );
	expect( $component->label )->toBe( 'Hero Image' );
} );

test( 'document featured image renders', function (): void {
	$view = $this->blade( '<x-ve-document-featured-image />' );
	expect( $view )->not->toBeNull();
} );

test( 'document featured image renders label', function (): void {
	$this->blade( '<x-ve-document-featured-image />' )
		->assertSee( 'Featured Image' );
} );

test( 'document featured image renders custom label', function (): void {
	$this->blade( '<x-ve-document-featured-image label="Hero Image" />' )
		->assertSee( 'Hero Image' );
} );

test( 'document featured image renders remove button', function (): void {
	$this->blade( '<x-ve-document-featured-image />' )
		->assertSee( 'Remove featured image' );
} );

test( 'document featured image renders meta key binding', function (): void {
	$view = $this->blade( '<x-ve-document-featured-image />' );

	$view->assertSee( 'getMeta', false );
} );

test( 'document featured image renders media picker button when media library is available', function (): void {
	$this->app->bind( DocumentFeaturedImage::class, function () {
		$component                  = new DocumentFeaturedImage();
		$component->hasMediaLibrary = true;

		return $component;
	} );

	$view = $this->blade( '<x-ve-document-featured-image />' );
	$view->assertSee( 'Select Image' );
	$view->assertSee( 'open-ve-media-picker', false );
} );

test( 'document featured image renders file input when media library is not available', function (): void {
	$this->app->bind( DocumentFeaturedImage::class, function () {
		$component                  = new DocumentFeaturedImage();
		$component->hasMediaLibrary = false;

		return $component;
	} );

	$view = $this->blade( '<x-ve-document-featured-image />' );
	$view->assertSee( 'file-input', false );
} );
