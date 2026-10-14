<?php

/**
 * Front-end color parity with the editor canvas (#852): per-block link
 * colors on every block that supports them, the package default palette
 * staying declared under a theme palette, and the button States panel's
 * hover / focus text + background colors.
 *
 * @since 1.13.1
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * Render a single-block tree through `<x-ve-blocks>`.
 *
 * @param  array<string, mixed>  $block
 */
function renderColorParityTree( array $block ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ $block ] ] );
}

/**
 * Pull the `<style data-ve-elements>` body out of a render.
 */
function elementsStyleBody( string $rendered ): string
{
	preg_match( '/<style data-ve-elements>(.*?)<\/style>/s', $rendered, $matches );

	return $matches[1] ?? '';
}

describe( 'per-block link colors on every block (#852)', function (): void {
	it( 'renders style.elements.link on the block wrapper and in the shared elements style', function ( string $name, array $extraAttributes, array $innerBlocks ): void {
		$rendered = renderColorParityTree( [
			'clientId'    => 'b-1',
			'name'        => $name,
			'attributes'  => array_merge( $extraAttributes, [
				'style' => [ 'elements' => [
					'link' => [
						'color'  => [ 'text' => 'var:preset|color|primary' ],
						':hover' => [ 'color' => [ 'text' => '#0f172a' ] ],
						':focus' => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
					],
				] ],
			] ),
			'innerBlocks' => $innerBlocks,
		] );

		expect( $rendered )->toMatch( '/class="[^"]*wp-elements-[a-f0-9]{8}/' );

		preg_match( '/wp-elements-[a-f0-9]{8}/', $rendered, $class );
		$scope = '.' . $class[0] . ' a:where(:not(.wp-element-button))';
		$css   = elementsStyleBody( $rendered );

		expect( $css )
			->toContain( $scope . '{color: var(--wp--preset--color--primary) !important;}' )
			->toContain( $scope . ':hover{color: #0f172a !important;}' )
			->toContain( $scope . ':focus{color: var(--wp--preset--color--accent) !important;}' );
	} )->with( [
		'paragraph' => [ 'core/paragraph', [ 'content' => 'See <a href="/x">this</a>' ], [] ],
		'heading'   => [ 'core/heading', [ 'content' => 'A <a href="/x">link</a>', 'level' => 2 ], [] ],
		'list'      => [ 'core/list', [], [] ],
		'quote'     => [ 'core/quote', [], [] ],
		'group'     => [ 'core/group', [], [] ],
		'columns'   => [ 'core/columns', [], [] ],
		'column'    => [ 'core/column', [], [] ],
		'cover'     => [ 'core/cover', [], [] ],
	] );

	it( 'keeps the link color off buttons nested in the same block', function (): void {
		$rendered = renderColorParityTree( [
			'clientId'    => 'g-1',
			'name'        => 'core/group',
			'attributes'  => [
				'style' => [ 'elements' => [ 'link' => [ 'color' => [ 'text' => '#ff0000' ] ] ] ],
			],
			'innerBlocks' => [
				[
					'clientId'    => 'bs-1',
					'name'        => 'core/buttons',
					'attributes'  => [],
					'innerBlocks' => [
						[
							'clientId'    => 'b-1',
							'name'        => 'core/button',
							'attributes'  => [ 'text' => 'Go', 'url' => '/go' ],
							'innerBlocks' => [],
						],
					],
				],
			],
		] );

		$css = elementsStyleBody( $rendered );

		expect( $css )->toContain( ' a:where(:not(.wp-element-button)){color: #ff0000 !important;}' )
			->and( $css )->not->toMatch( '/ a\{/' )
			->and( $rendered )->toContain( 'wp-block-button__link wp-element-button' );
	} );

	it( 'emits one rule set for identical sibling blocks', function (): void {
		$paragraph = static fn ( string $id ): array => [
			'clientId'    => $id,
			'name'        => 'core/paragraph',
			'attributes'  => [
				'content' => '<a href="/">x</a>',
				'style'   => [ 'elements' => [ 'link' => [ 'color' => [ 'text' => '#123456' ] ] ] ],
			],
			'innerBlocks' => [],
		];

		$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ $paragraph( 'p-1' ), $paragraph( 'p-2' ) ] ] );

		expect( substr_count( elementsStyleBody( $rendered ), '{color: #123456 !important;}' ) )->toBe( 1 );
	} );

	it( 'emits no elements style block when no block sets element colors', function (): void {
		$rendered = renderColorParityTree( [
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Plain' ],
			'innerBlocks' => [],
		] );

		expect( $rendered )->not->toContain( 'data-ve-elements' )
			->and( $rendered )->not->toContain( 'wp-elements-' );
	} );
} );

