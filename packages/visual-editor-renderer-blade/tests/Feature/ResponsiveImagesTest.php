<?php

/**
 * Covers the variants and loading hints the image blocks print (#1031).
 *
 * The media library is optional, so the suite doesn't load it. A fake
 * subclass answers the lookups with records shaped like its model.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveImages;
use Illuminate\Support\Facades\Storage;

/**
 * A record shaped like the media library's model.
 */
function fakeMedia( int $id, array $overrides = [] ): object
{
	return new class( array_merge( [
		'id'         => $id,
		'file_path'  => '2026/09/hero.jpg',
		'disk'       => 'public',
		'width'      => 1600,
		'height'     => 900,
		'updated_at' => '2026-09-01',
		'metadata'   => [
			'thumbnails'     => [
				'thumbnail' => '2026/09/hero-thumbnail.jpg',
				'medium'    => '2026/09/hero-medium.jpg',
				'large'     => '2026/09/hero-large.jpg',
			],
			'modern_formats' => [ 'webp' => '2026/09/hero.webp' ],
		],
	], $overrides ) ) {
		public function __construct( array $fields )
		{
			foreach ( $fields as $key => $value ) {
				$this->{$key} = $value;
			}
		}

		public function url(): string
		{
			return '/storage/' . $this->file_path;
		}
	};
}

/**
 * Swap in a resolver that reads the given records, and count its lookups.
 *
 * @param  array<int, object>  $records
 */
function useFakeMedia( array $records ): object
{
	$fake = new class( $records ) extends ResponsiveImages {
		public int $idQueries = 0;

		public int $pathQueries = 0;

		public function __construct( protected array $records )
		{
		}

		protected function fetchMedia( array $ids ): array
		{
			$this->idQueries++;

			return array_intersect_key( $this->records, array_flip( $ids ) );
		}

		protected function fetchMediaByPaths( array $paths ): ?object
		{
			$this->pathQueries++;

			foreach ( $this->records as $record ) {
				if ( in_array( $record->file_path, $paths, true ) ) {
					return $record;
				}
			}

			return null;
		}
	};

	app()->instance( ResponsiveImages::class, $fake );

	return $fake;
}

function imageBlock( array $attributes, string $name = 'core/image' ): array
{
	return [ 'name' => $name, 'attributes' => $attributes, 'innerBlocks' => [] ];
}

function renderImages( array ...$blocks ): string
{
	return app( BlockRenderer::class )->render( $blocks );
}

beforeEach( function (): void {
	config()->set( 'artisanpack.media.image_sizes', [
		'thumbnail' => [ 'width' => 150, 'height' => 150, 'crop' => true ],
		'medium'    => [ 'width' => 300, 'height' => 300, 'crop' => false ],
		'large'     => [ 'width' => 1024, 'height' => 1024, 'crop' => false ],
	] );

	useFakeMedia( [ 7 => fakeMedia( 7 ) ] );
} );

it( 'builds a srcset from the same-shape variants, leaving out the crop', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) ) )
		->toContain( 'srcset="/storage/2026/09/hero-medium.jpg 300w, /storage/2026/09/hero-large.jpg 1024w, /storage/2026/09/hero.jpg 1600w"' )
		->not->toContain( 'hero-thumbnail.jpg 150w' );
} );

it( 'prints the file dimensions on an image nobody resized', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) ) )
		->toContain( 'width="1600"' )
		->toContain( 'height="900"' );
} );

it( 'keeps a block-set size over the file dimensions', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg', 'width' => '400px' ] ) ) )
		->toContain( 'width="400"' )
		->toContain( 'sizes="(max-width: 400px) 100vw, 400px"' )
		->not->toContain( 'width="1600"' )
		->not->toContain( 'height="900"' );
} );

it( 'describes the variant a block saved as its url', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero-medium.jpg' ] ) ) )
		->toContain( 'src="/storage/2026/09/hero-medium.jpg"' )
		->toContain( 'width="300"' )
		->toContain( 'height="169"' );
} );

it( 'matches an absolute block url against a relative disk url', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => 'https://example.test/storage/2026/09/hero.jpg' ] ) ) )
		->toContain( 'srcset=' );
} );

it( 'ignores a media id the url no longer belongs to', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/images/other.jpg' ] ) ) )
		->not->toContain( 'srcset=' )
		->not->toContain( 'width=' )
		->not->toContain( '<picture>' );
} );

