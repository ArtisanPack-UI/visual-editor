<?php

/**
 * Every `artisanpack/toc` link lands on exactly one rendered heading `id`,
 * including headings whose text is non-Latin and post-content headings
 * (M-13).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * Build a canonical-shape block.
 *
 * @since 1.15.0
 *
 * @param  string                $name        Block name.
 * @param  array<string, mixed>  $attributes  Block attributes.
 *
 * @return array<string, mixed>
 */
function renderedAnchorBlock( string $name, array $attributes = [] ): array
{
	return [
		'clientId'    => 'cid-' . md5( $name . json_encode( $attributes ) ),
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => [],
	];
}

it( 'links every TOC entry to a unique rendered heading id (M-13)', function (): void {
	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [
		'tree' => [
			renderedAnchorBlock( 'artisanpack/toc' ),
			renderedAnchorBlock( 'core/heading', [ 'level' => 2, 'content' => 'Crème ½ x' ] ),
			renderedAnchorBlock( 'core/heading', [ 'level' => 2, 'content' => 'Crème ¼ x' ] ),
			renderedAnchorBlock( 'core/heading', [ 'level' => 2, 'content' => '日本語' ] ),
			renderedAnchorBlock( 'core/heading', [ 'level' => 2, 'content' => 'Résumé 日本' ] ),
			renderedAnchorBlock( 'artisanpack/heading', [ 'level' => 2, 'content' => 'Résumé 中文' ] ),
			renderedAnchorBlock( 'core/post-content', [ '_resolvedContent' => '<h2>中文</h2><h3 id="kept">Kept</h3>' ] ),
		],
	] );

	preg_match_all( '/href="#([^"]*)"/', $html, $links );
	preg_match_all( '/<h[1-6][^>]*\bid="([^"]*)"/', $html, $ids );

	expect( $links[1] )->toBe( [ 'creme-x', 'creme-x-1', 'heading', 'resume', 'resume-1', 'heading-1', 'kept' ] )
		->and( $ids[1] )->toBe( $links[1] )
		->and( array_unique( $ids[1] ) )->toHaveCount( count( $ids[1] ) );
} );
