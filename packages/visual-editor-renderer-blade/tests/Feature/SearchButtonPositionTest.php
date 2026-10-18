<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

/**
 * #961 — the Search block ignored `buttonPosition` (it keyed
 * `wp-block-search__button-inside` off `showLabel` instead) and dropped
 * the label when `showLabel` was off, leaving the input without an
 * accessible name. It now follows WordPress: a position class, an
 * icon/text button class when a button renders, no button for
 * `no-button`, and a screen-reader-only label when the label is hidden.
 */

function renderSearchBlock( array $attributes = [] ): string
{
	return app( BlockRenderer::class )->renderBlock( [
		'name'        => 'artisanpack/search',
		'attributes'  => $attributes,
		'innerBlocks' => [],
	] );
}

function searchFormClasses( string $html ): string
{
	preg_match( '/<form[^>]*class="([^"]*)"/', $html, $matches );

	return $matches[1] ?? '';
}

it( 'adds the button position class, defaulting to button-outside', function ( array $attributes, string $classes ): void {
	expect( searchFormClasses( renderSearchBlock( $attributes ) ) )->toBe( $classes );
} )->with( [
	'default'        => [ [], 'wp-block-search wp-block-search__button-outside wp-block-search__text-button' ],
	'button-outside' => [ [ 'buttonPosition' => 'button-outside' ], 'wp-block-search wp-block-search__button-outside wp-block-search__text-button' ],
	'button-inside'  => [ [ 'buttonPosition' => 'button-inside' ], 'wp-block-search wp-block-search__button-inside wp-block-search__text-button' ],
	'icon button'    => [ [ 'buttonPosition' => 'button-inside', 'buttonUseIcon' => true ], 'wp-block-search wp-block-search__button-inside wp-block-search__icon-button' ],
	'no-button'      => [ [ 'buttonPosition' => 'no-button', 'buttonUseIcon' => true ], 'wp-block-search wp-block-search__no-button' ],
	'button-only'    => [ [ 'buttonPosition' => 'button-only' ], 'wp-block-search wp-block-search__button-only wp-block-search__text-button' ],
	'unknown value'  => [ [ 'buttonPosition' => 'sideways' ], 'wp-block-search wp-block-search__button-outside wp-block-search__text-button' ],
] );

it( 'no longer keys button-inside off a hidden label', function (): void {
	expect( searchFormClasses( renderSearchBlock( [ 'showLabel' => false ] ) ) )
		->not->toContain( 'wp-block-search__button-inside' );
} );

it( 'renders no submit button for no-button', function (): void {
	$html = renderSearchBlock( [ 'buttonPosition' => 'no-button', 'buttonText' => 'Go' ] );

	expect( $html )->not->toContain( '<button' )
		->and( $html )->toContain( 'class="wp-block-search__input"' );
} );

it( 'renders the button for the other positions', function ( string $position ): void {
	expect( renderSearchBlock( [ 'buttonPosition' => $position, 'buttonText' => 'Go' ] ) )
		->toContain( '<button type="submit" class="wp-block-search__button">Go</button>' );
} )->with( [ 'button-outside', 'button-inside', 'button-only' ] );

it( 'keeps a hidden label in the markup for screen readers', function (): void {
	$html = renderSearchBlock( [ 'showLabel' => false, 'label' => 'Search posts' ] );

	expect( $html )->toMatch( '/<label class="wp-block-search__label screen-reader-text" for="wp-block-search-input-[a-f0-9]+">Search posts<\/label>/' );
} );

it( 'renders a visible label without the screen-reader class', function (): void {
	$html = renderSearchBlock( [ 'label' => 'Search posts' ] );

	expect( $html )->toContain( '<label class="wp-block-search__label" for=' )
		->and( $html )->not->toContain( 'screen-reader-text' );
} );
