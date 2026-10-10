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

it( 'detects headings in the editor block tree shape (#825)', function (): void {
	$this->prompter->queue( [
		'issues' => [
			[ 'block_id' => 'client-h4', 'issue' => 'skipped level', 'suggestion' => 'change to h3' ],
		],
	] );

	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[
				'clientId'    => 'client-h2',
				'name'        => 'artisanpack/heading',
				'attributes'  => [ 'level' => 2, 'content' => 'Intro' ],
				'innerBlocks' => [],
			],
			[
				'clientId'    => 'client-h4',
				'name'        => 'artisanpack/heading',
				'attributes'  => [ 'level' => 4, 'content' => 'Details' ],
				'innerBlocks' => [],
			],
		],
	] )->run();

	expect( $this->prompter->calls )->not->toBeEmpty();
	expect( $result['issues'] )->toHaveCount( 1 );
	expect( $result['issues'][0]['block_id'] )->toBe( 'client-h4' );
} );

it( 'detects a nested editor-shaped heading and keeps its clientId (#825)', function (): void {
	$this->prompter->queue( [
		'issues' => [
			[ 'block_id' => 'nested-h4', 'issue' => 'skipped level', 'suggestion' => 'change to h3' ],
			[ 'block_id' => 'ghost', 'issue' => 'hallucinated', 'suggestion' => 'nope' ],
		],
	] );

	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[
				'clientId'    => 'group-1',
				'name'        => 'artisanpack/group',
				'attributes'  => [],
				'innerBlocks' => [
					[
						'clientId'    => 'nested-h4',
						'name'        => 'artisanpack/heading',
						'attributes'  => [ 'level' => 4 ],
						'innerBlocks' => [],
					],
				],
			],
		],
	] )->run();

	expect( $this->prompter->calls )->not->toBeEmpty();
	expect( $result['issues'] )->toHaveCount( 1 );
	expect( $result['issues'][0]['block_id'] )->toBe( 'nested-h4' );
} );

it( 'detects headings in the parse_blocks shape', function (): void {
	$this->prompter->queue( [ 'issues' => [] ] );

	HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'blockName' => 'core/heading', 'attrs' => [ 'level' => 2 ], 'innerBlocks' => [] ],
		],
	] )->run();

	expect( $this->prompter->calls )->not->toBeEmpty();
} );

it( 'still short-circuits on an editor tree with no headings (#825)', function (): void {
	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'clientId' => 'p1', 'name' => 'artisanpack/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
			[ 'clientId' => 'x1', 'name' => 'acme/heading-ish', 'attributes' => [], 'innerBlocks' => [] ],
		],
	] )->run();

	expect( $result['issues'] )->toBeEmpty();
	expect( $this->prompter->calls )->toBeEmpty();
} );

it( 'raises FeatureError when input is malformed', function (): void {
	expect( fn () => HeadingHierarchyAgent::for( 'nope' )->run() )
		->toThrow( FeatureError::class );

	expect( fn () => HeadingHierarchyAgent::for( [ 'blocks' => 'not-an-array' ] )->run() )
		->toThrow( FeatureError::class );
} );

it( 'strips editor-only keys and non-text attributes before sending the tree (#828)', function (): void {
	$this->prompter->queue( [
		'issues' => [
			[ 'block_id' => 'client-h2', 'issue' => 'ambiguous heading', 'suggestion' => 'be specific' ],
		],
	] );

	$result = HeadingHierarchyAgent::for( [
		'blocks' => [
			[
				'clientId'         => 'client-h2',
				'name'             => 'artisanpack/heading',
				'isValid'          => true,
				'originalContent'  => '<h2>Details</h2>',
				'validationIssues' => [],
				'attributes'       => [
					'level'   => 2,
					'content' => '<strong>Details</strong>',
					'style'   => [ 'color' => [ 'text' => '#000' ] ],
					'url'     => 'https://example.com/huge.png',
				],
				'innerBlocks'      => [
					[
						'clientId'    => 'client-p',
						'name'        => 'artisanpack/paragraph',
						'attributes'  => [ 'content' => str_repeat( 'word ', 200 ), 'dropCap' => true ],
						'innerBlocks' => [],
					],
				],
			],
		],
	] )->run();

	$message = json_encode( $this->prompter->calls[0]['message'] );

	expect( $message )
		->not->toContain( 'originalContent' )
		->not->toContain( 'validationIssues' )
		->not->toContain( 'isValid' )
		->not->toContain( 'huge.png' )
		->not->toContain( 'dropCap' )
		->not->toContain( 'strong' )
		->toContain( 'client-h2' )
		->toContain( 'client-p' )
		->toContain( 'Details' );

	// Ids survive stripping, so the model's citation still validates.
	expect( $result['issues'] )->toHaveCount( 1 );
	expect( $result['issues'][0]['block_id'] )->toBe( 'client-h2' );
} );

