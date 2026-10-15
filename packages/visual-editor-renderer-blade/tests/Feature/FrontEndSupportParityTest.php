<?php

/**
 * Front-end parity for controls the editor offers but the renderer used
 * to drop:
 * - #873: Group background image panel, Image aspect ratio / scale, and
 *   the duotone filter.
 * - #874: block supports on dynamic (PHP-rendered) blocks.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\ClosureDynamicBlock;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Blocks\Icon\IconBlock;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;
use Illuminate\Support\Facades\Blade;

/**
 * Render a single block through `<x-ve-blocks>`.
 *
 * @param  array<string, mixed>  $attributes
 */
function renderSupportParityBlock( string $name, array $attributes ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [
		'tree' => [ [ 'clientId' => 'c1', 'name' => $name, 'attributes' => $attributes, 'innerBlocks' => [] ] ],
	] );
}

describe( 'group background (#873)', function (): void {
	it( 'renders the background image panel on the wrapper', function (): void {
		$html = renderSupportParityBlock( 'core/group', [
			'style' => [
				'background' => [
					'backgroundImage'    => [ 'url' => 'https://example.com/hero.jpg', 'id' => 3 ],
					'backgroundPosition' => '50% 0%',
					'backgroundRepeat'   => 'no-repeat',
				],
			],
		] );

		expect( html_entity_decode( $html ) )->toContain(
			"background-image: url('https://example.com/hero.jpg'); background-size: cover; background-position: 50% 0%; background-repeat: no-repeat;",
		);
	} );
} );

describe( 'image aspect ratio, scale and duotone (#873)', function (): void {
	it( 'puts aspect-ratio and object-fit on the img', function (): void {
		$html = renderSupportParityBlock( 'core/image', [
			'url'         => 'https://example.com/a.jpg',
			'aspectRatio' => '16/9',
			'scale'       => 'cover',
		] );

		expect( $html )->toContain( '<img src="https://example.com/a.jpg" alt="" style="aspect-ratio:16/9;object-fit:cover"/>' );
	} );

	it( 'drops an unknown scale and a hostile ratio', function (): void {
		$html = renderSupportParityBlock( 'core/image', [
			'url'         => 'https://example.com/a.jpg',
			'aspectRatio' => '16/9;position:fixed',
			'scale'       => 'stretch',
		] );

		expect( $html )->toContain( '<img src="https://example.com/a.jpg" alt=""/>' );
	} );

	it( 'applies a custom duotone and emits its filter once', function (): void {
		$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [
			'tree' => [
				[ 'clientId' => 'a', 'name' => 'core/image', 'attributes' => [ 'url' => '/a.jpg', 'style' => [ 'color' => [ 'duotone' => [ '#000000', '#ff0000' ] ] ] ], 'innerBlocks' => [] ],
				[ 'clientId' => 'b', 'name' => 'core/image', 'attributes' => [ 'url' => '/b.jpg', 'style' => [ 'color' => [ 'duotone' => [ '#000000', '#ff0000' ] ] ] ], 'innerBlocks' => [] ],
			],
		] );

		preg_match( '/class="wp-block-image (wp-duotone-[0-9a-f]{10})"/', $html, $matches );
		$class = $matches[1] ?? '';

		expect( $class )->not->toBe( '' )
			->and( $html )->toContain( sprintf( '<style data-ve-duotone>.%1$s img{filter:url(#%1$s) !important}</style>', $class ) )
			->and( substr_count( $html, sprintf( '<filter id="%s">', $class ) ) )->toBe( 1 )
			->and( $html )->toContain( '<feFuncR type="table" tableValues="0 1"/>' );
	} );

	it( 'prints each duotone filter once across two renders on a page (R-10)', function (): void {
		$tree = [ [ 'clientId' => 'a', 'name' => 'core/image', 'attributes' => [ 'url' => '/a.jpg', 'style' => [ 'color' => [ 'duotone' => [ '#000000', '#ff0000' ] ] ] ], 'innerBlocks' => [] ] ];

		$html = Blade::render( '<x-ve-blocks :tree="$tree" /><x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

		preg_match_all( '/<filter id="([^"]+)">/', $html, $matches );

		expect( $matches[1] )->toHaveCount( 1 )
			->and( substr_count( $html, 'class="wp-block-image wp-duotone-' ) )->toBe( 2 );
	} );

	it( 'emits no duotone for a featured image block without an image (R-11)', function (): void {
		$html = renderSupportParityBlock( 'core/post-featured-image', [
			'style' => [ 'color' => [ 'duotone' => [ '#000000', '#ff0000' ] ] ],
		] );

		expect( $html )->not->toContain( 'wp-duotone-' )
			->not->toContain( 'data-ve-duotone' )
			->not->toContain( '<svg' );
	} );

	it( 'emits no duotone for an image block without a url', function (): void {
		expect( renderSupportParityBlock( 'core/image', [ 'style' => [ 'color' => [ 'duotone' => [ '#000', '#f00' ] ] ] ] ) )
			->not->toContain( 'wp-duotone-' )
			->not->toContain( 'data-ve-duotone' );
	} );

	it( 'applies duotone to the post featured image too', function (): void {
		$html = renderSupportParityBlock( 'core/post-featured-image', [
			'_resolvedImageUrl' => '/a.jpg',
			'style'             => [ 'color' => [ 'duotone' => 'unset' ] ],
		] );

		expect( $html )->toContain( 'wp-duotone-unset' )
			->toContain( '.wp-duotone-unset img{filter:none !important}' );
	} );
} );