it( 'prints a plain image with no media record', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 99, 'url' => '/a.jpg' ] ), imageBlock( [ 'url' => '/b.jpg' ] ) ) )
		->toContain( '<img src="/a.jpg" alt="" class="wp-image-99"/>' )
		->toContain( '<img src="/b.jpg" alt="" loading="lazy"/>' );
} );

it( 'skips a srcset when the record has no recorded dimensions', function (): void {
	useFakeMedia( [ 7 => fakeMedia( 7, [ 'width' => null, 'height' => null ] ) ] );

	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) ) )
		->not->toContain( 'srcset=' );
} );

it( 'offers WebP through a picture without losing the small variants', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) ) )
		->toContain( '<picture><source type="image/webp" srcset="/storage/2026/09/hero-medium.jpg 300w, /storage/2026/09/hero-large.jpg 1024w, /storage/2026/09/hero.webp 1600w"' );
} );

it( 'puts AVIF ahead of WebP', function (): void {
	useFakeMedia( [ 7 => fakeMedia( 7, [ 'metadata' => [
		'thumbnails'     => [ 'medium' => '2026/09/hero-medium.jpg' ],
		'modern_formats' => [ 'webp' => '2026/09/hero.webp', 'avif' => '2026/09/hero.avif' ],
	] ] ) ] );

	$html = renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) );

	expect( strpos( $html, 'image/avif' ) )->toBeLessThan( strpos( $html, 'image/webp' ) );
} );

it( 'offers a lone modern file when there are no variants', function (): void {
	useFakeMedia( [ 7 => fakeMedia( 7, [ 'metadata' => [
		'thumbnails'     => [ 'thumbnail' => '2026/09/hero-thumbnail.jpg' ],
		'modern_formats' => [ 'webp' => '2026/09/hero.webp' ],
	] ] ) ] );

	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) ) )
		->toContain( '<source type="image/webp" srcset="/storage/2026/09/hero.webp"' )
		->not->toContain( ' srcset="/storage/2026/09/hero-thumbnail' );
} );

it( 'keeps the picture inside an image link', function (): void {
	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg', 'href' => '/page' ] ) ) )
		->toMatch( '#<a href="/page"><picture>.*</picture></a>#s' );
} );

it( 'probes storage once for a record whose metadata lists no variants', function (): void {
	Storage::fake( 'public' );
	Storage::disk( 'public' )->put( '2026/09/hero.webp', 'x' );

	$media = new class( fakeMedia( 7, [ 'metadata' => [] ] ) ) {
		public int $probes = 0;

		public function __construct( protected object $record )
		{
			foreach ( get_object_vars( $record ) as $key => $value ) {
				$this->{$key} = $value;
			}
		}

		public function url(): string
		{
			return $this->record->url();
		}

		public function getImageSizes(): array
		{
			$this->probes++;

			return [ 'medium' => '/storage/2026/09/hero-medium.jpg' ];
		}
	};

	useFakeMedia( [ 7 => $media ] );

	$html = renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) );

	useFakeMedia( [ 7 => $media ] );

	renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) );

	expect( $html )
		->toContain( 'srcset="/storage/2026/09/hero-medium.jpg 300w, /storage/2026/09/hero.jpg 1600w"' )
		->toContain( 'image/webp' )
		->and( $media->probes )->toBe( 1 );
} );

it( 'offers no variants when the file url cannot be adapted', function (): void {
	$signed = new class( fakeMedia( 7 ) ) {
		public function __construct( protected object $record )
		{
			foreach ( get_object_vars( $record ) as $key => $value ) {
				$this->{$key} = $value;
			}
		}

		public function url(): string
		{
			return '/storage/2026/09/hero.jpg?signature=abc';
		}
	};

	useFakeMedia( [ 7 => $signed ] );

	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg?signature=abc' ] ) ) )
		->not->toContain( 'hero-medium.jpg' )
		->not->toContain( '<picture>' );
} );

it( 'reads every image in a tree with one query', function (): void {
	$fake = useFakeMedia( [ 7 => fakeMedia( 7 ), 8 => fakeMedia( 8 ) ] );

	renderImages(
		imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ),
		[ 'name' => 'core/group', 'attributes' => [], 'innerBlocks' => [
			imageBlock( [ 'id' => 8, 'url' => '/storage/2026/09/hero.jpg' ] ),
			imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ),
		] ],
	);

	expect( $fake->idQueries )->toBe( 1 );
} );

