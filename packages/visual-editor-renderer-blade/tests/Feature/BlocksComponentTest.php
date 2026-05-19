<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders the x-ve-blocks component from an array tree', function () {
	$tree = [
		[
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Hello from Blade' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toBe( '<p class="wp-block-paragraph">Hello from Blade</p>' );
} );

it( 'accepts a JSON string tree', function () {
	$json = json_encode( [
		[
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'JSON string' ],
			'innerBlocks' => [],
		],
	], JSON_THROW_ON_ERROR );

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $json ] );

	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toBe( '<p class="wp-block-paragraph">JSON string</p>' );
} );

it( 'renders an empty output when the tree is null and cms-framework is not installed', function () {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => null ] );

	// #434: the legacy `GlobalStylesCssProvider` used to emit bundled
	// defaults when no DB record existed, so a null tree still produced
	// a `<style>` block. Without cms-framework's emitter in this test
	// environment, the resolver returns an empty string and no `<style>`
	// is rendered.
	expect( $rendered )->not->toContain( 'data-ve-global-styles' );
	expect( trim( $rendered ) )->toBe( '' );
} );

it( 'publishes block views under the visual-editor-blade-views tag', function () {
	$tag = 'visual-editor-blade-views';

	$artisan = $this->artisan( 'vendor:publish', [ '--tag' => $tag, '--force' => true ] );

	$artisan->assertExitCode( 0 );
} );

it( 'passes the nav-block tree through unchanged when no defaultTheme is supplied (Keystone #51)', function () {
	// Without a theme, the navigation resolver has no `(theme, location)`
	// to query against, so the tree should pass through. The block's
	// own Blade view renders an empty `<nav>` — same as before #51.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [ '__unstableLocation' => 'primary' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'wp-block-navigation' );
	// Empty container — no menu items projected.
	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toContain( '<ul class="wp-block-navigation__container"></ul>' );
} );

it( 'passes the nav-block tree through unchanged when cms-framework is not installed (Keystone #51)', function () {
	// cms-framework's `MenuLocationAssignment` model isn't autoloaded in
	// this Testbench environment, so the resolver's `class_exists` guard
	// short-circuits the lookup. With a theme present but no DB to
	// query, the tree still passes through — no menu items, no error.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [ '__unstableLocation' => 'primary' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render(
		'<x-ve-blocks :tree="$tree" default-theme="jmwd-default" />',
		[ 'tree' => $tree ],
	);

	expect( $rendered )->toContain( 'wp-block-navigation' );
	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toContain( '<ul class="wp-block-navigation__container"></ul>' );
} );

it( 'renders style.elements.link.color.text on the nav block as a scoped style + class (Keystone #56)', function () {
	// The dedicated Link color picker on core/navigation writes to
	// `style.elements.link.color.text` — a path separate from the
	// `textColor` / `customTextColor` attributes. Renderer-blade now
	// compiles it into a per-block `wp-elements-{hash}` class on the
	// wrapper plus a scoped `<style>` rule above the `<nav>`.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'style' => [ 'elements' => [
					'link' => [
						'color'  => [ 'text' => 'var:preset|color|accent' ],
						':hover' => [ 'color' => [ 'text' => '#0f172a' ] ],
					],
				] ],
			],
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

	// The wrapper picks up a single hash class shared by the scoped style.
	expect( $rendered )->toMatch( '/<nav[^>]*class="[^"]*wp-elements-[a-f0-9]{8}[^"]*"/' );

	// Preset slug expanded into a CSS custom property reference,
	// scoped to descendant `a` elements, with !important to defeat
	// the theme's default link color.
	expect( $rendered )->toContain( 'a{color: var(--wp--preset--color--accent) !important;}' );

	// Hover pseudo-state emitted alongside the base rule.
	expect( $rendered )->toContain( 'a:hover{color: #0f172a !important;}' );
} );

it( 'emits items-justified-* class from layout.justifyContent (Keystone #52)', function () {
	// Gutenberg writes the modern flex-layout justification to
	// `attributes.layout.justifyContent`. The legacy top-level
	// `itemsJustification` attribute is still emitted by some older
	// saves. Honor both, modern path winning, so a nav block aligned
	// right in the editor canvas aligns right on the front-end too.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'layout' => [ 'type' => 'flex', 'justifyContent' => 'right' ],
			],
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

	expect( $rendered )->toContain( 'items-justified-right' );
} );

it( 'falls back to the legacy itemsJustification attribute when layout.justifyContent is absent (Keystone #52)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [ 'itemsJustification' => 'center' ],
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

	expect( $rendered )->toContain( 'items-justified-center' );
} );

it( 'renders the overlay container + hamburger toggle by default (Keystone #54)', function () {
	// `overlayMenu` defaults to "mobile" so a nav block authored
	// without an explicit value still gets the responsive overlay
	// scaffolding on the front-end. The bundled style.css's
	// breakpoint media queries then collapse it on small screens.
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

	app( \ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker::class )->reset();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( 'wp-block-navigation__responsive-container-open' )
		->and( $rendered )->toContain( 'wp-block-navigation__responsive-container' )
		->and( $rendered )->toContain( 'wp-block-navigation__responsive-dialog' )
		->and( $rendered )->toContain( 'wp-block-navigation__responsive-container-close' )
		->and( $rendered )->toContain( 'data-ap-nav-overlay-open="ap-modal-nav-1"' )
		->and( $rendered )->toContain( 'id="ap-modal-nav-1"' )
		->and( $rendered )->toContain( 'aria-hidden="true"' )
		->and( $rendered )->toContain( 'aria-modal="true"' )
		->and( $rendered )->toContain( 'role="dialog"' )
		// Inline toggle script emitted once.
		->and( $rendered )->toContain( '__apNavOverlayInit' );
} );