describe( 'duotone on cover, avatar, site logo and post author (F-2)', function (): void {
	it( 'applies the filter to each block\'s media', function ( string $name, array $attributes, string $wrapperStart, string $target ): void {
		$attributes['style'] = [ 'color' => [ 'duotone' => [ '#000000', '#ff0000' ] ] ];

		$html = renderSupportParityBlock( $name, $attributes );

		preg_match( '/(wp-duotone-[0-9a-f]{10})/', $html, $matches );
		$class = $matches[1] ?? '';

		expect( $class )->not->toBe( '' )
			->and( $html )->toContain( $wrapperStart . ' ' . $class )
			->toContain( '<style data-ve-duotone>' . str_replace( '{c}', $class, $target ) . '{filter:url(#' . $class . ') !important}</style>' )
			->toContain( '<filter id="' . $class . '">' );
	} )->with( [
		'cover'       => [ 'core/cover', [ 'url' => '/bg.jpg' ], 'class="wp-block-cover is-dark is-layout-flex wp-block-cover-is-layout-flex', '.{c} > .wp-block-cover__image-background,.{c} > .wp-block-cover__video-background' ],
		'avatar'      => [ 'core/avatar', [ '_resolvedAuthorAvatar' => '/a.png' ], 'class="wp-block-avatar', '.{c} img' ],
		'site logo'   => [ 'core/site-logo', [ '_resolvedLogoUrl' => '/logo.png' ], 'class="wp-block-site-logo is-default-size', '.{c} img' ],
		'post author' => [ 'core/post-author', [ '_resolvedAuthorAvatar' => '/a.png', '_resolvedAuthorName' => 'Ada' ], 'class="wp-block-post-author', '.{c} .wp-block-post-author__avatar img' ],
	] );

	it( 'skips the filter when the media is not rendered', function ( string $name, array $attributes ): void {
		$attributes['style'] = [ 'color' => [ 'duotone' => [ '#000000', '#ff0000' ] ] ];

		expect( renderSupportParityBlock( $name, $attributes ) )
			->not->toContain( 'wp-duotone-' )
			->not->toContain( 'data-ve-duotone' );
	} )->with( [
		'cover without url'          => [ 'core/cover', [] ],
		'cover with hostile url'     => [ 'core/cover', [ 'url' => 'javascript:alert(1)' ] ],
		'avatar without image'       => [ 'core/avatar', [] ],
		'site logo without image'    => [ 'core/site-logo', [] ],
		'post author, avatar hidden' => [ 'core/post-author', [ '_resolvedAuthorAvatar' => '/a.png', 'showAvatar' => false ] ],
	] );
} );