it( 'reads heading text from parse_blocks markup when stripping (#828)', function (): void {
	$this->prompter->queue( [ 'issues' => [] ] );

	HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'blockName' => 'core/heading', 'attrs' => [ 'level' => 3 ], 'innerHTML' => '<h3 class="x">Pricing</h3>', 'innerBlocks' => [] ],
		],
	] )->run();

	$message = $this->prompter->calls[0]['message'][0]['text'];

	expect( $message )->toContain( 'Pricing' )->toContain( '"level":3' )->not->toContain( 'class' );
} );

it( 'rejects a tree with more blocks than the configured limit (#828)', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_blocks', 3 );

	$blocks = array_map(
		fn ( int $i ): array => [ 'id' => "h{$i}", 'type' => 'core/heading' ],
		range( 1, 4 ),
	);

	expect( fn () => HeadingHierarchyAgent::for( [ 'blocks' => $blocks ] )->run() )
		->toThrow( FeatureError::class, 'more than 3 blocks' );
	expect( $this->prompter->calls )->toBeEmpty();
} );

it( 'counts nested blocks toward the block limit (#828)', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_blocks', 2 );

	expect( fn () => HeadingHierarchyAgent::for( [
		'blocks' => [
			[
				'id'          => 'g',
				'type'        => 'core/group',
				'innerBlocks' => [
					[ 'id' => 'h1', 'type' => 'core/heading' ],
					[ 'id' => 'h2', 'type' => 'core/heading' ],
				],
			],
		],
	] )->run() )->toThrow( FeatureError::class );
} );

it( 'rejects a tree nested deeper than the configured limit (#828)', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_depth', 3 );

	$block = [ 'id' => 'h', 'type' => 'core/heading' ];
	for ( $i = 0; $i < 3; $i++ ) {
		$block = [ 'id' => "g{$i}", 'type' => 'core/group', 'innerBlocks' => [ $block ] ];
	}

	expect( fn () => HeadingHierarchyAgent::for( [ 'blocks' => [ $block ] ] )->run() )
		->toThrow( FeatureError::class, 'more than 3 levels' );
	expect( $this->prompter->calls )->toBeEmpty();
} );

it( 'accepts a tree exactly at the depth and count limits (#828)', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_depth', 2 );
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_blocks', 2 );
	$this->prompter->queue( [ 'issues' => [] ] );

	HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'id' => 'g', 'type' => 'core/group', 'innerBlocks' => [ [ 'id' => 'h', 'type' => 'core/heading' ] ] ],
		],
	] )->run();

	expect( $this->prompter->calls )->toHaveCount( 1 );
} );

it( 'rejects a serialized payload over the byte limit instead of truncating it (#828)', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_bytes', 200 );

	expect( fn () => HeadingHierarchyAgent::for( [
		'blocks' => [
			[ 'id' => 'h', 'type' => 'core/heading', 'attrs' => [ 'content' => str_repeat( 'Long heading ', 50 ) ] ],
		],
	] )->run() )->toThrow( FeatureError::class, 'too large' );
	expect( $this->prompter->calls )->toBeEmpty();
} );

it( 'rejects oversized payloads on the Livewire entry point (#828)', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_blocks', 1 );
	Illuminate\Support\Facades\Gate::define( ArtisanPackUI\VisualEditor\Ai\Support\AiAccess::ABILITY, fn ( $user = null ) => true );

	$tools = new class extends ArtisanPackUI\VisualEditor\Livewire\Ai\AiTools {
		/** @var array<int, array{0: string, 1: array<string, mixed>}> */
		public array $events = [];

		public function dispatch( $event, ...$params )
		{
			$this->events[] = [ $event, $params ];

			return null;
		}
	};

	$tools->checkHeadings( [
		[ 'id' => 'h1', 'type' => 'core/heading' ],
		[ 'id' => 'h2', 'type' => 'core/heading' ],
	] );

	expect( $tools->events )->toHaveCount( 1 );
	expect( $tools->events[0][0] )->toBe( 'ap-ve-ai:visual_editor.heading_hierarchy:invalid-input' );
	expect( $this->prompter->calls )->toBeEmpty();
} );
