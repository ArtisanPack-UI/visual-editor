<?php

/**
 * Unit tests for {@see BlockSupports::applyToRenderedHtml()} — the
 * supports merge dynamic (PHP-rendered) blocks go through (#874).
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\StateCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

describe( 'BlockSupports::applyToRenderedHtml', function (): void {
	it( 'merges classes, inline style and id into the first tag', function (): void {
		$html = BlockSupports::applyToRenderedHtml( '<div class="wp-block-acme-terminal">$ ls</div>', [
			'anchor'    => 'install',
			'align'     => 'wide',
			'className' => 'is-style-dark',
			'style'     => [ 'spacing' => [ 'padding' => 'var:preset|spacing|40' ] ],
		] );

		expect( $html )->toBe(
			'<div class="wp-block-acme-terminal alignwide is-style-dark" style="padding: var(--wp--preset--spacing--40);" id="install">$ ls</div>',
		);
	} );

	it( 'adds palette and custom gradient backgrounds', function (): void {
		expect( BlockSupports::applyToRenderedHtml( '<div class="tile">x</div>', [ 'gradient' => 'sunset' ] ) )
			->toBe( '<div class="tile has-sunset-gradient-background has-background">x</div>' );

		expect( BlockSupports::applyToRenderedHtml( '<div>x</div>', [
			'style' => [ 'color' => [ 'gradient' => 'linear-gradient(90deg, red, blue)' ] ],
		] ) )->toBe( '<div class="has-background" style="background: linear-gradient(90deg, red, blue);">x</div>' );
	} );

	it( "never overrides the handler's own declarations, id or classes", function (): void {
		$html = BlockSupports::applyToRenderedHtml(
			'<section id="own" class="a has-background" style="padding: 4px; color: red">x</section>',
			[
				'anchor' => 'ignored',
				'style'  => [
					'color'   => [ 'background' => '#fff' ],
					'spacing' => [ 'padding' => '2rem', 'margin' => '1rem' ],
				],
			],
		);

		expect( $html )->toBe(
			'<section id="own" class="a has-background" style="padding: 4px; color: red; background-color: #fff; margin: 1rem;">x</section>',
		);
	} );

	it( 'never lets a longhand override the handler\'s shorthand, or a shorthand its longhand (R-7)', function (): void {
		$html = BlockSupports::applyToRenderedHtml(
			'<div style="padding: 4px; background: #000">x</div>',
			[ 'style' => [ 'color' => [ 'background' => '#fff' ], 'spacing' => [ 'padding' => [ 'top' => '3rem' ] ] ] ],
		);

		expect( $html )->toBe( '<div style="padding: 4px; background: #000" class="has-background">x</div>' );

		$html = BlockSupports::applyToRenderedHtml(
			'<div style="background-color: red; border-top-width: 2px; inset: 0">x</div>',
			[ 'style' => [
				'color'  => [ 'gradient' => 'linear-gradient(red, blue)' ],
				'border' => [ 'width' => '1px', 'radius' => '4px' ],
			] ],
		);

		expect( $html )->not->toContain( 'background: linear-gradient' )
			->not->toContain( 'border-width: 1px' )
			->toContain( 'border-radius: 4px' );
	} );

	it( 'still adds sibling longhands the handler did not set (R-7)', function (): void {
		$html = BlockSupports::applyToRenderedHtml(
			'<div style="padding-top: 4px">x</div>',
			[ 'style' => [ 'spacing' => [ 'padding' => [ 'bottom' => '2rem' ], 'margin' => '1rem' ] ] ],
		);

		expect( $html )->toBe( '<div style="padding-top: 4px; padding-bottom: 2rem; margin: 1rem;">x</div>' );
	} );

	it( 'skips leading decorative elements when locating the wrapper (R-8)', function ( string $prefix ): void {
		$html = BlockSupports::applyToRenderedHtml(
			$prefix . '<div class="real">x</div>',
			[ 'anchor' => 'install', 'className' => 'mine' ],
		);

		expect( $html )->toBe( $prefix . '<div class="real mine" id="install">x</div>' );
	} )->with( [
		'hidden svg'          => '<svg aria-hidden="true"></svg>',
		'svg with children'   => '<svg viewBox="0 0 1 1"><g><svg></svg><path d="M0"/></g></svg>',
		'hidden span'         => '<span aria-hidden="true"><span>*</span></span> ',
		'self-closing hidden' => '<i aria-hidden=\'TRUE\'/>',
		'hidden void element' => '<img src="/x.png" alt="" aria-hidden="true">',
	] );

	it( 'targets the element marked data-ve-wrapper (R-8)', function (): void {
		$html = BlockSupports::applyToRenderedHtml(
			'<div class="outer"><p class="intro" data-x="data-ve-wrapper">a</p><section data-ve-wrapper class="real">b</section></div>',
			[ 'className' => 'mine' ],
		);

		expect( $html )->toBe( '<div class="outer"><p class="intro" data-x="data-ve-wrapper">a</p><section data-ve-wrapper class="real mine">b</section></div>' );
	} );

	it( 'merges nothing when only decorative elements are rendered (R-8)', function (): void {
		expect( BlockSupports::applyToRenderedHtml( '<svg aria-hidden="true"></svg>', [ 'className' => 'mine' ] ) )
			->toBe( '<svg aria-hidden="true"></svg>' );
	} );

	it( "keeps the handler's style verbatim, including semicolons inside values", function (): void {
		$html = BlockSupports::applyToRenderedHtml(
			'<div style="background-image: url(&quot;data:image/svg+xml;utf8,&lt;svg/&gt;&quot;)">x</div>',
			[ 'style' => [ 'spacing' => [ 'margin' => '1rem' ] ] ],
		);

		expect( $html )->toBe( '<div style="background-image: url(&quot;data:image/svg+xml;utf8,&lt;svg/&gt;&quot;); margin: 1rem;">x</div>' );
	} );

	it( 'is idempotent', function (): void {
		$attributes = [ 'anchor' => 'a', 'className' => 'b', 'style' => [ 'spacing' => [ 'margin' => '1rem' ] ] ];
		$once       = BlockSupports::applyToRenderedHtml( '<div>x</div>', $attributes );

		expect( BlockSupports::applyToRenderedHtml( $once, $attributes ) )->toBe( $once );
	} );

	it( 'skips leading whitespace, comments and asset elements', function (): void {
		$html = BlockSupports::applyToRenderedHtml(
			"\n<!-- block --><style>.x{}</style><script>var a = '<div>';</script><link rel=\"stylesheet\" href=\"/a.css\"><div class=\"real\">x</div>",
			[ 'className' => 'extra' ],
		);

		expect( $html )->toContain( '<style>.x{}</style>' )
			->toContain( "<script>var a = '<div>';</script>" )
			->toContain( '<div class="real extra">x</div>' );
	} );

	it( 'handles single-quoted, unquoted and boolean attributes and self-closing tags', function (): void {
		expect( BlockSupports::applyToRenderedHtml( "<img src=a.jpg alt='say \"hi\"' hidden />", [ 'className' => 'c' ] ) )
			->toBe( '<img src="a.jpg" alt="say &quot;hi&quot;" hidden class="c" />' );
	} );

	it( 'escapes compiled values', function (): void {
		expect( BlockSupports::applyToRenderedHtml( '<div>x</div>', [ 'className' => '"><script>' ] ) )
			->toBe( '<div class="&quot;&gt;&lt;script&gt;">x</div>' );
	} );

	it( 'leaves output without a wrapper element, or with nothing to merge, unchanged', function (): void {
		expect( BlockSupports::applyToRenderedHtml( 'plain text', [ 'className' => 'c' ] ) )->toBe( 'plain text' )
			->and( BlockSupports::applyToRenderedHtml( '', [ 'className' => 'c' ] ) )->toBe( '' )
			->and( BlockSupports::applyToRenderedHtml( '<div>x</div>', [ 'title' => 'no supports' ] ) )->toBe( '<div>x</div>' );
	} );

	it( 'stamps the states scope class and pushes its rules', function (): void {
		$accumulator = app( StateCssAccumulator::class );
		$accumulator->reset();

		$html = BlockSupports::applyToRenderedHtml( '<div>x</div>', [
			'states' => [
				'_scopeId'               => 'card1',
				'style.color.background' => [ 'hover' => 'var:preset|color|primary' ],
			],
		] );

		expect( $html )->toBe( '<div class="ap-state-card1">x</div>' )
			->and( $accumulator->flush() )->toContain( 'background-color: var(--wp--preset--color--primary) !important;' );
	} );
} );
