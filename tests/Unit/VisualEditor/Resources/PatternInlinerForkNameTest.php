<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PatternInliner;
use ArtisanPackUI\VisualEditor\Support\BlockShape;

/**
 * #822 — a template saved from the site editor can carry a synced-pattern
 * reference under the `artisanpack/block` fork name. The inliner used to
 * match only `core/block`, leaving those references unresolved.
 */

/**
 * Build an inliner whose pattern lookup reads from an in-memory id map
 * instead of cms-framework's BlockPattern model.
 *
 * @param  array<int, array<int, array<string, mixed>>>  $patterns
 */
function forkNamePatternInliner( array $patterns ): PatternInliner
{
	return new class( $patterns ) extends PatternInliner {
		/**
		 * @param  array<int, array<int, array<string, mixed>>>  $patterns
		 */
		public function __construct( private readonly array $patterns )
		{
			parent::__construct();
		}

		protected function findPatternBlocks( int $ref ): ?array
		{
			return $this->patterns[ $ref ] ?? null;
		}
	};
}

/**
 * @return array<string, mixed>
 */
function forkPatternRef( int $ref, string $name = 'artisanpack/block' ): array
{
	return [ 'clientId' => 'pat-' . $ref, 'name' => $name, 'attributes' => [ 'ref' => $ref ], 'innerBlocks' => [] ];
}

it( 'lists both the core and fork pattern-reference names', function (): void {
	expect( BlockShape::PATTERN_REF_NAMES )
		->toContain( 'core/block' )
		->toContain( 'artisanpack/block' );
} );

it( 'resolves an artisanpack/block reference as core/block', function (): void {
	$tree = forkNamePatternInliner( [
		1 => [ [ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'Hero' ], 'innerBlocks' => [] ] ],
	] )->inline( [ forkPatternRef( 1 ) ] );

	expect( $tree[0]['name'] )->toBe( 'core/block' );
	expect( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Hero' );
} );

it( 'marks an artisanpack/block with an unknown ref as not-found', function (): void {
	$tree = forkNamePatternInliner( [] )->inline( [ forkPatternRef( 9999 ) ] );

	expect( $tree[0]['name'] )->toBe( 'core/block' );
	expect( $tree[0]['attributes']['_resolutionError'] )->toBe( PatternInliner::ERROR_NOT_FOUND );
} );

it( 'catches a cycle across mixed core and fork pattern names', function (): void {
	$tree = forkNamePatternInliner( [
		1 => [ forkPatternRef( 2, 'core/block' ) ],
		2 => [ forkPatternRef( 1 ) ],
	] )->inline( [ forkPatternRef( 1 ) ] );

	expect( $tree[0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['_resolutionError'] )
		->toBe( PatternInliner::ERROR_CYCLE );
} );
