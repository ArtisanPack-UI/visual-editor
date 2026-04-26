<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Resources\PatternInliner;

function patternInlinerFixture( string $slug, array $blocks, bool $synced = true ): VisualEditorPattern
{
	return VisualEditorPattern::create( [
		'slug'    => $slug,
		'title'   => ucfirst( $slug ),
		'content' => [ 'raw' => '', 'blocks' => $blocks ],
		'synced'  => $synced,
		'status'  => 'publish',
	] );
}

function patternInlinerRef( int $ref, string $clientId = 'pat-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/block',
		'attributes'  => [ 'ref' => $ref ],
		'innerBlocks' => [],
	];
}

function patternInlinerParagraph( string $text, string $clientId = 'p-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => $text ],
		'innerBlocks' => [],
	];
}

it( 'inlines a single core/block reference', function () {
	$pattern = patternInlinerFixture( 'hero', [ patternInlinerParagraph( 'Hero copy' ) ] );

	$tree = [ patternInlinerRef( $pattern->id ) ];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved )->toHaveCount( 1 );
	expect( $resolved[0]['name'] )->toBe( 'core/block' );
	expect( $resolved[0]['innerBlocks'] )->toHaveCount( 1 );
	expect( $resolved[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Hero copy' );
} );

it( 'descends into nested innerBlocks looking for synced patterns', function () {
	$pattern = patternInlinerFixture( 'inside', [ patternInlinerParagraph( 'Inside group' ) ] );

	$tree = [
		[
			'clientId'    => 'g-1',
			'name'        => 'core/group',
			'attributes'  => [],
			'innerBlocks' => [ patternInlinerRef( $pattern->id ) ],
		],
	];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved[0]['innerBlocks'][0]['name'] )->toBe( 'core/block' );
	expect( $resolved[0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Inside group' );
} );

it( 'recurses through nested synced patterns', function () {
	$inner = patternInlinerFixture( 'inner', [ patternInlinerParagraph( 'Innermost' ) ] );
	$outer = patternInlinerFixture( 'outer', [ patternInlinerRef( $inner->id, 'inner-ref' ) ] );

	$tree = [ patternInlinerRef( $outer->id, 'outer-root' ) ];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved[0]['innerBlocks'][0]['name'] )->toBe( 'core/block' );
	expect( $resolved[0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Innermost' );
} );

it( 'marks a missing ref attribute with the missing-ref error', function () {
	$tree = [
		[
			'clientId'    => 'pat-1',
			'name'        => 'core/block',
			'attributes'  => [],
			'innerBlocks' => [],
		],
	];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] )->toBe( PatternInliner::ERROR_MISSING_REF );
	expect( $resolved[0]['innerBlocks'] )->toBe( [] );
} );

it( 'marks a missing record with the not-found error', function () {
	$tree = [ patternInlinerRef( 9999 ) ];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] )->toBe( PatternInliner::ERROR_NOT_FOUND );
} );

it( 'detects a direct cycle (a → a)', function () {
	$pattern = patternInlinerFixture( 'looper', [] );

	$pattern->setContentEnvelope( [
		'raw'    => '',
		'blocks' => [ patternInlinerRef( $pattern->id, 'self-ref' ) ],
	] );
	$pattern->save();

	$tree = [ patternInlinerRef( $pattern->id, 'root' ) ];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] ?? null )->toBeNull();
	expect( $resolved[0]['innerBlocks'][0]['attributes']['_resolutionError'] )
		->toBe( PatternInliner::ERROR_CYCLE );
} );

it( 'detects an indirect cycle (a → b → a)', function () {
	$patternA = patternInlinerFixture( 'a', [] );
	$patternB = patternInlinerFixture( 'b', [] );

	$patternA->setContentEnvelope( [
		'raw'    => '',
		'blocks' => [ patternInlinerRef( $patternB->id, 'b-ref' ) ],
	] );
	$patternA->save();

	$patternB->setContentEnvelope( [
		'raw'    => '',
		'blocks' => [ patternInlinerRef( $patternA->id, 'a-ref' ) ],
	] );
	$patternB->save();

	$tree = [ patternInlinerRef( $patternA->id, 'a-root' ) ];

	$resolved = ( new PatternInliner() )->inline( $tree );

	$cycleNode = $resolved[0]['innerBlocks'][0]['innerBlocks'][0];

	expect( $cycleNode['name'] )->toBe( 'core/block' );
	expect( $cycleNode['attributes']['_resolutionError'] )->toBe( PatternInliner::ERROR_CYCLE );
} );

it( 'enforces the depth limit', function () {
	// Build a five-level chain: p0 → p1 → p2 → p3 → p4 (terminal).
	$created = [];
	for ( $i = 0; $i < 5; $i++ ) {
		$created[ $i ] = patternInlinerFixture( 'p' . $i, [] );
	}

	for ( $i = 0; $i < 4; $i++ ) {
		$created[ $i ]->setContentEnvelope( [
			'raw'    => '',
			'blocks' => [ patternInlinerRef( $created[ $i + 1 ]->id, 'p' . ( $i + 1 ) . '-ref' ) ],
		] );
		$created[ $i ]->save();
	}

	$tree = [ patternInlinerRef( $created[ 0 ]->id, 'p0-root' ) ];

	$resolved = ( new PatternInliner( 2 ) )->inline( $tree );

	$third = $resolved[0]['innerBlocks'][0]['innerBlocks'][0];

	expect( $third['name'] )->toBe( 'core/block' );
	expect( $third['attributes']['_resolutionError'] )->toBe( PatternInliner::ERROR_DEPTH_LIMIT );
} );

it( 'preserves non-core/block blocks untouched', function () {
	$tree = [ patternInlinerParagraph( 'Just text', 'p-only' ) ];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved )->toBe( $tree );
} );

it( 'returns an empty array when given non-array entries', function () {
	$tree = [ 'not-a-block', null, 42 ];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved )->toBe( [] );
} );

it( 'normalizes numeric-string refs to integers', function () {
	$pattern = patternInlinerFixture( 'numeric-string', [ patternInlinerParagraph( 'Resolved' ) ] );

	$tree = [
		[
			'clientId'    => 'pat-num-string',
			'name'        => 'core/block',
			'attributes'  => [ 'ref' => (string) $pattern->id ],
			'innerBlocks' => [],
		],
	];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] ?? null )->toBeNull();
	expect( $resolved[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Resolved' );
} );

it( 'rejects non-numeric ref values with the missing-ref error', function () {
	$tree = [
		[
			'clientId'    => 'pat-bad-ref',
			'name'        => 'core/block',
			'attributes'  => [ 'ref' => 'not-a-number' ],
			'innerBlocks' => [],
		],
	];

	$resolved = ( new PatternInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] )->toBe( PatternInliner::ERROR_MISSING_REF );
} );

it( 'returns an empty resolved tree (no core/block references) when only unsynced patterns exist', function () {
	// The synced/unsynced contract: unsynced patterns are inlined into
	// the saved tree at insert time by the editor, so the inliner only
	// ever sees `core/block` references for synced patterns. A tree
	// composed of inlined unsynced-pattern blocks passes through
	// untouched.
	$pattern = patternInlinerFixture(
		'unsynced',
		[ patternInlinerParagraph( 'Inlined at insert time' ) ],
		false
	);

	$treeAsSaved = $pattern->getBlocks();

	$resolved = ( new PatternInliner() )->inline( $treeAsSaved );

	expect( $resolved )->toBe( $treeAsSaved );
} );
