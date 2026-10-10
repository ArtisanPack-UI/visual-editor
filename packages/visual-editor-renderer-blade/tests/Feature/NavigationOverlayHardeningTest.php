<?php

/**
 * Pre-release hardening for the `core/navigation` overlay (1.13.0 review).
 *
 * Covers RN-1 (no `aria-hidden` / static dialog semantics), RN-2
 * (overlay recursion guard), RN-3 (visibility-emptied overlay falls back
 * to the menu), RN-4 (nav-level custom colors sanitized), RN-5
 * (`aria-expanded` / `aria-controls` + focus wrap), RN-6 (link click
 * closes the drawer), RN-7 (overlay preset slugs sanitized) and RN-11
 * (nested `render()` keeps renderer state).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
use Illuminate\Support\Facades\Blade;

/**
 * Bind a TemplatePartResolver stub that resolves overlay parts by slug.
 *
 * @since 1.13.0
 *
 * @param  array<string, array<int, array<string, mixed>>>  $parts  Slug => overlay blocks.
 */
function bindOverlayResolverBySlug( array $parts ): void {
	$stub = new class( $parts ) {
		/**
		 * @param  array<string, array<int, array<string, mixed>>>  $parts
		 */
		public function __construct( private array $parts ) {}

		public function resolve( string $slug ): ?object
		{
			if ( ! array_key_exists( $slug, $this->parts ) ) {
				return null;
			}

			return (object) [
				'area'   => 'navigation-overlay',
				'blocks' => $this->parts[ $slug ],
			];
		}
	};

	app()->bind(
		'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver',
		fn () => $stub,
	);
}

/**
 * Build a `core/navigation` block pointing at the given overlay slug.
 *
 * @since 1.13.0
 *
 * @param  array<int, array<string, mixed>>  $innerBlocks
 *
 * @return array<string, mixed>
 */
function overlayNavBlock( string $overlaySlug, array $innerBlocks = [] ): array {
	return [
		'clientId'    => 'nav-' . $overlaySlug,
		'name'        => 'core/navigation',
		'attributes'  => [ 'overlay' => $overlaySlug ],
		'innerBlocks' => $innerBlocks,
	];
}

/**
 * Build a `core/paragraph` block.
 *
 * @since 1.13.0
 *
 * @param  array<string, mixed>  $extraAttributes
 *
 * @return array<string, mixed>
 */
function overlayParagraph( string $content, array $extraAttributes = [] ): array {
	return [
		'clientId'    => 'p-' . md5( $content ),
		'name'        => 'core/paragraph',
		'attributes'  => array_merge( [ 'content' => $content ], $extraAttributes ),
		'innerBlocks' => [],
	];
}

beforeEach( function () {
	app( NavigationOverlayTracker::class )->reset();
} );

// RN-1 ----------------------------------------------------------------

it( 'never puts aria-hidden or static dialog semantics on a closed default nav (RN-1)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'clientId'    => 'l-1',
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( '<div class="wp-block-navigation__responsive-container" id="ap-modal-nav-1">' )
		->and( $rendered )->toContain( '<div class="wp-block-navigation__responsive-dialog">' )
		->and( $rendered )->not->toMatch( '/<div[^>]+wp-block-navigation__responsive-container"[^>]*aria-hidden/' )
		->and( $rendered )->not->toContain( 'role="dialog"' )
		->and( $rendered )->not->toContain( 'aria-modal="true"' );
} );

