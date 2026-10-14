<?php

/**
 * `core/file` front-end markup: the download button carries the button
 * element class like the editor save, so link colors skip it and global
 * button styles reach it, and it points at the file-name link for its
 * accessible description.
 *
 * @since 1.14.0
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * @param  array<string, mixed>  $attributes
 */
function renderFileBlock( array $attributes ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'file-1',
		'name'        => 'core/file',
		'attributes'  => $attributes,
		'innerBlocks' => [],
	] ] ] );
}

it( 'gives the download button the button element class', function (): void {
	$html = renderFileBlock( [ 'href' => 'https://example.test/doc.pdf', 'fileName' => 'doc.pdf' ] );

	expect( $html )->toContain( 'class="wp-block-file__button wp-element-button" download>' )
		->and( $html )->not->toContain( 'aria-describedby' );
} );

it( 'links the download button to the file name for its description', function (): void {
	$html = renderFileBlock( [
		'href'     => 'https://example.test/doc.pdf',
		'fileId'   => 'wp-block-file--media-1',
		'fileName' => 'doc.pdf',
	] );

	expect( $html )->toContain( '<a id="wp-block-file--media-1" href="https://example.test/doc.pdf">doc.pdf</a>' )
		->and( $html )->toContain( 'download aria-describedby="wp-block-file--media-1">' );
} );

it( 'skips the description link when the file name is empty', function (): void {
	$html = renderFileBlock( [ 'href' => 'https://example.test/doc.pdf', 'fileId' => 'wp-block-file--media-1' ] );

	expect( $html )->not->toContain( 'id="wp-block-file--media-1"' )
		->and( $html )->not->toContain( 'aria-describedby' );
} );

it( 'keeps a parent link color off the download button', function (): void {
	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'g-1',
		'name'        => 'core/group',
		'attributes'  => [ 'style' => [ 'elements' => [ 'link' => [ 'color' => [ 'text' => '#ff0000' ] ] ] ] ],
		'innerBlocks' => [ [
			'clientId'    => 'file-1',
			'name'        => 'core/file',
			'attributes'  => [ 'href' => 'https://example.test/doc.pdf', 'fileName' => 'doc.pdf' ],
			'innerBlocks' => [],
		] ],
	] ] ] );

	expect( $html )->toContain( 'a:where(:not(.wp-element-button))' )
		->and( $html )->toContain( 'class="wp-block-file__button wp-element-button"' );
} );
