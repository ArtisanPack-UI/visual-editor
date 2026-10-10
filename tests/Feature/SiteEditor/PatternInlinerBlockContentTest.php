<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\VisualEditor\Resources\PatternInliner;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;

/**
 * Synced `core/block` references must resolve against cms-framework's
 * `BlockPattern::block_content` column, not the legacy `content` envelope.
 */

uses( TestCase::class, WithCmsFramework::class );

it( 'resolves a synced pattern reference from block_content', function (): void {
	$pattern = BlockPattern::create( [
		'slug'          => 'synced-cta',
		'title'         => 'Synced CTA',
		'source'        => 'user',
		'synced'        => true,
		'categories'    => [],
		'block_types'   => [],
		'block_content' => [
			[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'From block_content' ], 'innerBlocks' => [] ],
		],
		'author_id'     => null,
	] );

	$tree = app( PatternInliner::class )->inline( [
		[ 'clientId' => 'ref-1', 'name' => 'core/block', 'attributes' => [ 'ref' => $pattern->id ], 'innerBlocks' => [] ],
	] );

	expect( $tree[0]['attributes'] )->not->toHaveKey( '_resolutionError' )
		->and( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'From block_content' );
} );

it( 'marks a pattern with empty block_content as not found', function (): void {
	$pattern = BlockPattern::create( [
		'slug'          => 'empty',
		'title'         => 'Empty',
		'source'        => 'user',
		'synced'        => true,
		'categories'    => [],
		'block_types'   => [],
		'block_content' => [],
		'author_id'     => null,
	] );

	$tree = app( PatternInliner::class )->inline( [
		[ 'clientId' => 'ref-2', 'name' => 'core/block', 'attributes' => [ 'ref' => $pattern->id ], 'innerBlocks' => [] ],
	] );

	expect( $tree[0]['innerBlocks'] )->toBe( [] );
} );
