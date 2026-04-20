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
