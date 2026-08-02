<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Support\BlockMarkupHydrator;

beforeEach( function () {
	$this->hydrator = app( BlockMarkupHydrator::class );
} );

it( 'recovers paragraph text that lives only in the saved HTML', function () {
	$markup = <<<'HTML'
	<!-- wp:artisanpack/paragraph {"textColor":"text-muted"} -->
	<p class="has-text-muted-color has-text-color">Supporting subheading.</p>
	<!-- /wp:artisanpack/paragraph -->
	HTML;

	$tree = $this->hydrator->hydrate( $markup );

	expect( $tree )->toHaveCount( 1 );
	expect( $tree[0]['name'] )->toBe( 'artisanpack/paragraph' );
	expect( $tree[0]['attributes']['content'] )->toBe( 'Supporting subheading.' );
	expect( $tree[0]['attributes']['textColor'] )->toBe( 'text-muted' );
} );

it( 'recovers text for core-namespaced theme markup via the artisanpack fork', function () {
	$tree = $this->hydrator->hydrate( '<!-- wp:paragraph --><p>HELLO</p><!-- /wp:paragraph -->' );

	expect( $tree[0]['name'] )->toBe( 'core/paragraph' );
	expect( $tree[0]['attributes']['content'] )->toBe( 'HELLO' );
} );

it( 'preserves inline formatting inside recovered rich text', function () {
	$tree = $this->hydrator->hydrate(
		'<!-- wp:artisanpack/paragraph --><p>Hello <strong>world</strong>.</p><!-- /wp:artisanpack/paragraph -->'
	);

	expect( $tree[0]['attributes']['content'] )->toBe( 'Hello <strong>world</strong>.' );
} );

it( 'recovers heading content regardless of level', function () {
	$tree = $this->hydrator->hydrate(
		'<!-- wp:artisanpack/heading {"level":3} --><h3 class="wp-block-heading">Our Story</h3><!-- /wp:artisanpack/heading -->'
	);

	expect( $tree[0]['attributes']['content'] )->toBe( 'Our Story' );
	expect( $tree[0]['attributes']['level'] )->toBe( 3 );
} );

it( 'recovers button text and link attributes', function () {
	$markup = '<!-- wp:artisanpack/button -->'
		. '<div class="wp-block-button"><a class="wp-block-button__link" href="/contact" target="_blank" rel="noopener">Get in touch</a></div>'
		. '<!-- /wp:artisanpack/button -->';

	$attributes = $this->hydrator->hydrate( $markup )[0]['attributes'];

	expect( $attributes['text'] )->toBe( 'Get in touch' );
	expect( $attributes['url'] )->toBe( '/contact' );
	expect( $attributes['linkTarget'] )->toBe( '_blank' );
	expect( $attributes['rel'] )->toBe( 'noopener' );
} );

it( 'recovers image src, alt and caption from their distinct nodes', function () {
	$markup = '<!-- wp:artisanpack/image -->'
		. '<figure class="wp-block-image"><a href="/full"><img src="/cat.jpg" alt="A cat"/></a>'
		. '<figcaption>Our office cat</figcaption></figure>'
		. '<!-- /wp:artisanpack/image -->';

	$attributes = $this->hydrator->hydrate( $markup )[0]['attributes'];

	expect( $attributes['url'] )->toBe( '/cat.jpg' );
	expect( $attributes['alt'] )->toBe( 'A cat' );
	expect( $attributes['caption'] )->toBe( 'Our office cat' );
	expect( $attributes['href'] )->toBe( '/full' );
} );

it( 'recovers multiline list values as whole child elements', function () {
	$markup = '<!-- wp:artisanpack/list --><ul><li>One</li><li>Two</li></ul><!-- /wp:artisanpack/list -->';

	expect( $this->hydrator->hydrate( $markup )[0]['attributes']['values'] )
		->toBe( '<li>One</li><li>Two</li>' );
} );