describe( 'dynamic block supports (#874)', function (): void {
	it( 'merges declared supports into a dynamic block wrapper', function (): void {
		app( DynamicBlockRegistry::class )->register( new ClosureDynamicBlock(
			blockName: 'acme/terminal',
			renderCallback: static fn ( array $attrs ): string => '<div class="wp-block-acme-terminal"><code>$ composer require</code></div>',
		) );

		$html = renderSupportParityBlock( 'acme/terminal', [
			'anchor' => 'install',
			'align'  => 'wide',
			'style'  => [ 'spacing' => [ 'padding' => [ 'top' => '2rem', 'bottom' => 'var:preset|spacing|40' ] ] ],
			'states' => [ '_scopeId' => 'term', 'style.border.color' => [ 'hover' => 'var:preset|color|primary' ] ],
		] );

		expect( $html )
			->toContain( '<div class="wp-block-acme-terminal alignwide ap-state-term" style="padding-top: 2rem; padding-bottom: var(--wp--preset--spacing--40);" id="install">' )
			->toContain( 'border-color: var(--wp--preset--color--primary) !important;' );
	} );

	it( 'leaves blocks that apply their own supports alone', function (): void {
		app( DynamicBlockRegistry::class )->register( new ClosureDynamicBlock(
			blockName: 'acme/own-supports',
			renderCallback: static fn ( array $attrs ): string => '<div class="own">x</div>',
			ownSupports: true,
		) );

		expect( renderSupportParityBlock( 'acme/own-supports', [ 'anchor' => 'a', 'className' => 'b' ] ) )
			->toContain( '<div class="own">x</div>' );
	} );

	it( 'lets a block reshape the attributes supports compile from', function (): void {
		app( DynamicBlockRegistry::class )->register( new class extends DynamicBlock {
			public function name(): string
			{
				return 'acme/reshaped';
			}

			public function render( array $attrs )
			{
				return '<p>x</p>';
			}

			public function supportAttributes( array $attrs ): array
			{
				unset( $attrs['className'] );

				return $attrs;
			}
		} );

		expect( renderSupportParityBlock( 'acme/reshaped', [ 'className' => 'gone', 'anchor' => 'kept' ] ) )
			->toContain( '<p id="kept">x</p>' );
	} );

	it( 'renders an Icon block gradient on the front end', function (): void {
		app( DynamicBlockRegistry::class )->register( new IconBlock( new SvgSanitizer(), new IconSvgResolver( [] ) ) );

		$html = renderSupportParityBlock( 'artisanpack/icon', [
			'customSvg' => '<svg viewBox="0 0 24 24"><path d="M0 0"/></svg>',
			'gradient'  => 'sunset',
			'anchor'    => 'icon-tile',
			'style'     => [ 'spacing' => [ 'padding' => 'var:preset|spacing|30' ] ],
		] );

		expect( $html )->toContain( 'has-sunset-gradient-background' )
			->toContain( 'id="icon-tile"' )
			->toContain( 'padding: var(--wp--preset--spacing--30)' )
			->and( substr_count( $html, 'has-background' ) )->toBe( 1 );
	} );
} );

it( 'writes a tiny cover min-height without exponent notation (R-13)', function (): void {
	expect( renderSupportParityBlock( 'core/cover', [ 'minHeight' => 1e-8, 'minHeightUnit' => 'vh' ] ) )
		->toContain( 'min-height: 0vh;' )
		->not->toContain( 'E-8' );
} );