it( 'leaves the first image eager and lazy-loads the rest', function (): void {
	$html = renderImages( imageBlock( [ 'url' => '/a.jpg' ] ), imageBlock( [ 'url' => '/b.jpg' ] ), imageBlock( [ 'url' => '/c.jpg' ] ) );

	expect( $html )
		->toContain( '<img src="/a.jpg" alt=""/>' )
		->toContain( '<img src="/b.jpg" alt="" loading="lazy"/>' )
		->toContain( '<img src="/c.jpg" alt="" loading="lazy"/>' );
} );

it( 'gives a single template featured image the priority hint', function (): void {
	$html = renderImages( imageBlock( [
		'_resolvedImageUrl' => '/storage/2026/09/hero.jpg',
		'_resolvedImageId'  => 7,
	], 'core/post-featured-image' ) );

	expect( $html )->toContain( 'fetchpriority="high"' )->not->toContain( 'loading="lazy"' );
} );

it( 'prints a featured image size from the record when none was stamped', function (): void {
	expect( renderImages( imageBlock( [
		'_resolvedImageUrl' => '/storage/2026/09/hero.jpg',
		'_resolvedImageId'  => 7,
	], 'core/post-featured-image' ) ) )
		->toContain( 'width="1600"' )
		->toContain( 'height="900"' );
} );

it( 'prefers the stamped featured image size', function (): void {
	expect( renderImages( imageBlock( [
		'_resolvedImageUrl'    => '/storage/2026/09/hero.jpg',
		'_resolvedImageId'     => 7,
		'_resolvedImageWidth'  => 800,
		'_resolvedImageHeight' => 450,
	], 'core/post-featured-image' ) ) )
		->toContain( 'width="800"' )
		->toContain( 'height="450"' );
} );

it( 'lets only one image take the priority hint', function (): void {
	$hero = imageBlock( [ '_resolvedImageUrl' => '/a.jpg' ], 'core/post-featured-image' );

	expect( substr_count( renderImages( $hero, $hero ), 'fetchpriority' ) )->toBe( 1 );
} );

it( 'gives a hero the hint even after a small image', function (): void {
	expect( renderImages(
		imageBlock( [ 'url' => '/logo.png' ] ),
		imageBlock( [ '_resolvedImageUrl' => '/hero.jpg' ], 'core/post-featured-image' ),
	) )->toContain( 'fetchpriority="high"' );
} );

it( 'never gives a card in a query loop the priority hint', function ( string $loop ): void {
	$html = renderImages(
		imageBlock( [ 'url' => '/first.jpg' ] ),
		[ 'name' => $loop, 'attributes' => [ '_resolvedItems' => 2 ], 'innerBlocks' => [
			imageBlock( [ '_resolvedImageUrl' => '/card.jpg' ], 'core/post-featured-image' ),
			imageBlock( [ '_resolvedImageUrl' => '/card-2.jpg' ], 'core/post-featured-image' ),
		] ],
	);

	expect( $html )->not->toContain( 'fetchpriority' )
		->and( substr_count( $html, 'loading="lazy"' ) )->toBe( 2 );
} )->with( [ 'core/post-template', 'artisanpack/post-template', 'artisanpack/related-posts' ] );

it( 'restores hero handling once a loop has closed', function (): void {
	expect( renderImages(
		[ 'name' => 'core/post-template', 'attributes' => [], 'innerBlocks' => [] ],
		imageBlock( [ '_resolvedImageUrl' => '/hero.jpg' ], 'core/post-featured-image' ),
	) )->toContain( 'fetchpriority="high"' );
} );

it( 'adds sizes=auto to a lazy card so it follows the laid-out width', function (): void {
	$image = imageBlock( [ '_resolvedImageUrl' => '/storage/2026/09/hero.jpg', '_resolvedImageId' => 7 ], 'core/post-featured-image' );

	expect( renderImages( imageBlock( [ 'url' => '/first.jpg' ] ), [ 'name' => 'core/post-template', 'attributes' => [], 'innerBlocks' => [ $image ] ] ) )
		->toContain( 'sizes="auto, (max-width: 1600px) 100vw, 1600px"' );
} );