it( 'leaves a modern list empty so the partial renders its inner blocks', function () {
	// The list a real theme emits: items are `list-item` INNER BLOCKS,
	// so the parser leaves only `<ul></ul>` in the list's own innerHTML.
	// `values` must stay empty — recovering it would double-render every
	// item, since the partial prefers `$innerBlocksHtml`.
	$markup = <<<'HTML'
	<!-- wp:artisanpack/list -->
	<ul class="wp-block-list">
	<!-- wp:artisanpack/list-item --><li>One</li><!-- /wp:artisanpack/list-item -->
	<!-- wp:artisanpack/list-item --><li>Two</li><!-- /wp:artisanpack/list-item -->
	</ul>
	<!-- /wp:artisanpack/list -->
	HTML;

	$list = $this->hydrator->hydrate( $markup )[0];

	expect( $list['attributes']['values'] ?? '' )->toBe( '' );
	expect( $list['innerBlocks'] )->toHaveCount( 2 );
	expect( $list['innerBlocks'][0]['attributes']['content'] )->toBe( 'One' );
	expect( $list['innerBlocks'][1]['attributes']['content'] )->toBe( 'Two' );
} );

it( 'recovers quote value and citation', function () {
	$markup = '<!-- wp:artisanpack/quote -->'
		. '<blockquote class="wp-block-quote"><p>Ship it.</p><cite>A colleague</cite></blockquote>'
		. '<!-- /wp:artisanpack/quote -->';

	$attributes = $this->hydrator->hydrate( $markup )[0]['attributes'];

	expect( $attributes['value'] )->toBe( '<p>Ship it.</p>' );
	expect( $attributes['citation'] )->toBe( 'A colleague' );
} );

it( 'treats boolean attribute sources as presence, not value', function () {
	$markup = '<!-- wp:artisanpack/video -->'
		. '<figure><video controls loop src="/clip.mp4"></video></figure>'
		. '<!-- /wp:artisanpack/video -->';

	$attributes = $this->hydrator->hydrate( $markup )[0]['attributes'];

	expect( $attributes['controls'] )->toBeTrue();
	expect( $attributes['loop'] )->toBeTrue();
	expect( $attributes['autoplay'] )->toBeFalse();
	expect( $attributes['src'] )->toBe( '/clip.mp4' );
} );

it( 'recovers a query source into one entry per match', function () {
	$markup = '<!-- wp:artisanpack/table -->'
		. '<figure class="wp-block-table"><table><tbody>'
		. '<tr><td>A1</td><td>B1</td></tr><tr><td>A2</td><td>B2</td></tr>'
		. '</tbody></table></figure>'
		. '<!-- /wp:artisanpack/table -->';

	$body = $this->hydrator->hydrate( $markup )[0]['attributes']['body'];

	expect( $body )->toHaveCount( 2 );
	expect( array_column( $body[0]['cells'], 'content' ) )->toBe( [ 'A1', 'B1' ] );
	expect( $body[1]['cells'][1]['tag'] )->toBe( 'td' );
} );

