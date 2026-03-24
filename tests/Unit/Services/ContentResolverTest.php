<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\ContentResolver;

test( 'content resolver returns empty title by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getTitle() )->toBe( '' );
} );

test( 'content resolver returns empty body by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getBody() )->toBe( '' );
} );

test( 'content resolver returns empty excerpt by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getExcerpt() )->toBe( '' );
} );

test( 'content resolver returns empty date by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getDate() )->toBe( '' );
} );

test( 'content resolver returns empty modified date by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getModifiedDate() )->toBe( '' );
} );

test( 'content resolver returns empty featured image url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getFeaturedImageUrl() )->toBe( '' );
} );

test( 'content resolver returns empty featured image alt by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getFeaturedImageAlt() )->toBe( '' );
} );

test( 'content resolver returns empty permalink by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getPermalink() )->toBe( '' );
} );

test( 'content resolver to array returns all fields', function (): void {
	$resolver = new ContentResolver();
	$array    = $resolver->toArray();

	expect( $array )->toHaveKeys( [
		'title',
		'body',
		'excerpt',
		'date',
		'modifiedDate',
		'featuredImageUrl',
		'featuredImageAlt',
		'permalink',
	] );
} );

test( 'content resolver passes context to filters', function (): void {
	$resolver = new ContentResolver();
	$context  = [ 'model_id' => 42, 'model_type' => 'post' ];

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.title', function ( $default, $ctx ) {
			if ( is_array( $ctx ) && 42 === ( $ctx['model_id'] ?? null ) ) {
				return 'Filtered Title';
			}

			return $default;
		} );

		expect( $resolver->getTitle( $context ) )->toBe( 'Filtered Title' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.title' );
		}
	} else {
		expect( $resolver->getTitle( $context ) )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe permalink urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.permalink', function () {
			return 'javascript:alert(1)';
		} );

		expect( $resolver->getPermalink() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.permalink' );
		}
	} else {
		expect( $resolver->getPermalink() )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe featured image urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.featured-image-url', function () {
			return 'data:text/html,<script>alert(1)</script>';
		} );

		expect( $resolver->getFeaturedImageUrl() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.featured-image-url' );
		}
	} else {
		expect( $resolver->getFeaturedImageUrl() )->toBe( '' );
	}
} );

test( 'content resolver preserves valid http permalink urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.permalink', function () {
			return 'http://example.com/posts/1';
		} );

		expect( $resolver->getPermalink() )->toBe( 'http://example.com/posts/1' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.permalink' );
		}
	} else {
		expect( $resolver->getPermalink() )->toBe( '' );
	}
} );

test( 'content resolver preserves valid https featured image urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.featured-image-url', function () {
			return 'https://example.com/images/hero.jpg';
		} );

		expect( $resolver->getFeaturedImageUrl() )->toBe( 'https://example.com/images/hero.jpg' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.featured-image-url' );
		}
	} else {
		expect( $resolver->getFeaturedImageUrl() )->toBe( '' );
	}
} );