it( 'uses the frame width for a pixel-sized featured image', function (): void {
	expect( renderImages( imageBlock( [
		'_resolvedImageUrl' => '/storage/2026/09/hero.jpg',
		'_resolvedImageId'  => 7,
		'width'             => '78px',
	], 'core/post-featured-image' ) ) )->toContain( 'sizes="(max-width: 78px) 100vw, 78px"' );
} );

it( 'loads a cover like a hero with a viewport-wide srcset and no picture', function (): void {
	$html = renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ], 'core/cover' ) );

	expect( $html )
		->toContain( 'fetchpriority="high"' )
		->toContain( 'sizes="100vw"' )
		->toContain( 'hero-medium.jpg 300w' )
		->not->toContain( '<picture>' );
} );

it( 'uses the resolved image id for a cover showing the featured image', function (): void {
	expect( renderImages( imageBlock( [
		'useFeaturedImage'  => true,
		'id'                => 99,
		'_resolvedImageUrl' => '/storage/2026/09/hero.jpg',
		'_resolvedImageId'  => 7,
	], 'core/cover' ) ) )->toContain( 'hero-medium.jpg 300w' );
} );

it( 'gives media-text images variants and dimensions', function (): void {
	$html = renderImages( imageBlock( [ 'mediaId' => 7, 'mediaUrl' => '/storage/2026/09/hero.jpg', 'mediaType' => 'image' ], 'core/media-text' ) );

	expect( $html )
		->toContain( 'width="1600" height="900"' )
		->toContain( 'hero-medium.jpg 300w' )
		->toContain( '<picture>' );
} );

it( 'leaves media-text video alone', function (): void {
	expect( renderImages( imageBlock( [ 'mediaUrl' => '/clip.mp4', 'mediaType' => 'video' ], 'core/media-text' ) ) )
		->toContain( '<video controls src="/clip.mp4"></video>' )
		->not->toContain( 'loading=' );
} );

it( 'uses the smallest square variant that stays sharp for an avatar', function ( string $block ): void {
	useFakeMedia( [ 7 => fakeMedia( 7, [ 'file_path' => '2026/09/hero.jpg', 'width' => 1200, 'height' => 1200 ] ) ] );

	$attributes = [ '_resolvedAuthorAvatar' => '/storage/2026/09/hero.jpg', 'size' => 28, 'avatarSize' => 28, '_resolvedAuthorName' => 'A' ];

	expect( renderImages( imageBlock( $attributes, $block ) ) )
		->toContain( 'src="/storage/2026/09/hero-thumbnail.jpg"' )
		->toContain( 'loading="lazy"' );
} )->with( [ 'core/avatar', 'core/post-author' ] );

it( 'keeps the avatar url when no variant is big enough', function (): void {
	useFakeMedia( [ 7 => fakeMedia( 7, [ 'metadata' => [ 'thumbnails' => [ 'thumbnail' => '2026/09/hero-thumbnail.jpg' ] ] ] ) ] );

	expect( renderImages( imageBlock( [ '_resolvedAuthorAvatar' => '/storage/2026/09/hero.jpg', 'size' => 96 ], 'core/avatar' ) ) )
		->toContain( 'src="/storage/2026/09/hero.jpg"' );
} );

it( 'does not match an off-site avatar to a local file with the same path', function (): void {
	expect( renderImages( imageBlock( [ '_resolvedAuthorAvatar' => 'https://other.test/storage/2026/09/hero.jpg', 'size' => 28 ], 'core/avatar' ) ) )
		->toContain( 'src="https://other.test/storage/2026/09/hero.jpg"' );
} );

it( 'does not match a same-host avatar to a record whose path is only a tail of it', function (): void {
	useFakeMedia( [ 7 => fakeMedia( 7, [ 'file_path' => 'hero.jpg', 'metadata' => [ 'thumbnails' => [ 'thumbnail' => 'hero-thumbnail.jpg' ] ] ] ) ] );

	expect( renderImages( imageBlock( [ '_resolvedAuthorAvatar' => '/images/hero.jpg', 'size' => 28 ], 'core/avatar' ) ) )
		->toContain( 'src="/images/hero.jpg"' );
} );

it( 'leaves an off-site avatar alone', function (): void {
	$url = 'https://www.gravatar.com/avatar/abc';

	expect( renderImages( imageBlock( [ '_resolvedAuthorAvatar' => $url, 'size' => 48 ], 'core/avatar' ) ) )
		->toContain( 'src="' . $url . '"' );
} );

