<?php

declare( strict_types=1 );

/**
 * Post Title heading level (#994): level 0 is the "Paragraph" option of
 * the editor's heading-level dropdown and renders `<p>`; 1-6 render the
 * matching heading.
 *
 * @since 1.15.3
 */

use Illuminate\Support\Facades\Blade;

function renderPostTitleAtLevel( string $name, int $level ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [
		'tree' => [
			[
				'clientId'    => 'cid',
				'name'        => $name,
				'attributes'  => [
					'level'          => $level,
					'_resolvedTitle' => 'Hello World',
				],
				'innerBlocks' => [],
			],
		],
	] );
}

it( 'renders level 0 as a paragraph', function ( string $name ): void {
	$rendered = $this->stripGlobalStyles( renderPostTitleAtLevel( $name, 0 ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( '<p class="wp-block-post-title">Hello World</p>' )
		->and( $rendered )->not->toContain( '<h1' );
} )->with( [ 'core/post-title', 'artisanpack/post-title' ] );

it( 'renders levels 1-6 as the matching heading', function ( int $level ): void {
	$rendered = $this->stripGlobalStyles( renderPostTitleAtLevel( 'core/post-title', $level ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( sprintf( '<h%1$d class="wp-block-post-title">Hello World</h%1$d>', $level ) );
} )->with( [ 1, 2, 3, 4, 5, 6 ] );

it( 'clamps out-of-range levels into h1-h6', function ( int $level, string $tag ): void {
	$rendered = $this->stripGlobalStyles( renderPostTitleAtLevel( 'core/post-title', $level ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( sprintf( '<%1$s class="wp-block-post-title">Hello World</%1$s>', $tag ) );
} )->with( [
	'negative' => [ -3, 'h1' ],
	'too deep' => [ 9, 'h6' ],
] );