describe( 'default palette under a theme palette (#852)', function (): void {
	it( 'declares the package default swatches and utilities next to the theme palette', function (): void {
		$rendered = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
			'themeJson' => [
				'settings' => [
					'color' => [ 'palette' => [
						[ 'slug' => 'legends-navy', 'color' => '#002b49', 'name' => 'Navy' ],
						[ 'slug' => 'primary', 'color' => '#00b5e2', 'name' => 'Brand primary' ],
					] ],
				],
			],
		] );

		expect( $rendered )
			->toContain( '--wp--preset--color--legends-navy: #002b49;' )
			->toContain( '--wp--preset--color--base-content: #1f2937;' )
			->toContain( '.has-base-content-color { color: var(--wp--preset--color--base-content) !important; }' )
			->toContain( '.has-base-content-background-color {' )
			->toContain( '.has-base-content-border-color {' )
			->toContain( '--wp--preset--color--primary: #00b5e2;' )
			->not->toContain( '--wp--preset--color--primary: #2563eb;' );
	} );

	it( 'renders a button whose text color is a default swatch with a resolvable class', function (): void {
		$rendered = renderColorParityTree( [
			'clientId'    => 'b-1',
			'name'        => 'core/button',
			'attributes'  => [ 'text' => 'View on GitHub', 'url' => '/gh', 'textColor' => 'base-content', 'backgroundColor' => 'legends-teal' ],
			'innerBlocks' => [],
		] );

		$styles = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
			'themeJson' => [ 'settings' => [ 'color' => [ 'palette' => [
				[ 'slug' => 'legends-teal', 'color' => '#00b5e2' ],
			] ] ] ],
		] );

		expect( $rendered )->toContain( 'has-base-content-color' )
			->and( $styles )->toContain( '.has-base-content-color {' )
			->and( $styles )->toContain( '--wp--preset--color--base-content: #1f2937;' );
	} );
} );

describe( 'button States panel colors (#852)', function (): void {
	it( 'emits hover + focus text and background colors for preset slugs and custom values', function (): void {
		$rendered = renderColorParityTree( [
			'clientId'    => 'b-1',
			'name'        => 'core/button',
			'attributes'  => [
				'text'   => 'Go',
				'url'    => '/go',
				'states' => [
					'_scopeId'        => 'btn852',
					'backgroundColor' => [ 'hover' => 'legends-navy' ],
					'textColor'       => [ 'hover' => 'base-content', 'focus' => 'primary' ],
					'color.text'      => [ 'focus-visible' => '#ff00ff' ],
				],
			],
			'innerBlocks' => [],
		] );

		preg_match( '/<style data-ve-states>(.*?)<\/style>/s', $rendered, $matches );
		$css = $matches[1] ?? '';

		expect( $rendered )->toContain( 'ap-state-btn852' )
			->and( $css )->toContain( '.ap-state-btn852:hover' )
			->and( $css )->toContain( 'background-color: var(--wp--preset--color--legends-navy) !important' )
			->and( $css )->toContain( 'color: var(--wp--preset--color--base-content) !important' )
			->and( $css )->toContain( '.ap-state-btn852:focus' )
			->and( $css )->toContain( 'color: var(--wp--preset--color--primary) !important' )
			->and( $css )->toContain( '.ap-state-btn852:focus-visible' )
			->and( $css )->toContain( 'color: #ff00ff !important' );
	} );

	it( 'declares every default slug a state color references, even under a theme palette', function (): void {
		$styles = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
			'themeJson' => [ 'settings' => [ 'color' => [ 'palette' => [
				[ 'slug' => 'legends-navy', 'color' => '#002b49' ],
			] ] ] ],
		] );

		foreach ( [ 'base-content', 'primary', 'legends-navy' ] as $slug ) {
			expect( $styles )->toContain( "--wp--preset--color--{$slug}:" );
		}
	} );
} );
