<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use Illuminate\Contracts\View\Factory as ViewFactory;

function makeRenderer(): BlockRenderer
{
	return app( BlockRenderer::class );
}

function makeBlock( string $name, array $attributes = [], array $innerBlocks = [], string $clientId = 'cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

it( 'returns empty string for empty tree', function () {
	expect( makeRenderer()->render( [] ) )->toBe( '' );
} );

it( 'skips non-array entries in the tree', function () {
	$out = makeRenderer()->render( [ 'not-a-block', null, 42 ] );

	expect( $out )->toBe( '' );
} );

it( 'skips blocks with missing or empty names', function () {
	$tree = [
		[ 'clientId' => 'a', 'name' => '', 'attributes' => [], 'innerBlocks' => [] ],
		[ 'clientId' => 'b', 'attributes' => [], 'innerBlocks' => [] ],
	];

	expect( makeRenderer()->render( $tree ) )->toBe( '' );
} );

it( 'renders a paragraph block from its partial', function () {
	$tree = [ makeBlock( 'core/paragraph', [ 'content' => 'Hello <strong>world</strong>' ] ) ];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toBe( '<p class="wp-block-paragraph">Hello <strong>world</strong></p>' );
} );

it( 'renders a heading block with the configured level', function () {
	$tree = [ makeBlock( 'core/heading', [ 'level' => 3, 'content' => 'Section' ] ) ];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toBe( '<h3 class="wp-block-heading">Section</h3>' );
} );

it( 'clamps invalid heading levels to the allowed range', function () {
	$tooHigh = [ makeBlock( 'core/heading', [ 'level' => 99, 'content' => 'X' ] ) ];
	$tooLow  = [ makeBlock( 'core/heading', [ 'level' => 0, 'content' => 'Y' ] ) ];

	expect( $this->normalizeHtml( makeRenderer()->render( $tooHigh ) ) )->toContain( '<h6' );
	expect( $this->normalizeHtml( makeRenderer()->render( $tooLow ) ) )->toContain( '<h1' );
} );

it( 'renders a list block with inner list items', function () {
	$tree = [
		makeBlock( 'core/list', [ 'ordered' => false ], [
			makeBlock( 'core/list-item', [ 'content' => 'One' ], [], 'li-1' ),
			makeBlock( 'core/list-item', [ 'content' => 'Two' ], [], 'li-2' ),
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<ul' )
		->toContain( '<li>One</li>' )
		->toContain( '<li>Two</li>' )
		->toContain( '</ul>' );
} );

it( 'renders an ordered list with start + reversed attributes', function () {
	$tree = [
		makeBlock( 'core/list', [ 'ordered' => true, 'start' => 5, 'reversed' => true ], [
			makeBlock( 'core/list-item', [ 'content' => 'A' ], [], 'li-1' ),
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<ol' )
		->toContain( 'start="5"' )
		->toContain( 'reversed' );
} );

it( 'renders a quote block with citation', function () {
	$tree = [
		makeBlock( 'core/quote', [ 'citation' => 'Someone Famous' ], [
			makeBlock( 'core/paragraph', [ 'content' => 'Quoted text' ], [], 'p1' ),
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<blockquote class="wp-block-quote">' )
		->toContain( '<p class="wp-block-paragraph">Quoted text</p>' )
		->toContain( '<cite>Someone Famous</cite>' );
} );

it( 'renders a code block', function () {
	$tree = [ makeBlock( 'core/code', [ 'content' => 'echo 1;' ] ) ];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toBe( '<pre class="wp-block-code"><code>echo 1;</code></pre>' );
} );

it( 'renders a preformatted block', function () {
	$tree = [ makeBlock( 'core/preformatted', [ 'content' => "line 1\nline 2" ] ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( '<pre class="wp-block-preformatted">' )
		->toContain( "line 1\nline 2" );
} );

it( 'renders a verse block', function () {
	$tree = [ makeBlock( 'core/verse', [ 'content' => 'Roses are red' ] ) ];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<pre class="wp-block-verse">Roses are red</pre>' );
} );

it( 'renders an image block with caption and link', function () {
	$tree = [
		makeBlock( 'core/image', [
			'url'     => 'https://example.test/image.jpg',
			'alt'     => 'An image',
			'caption' => 'A <em>caption</em>',
			'href'    => 'https://example.test/full.jpg',
			'id'      => 42,
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<figure class="wp-block-image">' )
		->toContain( 'src="https://example.test/image.jpg"' )
		->toContain( 'alt="An image"' )
		->toContain( 'class="wp-image-42"' )
		->toContain( 'href="https://example.test/full.jpg"' )
		->toContain( '<figcaption>A <em>caption</em></figcaption>' );
} );

it( 'renders a separator block', function () {
	$tree = [ makeBlock( 'core/separator', [ 'style' => 'wide' ] ) ];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toBe( '<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>' );
} );

it( 'renders a spacer block with numeric height as px', function () {
	$tree = [ makeBlock( 'core/spacer', [ 'height' => 48 ] ) ];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( 'style="height: 48px;"' );
} );

it( 'renders a table block across head, body, and foot', function () {
	$tree = [
		makeBlock( 'core/table', [
			'head' => [ [ 'cells' => [ [ 'content' => 'Name', 'tag' => 'th' ] ] ] ],
			'body' => [ [ 'cells' => [ [ 'content' => 'Ada', 'tag' => 'td' ] ] ] ],
			'foot' => [ [ 'cells' => [ [ 'content' => 'Totals', 'tag' => 'td' ] ] ] ],
			'caption' => 'Demo',
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<thead>' )
		->toContain( '<tbody>' )
		->toContain( '<tfoot>' )
		->toContain( '<th>Name</th>' )
		->toContain( '<td>Ada</td>' )
		->toContain( '<figcaption>Demo</figcaption>' );
} );

it( 'renders nested inner blocks for a group', function () {
	$tree = [
		makeBlock( 'core/group', [], [
			makeBlock( 'core/paragraph', [ 'content' => 'Inner' ], [], 'inner-1' ),
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<div class="wp-block-group is-layout-flow">' )
		->toContain( '<p class="wp-block-paragraph">Inner</p>' );
} );

it( 'renders a buttons group with a button inside', function () {
	$tree = [
		makeBlock( 'core/buttons', [], [
			makeBlock( 'core/button', [ 'text' => 'Click me', 'url' => 'https://example.test' ], [], 'b-1' ),
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<div class="wp-block-buttons' )
		->toContain( '<a class="wp-block-button__link wp-element-button" href="https://example.test"' )
		->toContain( 'Click me</a>' );
} );

it( 'renders columns with inner column blocks', function () {
	$tree = [
		makeBlock( 'core/columns', [], [
			makeBlock( 'core/column', [ 'width' => 60 ], [
				makeBlock( 'core/paragraph', [ 'content' => 'Left' ], [], 'l-1' ),
			], 'col-1' ),
			makeBlock( 'core/column', [], [
				makeBlock( 'core/paragraph', [ 'content' => 'Right' ], [], 'r-1' ),
			], 'col-2' ),
		] ),
	];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<div class="wp-block-columns' )
		->toContain( 'flex-basis: 60%;' )
		->toContain( '<p class="wp-block-paragraph">Left</p>' )
		->toContain( '<p class="wp-block-paragraph">Right</p>' );
} );

it( 'falls back to an unknown-block comment for missing partials', function () {
	$tree = [ makeBlock( 'third-party/unknown-widget' ) ];

	$html = $this->normalizeHtml( makeRenderer()->render( $tree ) );

	expect( $html )->toContain( '<!-- visual-editor: no partial for third-party/unknown-widget -->' )
		->toContain( 'data-ve-unknown-block="third-party/unknown-widget"' );
} );

it( 'escapes unknown block names in the fallback marker', function () {
	$tree = [ makeBlock( 'third-party/"><script>' ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->not->toContain( '<script>' )
		->toContain( '&quot;' );
} );

it( 'invokes a registered dynamic block instead of a partial', function () {
	$registry = app( DynamicBlockRegistry::class );

	$registry->register( new class extends DynamicBlock {
		public function name(): string
		{
			return 'acme/latest-posts';
		}

		public function render( array $attrs )
		{
			return sprintf( '<section data-latest-posts="true">%s</section>', (string) ( $attrs['title'] ?? 'Latest' ) );
		}
	} );

	$tree = [ makeBlock( 'acme/latest-posts', [ 'title' => 'Recent articles' ] ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toBe( '<section data-latest-posts="true">Recent articles</section>' );
} );

it( 'passes validated attributes to dynamic blocks', function () {
	$registry = app( DynamicBlockRegistry::class );

	$registry->register( new class extends DynamicBlock {
		public function name(): string
		{
			return 'acme/validated';
		}

		public function validateAttrs( array $attrs ): array
		{
			$attrs['count'] = max( 1, (int) ( $attrs['count'] ?? 0 ) );

			return $attrs;
		}

		public function render( array $attrs )
		{
			return sprintf( '<em>%d</em>', $attrs['count'] );
		}
	} );

	$tree = [ makeBlock( 'acme/validated', [ 'count' => -5 ] ) ];

	expect( makeRenderer()->render( $tree ) )->toBe( '<em>1</em>' );
} );

it( 'coerces a view returned from a dynamic block into a string', function () {
	$registry = app( DynamicBlockRegistry::class );

	$registry->register( new class extends DynamicBlock {
		public function name(): string
		{
			return 'acme/view-block';
		}

		public function render( array $attrs )
		{
			return app( ViewFactory::class )->make( 'visual-editor-renderer-blade::components.blocks', [
				'html' => sprintf( '<div>%s</div>', (string) ( $attrs['label'] ?? '' ) ),
			] );
		}
	} );

	$tree = [ makeBlock( 'acme/view-block', [ 'label' => 'from view' ] ) ];

	expect( $this->normalizeHtml( makeRenderer()->render( $tree ) ) )->toBe( '<div>from view</div>' );
} );

it( 'recovers from exceptions thrown by a dynamic block', function () {
	$registry = app( DynamicBlockRegistry::class );

	$registry->register( new class extends DynamicBlock {
		public function name(): string
		{
			return 'acme/explodes';
		}

		public function render( array $attrs )
		{
			throw new RuntimeException( 'boom' );
		}
	} );

	$tree = [ makeBlock( 'acme/explodes' ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'data-ve-unknown-block="acme/explodes"' );
} );

it( 'drops unsafe URL schemes from button hrefs', function () {
	$tree = [ makeBlock( 'core/button', [ 'text' => 'Click', 'url' => 'javascript:alert(1)' ] ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->not->toContain( 'javascript:' )
		->not->toContain( '<a ' )
		->toContain( '<span class="wp-block-button__link' );
} );

it( 'enforces noopener noreferrer on target=_blank buttons', function () {
	$tree = [
		makeBlock( 'core/button', [
			'text'       => 'External',
			'url'        => 'https://example.com',
			'linkTarget' => '_blank',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'rel="noopener noreferrer"' )
		->toContain( 'target="_blank"' );
} );

it( 'drops unsafe URL schemes from image hrefs and src', function () {
	$tree = [
		makeBlock( 'core/image', [
			'url'  => 'https://example.test/safe.jpg',
			'href' => 'javascript:void(0)',
			'alt'  => 'safe',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->not->toContain( 'javascript:' )
		->toContain( 'src="https://example.test/safe.jpg"' )
		->not->toContain( '<a ' );
} );

it( 'places media-text width on the right column when mediaPosition is right', function () {
	$tree = [
		makeBlock( 'core/media-text', [
			'mediaUrl'      => 'https://example.test/photo.jpg',
			'mediaPosition' => 'right',
			'mediaWidth'    => 30,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'grid-template-columns: auto 30%;' );
} );

it( 'clamps cover dimRatio to 0-100 and whitelists minHeightUnit', function () {
	$tree = [
		makeBlock( 'core/cover', [
			'dimRatio'      => 250,
			'minHeight'     => 40,
			'minHeightUnit' => 'javascript:alert(1)',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'opacity: 1;' )
		->toContain( 'min-height: 40px;' )
		->not->toContain( 'javascript:' );
} );

it( 'routes cover palette gradient class to the overlay span, not the wrapper', function () {
	$tree = [
		makeBlock( 'core/cover', [
			'gradient' => 'sunset',
			'dimRatio' => 50,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	preg_match( '/<div class="([^"]*wp-block-cover[^"]*)"/', $html, $wrapperMatches );
	preg_match( '/<span aria-hidden="true" class="([^"]*wp-block-cover__background[^"]*)"/', $html, $overlayMatches );

	$wrapperClasses = $wrapperMatches[1] ?? '';
	$overlayClasses = $overlayMatches[1] ?? '';

	expect( $overlayClasses )->toContain( 'has-sunset-gradient-background' )
		->toContain( 'has-background' );

	expect( $wrapperClasses )->not->toContain( 'has-sunset-gradient-background' )
		->not->toContain( 'has-background' );
} );

it( 'routes cover custom background-color inline declaration to the overlay span', function () {
	$tree = [
		makeBlock( 'core/cover', [
			'style'    => [ 'color' => [ 'background' => '#bada55' ] ],
			'dimRatio' => 40,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	preg_match( '/<div class="[^"]*wp-block-cover[^"]*"([^>]*)>/', $html, $wrapperMatches );
	preg_match( '/<span aria-hidden="true"[^>]*style="([^"]*)"/', $html, $overlayMatches );

	$wrapperAttrs = $wrapperMatches[1] ?? '';
	$overlayStyle = $overlayMatches[1] ?? '';

	expect( $overlayStyle )->toContain( 'background-color: #bada55' )
		->toContain( 'opacity: 0.4' );

	expect( $wrapperAttrs )->not->toContain( 'background-color' )
		->not->toContain( '#bada55' );
} );

it( 'routes cover overlayColor palette slug to the overlay span', function () {
	$tree = [
		makeBlock( 'core/cover', [
			'overlayColor' => 'primary',
			'dimRatio'     => 60,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	preg_match( '/<div class="([^"]*wp-block-cover[^"]*)"/', $html, $wrapperMatches );
	preg_match( '/<span aria-hidden="true" class="([^"]*wp-block-cover__background[^"]*)"/', $html, $overlayMatches );

	$wrapperClasses = $wrapperMatches[1] ?? '';
	$overlayClasses = $overlayMatches[1] ?? '';

	expect( $overlayClasses )->toContain( 'has-primary-background-color' )
		->toContain( 'has-background' );

	expect( $wrapperClasses )->not->toContain( 'has-primary-background-color' )
		->not->toContain( 'has-background' );
} );

it( 'routes cover customOverlayColor inline declaration to the overlay span', function () {
	$tree = [
		makeBlock( 'core/cover', [
			'customOverlayColor' => '#eb3824',
			'dimRatio'           => 50,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	preg_match( '/<div class="[^"]*wp-block-cover[^"]*"([^>]*)>/', $html, $wrapperMatches );
	preg_match( '/<span aria-hidden="true" class="([^"]*wp-block-cover__background[^"]*)"[^>]*style="([^"]*)"/', $html, $overlayMatches );

	$wrapperAttrs   = $wrapperMatches[1] ?? '';
	$overlayClasses = $overlayMatches[1] ?? '';
	$overlayStyle   = $overlayMatches[2] ?? '';

	expect( $overlayStyle )->toContain( 'background-color: #eb3824' )
		->toContain( 'opacity: 0.5' );
	expect( $overlayClasses )->toContain( 'has-background' );

	expect( $wrapperAttrs )->not->toContain( '#eb3824' )
		->not->toContain( 'has-background' );
} );

it( 'routes cover customGradient inline declaration to the overlay span', function () {
	$tree = [
		makeBlock( 'core/cover', [
			'customGradient' => 'linear-gradient(135deg, red, blue)',
			'dimRatio'       => 50,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	preg_match( '/<div class="[^"]*wp-block-cover[^"]*"([^>]*)>/', $html, $wrapperMatches );
	preg_match( '/<span aria-hidden="true" class="([^"]*wp-block-cover__background[^"]*)"[^>]*style="([^"]*)"/', $html, $overlayMatches );

	$wrapperAttrs   = $wrapperMatches[1] ?? '';
	$overlayClasses = $overlayMatches[1] ?? '';
	$overlayStyle   = $overlayMatches[2] ?? '';

	expect( $overlayStyle )->toContain( 'background: linear-gradient(135deg, red, blue)' );
	expect( $overlayClasses )->toContain( 'has-background' );

	expect( $wrapperAttrs )->not->toContain( 'linear-gradient' )
		->not->toContain( 'has-background' );
} );

it( 'keeps cover text-color output on the wrapper, not the overlay span', function () {
	$tree = [
		makeBlock( 'core/cover', [
			'textColor' => 'midnight',
			'dimRatio'  => 50,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	preg_match( '/<div class="([^"]*wp-block-cover[^"]*)"/', $html, $wrapperMatches );
	preg_match( '/<span aria-hidden="true" class="([^"]*wp-block-cover__background[^"]*)"/', $html, $overlayMatches );

	$wrapperClasses = $wrapperMatches[1] ?? '';
	$overlayClasses = $overlayMatches[1] ?? '';

	expect( $wrapperClasses )->toContain( 'has-midnight-color' )
		->toContain( 'has-text-color' );

	expect( $overlayClasses )->not->toContain( 'has-midnight-color' )
		->not->toContain( 'has-text-color' );
} );

it( 'validates table cell alignment against an allowlist', function () {
	$tree = [
		makeBlock( 'core/table', [
			'body' => [ [ 'cells' => [
				[ 'content' => 'A', 'tag' => 'td', 'align' => 'center' ],
				[ 'content' => 'B', 'tag' => 'td', 'align' => 'expression(alert(1))' ],
			] ] ],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'style="text-align: center;"' )
		->not->toContain( 'expression' );
} );

it( 'generates stable unique ids for multiple search blocks on the same page', function () {
	$tree = [
		makeBlock( 'core/search', [ 'label' => 'First', 'buttonText' => 'Go' ], [], 's-1' ),
		makeBlock( 'core/search', [ 'label' => 'Second', 'buttonText' => 'Find' ], [], 's-2' ),
	];

	$html = makeRenderer()->render( $tree );

	preg_match_all( '/id="(wp-block-search-input-[a-f0-9]+)"/', $html, $m );

	expect( $m[1] )->toHaveCount( 2 );
	expect( $m[1][0] )->not->toBe( $m[1][1] );
} );

it( 'renders an icon-only submit button with accessible name when buttonUseIcon is true', function () {
	$tree = [ makeBlock( 'core/search', [ 'buttonText' => 'Go', 'buttonUseIcon' => true ] ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'class="wp-block-search__button has-icon"' )
		->toContain( 'aria-label="Go"' )
		->toContain( '<svg class="wp-block-search__button-icon"' )
		->not->toContain( '<button type="submit" class="wp-block-search__button"></button>' );
} );

it( 'falls back to label when buttonText is empty and buttonUseIcon is true', function () {
	$tree = [ makeBlock( 'core/search', [ 'label' => 'Find stuff', 'buttonText' => '', 'buttonUseIcon' => true ] ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'aria-label="Find stuff"' );
} );

it( 'carries the #338 a11y fix forward to artisanpack/search (I4 fork)', function () {
	$tree = [ makeBlock( 'artisanpack/search', [ 'buttonText' => 'Go', 'buttonUseIcon' => true ] ) ];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'class="wp-block-search__button has-icon"' )
		->toContain( 'aria-label="Go"' )
		->toContain( '<svg class="wp-block-search__button-icon"' )
		->not->toContain( '<button type="submit" class="wp-block-search__button"></button>' );
} );

it( 'preserves fractional column widths', function () {
	$tree = [
		makeBlock( 'core/columns', [], [
			makeBlock( 'core/column', [ 'width' => 33.33 ], [
				makeBlock( 'core/paragraph', [ 'content' => 'A' ], [], 'p-a' ),
			], 'col-a' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'flex-basis: 33.33%;' );
} );

it( 'renders the artisanpack/callout reference block with severity and icon', function () {
	$tree = [
		makeBlock( 'artisanpack/callout', [
			'severity' => 'warning',
			'icon'     => 'warning',
			'content'  => 'Check this out.',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-callout ap-callout--warning' )
		->and( $html )->toContain( 'data-severity="warning"' )
		->and( $html )->toContain( '<div class="ap-callout__body">Check this out.</div>' )
		->and( $html )->toContain( '<svg' );
} );

it( 'falls back to safe defaults when callout severity or icon is invalid', function () {
	$tree = [
		makeBlock( 'artisanpack/callout', [
			'severity' => 'catastrophic',
			'icon'     => 'rocket',
			'content'  => 'fallback',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-callout--info' )
		->and( $html )->toContain( 'data-severity="info"' );
} );

it( 'renders the artisanpack/breadcrumbs block with a resolved trail and schema microdata', function () {
	$tree = [
		makeBlock( 'artisanpack/breadcrumbs', [
			'separatorIcon'     => 'chevron-right',
			'breadcrumbsSchema' => true,
			'_resolvedTrail'    => [
				[ 'label' => 'Home', 'url' => '/' ],
				[ 'label' => 'Blog', 'url' => '/blog' ],
				[ 'label' => 'Hello World', 'current' => true ],
			],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( '<nav' )
		->and( $html )->toContain( 'ap-breadcrumbs' )
		->and( $html )->toContain( 'aria-label="Breadcrumb"' )
		->and( $html )->toContain( 'itemtype="https://schema.org/BreadcrumbList"' )
		->and( $html )->toContain( 'itemtype="https://schema.org/ListItem"' )
		->and( $html )->toContain( '<a class="ap-breadcrumbs__link" href="/"' )
		->and( $html )->toContain( '<a class="ap-breadcrumbs__link" href="/blog"' )
		->and( $html )->toContain( 'Hello World' )
		->and( $html )->toContain( 'aria-current="page"' )
		->and( $html )->toContain( 'itemprop="position" content="1"' )
		->and( $html )->toContain( 'itemprop="position" content="3"' )
		->and( $html )->toContain( '<svg' )
		->and( $html )->toContain( 'd="m9 6 6 6-6 6"' );
} );

it( 'falls back to safe defaults when breadcrumbs separator is invalid and omits schema when disabled', function () {
	$tree = [
		makeBlock( 'artisanpack/breadcrumbs', [
			'separatorIcon'     => 'spinning-rocket',
			'breadcrumbsSchema' => false,
			'_resolvedTrail'    => [
				[ 'label' => 'Home', 'url' => '/' ],
				[ 'label' => 'Page', 'current' => true ],
			],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'd="m9 6 6 6-6 6"' )
		->and( $html )->not->toContain( 'schema.org/BreadcrumbList' )
		->and( $html )->not->toContain( 'itemprop="position"' );
} );

it( 'drops unsafe URLs from breadcrumbs trail entries', function () {
	$tree = [
		makeBlock( 'artisanpack/breadcrumbs', [
			'_resolvedTrail' => [
				[ 'label' => 'Home', 'url' => '/' ],
				[ 'label' => 'XSS', 'url' => 'javascript:alert(1)' ],
				[ 'label' => 'Page', 'current' => true ],
			],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->not->toContain( 'javascript:' )
		->and( $html )->not->toContain( 'href="javascript:alert(1)"' )
		->and( $html )->toContain( 'href="/"' )
		->and( $html )->toContain( '>XSS<' )
		// Non-current entries whose URL was sanitized away render as plain
		// spans — never with the "current" class, which only marks the
		// trail's final crumb.
		->and( substr_count( $html, 'ap-breadcrumbs__current' ) )->toBe( 1 );
} );

it( 'renders an empty list when breadcrumbs trail is missing', function () {
	$tree = [
		makeBlock( 'artisanpack/breadcrumbs', [] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-breadcrumbs__list' )
		->and( $html )->not->toContain( '<li class="ap-breadcrumbs__item' );
} );

it( 'renders the artisanpack/copyright block with the © + text + current year (icon-text default)', function () {
	$tree = [
		makeBlock( 'artisanpack/copyright', [
			'copyrightType' => 'icon-text',
			'copyrightText' => 'Acme, Inc.',
		] ),
	];

	$year = gmdate( 'Y' );
	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'class="ap-copyright"' )
		->and( $html )->toContain( '© Acme, Inc. ' . $year )
		->and( $html )->toContain( '<p' );
} );

it( 'omits the text when copyright type is icon-only and drops the icon when text-only', function () {
	$iconOnly = makeRenderer()->render( [
		makeBlock( 'artisanpack/copyright', [
			'copyrightType' => 'icon-only',
			'copyrightText' => 'Should not appear',
		] ),
	] );

	$textOnly = makeRenderer()->render( [
		makeBlock( 'artisanpack/copyright', [
			'copyrightType' => 'text-only',
			'copyrightText' => 'Plain',
		] ),
	] );

	$year = gmdate( 'Y' );

	expect( $iconOnly )->toContain( '© ' . $year )
		->and( $iconOnly )->not->toContain( 'Should not appear' )
		->and( $textOnly )->toContain( 'Plain ' . $year )
		->and( $textOnly )->not->toContain( '©' );
} );

it( 'falls back to icon-text when copyright type is invalid', function () {
	$tree = [
		makeBlock( 'artisanpack/copyright', [
			'copyrightType' => 'made-up-mode',
			'copyrightText' => 'Fallback',
		] ),
	];

	$year = gmdate( 'Y' );
	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( '© Fallback ' . $year );
} );

it( 'renders the artisanpack/marquee block with width + animation styles applied', function () {
	$tree = [
		makeBlock( 'artisanpack/marquee', [
			'marqueeContent' => 'Breaking news',
			'marqueeWidth'   => 60,
			'marqueeSpeed'   => 10,
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'class="ap-marquee"' )
		->and( $html )->toContain( 'width: 60%' )
		->and( $html )->toContain( 'animation: ap-marquee-scroll 10s linear infinite' )
		->and( $html )->toContain( 'class="ap-marquee__text"' )
		->and( $html )->toContain( 'Breaking news' );
} );

it( 'clamps marquee width and speed to their valid range and falls back on non-numeric input', function () {
	$tree = [
		makeBlock( 'artisanpack/marquee', [
			'marqueeContent' => 'x',
			'marqueeWidth'   => 999,
			'marqueeSpeed'   => 'fast',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'width: 100%' )
		->and( $html )->toContain( 'animation: ap-marquee-scroll 5s linear infinite' );
} );

it( 'renders the artisanpack/comments-number block with the resolved count and plural label', function () {
	$tree = [
		makeBlock( 'artisanpack/comments-number', [
			'_resolvedCommentCount' => 5,
			'singularCommentText'   => 'Reply',
			'pluralCommentText'     => 'Replies',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'class="ap-comments-number"' )
		->and( $html )->toContain( '5 Replies' )
		->and( $html )->not->toContain( 'Reply<' );
} );

it( 'uses the singular comments-number label when the resolved count is exactly one', function () {
	$tree = [
		makeBlock( 'artisanpack/comments-number', [
			'_resolvedCommentCount' => 1,
			'singularCommentText'   => 'Reply',
			'pluralCommentText'     => 'Replies',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( '1 Reply' );
} );

it( 'falls back to zero comments and default labels when the resolved count is missing', function () {
	$tree = [
		makeBlock( 'artisanpack/comments-number', [] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( '0 Comments' );
} );

it( 'renders an artisanpack/accordions tree with its nested panel + title/body grandchildren', function () {
	$tree = [
		makeBlock( 'artisanpack/accordions', [], [
			makeBlock( 'artisanpack/accordion', [
				'panelId'   => 'faq-1',
				'panelIcon' => 'arrows',
			], [
				makeBlock( 'artisanpack/accordion-title', [], [
					makeBlock( 'core/heading', [ 'level' => 3, 'content' => 'Question' ], [], 'h-1' ),
				], 'title-1' ),
				makeBlock( 'artisanpack/accordion-body', [], [
					makeBlock( 'core/paragraph', [ 'content' => 'Answer.' ], [], 'p-1' ),
				], 'body-1' ),
			], 'panel-1' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'class="ap-accordions"' )
		->and( $html )->toContain( 'class="ap-accordion"' )
		->and( $html )->toContain( 'data-panel-id="faq-1"' )
		->and( $html )->toContain( 'data-panel-icon="arrows"' )
		->and( $html )->toContain( 'id="faq-1-control"' )
		->and( $html )->toContain( 'aria-controls="faq-1"' )
		->and( $html )->toContain( 'aria-expanded="false"' )
		->and( $html )->toContain( 'ap-accordion__icon--arrows' )
		->and( $html )->toContain( 'id="faq-1"' )
		->and( $html )->toContain( 'aria-labelledby="faq-1-control"' )
		->and( $html )->toContain( '<p class="wp-block-paragraph">Answer.</p>' )
		->and( $html )->toContain( '<h3 class="wp-block-heading">Question</h3>' );
} );

it( 'falls back to safe defaults when accordion panelIcon is invalid', function () {
	$tree = [
		makeBlock( 'artisanpack/accordion', [
			'panelId'   => 'faq-1',
			'panelIcon' => 'evil"><script>',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'data-panel-icon="plus-minus"' )
		->and( $html )->not->toContain( '<script>' );
} );

it( 'omits accordion-title aria wiring when the parent panel id is empty', function () {
	$tree = [
		makeBlock( 'artisanpack/accordion', [ 'panelId' => '', 'panelIcon' => 'plus-minus' ], [
			makeBlock( 'artisanpack/accordion-title', [], [], 'title-1' ),
			makeBlock( 'artisanpack/accordion-body', [], [], 'body-1' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->not->toContain( 'aria-controls=' )
		->and( $html )->not->toContain( 'aria-labelledby=' )
		->and( $html )->not->toContain( 'id="-control"' );
} );

it( 'renders an artisanpack/tabs tree with triggers derived from tab-section children', function () {
	$tree = [
		makeBlock( 'artisanpack/tabs', [
			'tabsAlign'   => 'horizontal',
			'tabsSpacing' => 'center',
		], [
			makeBlock( 'artisanpack/tab-section', [
				'label' => 'Overview',
				'tabId' => 'overview',
			], [
				makeBlock( 'core/paragraph', [ 'content' => 'Tab body' ], [], 'p-1' ),
			], 'sec-1' ),
			makeBlock( 'artisanpack/tab-section', [
				'label' => 'Specs',
				'tabId' => 'specs',
			], [], 'sec-2' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'align-tabs-horizontal' )
		->and( $html )->toContain( 'space-tabs-center' )
		->and( $html )->toContain( 'data-ap-tabs' )
		->and( $html )->toContain( 'role="tablist"' )
		->and( $html )->toContain( 'href="#tabs-panel-overview"' )
		->and( $html )->toContain( 'aria-controls="tabs-panel-overview"' )
		->and( $html )->toContain( 'id="tabs-tab-overview"' )
		->and( $html )->toContain( 'aria-selected="true"' )
		->and( $html )->toContain( 'aria-selected="false"' )
		->and( $html )->toContain( '>Overview</a>' )
		->and( $html )->toContain( '>Specs</a>' )
		->and( $html )->toContain( 'id="tabs-panel-overview"' )
		->and( $html )->toContain( 'aria-labelledby="tabs-tab-overview"' )
		->and( $html )->toContain( 'role="tabpanel"' )
		->and( $html )->toContain( 'Tab body' );
} );

it( 'falls back to safe defaults when tabsAlign / tabsSpacing are invalid', function () {
	$tree = [
		makeBlock( 'artisanpack/tabs', [
			'tabsAlign'   => 'diagonal',
			'tabsSpacing' => 'sprawl',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'align-tabs-horizontal' )
		->and( $html )->toContain( 'space-tabs-start' );
} );

it( 'deduplicates tab ids when two sections share the same slug', function () {
	$tree = [
		makeBlock( 'artisanpack/tabs', [], [
			makeBlock( 'artisanpack/tab-section', [ 'label' => 'One', 'tabId' => 'overview' ], [], 'sec-1' ),
			makeBlock( 'artisanpack/tab-section', [ 'label' => 'Two', 'tabId' => 'overview' ], [], 'sec-2' ),
			makeBlock( 'artisanpack/tab-section', [ 'label' => 'Three', 'tabId' => 'overview' ], [], 'sec-3' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'id="tabs-panel-overview"' )
		->and( $html )->toContain( 'id="tabs-panel-overview-2"' )
		->and( $html )->toContain( 'id="tabs-panel-overview-3"' )
		->and( $html )->toContain( 'href="#tabs-panel-overview-2"' )
		->and( $html )->toContain( 'href="#tabs-panel-overview-3"' );
} );

it( 'auto-fills tab labels and ids by position when sections leave them blank', function () {
	$tree = [
		makeBlock( 'artisanpack/tabs', [], [
			makeBlock( 'artisanpack/tab-section', [], [], 'sec-1' ),
			makeBlock( 'artisanpack/tab-section', [], [], 'sec-2' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( '>Tab 1</a>' )
		->and( $html )->toContain( '>Tab 2</a>' )
		// Triggers and panels MUST share the same resolved id so the
		// aria-controls / id hookup actually resolves.
		->and( $html )->toContain( 'href="#tabs-panel-tab-1"' )
		->and( $html )->toContain( 'href="#tabs-panel-tab-2"' )
		->and( $html )->toContain( 'id="tabs-panel-tab-1"' )
		->and( $html )->toContain( 'id="tabs-panel-tab-2"' )
		->and( $html )->toContain( 'aria-labelledby="tabs-tab-tab-1"' )
		->and( $html )->toContain( 'aria-labelledby="tabs-tab-tab-2"' );
} );

it( 'renders a tab-section without aria wiring when tabId is empty', function () {
	$tree = [
		makeBlock( 'artisanpack/tab-section', [ 'tabId' => '' ], [
			makeBlock( 'core/paragraph', [ 'content' => 'Naked' ], [], 'p-1' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->not->toContain( 'id="tabs-panel-"' )
		->and( $html )->not->toContain( 'aria-labelledby=' )
		->and( $html )->toContain( 'Naked' );
} );

it( 'renders an artisanpack/grid tree with per-breakpoint column + span classes', function () {
	$tree = [
		makeBlock( 'artisanpack/grid', [
			'numColumns' => 4,
		], [
			makeBlock( 'artisanpack/grid-item', [
				'innerLayout'    => 'center',
				'gridColumnSpan' => 2,
				'gridRowSpan'    => 1,
			], [
				makeBlock( 'core/paragraph', [ 'content' => 'Cell content' ], [], 'p-1' ),
			], 'item-1' ),
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-grid' )
		->and( $html )->toContain( 'ap-grid-has-4-base-columns' )
		->and( $html )->toContain( 'ap-grid-item' )
		->and( $html )->toContain( 'ap-grid-item-layout-center' )
		->and( $html )->toContain( 'ap-grid-item-span-2-base-columns' )
		->and( $html )->toContain( 'ap-grid-item-span-1-base-row' )
		->and( $html )->toContain( 'Cell content' );
} );

it( 'emits an `ap-grid-has-N-{bp}-columns` class for every responsive.numColumns override', function () {
	$tree = [
		makeBlock( 'artisanpack/grid', [
			'numColumns' => 1,
			'responsive' => [
				'numColumns' => [
					'md' => 2,
					'lg' => 4,
				],
			],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-grid-has-1-base-columns' )
		->and( $html )->toContain( 'ap-grid-has-2-md-columns' )
		->and( $html )->toContain( 'ap-grid-has-4-lg-columns' );
} );

it( 'clamps grid column counts outside 1-12 to safe defaults', function () {
	$tooHigh = [
		makeBlock( 'artisanpack/grid', [ 'numColumns' => 99 ] ),
	];
	$tooLow = [
		makeBlock( 'artisanpack/grid', [ 'numColumns' => 0 ] ),
	];

	expect( makeRenderer()->render( $tooHigh ) )->toContain( 'ap-grid-has-12-base-columns' );
	expect( makeRenderer()->render( $tooLow ) )->toContain( 'ap-grid-has-1-base-columns' );
} );

it( 'emits per-breakpoint grid-item span classes for both columns and rows', function () {
	$tree = [
		makeBlock( 'artisanpack/grid-item', [
			'gridColumnSpan' => 1,
			'gridRowSpan'    => 1,
			'responsive'     => [
				'gridColumnSpan' => [ 'md' => 2, 'lg' => 3 ],
				'gridRowSpan'    => [ 'md' => 2 ],
			],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-grid-item-span-1-base-columns' )
		->and( $html )->toContain( 'ap-grid-item-span-1-base-row' )
		->and( $html )->toContain( 'ap-grid-item-span-2-md-columns' )
		->and( $html )->toContain( 'ap-grid-item-span-3-lg-columns' )
		->and( $html )->toContain( 'ap-grid-item-span-2-md-row' );
} );

it( 'skips numColumns / span overrides at unknown breakpoints', function () {
	$tree = [
		makeBlock( 'artisanpack/grid', [
			'numColumns' => 2,
			'responsive' => [ 'numColumns' => [ 'orphan' => 6 ] ],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-grid-has-2-base-columns' )
		->and( $html )->not->toContain( 'orphan-columns' )
		->and( $html )->not->toContain( '-orphan-' );
} );

it( 'falls back to a safe default when grid-item innerLayout is invalid', function () {
	$tree = [
		makeBlock( 'artisanpack/grid-item', [
			'innerLayout' => 'evil"><script>',
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'ap-grid-item-layout-normal' )
		->and( $html )->not->toContain( '<script>' )
		->and( $html )->not->toContain( 'ap-grid-item-layout-evil' );
} );

it( 'compiles spacing.blockGap with horizontal/vertical sides into row-gap + column-gap', function () {
	$tree = [
		makeBlock( 'artisanpack/grid', [
			'style' => [
				'spacing' => [
					'blockGap' => [ 'top' => '2rem', 'left' => '1rem' ],
				],
			],
		] ),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'row-gap: 2rem' )
		->and( $html )->toContain( 'column-gap: 1rem' );
} );

it( 'renders artisanpack/next-post wrapper around its inner blocks when an adjacent post is resolved', function () {
	$tree = [
		makeBlock(
			'artisanpack/next-post',
			[ '_resolvedHasAdjacent' => true ],
			[ makeBlock( 'core/paragraph', [ 'content' => 'Adjacent body' ] ) ]
		),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'wp-block-artisanpack-next-post' )
		->and( $html )->toContain( 'navigation-post' )
		->and( $html )->toContain( 'Adjacent body' );
} );

it( 'emits nothing for artisanpack/next-post when no adjacent post is resolved', function () {
	$tree = [
		makeBlock(
			'artisanpack/next-post',
			[ '_resolvedHasAdjacent' => false ],
			[ makeBlock( 'core/paragraph', [ 'content' => 'Hidden' ] ) ]
		),
	];

	$html = trim( makeRenderer()->render( $tree ) );

	expect( $html )->toBe( '' );
} );

it( 'renders artisanpack/previous-post wrapper around its inner blocks when an adjacent post is resolved', function () {
	$tree = [
		makeBlock(
			'artisanpack/previous-post',
			[ '_resolvedHasAdjacent' => true ],
			[ makeBlock( 'core/paragraph', [ 'content' => 'Older neighbor' ] ) ]
		),
	];

	$html = makeRenderer()->render( $tree );

	expect( $html )->toContain( 'wp-block-artisanpack-previous-post' )
		->and( $html )->toContain( 'navigation-post' )
		->and( $html )->toContain( 'Older neighbor' );
} );

it( 'emits nothing for artisanpack/previous-post when no adjacent post is resolved', function () {
	$tree = [
		makeBlock(
			'artisanpack/previous-post',
			[ '_resolvedHasAdjacent' => false ],
			[ makeBlock( 'core/paragraph', [ 'content' => 'Hidden' ] ) ]
		),
	];

	$html = trim( makeRenderer()->render( $tree ) );

	expect( $html )->toBe( '' );
} );

describe( 'Keystone #50 — block-supports compilation on the rendered HTML', function (): void {
	it( 'renders a custom background color on core/group as both class marker and inline style', function (): void {
		$tree = [ makeBlock( 'core/group', [
			'style' => [ 'color' => [ 'background' => '#0f172a' ] ],
		] ) ];

		$html = makeRenderer()->render( $tree );

		// `has-background` marker so theme CSS can target the wrapper.
		expect( $html )->toContain( 'has-background' );
		// The custom value lands as an inline style declaration.
		expect( $html )->toContain( 'background-color: #0f172a' );
	} );

	it( 'renders a palette-slug background as has-{slug}-background-color', function (): void {
		$tree = [ makeBlock( 'core/group', [
			'backgroundColor' => 'accent',
		] ) ];

		$html = makeRenderer()->render( $tree );

		expect( $html )->toContain( 'has-accent-background-color' );
		expect( $html )->toContain( 'has-background' );
	} );

	it( 'renders block-level alignment as the alignwide / alignfull class', function (): void {
		$wide = makeRenderer()->render( [ makeBlock( 'core/group', [ 'align' => 'wide' ] ) ] );
		$full = makeRenderer()->render( [ makeBlock( 'core/group', [ 'align' => 'full' ] ) ] );

		expect( $wide )->toContain( 'alignwide' );
		expect( $full )->toContain( 'alignfull' );
	} );

	it( 'renders custom padding through style.spacing.padding as inline style', function (): void {
		$tree = [ makeBlock( 'core/group', [
			'style' => [ 'spacing' => [ 'padding' => [ 'top' => '2rem', 'bottom' => '2rem' ] ] ],
		] ) ];

		$html = makeRenderer()->render( $tree );

		expect( $html )->toContain( 'padding-top: 2rem' );
		expect( $html )->toContain( 'padding-bottom: 2rem' );
	} );

	it( 'renders typography palette slugs on core/heading', function (): void {
		$tree = [ makeBlock( 'core/heading', [
			'level'    => 2,
			'content'  => 'Hello',
			'fontSize' => 'large',
		] ) ];

		$html = makeRenderer()->render( $tree );

		expect( $html )->toContain( 'has-large-font-size' );
		expect( $html )->toContain( 'has-defined-font-size' );
	} );

	it( 'renders anchor as id on the wrapper', function (): void {
		$tree = [ makeBlock( 'core/group', [ 'anchor' => 'hero-section' ] ) ];

		$html = makeRenderer()->render( $tree );

		expect( $html )->toContain( 'id="hero-section"' );
	} );

	it( 'renders core/button with color on the inner link, not the outer wrapper', function (): void {
		$tree = [ makeBlock( 'core/button', [
			'text'            => 'Click',
			'url'             => 'https://example.com',
			'backgroundColor' => 'accent',
		] ) ];

		$html = makeRenderer()->render( $tree );

		// The wrapper div carries `wp-block-button` only — the visual
		// classes live on the inner `<a>` so theme.json's
		// `styles.elements.button` targeting `.wp-element-button`
		// actually picks up the slug-based class set.
		expect( $html )->toMatch( '/<a [^>]*has-accent-background-color/' );
		// Negative assertion: the outer `<div class="wp-block-button">`
		// must NOT carry the color class (CodeRabbit on PR #457). The
		// contract is "color on the inner link, NOT the outer wrapper" —
		// pin both sides so a future refactor that accidentally lifts
		// color onto the wrapper fails this test.
		expect( $html )->not->toMatch( '/<div [^>]*class="[^"]*has-accent-background-color/' );
	} );

	it( 'whitelists core/buttons layout.justifyContent against invalid values', function (): void {
		$bad  = makeRenderer()->render( [ makeBlock( 'core/buttons', [
			'layout' => [ 'justifyContent' => 'evil"><script>' ],
		] ) ] );
		$good = makeRenderer()->render( [ makeBlock( 'core/buttons', [
			'layout' => [ 'justifyContent' => 'space-between' ],
		] ) ] );

		// Anything outside WP's known enum falls back to `left`.
		expect( $bad )->toContain( 'is-content-justification-left' );
		expect( $bad )->not->toContain( '<script>' );
		expect( $bad )->not->toContain( 'is-content-justification-evil' );

		// Legitimate values pass through.
		expect( $good )->toContain( 'is-content-justification-space-between' );
	} );
} );