it( 'recurses into inner blocks', function () {
	$markup = <<<'HTML'
	<!-- wp:artisanpack/group -->
	<div class="wp-block-group">
	<!-- wp:artisanpack/paragraph --><p>Nested</p><!-- /wp:artisanpack/paragraph -->
	</div>
	<!-- /wp:artisanpack/group -->
	HTML;

	$tree = $this->hydrator->hydrate( $markup );

	expect( $tree[0]['name'] )->toBe( 'artisanpack/group' );
	expect( $tree[0]['innerBlocks'] )->toHaveCount( 1 );
	expect( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Nested' );
} );

it( 'lets delimiter attributes win over recovered ones', function () {
	$markup = '<!-- wp:artisanpack/paragraph {"content":"From the delimiter"} -->'
		. '<p>From the HTML</p>'
		. '<!-- /wp:artisanpack/paragraph -->';

	expect( $this->hydrator->hydrate( $markup )[0]['attributes']['content'] )
		->toBe( 'From the delimiter' );
} );

it( 'passes unregistered blocks through with their delimiter attributes intact', function () {
	$markup = '<!-- wp:acme/widget {"mode":"compact"} --><div>Body</div><!-- /wp:acme/widget -->';

	$block = $this->hydrator->hydrate( $markup )[0];

	expect( $block['name'] )->toBe( 'acme/widget' );
	expect( $block['attributes'] )->toBe( [ 'mode' => 'compact' ] );
	expect( $block['innerBlocks'] )->toBe( [] );
} );

it( 'keeps the saved HTML of a block whose content lives only in innerHTML', function () {
	// `core/html` is the canonical case: no sourced attributes, all of
	// its content in the saved markup. Dropping innerHTML would lose it
	// outright.
	$block = $this->hydrator->hydrate( '<!-- wp:html --><div class="x">Raw HTML</div><!-- /wp:html -->' )[0];

	expect( $block['innerHTML'] )->toContain( 'Raw HTML' );
} );

it( 'omits innerHTML for a self-closing block that saved none', function () {
	$block = $this->hydrator->hydrate( '<!-- wp:artisanpack/spacer {"height":"40px"} /-->' )[0];

	expect( $block )->not->toHaveKey( 'innerHTML' );
} );

it( 'returns an empty tree for blank markup', function () {
	expect( $this->hydrator->hydrate( '' ) )->toBe( [] );
	expect( $this->hydrator->hydrate( "   \n  " ) )->toBe( [] );
} );

it( 'drops freeform siblings that carry no block name', function () {
	$tree = $this->hydrator->hydrateTree( [
		[ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>orphan</p>' ],
		[ 'blockName' => 'artisanpack/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>kept</p>' ],
	] );

	expect( $tree )->toHaveCount( 1 );
	expect( $tree[0]['attributes']['content'] )->toBe( 'kept' );
} );

it( 'round-trips a tree that is already in editor shape', function () {
	$tree = $this->hydrator->hydrateTree( [
		[
			'name'        => 'artisanpack/paragraph',
			'attributes'  => [ 'content' => 'Already here' ],
			'innerBlocks' => [],
		],
	] );

	expect( $tree[0]['attributes']['content'] )->toBe( 'Already here' );
} );

it( 'preserves multibyte text through the DOM round trip', function () {
	$markup = '<!-- wp:artisanpack/paragraph --><p>Café — naïve ☕</p><!-- /wp:artisanpack/paragraph -->';

	expect( $this->hydrator->hydrate( $markup )[0]['attributes']['content'] )
		->toBe( 'Café — naïve ☕' );
} );

it( 'truncates a pathologically deep tree instead of exhausting the stack', function () {
	$block = [ 'blockName' => 'artisanpack/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>deep</p>' ];

	for ( $i = 0; $i < BlockMarkupHydrator::MAX_DEPTH + 20; $i++ ) {
		$block = [
			'blockName'   => 'artisanpack/group',
			'attrs'       => [],
			'innerHTML'   => '',
			'innerBlocks' => [ $block ],
		];
	}

	$tree  = $this->hydrator->hydrateTree( [ $block ] );
	$depth = 0;
	$node  = $tree[0];

	while ( [] !== $node['innerBlocks'] ) {
		$node = $node['innerBlocks'][0];
		$depth++;
	}

	expect( $depth )->toBeLessThan( BlockMarkupHydrator::MAX_DEPTH );
} );

it( 'recovers nothing when the block type declares no sourced attributes', function () {
	app( BlockTypeRegistry::class )->register( 'acme/plain', [
		'attributes' => [ 'label' => [ 'type' => 'string' ] ],
	] );

	$block = $this->hydrator->hydrate( '<!-- wp:acme/plain --><p>ignored</p><!-- /wp:acme/plain -->' )[0];

	expect( $block['attributes'] )->toBe( [] );
} );