it( 'looks up each avatar url once', function (): void {
	$fake = useFakeMedia( [ 7 => fakeMedia( 7 ) ] );

	$avatar = imageBlock( [ '_resolvedAuthorAvatar' => '/storage/2026/09/hero.jpg', 'size' => 28 ], 'core/avatar' );

	renderImages( $avatar, $avatar, $avatar );

	expect( $fake->pathQueries )->toBe( 1 );
} );

it( 'leaves a GIF as its original file so an animation survives', function (): void {
	useFakeMedia( [ 7 => fakeMedia( 7, [ 'mime_type' => 'image/gif' ] ) ] );

	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/storage/2026/09/hero.jpg' ] ) ) )
		->toContain( 'width="1600"' )
		->not->toContain( 'srcset=' )
		->not->toContain( '<picture>' );
} );

it( 'still prints the image when the record cannot produce a url', function (): void {
	$broken = new class( fakeMedia( 7 ) ) {
		public function __construct( protected object $record )
		{
			foreach ( get_object_vars( $record ) as $key => $value ) {
				$this->{$key} = $value;
			}
		}

		public function url(): string
		{
			throw new RuntimeException( 'Disk [gone] does not have a configured driver.' );
		}
	};

	useFakeMedia( [ 7 => $broken ] );

	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/a.jpg' ] ), imageBlock( [ '_resolvedAuthorAvatar' => '/storage/2026/09/hero.jpg' ], 'core/avatar' ) ) )
		->toContain( 'src="/a.jpg"' )
		->toContain( 'src="/storage/2026/09/hero.jpg"' )
		->not->toContain( 'data-ve-unknown-block' );
} );

it( 'still renders when reading the media table fails', function (): void {
	app()->instance( ResponsiveImages::class, new class() extends ResponsiveImages {
		protected function fetchMedia( array $ids ): array
		{
			throw new RuntimeException( 'no such table: media' );
		}
	} );

	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/a.jpg' ] ) ) )->toContain( 'src="/a.jpg"' );
} );

it( 'closes the loop when a block inside it throws', function (): void {
	addFilter( 'ap.visualEditor.beforeRender', function ( array $attributes, string $name ): array {
		if ( 'core/paragraph' === $name ) {
			throw new RuntimeException( 'filter failed' );
		}

		return $attributes;
	} );

	try {
		renderImages( [ 'name' => 'core/post-template', 'attributes' => [], 'innerBlocks' => [
			[ 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
		] ] );
	} catch ( RuntimeException ) {
		// Swallowed, as a block further up the tree would.
	}

	removeAllFilters( 'ap.visualEditor.beforeRender' );

	expect( renderImages( imageBlock( [ '_resolvedImageUrl' => '/hero.jpg' ], 'core/post-featured-image' ) ) )
		->toContain( 'fetchpriority="high"' );
} );

it( 'fills photo grid cells through picture and link wrappers', function ( string $file ): void {
	$css    = (string) file_get_contents( $file );
	$figure = '.has-photo-grid :is(.wp-block-image, .wp-block-artisanpack-image)';

	expect( $css )
		->toContain( $figure . ' > img,' )
		->toContain( $figure . ' > :is(a, picture) > img,' )
		->toContain( $figure . ' > a > picture > img {' )
		->not->toContain( '> :is(img, a > img, picture > img)' );
} )->with( [
	'renderer' => [ dirname( __DIR__, 2 ) . '/resources/assets/frontend/photo-grid.css' ],
	'editor'   => [ dirname( __DIR__, 4 ) . '/resources/js/visual-editor/blocks/_shared/photo-grid/photo-grid.css' ],
] );

it( 'keeps rendering with no media library installed', function (): void {
	app()->forgetInstance( ResponsiveImages::class );
	app()->scoped( ResponsiveImages::class, fn (): ResponsiveImages => new ResponsiveImages() );

	expect( renderImages( imageBlock( [ 'id' => 7, 'url' => '/a.jpg' ] ), imageBlock( [ '_resolvedAuthorAvatar' => '/b.jpg' ], 'core/avatar' ) ) )
		->toContain( 'src="/a.jpg"' )
		->toContain( 'src="/b.jpg"' );
} );
