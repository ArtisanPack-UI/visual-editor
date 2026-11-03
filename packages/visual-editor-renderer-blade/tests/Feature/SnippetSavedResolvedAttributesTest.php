<?php

/**
 * A snippet's saved `_resolved*` attributes don't reach its render (#1096).
 *
 * The snippet block renders its stored tree on its own, outside the
 * `<x-ve-blocks>` boundary, so it strips the tree itself.
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\DynamicContent\SnippetBlock;
use ArtisanPackUI\VisualEditor\Models\Snippet;

it( 'ignores a saved login URL in a snippet', function (): void {
	Snippet::factory()->create( [
		'slug'   => 'login-cta',
		'blocks' => [
			[
				'name'        => 'artisanpack/loginout',
				'attributes'  => [
					'redirectToCurrent'    => false,
					'_resolvedLoginoutUrl' => 'https://evil.test/login',
				],
				'innerBlocks' => [],
			],
		],
	] );

	$html = (string) app( SnippetBlock::class )->render( [ 'slug' => 'login-cta' ] );

	expect( $html )
		->toContain( 'wp-block-artisanpack-loginout' )
		->not->toContain( 'evil.test' );
} );
