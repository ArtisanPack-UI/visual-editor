<?php

/**
 * Covers the Image and Gallery lightbox output (#1046).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Support\ImageLightbox;

const LIGHTBOX_IMAGE_URL = 'https://example.test/a.jpg';

function renderLightboxImage( array $attributes, string $name = 'artisanpack/image' ): string
{
	return app( BlockRenderer::class )->render( [
		[
			'clientId'    => 'image',
			'name'        => $name,
			'attributes'  => [ 'url' => LIGHTBOX_IMAGE_URL, 'alt' => 'A cat', ...$attributes ],
			'innerBlocks' => [],
		],
	] );
}

it( 'renders an existing image unchanged', function (): void {
	expect( renderLightboxImage( [] ) )->not->toContain( 'lightbox' );
} );

it( 'prints the container, labels and a hidden button trigger', function (): void {
	expect( renderLightboxImage( [ 'lightbox' => [ 'enabled' => true ] ] ) )
		->toContain( 'class="wp-block-image wp-lightbox-container"' )
		->toContain( 'data-ap-lightbox-labels="{&quot;dialog&quot;:&quot;Enlarged image&quot;' )
		->toContain( '<button type="button" class="lightbox-trigger" aria-haspopup="dialog" aria-label="Enlarge image: A cat" style="display:none">' )
		->toContain( 'aria-hidden="true"' );
} );

it( 'labels the trigger without alt text', function (): void {
	expect( renderLightboxImage( [ 'alt' => '', 'lightbox' => [ 'enabled' => true ] ] ) )
		->toContain( 'aria-label="Enlarge image"' );
} );

it( 'escapes alt text in the trigger label', function (): void {
	expect( renderLightboxImage( [ 'alt' => '"><script>', 'lightbox' => [ 'enabled' => true ] ] ) )
		->not->toContain( '"><script>"' )
		->toContain( 'aria-label="Enlarge image: &quot;&gt;&lt;script&gt;"' );
} );

it( 'prints the trigger before the caption', function (): void {
	$html = renderLightboxImage( [ 'caption' => 'Hello', 'lightbox' => [ 'enabled' => true ] ] );

	expect( strpos( $html, 'lightbox-trigger' ) )->toBeLessThan( strpos( $html, '<figcaption' ) );
} );

it( 'prints nothing with no image to enlarge', function (): void {
	expect( renderLightboxImage( [ 'url' => '', 'lightbox' => [ 'enabled' => true ] ] ) )
		->not->toContain( 'lightbox' );
} );

it( 'keeps the link and prints no lightbox on a linked image', function (): void {
	expect( renderLightboxImage( [ 'href' => 'https://example.test/page', 'lightbox' => [ 'enabled' => true ] ] ) )
		->toContain( '<a href="https://example.test/page">' )
		->not->toContain( 'lightbox' );
} );

it( 'treats an unsafe link as no link, since it does not print', function (): void {
	expect( ImageLightbox::enabled( [ 'lightbox' => [ 'enabled' => true ] ], LIGHTBOX_IMAGE_URL, '' ) )->toBeTrue();
} );

it( 'reads the theme default from either block key', function ( array $blocks, bool $expected ): void {
	expect( ImageLightbox::themeDefault( [ 'blocks' => $blocks ] ) )->toBe( $expected );
} )->with( [
	'nothing'           => [ [], false ],
	'first-party on'    => [ [ 'artisanpack/image' => [ 'lightbox' => [ 'enabled' => true ] ] ], true ],
	'core on'           => [ [ 'core/image' => [ 'lightbox' => [ 'enabled' => true ] ] ], true ],
	'first-party wins'  => [
		[
			'artisanpack/image' => [ 'lightbox' => [ 'enabled' => false ] ],
			'core/image'        => [ 'lightbox' => [ 'enabled' => true ] ],
		],
		false,
	],
	'not a boolean'     => [ [ 'core/image' => [ 'lightbox' => [ 'enabled' => 'yes' ] ] ], false ],
	'allowEditing only' => [ [ 'core/image' => [ 'lightbox' => [ 'allowEditing' => true ] ] ], false ],
] );

it( 'is off without an active theme', function (): void {
	expect( ImageLightbox::themeDefault() )->toBeFalse();
} );

it( 'applies the active theme\'s default, and a block can override it', function (): void {
	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'slug'     => 'test-theme',
			'settings' => [ 'blocks' => [ 'artisanpack/image' => [ 'lightbox' => [ 'enabled' => true ] ] ] ],
		] );
	} );

	expect( renderLightboxImage( [], 'core/image' ) )->toContain( 'wp-lightbox-container' )
		->and( renderLightboxImage( [ 'lightbox' => [ 'enabled' => false ] ] ) )->not->toContain( 'lightbox' )
		->and( renderLightboxImage( [ 'href' => 'https://example.test/page' ] ) )->not->toContain( 'lightbox' );
} );

it( 'reads the theme once however many images render', function (): void {
	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->once()->andReturn( [ 'slug' => 'test-theme', 'settings' => [] ] );
	} );

	renderLightboxImage( [] );
	renderLightboxImage( [] );
	renderLightboxImage( [] );
} );

it( 'tells the overlay a non-default navigation button type', function ( string $type ): void {
	$html = app( BlockRenderer::class )->render( [
		[ 'clientId' => 'g', 'name' => 'core/gallery', 'attributes' => [ 'navigationButtonType' => $type ], 'innerBlocks' => [] ],
	] );

	expect( $html )->toContain( sprintf( 'data-ap-lightbox-navigation="%s"', $type ) );
} )->with( [ 'text', 'both' ] );

it( 'prints nothing for the default or an unknown navigation button type', function ( mixed $type ): void {
	expect( ImageLightbox::navigationAttribute( $type ) )->toBe( '' );
} )->with( [ 'icon', null, 'x" onclick="y', 1 ] );

it( 'gives the overlay a translatable gallery position label', function (): void {
	$labels = json_decode( ImageLightbox::labels(), true );

	expect( $labels )->toHaveKey( 'position' )
		->and( $labels['position'] )->toBe( 'Image :current of :total' );

	app( 'translator' )->addLines( [ '*.Image :current of :total' => 'Bild :current von :total' ], 'en' );

	expect( json_decode( ImageLightbox::labels(), true )['position'] )->toBe( 'Bild :current von :total' );
} );