it( 'distinguishes the backdrop wrapper from the close button so link clicks inside the dialog navigate (CodeRabbit follow-up on #54)', function () {
	// Backdrop carries `data-ap-nav-overlay-backdrop`, close button
	// carries `data-ap-nav-overlay-close`. The JS handler only
	// treats backdrop clicks as close when the click target IS the
	// backdrop (not a descendant). Without this split a menu-link
	// click inside the dialog bubbles up to the backdrop, matches
	// `closest("[data-ap-nav-overlay-close]")`, and `preventDefault`
	// cancels the link navigation.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [],
			'innerBlocks' => [],
		],
	];

	app( \ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker::class )->reset();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// Backdrop wrapper — close-on-self only.
	expect( $rendered )->toMatch( '/<div class="wp-block-navigation__responsive-close"[^>]*data-ap-nav-overlay-backdrop/' );
	// Close button — intentional close trigger.
	expect( $rendered )->toMatch( '/<button[^>]+class="wp-block-navigation__responsive-container-close"[^>]+data-ap-nav-overlay-close/' );
	// Backdrop does NOT carry data-ap-nav-overlay-close — would
	// re-introduce the bubbling-click-cancels-link bug.
	expect( $rendered )->not->toMatch( '/<div class="wp-block-navigation__responsive-close"[^>]*data-ap-nav-overlay-close/' );
} );

it( 'adds the is-always-overlay class when overlayMenu is "always" (Keystone #54)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'always' ],
			'innerBlocks' => [],
		],
	];

	app( \ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker::class )->reset();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( 'is-always-overlay' )
		->and( $rendered )->toContain( 'wp-block-navigation__responsive-container-open' );
} );

it( 'skips overlay scaffolding entirely when overlayMenu is "never" (Keystone #54)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'never' ],
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

	app( \ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker::class )->reset();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->not->toContain( 'wp-block-navigation__responsive-container' )
		->and( $rendered )->not->toContain( 'wp-block-navigation__responsive-container-open' )
		->and( $rendered )->not->toContain( '__apNavOverlayInit' )
		// Inline `<ul>` still renders with the menu item.
		->and( $rendered )->toContain( 'wp-block-navigation__container' )
		->and( $rendered )->toContain( 'href="/"' )
		->and( $rendered )->toContain( 'Home' );
} );

it( 'applies overlayBackgroundColor + overlayTextColor presets to the responsive container (Keystone #54)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'overlayBackgroundColor' => 'page',
				'overlayTextColor'       => 'accent',
			],
			'innerBlocks' => [],
		],
	];

	app( \ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker::class )->reset();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// Overlay container picks up the preset classes — bound to
	// `.has-{slug}-*` rules by cms-framework's emitter (Keystone #53).
	expect( $rendered )
		->toMatch( '/<div class="[^"]*has-page-background-color[^"]*has-background[^"]*"[^>]*id="ap-modal-nav-1"/' )
		->and( $rendered )->toContain( 'has-accent-color' )
		->and( $rendered )->toContain( 'has-text-color' );
} );

it( 'applies customOverlayBackgroundColor + customOverlayTextColor as inline styles on the responsive container (Keystone #54)', function () {
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [
				'customOverlayBackgroundColor' => '#0a0606',
				'customOverlayTextColor'       => '#ffffff',
			],
			'innerBlocks' => [],
		],
	];

	app( \ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker::class )->reset();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// Inline style wins over the bundled `background-color: inherit`
	// rule — overlay no longer inherits the parent nav's color.
	expect( $rendered )
		->toMatch( '/<div class="[^"]*wp-block-navigation__responsive-container[^"]*"[^>]*id="ap-modal-nav-1"[^>]*style="background-color: #0a0606; color: #ffffff"/' );
} );

it( 'emits the overlay toggle script exactly once even with multiple nav blocks (Keystone #54)', function () {
	// Two nav blocks on the same page get distinct overlay ids and
	// share a single inline `<script>` — the tracker gates emission
	// so the toggle controller only initializes once.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'nav-2',
			'name'        => 'core/navigation',
			'attributes'  => [],
			'innerBlocks' => [],
		],
	];

	app( \ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker::class )->reset();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( substr_count( $rendered, 'data-ap-nav-overlay-open="ap-modal-nav-1"' ) )->toBe( 1 );
	expect( substr_count( $rendered, 'data-ap-nav-overlay-open="ap-modal-nav-2"' ) )->toBe( 1 );
	// One `<script>` block regardless of how many nav blocks fired —
	// the comment marker is unique to the controller header.
	expect( substr_count( $rendered, 'Keystone #54 — nav overlay toggle' ) )->toBe( 1 );
} );

it( 'preserves authored innerBlocks on a nav block instead of overwriting them (Keystone #51)', function () {
	// A nav block authored with explicit nav-links keeps them — the
	// resolver only projects menu items when the tree is empty.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'clientId'    => 'l-1',
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Authored', 'url' => '/authored' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'Authored' );
	expect( $rendered )->toContain( 'href="/authored"' );
} );
