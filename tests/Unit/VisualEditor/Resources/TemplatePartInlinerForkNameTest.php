<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\Support\BlockShape;

/**
 * #822 — templates saved from the site editor store template-part
 * references under the `artisanpack/template-part` fork name (the
 * adapter rewrites core names on read). The inliner used to match only
 * `core/template-part`, so every part in a saved template rendered as
 * an empty wrapper on the front end.
 */

/**
 * Build an inliner whose part lookup reads from an in-memory slug map
 * instead of cms-framework's resolver.
 *
 * @param  array<string, array<int, array<string, mixed>>>  $parts
 */
function forkNameInliner( array $parts ): TemplatePartInliner
{
	return new class( $parts ) extends TemplatePartInliner
	{
		/**
		 * @param  array<string, array<int, array<string, mixed>>>  $parts
		 */
		public function __construct( private readonly array $parts )
		{
			parent::__construct();
		}

		protected function findPartBlocks( string $slug ): ?array
		{
			return $this->parts[ $slug ] ?? null;
		}
	};
}

/**
 * @return array<string, mixed>
 */
function forkPartRef( string $slug, string $name = 'artisanpack/template-part', array $innerBlocks = [] ): array
{
	return [
		'clientId'    => 'tp-' . $slug,
		'name'        => $name,
		'attributes'  => [ 'slug' => $slug, 'theme' => 'artisanpack-ui', 'tagName' => 'header' ],
		'innerBlocks' => $innerBlocks,
	];
}

/**
 * @return array<string, mixed>
 */
function forkParagraph( string $content ): array
{
	return [ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => $content ], 'innerBlocks' => [] ];
}

it( 'lists both the core and fork template-part names', function () {
	expect( BlockShape::TEMPLATE_PART_NAMES )
		->toContain( 'core/template-part' )
		->toContain( 'artisanpack/template-part' );
} );

it( 'resolves an artisanpack/template-part block and keeps the fork name', function () {
	$tree = forkNameInliner( [ 'header' => [ forkParagraph( 'Site header' ) ] ] )
		->inline( [ forkPartRef( 'header' ) ] );

	expect( $tree[0]['name'] )->toBe( 'artisanpack/template-part' );
	expect( $tree[0]['attributes'] )->not->toHaveKey( '_resolutionError' );
	expect( $tree[0]['attributes']['tagName'] )->toBe( 'header' );
	expect( $tree[0]['innerBlocks'] )->toHaveCount( 1 );
	expect( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Site header' );
} );

it( 'still resolves a core/template-part block from a theme file', function () {
	$tree = forkNameInliner( [ 'header' => [ forkParagraph( 'Site header' ) ] ] )
		->inline( [ forkPartRef( 'header', 'core/template-part' ) ] );

	expect( $tree[0]['name'] )->toBe( 'core/template-part' );
	expect( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Site header' );
} );

it( 'replaces a saved innerBlocks snapshot with the live part contents', function () {
	$stale = forkPartRef( 'header', 'artisanpack/template-part', [ forkParagraph( 'Stale snapshot' ) ] );

	$tree = forkNameInliner( [ 'header' => [ forkParagraph( 'Live header' ) ] ] )->inline( [ $stale ] );

	expect( $tree[0]['innerBlocks'] )->toHaveCount( 1 );
	expect( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Live header' );
} );

it( 'marks an artisanpack/template-part with an unknown slug as not-found', function () {
	$stale = forkPartRef( 'missing', 'artisanpack/template-part', [ forkParagraph( 'Stale snapshot' ) ] );

	$tree = forkNameInliner( [] )->inline( [ $stale ] );

	expect( $tree[0]['name'] )->toBe( 'artisanpack/template-part' );
	expect( $tree[0]['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_NOT_FOUND );
	expect( $tree[0]['innerBlocks'] )->toBe( [] );
} );

it( 'marks an artisanpack/template-part without a slug as missing-slug', function () {
	$tree = forkNameInliner( [] )->inline( [
		[ 'name' => 'artisanpack/template-part', 'attributes' => [], 'innerBlocks' => [] ],
	] );

	expect( $tree[0]['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_MISSING_SLUG );
} );

it( 'catches a cycle across mixed core and fork template-part names', function () {
	$tree = forkNameInliner( [
		'a' => [ forkPartRef( 'b', 'core/template-part' ) ],
		'b' => [ forkPartRef( 'a' ) ],
	] )->inline( [ forkPartRef( 'a' ) ] );

	$cycleNode = $tree[0]['innerBlocks'][0]['innerBlocks'][0];

	expect( $tree[0]['innerBlocks'][0]['name'] )->toBe( 'core/template-part' );
	expect( $cycleNode['name'] )->toBe( 'artisanpack/template-part' );
	expect( $cycleNode['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_CYCLE );
} );

it( 'applies the depth limit to fork-named part chains', function () {
	$inliner = new class( [
		'p0' => [ forkPartRef( 'p1' ) ],
		'p1' => [ forkPartRef( 'p2', 'core/template-part' ) ],
		'p2' => [ forkParagraph( 'Too deep' ) ],
	] ) extends TemplatePartInliner
	{
		/**
		 * @param  array<string, array<int, array<string, mixed>>>  $parts
		 */
		public function __construct( private readonly array $parts )
		{
			parent::__construct( 2 );
		}

		protected function findPartBlocks( string $slug ): ?array
		{
			return $this->parts[ $slug ] ?? null;
		}
	};

	$tree = $inliner->inline( [ forkPartRef( 'p0' ) ] );

	expect( $tree[0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['_resolutionError'] )
		->toBe( TemplatePartInliner::ERROR_DEPTH_LIMIT );
} );

it( 'resolves fork-named parts nested inside other blocks', function () {
	$tree = forkNameInliner( [ 'footer' => [ forkParagraph( 'Site footer' ) ] ] )->inline( [
		[
			'name'        => 'artisanpack/group',
			'attributes'  => [],
			'innerBlocks' => [ forkPartRef( 'footer' ) ],
		],
	] );

	expect( $tree[0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Site footer' );
} );
