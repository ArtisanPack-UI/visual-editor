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
	} );
} );
