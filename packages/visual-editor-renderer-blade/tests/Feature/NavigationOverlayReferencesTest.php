<?php

/**
 * A navigation overlay part runs the page's reference passes (#973).
 *
 * The overlay part's blocks are fetched while the page is already
 * rendering, so before #973 they skipped the template-part, synced-pattern
 * and navigation passes `<x-ve-blocks>` runs on the page tree: a menu
 * inside the overlay rendered as an empty `<ul>`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.15.2
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\NavigationBlockRefResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\BlockTreeReferenceResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
use Illuminate\Support\Facades\Blade;

/**
 * Bind a TemplatePartResolver stub that resolves parts by slug.
 *
 * @since 1.15.2
 *
 * @param  array<string, array{area: string, blocks: array<int, array<string, mixed>>}>  $parts  Slug => part.
 */
function bindReferencePartsBySlug( array $parts ): void
{
	$stub = new class( $parts ) {
		/**
		 * @param  array<string, array{area: string, blocks: array<int, array<string, mixed>>}>  $parts
		 */
		public function __construct( private array $parts )
		{
		}

		public function resolve( string $slug ): ?object
		{
			return isset( $this->parts[ $slug ] ) ? (object) $this->parts[ $slug ] : null;
		}
	};

	app()->bind(
		'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver',
		fn () => $stub,
	);
}

/**
 * Bind a NavigationBlockRefResolver that projects one link per
 * `__unstableLocation`, labelled with the theme it resolved against,
 * standing in for cms-framework's menu tables.
 *
 * @since 1.15.2
 */
function bindLocationMenuStub(): void
{
	app()->bind( NavigationBlockRefResolver::class, fn () => new class extends NavigationBlockRefResolver {
		/**
		 * @param  array<string, mixed>  $block
		 *
		 * @return array<string, mixed>
		 */
		protected function resolveBlock( array $block, string $theme ): array
		{
			$innerBlocks = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];
			$location    = $block['attributes']['__unstableLocation'] ?? null;

			if ( 'core/navigation' === ( $block['name'] ?? null ) && is_string( $location ) && [] === $innerBlocks ) {
				$block['innerBlocks'] = [
					[
						'clientId'    => 'link-' . $location,
						'name'        => 'core/navigation-link',
						'attributes'  => [ 'label' => ucfirst( $location ) . ' item from ' . $theme, 'url' => '/' . $location ],
						'innerBlocks' => [],
					],
				];

				return $block;
			}

			$block['innerBlocks'] = $this->resolve( $innerBlocks, $theme );

			return $block;
		}
	} );
}

/**
 * Build a `core/navigation` block.
 *
 * @since 1.15.2
 *
 * @param  array<string, mixed>  $attributes
 *
 * @return array<string, mixed>
 */
function referenceNavBlock( string $clientId, array $attributes ): array
{
	return [ 'clientId' => $clientId, 'name' => 'core/navigation', 'attributes' => $attributes, 'innerBlocks' => [] ];
}

/**
 * Inner HTML of the first overlay-content wrapper in `$html`.
 *
 * @since 1.15.2
 */
function overlayContentOf( string $html ): string
{
	$start = strpos( $html, 'class="wp-block-navigation__overlay-content"' );

	expect( $start )->not->toBeFalse();

	return substr( $html, (int) $start );
}

beforeEach( function (): void {
	app( NavigationOverlayTracker::class )->reset();
	bindLocationMenuStub();
} );