it( 'adds the dialog semantics only from the toggle script open/close handlers (RN-1)', function () {
	$tree = [ [ 'clientId' => 'nav-1', 'name' => 'core/navigation', 'attributes' => [], 'innerBlocks' => [] ] ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( "d.setAttribute('role','dialog')" )
		->and( $rendered )->toContain( "d.setAttribute('aria-modal','true')" )
		->and( $rendered )->toContain( "d.setAttribute('aria-label'" )
		->and( $rendered )->toContain( "d.removeAttribute('role')" )
		->and( $rendered )->toContain( "d.removeAttribute('aria-modal')" )
		->and( $rendered )->toContain( "d.removeAttribute('aria-label')" )
		->and( $rendered )->not->toContain( "'aria-hidden'" );
} );

// RN-5 ----------------------------------------------------------------

it( 'renders aria-expanded and aria-controls on the open button (RN-5)', function () {
	$tree = [ [ 'clientId' => 'nav-1', 'name' => 'core/navigation', 'attributes' => [], 'innerBlocks' => [] ] ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( '<button type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="ap-modal-nav-1" aria-label="Menu" class="wp-block-navigation__responsive-container-open" data-ap-nav-overlay-open="ap-modal-nav-1">' )
		->and( $rendered )->toContain( "t.setAttribute('aria-expanded','true')" )
		->and( $rendered )->toContain( "t.setAttribute('aria-expanded','false')" );
} );

it( 'wraps Tab and Shift+Tab focus inside the open dialog (RN-5)', function () {
	$tree = [ [ 'clientId' => 'nav-1', 'name' => 'core/navigation', 'attributes' => [], 'innerBlocks' => [] ] ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( "e.key!=='Tab'" )
		->and( $rendered )->toContain( 'if(e.shiftKey&&(cur===first||!inside)){e.preventDefault();last.focus();}' )
		->and( $rendered )->toContain( 'else if(!e.shiftKey&&(cur===last||!inside)){e.preventDefault();first.focus();}' );
} );

// RN-6 ----------------------------------------------------------------

it( 'closes an open drawer when a link inside it is clicked, without preventDefault or focus stealing (RN-6)', function () {
	$tree = [ [ 'clientId' => 'nav-1', 'name' => 'core/navigation', 'attributes' => [], 'innerBlocks' => [] ] ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( "var a=e.target.closest('a[href]');if(a){var m=a.closest('.wp-block-navigation__responsive-container.is-menu-open');if(m)close(m,true);return;}" )
		->and( $rendered )->toContain( 'if(!keepFocus)t.focus();' );
} );

// RN-2 ----------------------------------------------------------------

it( 'stops a self-referencing overlay part after one level (RN-2)', function () {
	bindOverlayResolverBySlug( [
		'mobile-overlay' => [
			overlayParagraph( 'Self overlay CTA' ),
			overlayNavBlock( 'mobile-overlay' ),
		],
	] );

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ overlayNavBlock( 'mobile-overlay' ) ] ] );

	expect( substr_count( $rendered, 'class="wp-block-navigation__overlay-content"' ) )->toBe( 1 )
		->and( substr_count( $rendered, 'Self overlay CTA' ) )->toBe( 1 );
} );

it( 'stops an A -> B -> A overlay cycle (RN-2)', function () {
	bindOverlayResolverBySlug( [
		'overlay-a' => [ overlayParagraph( 'From A' ), overlayNavBlock( 'overlay-b' ) ],
		'overlay-b' => [ overlayParagraph( 'From B' ), overlayNavBlock( 'overlay-a' ) ],
	] );

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ overlayNavBlock( 'overlay-a' ) ] ] );

	expect( substr_count( $rendered, 'class="wp-block-navigation__overlay-content"' ) )->toBe( 2 )
		->and( substr_count( $rendered, 'From A' ) )->toBe( 1 )
		->and( substr_count( $rendered, 'From B' ) )->toBe( 1 );
} );

