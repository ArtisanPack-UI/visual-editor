<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\VisualEditor\Ai\Agents\HeadingHierarchyAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'short-circuits to an empty issues array when no headings are present', function (): void {
	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'id' => 'a', 'type' => 'core/paragraph' ],
			[ 'id' => 'b', 'type' => 'core/list' ],
		],
	] )->run();

	expect( $result['issues'] )->toBeArray()->toBeEmpty();
	expect( $this->prompter->calls )->toBeEmpty();
} );

it( 'returns issues shaped by the model when headings are present', function (): void {
	$this->prompter->queue( [
		'issues' => [
			[ 'block_id' => 'h1', 'issue' => 'duplicate h1', 'suggestion' => 'demote to h2' ],
			[ 'block_id' => 'h2', 'issue' => 'skipped level', 'suggestion' => 'insert h2 before' ],
		],
	] );

	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'id' => 'h1', 'type' => 'core/heading' ],
			[ 'id' => 'h2', 'type' => 'core/heading' ],
		],
	] )->run();

	expect( $result['issues'] )->toHaveCount( 2 );
	expect( $result['issues'][0]['block_id'] )->toBe( 'h1' );
} );

it( 'drops issues that reference block ids not in the input', function (): void {
	$this->prompter->queue( [
		'issues' => [
			[ 'block_id' => 'h1', 'issue' => 'real', 'suggestion' => 'fix' ],
			[ 'block_id' => 'ghost', 'issue' => 'hallucinated', 'suggestion' => 'nope' ],
		],
	] );

	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'id' => 'h1', 'type' => 'core/heading' ],
		],
	] )->run();

	expect( $result['issues'] )->toHaveCount( 1 );
	expect( $result['issues'][0]['block_id'] )->toBe( 'h1' );
} );

it( 'walks innerBlocks when detecting whether headings exist', function (): void {
	$this->prompter->queue( [
		'issues' => [
			[ 'block_id' => 'nested-h4', 'issue' => 'skipped level', 'suggestion' => 'change to h3' ],
		],
	] );

	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[
				'id'          => 'columns-1',
				'type'        => 'core/columns',
				'innerBlocks' => [
					[
						'id'          => 'col-a',
						'type'        => 'core/column',
						'innerBlocks' => [
							[ 'id' => 'nested-h4', 'type' => 'core/heading', 'attrs' => [ 'level' => 4 ] ],
						],
					],
				],
			],
		],
	] )->run();

	// Precondition: without recursion the agent would short-circuit and
	// the prompter would never be called.
	expect( $this->prompter->calls )->not->toBeEmpty();
	expect( $result['issues'] )->toHaveCount( 1 );
	expect( $result['issues'][0]['block_id'] )->toBe( 'nested-h4' );
} );

it( 'raises FeatureError when input is malformed', function (): void {
	expect( fn () => HeadingHierarchyAgent::for( 'nope' )->run() )
		->toThrow( FeatureError::class );

	expect( fn () => HeadingHierarchyAgent::for( [ 'blocks' => 'not-an-array' ] )->run() )
		->toThrow( FeatureError::class );
} );