it( 'renders the menu items of a location-based navigation inside the overlay part', function (): void {
	bindReferencePartsBySlug( [
		'mobile-menu' => [
			'area'   => 'navigation-overlay',
			'blocks' => [ referenceNavBlock( 'overlay-nav', [ '__unstableLocation' => 'primary', 'overlayMenu' => 'never' ] ) ],
		],
	] );

	$tree = [ referenceNavBlock( 'header-nav', [ '__unstableLocation' => 'footer', 'overlay' => 'mobile-menu' ] ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="site-theme" />', [ 'tree' => $tree ] );
	$overlay  = overlayContentOf( $rendered );

	expect( $overlay )
		->toContain( 'Primary item from site-theme' )
		->and( $overlay )->toContain( 'href="/primary"' )
		->and( $overlay )->not->toContain( '<ul class="wp-block-navigation__container"></ul>' )
		->and( substr_count( $rendered, 'class="wp-block-navigation__overlay-content"' ) )->toBe( 1 );
} );

it( 'leaves an overlay menu location unresolved when the page has no theme', function (): void {
	bindReferencePartsBySlug( [
		'mobile-menu' => [
			'area'   => 'navigation-overlay',
			'blocks' => [ referenceNavBlock( 'overlay-nav', [ '__unstableLocation' => 'primary', 'overlayMenu' => 'never' ] ) ],
		],
	] );

	$tree = [ referenceNavBlock( 'header-nav', [ 'overlay' => 'mobile-menu' ] ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( overlayContentOf( $rendered ) )->not->toContain( 'Primary item' )
		->and( app( BlockTreeReferenceResolver::class )->currentTheme() )->toBeNull();
} );

it( 'inlines a template part nested in the overlay part', function (): void {
	bindReferencePartsBySlug( [
		'mobile-menu'  => [
			'area'   => 'navigation-overlay',
			'blocks' => [
				[ 'clientId' => 'tp-cta', 'name' => 'core/template-part', 'attributes' => [ 'slug' => 'overlay-cta' ], 'innerBlocks' => [] ],
			],
		],
		'overlay-cta' => [
			'area'   => 'uncategorized',
			'blocks' => [
				[ 'clientId' => 'p-cta', 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'Nested overlay CTA' ], 'innerBlocks' => [] ],
				referenceNavBlock( 'cta-nav', [ '__unstableLocation' => 'social', 'overlayMenu' => 'never' ] ),
			],
		],
	] );

	$tree = [ referenceNavBlock( 'header-nav', [ 'overlay' => 'mobile-menu' ] ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="site-theme" />', [ 'tree' => $tree ] );
	$overlay  = overlayContentOf( $rendered );

	expect( $overlay )
		->toContain( 'Nested overlay CTA' )
		->and( $overlay )->toContain( 'Social item from site-theme' );
} );

it( 'still stops an overlay whose resolved navigation points back at itself', function (): void {
	bindReferencePartsBySlug( [
		'mobile-menu' => [
			'area'   => 'navigation-overlay',
			'blocks' => [
				[ 'clientId' => 'p-self', 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'Self overlay body' ], 'innerBlocks' => [] ],
				referenceNavBlock( 'overlay-nav', [ '__unstableLocation' => 'primary', 'overlay' => 'mobile-menu' ] ),
			],
		],
	] );

	$tree = [ referenceNavBlock( 'header-nav', [ '__unstableLocation' => 'primary', 'overlay' => 'mobile-menu' ] ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="site-theme" />', [ 'tree' => $tree ] );

	expect( substr_count( $rendered, 'class="wp-block-navigation__overlay-content"' ) )->toBe( 1 )
		->and( substr_count( $rendered, 'Self overlay body' ) )->toBe( 1 )
		->and( overlayContentOf( $rendered ) )->toContain( 'Primary item from site-theme' );
} );

it( 'restores the previous theme after a render, including one that throws', function (): void {
	$references = app( BlockTreeReferenceResolver::class );

	$seen = $references->withTheme( 'outer', fn (): array => [
		$references->currentTheme(),
		$references->withTheme( 'inner', fn (): ?string => $references->currentTheme() ),
		$references->currentTheme(),
	] );

	expect( $seen )->toBe( [ 'outer', 'inner', 'outer' ] )
		->and( $references->currentTheme() )->toBeNull();

	expect( fn () => $references->withTheme( 'boom', function (): never {
		throw new RuntimeException( 'render failed' );
	} ) )->toThrow( RuntimeException::class );

	expect( $references->currentTheme() )->toBeNull();
} );