it( 'still renders the same overlay for sibling navs once the first has unwound (RN-2)', function () {
	bindOverlayResolverBySlug( [
		'mobile-overlay' => [ overlayParagraph( 'Shared overlay' ) ],
	] );

	$tree = [ overlayNavBlock( 'mobile-overlay' ), overlayNavBlock( 'mobile-overlay' ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( substr_count( $rendered, 'Shared overlay' ) )->toBe( 2 );
} );

it( 'tracks the overlay stack with cycle detection, a depth cap and reset (RN-2)', function () {
	$tracker = new NavigationOverlayTracker();

	expect( $tracker->enterOverlay( 'a' ) )->toBeTrue()
		->and( $tracker->enterOverlay( 'a' ) )->toBeFalse();

	$tracker->leaveOverlay();

	expect( $tracker->enterOverlay( 'a' ) )->toBeTrue();

	for ( $i = 1; $i < NavigationOverlayTracker::MAX_OVERLAY_DEPTH; $i++ ) {
		expect( $tracker->enterOverlay( 'part-' . $i ) )->toBeTrue();
	}

	expect( $tracker->enterOverlay( 'one-too-many' ) )->toBeFalse();

	$tracker->nextOverlayId();
	$tracker->markScriptEmitted();
	$tracker->reset();

	expect( $tracker->hasEmittedScript() )->toBeFalse()
		->and( $tracker->nextOverlayId() )->toBe( 'ap-modal-nav-1' )
		->and( $tracker->enterOverlay( 'a' ) )->toBeTrue();
} );

// RN-3 ----------------------------------------------------------------

it( 'falls back to the menu when every overlay block is hidden by visibility (RN-3)', function () {
	bindOverlayResolverBySlug( [
		'mobile-overlay' => [
			overlayParagraph( 'Hidden overlay CTA', [ 'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ] ] ),
		],
	] );

	$tree = [
		overlayNavBlock( 'mobile-overlay', [
			[
				'clientId'    => 'l-1',
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
				'innerBlocks' => [],
			],
		] ),
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->not->toContain( 'responsive-container-content has-overlay-template' )
		->and( $rendered )->not->toContain( 'class="wp-block-navigation__overlay-content"' )
		->and( $rendered )->not->toContain( 'Hidden overlay CTA' )
		->and( $rendered )->toContain( '<ul class="wp-block-navigation__container">' )
		->and( $rendered )->toContain( 'href="/"' );
} );

// RN-4 ----------------------------------------------------------------

it( 'drops nav custom colors that would inject extra declarations (RN-4)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'customBackgroundColor' => '#000; position:fixed; inset:0; z-index:99999',
				'customTextColor'       => 'red;background-image:url(x)',
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	preg_match( '/<nav[^>]*>/', $rendered, $navTag );

	expect( $rendered )
		->not->toContain( 'position:fixed' )
		->and( $rendered )->not->toContain( 'background-image' )
		->and( $navTag[0] )->not->toContain( 'has-background' )
		->and( $navTag[0] )->not->toContain( 'has-text-color' )
		->and( $navTag[0] )->not->toContain( 'style=' );
} );

it( 'keeps safe nav custom colors (RN-4)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'customBackgroundColor' => '#111111',
				'customTextColor'       => 'rgb(255, 255, 255)',
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	preg_match( '/<nav[^>]*>/', $rendered, $navTag );

	expect( $navTag[0] )
		->toContain( 'has-text-color' )
		->and( $navTag[0] )->toContain( 'has-background' )
		->and( $navTag[0] )->toContain( 'style="color: rgb(255, 255, 255); background-color: #111111"' );
} );

// RN-7 ----------------------------------------------------------------

it( 'reduces overlay preset slugs to a single sanitized class each (RN-7)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'overlayBackgroundColor' => 'x is-menu-open',
				'overlayTextColor'       => 'Brand "Accent"',
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( '<div class="wp-block-navigation__responsive-container has-x-is-menu-open-background-color has-background has-brand-accent-color has-text-color" id="ap-modal-nav-1">' )
		->and( $rendered )->not->toMatch( '/class="(?:[^"]* )?is-menu-open[ "]/' );
} );

it( 'emits no overlay preset class when the slug sanitizes to empty (RN-7)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'overlayBackgroundColor' => '"  ;',
				'overlayTextColor'       => '!!!',
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( '<div class="wp-block-navigation__responsive-container" id="ap-modal-nav-1">' );
} );

it( 'exposes slugify publicly for partials (RN-7)', function () {
	expect( BlockSupports::slugify( '  Primary Accent ' ) )->toBe( 'primary-accent' )
		->and( BlockSupports::slugify( 'x is-menu-open' ) )->toBe( 'x-is-menu-open' )
		->and( BlockSupports::slugify( '"\';' ) )->toBe( '' );
} );

// RN-11 ---------------------------------------------------------------

it( 'keeps innerDepth balanced after a nested overlay render (RN-11)', function () {
	bindOverlayResolverBySlug( [
		'mobile-overlay' => [ overlayParagraph( 'Overlay body' ) ],
	] );

	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'clientId'    => 'g-1',
			'name'        => 'core/group',
			'attributes'  => [],
			'innerBlocks' => [
				overlayNavBlock( 'mobile-overlay' ),
				[
					'clientId'    => 'g-2',
					'name'        => 'core/group',
					'attributes'  => [],
					'innerBlocks' => [
						[
							'clientId'    => 'g-3',
							'name'        => 'core/group',
							'attributes'  => [],
							'innerBlocks' => [ overlayParagraph( 'Deep paragraph' ) ],
						],
					],
				],
			],
		],
	];

	$html = $renderer->render( $tree );

	$innerDepth = ( new ReflectionProperty( BlockRenderer::class, 'innerDepth' ) )->getValue( $renderer );

	expect( $html )->toContain( 'Overlay body' )
		->and( $html )->toContain( 'Deep paragraph' )
		->and( $innerDepth )->toBe( 0 );
} );

it( 'gives a search field inside the overlay and one on the page distinct ids (RN-11)', function () {
	$searchField = [
		'clientId'    => 'sf-1',
		'name'        => 'artisanpack/search-field',
		'attributes'  => [],
		'innerBlocks' => [],
	];

	bindOverlayResolverBySlug( [
		'mobile-overlay' => [ $searchField ],
	] );

	$html = app( BlockRenderer::class )->render( [ $searchField, overlayNavBlock( 'mobile-overlay' ) ] );

	preg_match_all( '/id="(ap-search-field-[^"]+)"/', $html, $matches );

	expect( $matches[1] )->toHaveCount( 2 )
		->and( array_unique( $matches[1] ) )->toHaveCount( 2 );
} );

it( 'still applies the whole-content filter exactly once around a nested overlay render (RN-11)', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'artisanpack-ui/hooks is not installed.' );
	}

	bindOverlayResolverBySlug( [
		'mobile-overlay' => [ overlayParagraph( 'Overlay body' ) ],
	] );

	$calls    = 0;
	$callback = function ( string $html ) use ( &$calls ): string {
		$calls++;

		return $html . '<!-- filtered -->';
	};

	addFilter( 'ap.visualEditor.renderedContent', $callback );

	try {
		$html = app( BlockRenderer::class )->render( [ overlayNavBlock( 'mobile-overlay' ) ] );
	} finally {
		removeFilter( 'ap.visualEditor.renderedContent', $callback );
	}

	expect( $calls )->toBe( 1 )
		->and( substr_count( $html, '<!-- filtered -->' ) )->toBe( 1 )
		->and( $html )->toContain( 'Overlay body' );
} );
