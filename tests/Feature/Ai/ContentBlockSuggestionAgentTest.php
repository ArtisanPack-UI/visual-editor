<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\VisualEditor\Ai\Agents\ContentBlockSuggestionAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns shaped suggestions when the prompter responds', function (): void {
	$this->prompter->queue( [
		'suggestions' => [
			[ 'block_type' => 'core/heading', 'why' => 'starts a new section' ],
			[ 'block_type' => 'core/paragraph', 'why' => 'continues the narrative', 'starter_content' => 'To begin,' ],
		],
	] );

	$result = ContentBlockSuggestionAgent::for( [
		'existing_blocks' => [ [ 'type' => 'core/paragraph' ] ],
		'cursor_position' => 1,
	] )->run();

	expect( $result['suggestions'] )->toHaveCount( 2 );
	expect( $result['suggestions'][0]['block_type'] )->toBe( 'core/heading' );
	expect( $result['suggestions'][1] )->toHaveKey( 'starter_content' );
} );

it( 'drops suggestions missing required fields', function (): void {
	$this->prompter->queue( [
		'suggestions' => [
			[ 'block_type' => 'core/list', 'why' => 'good for enumerating' ],
			[ 'block_type' => '', 'why' => 'empty type is invalid' ],
			[ 'why' => 'no block_type at all' ],
		],
	] );

	$result = ContentBlockSuggestionAgent::for( [
		'existing_blocks' => [],
		'cursor_position' => 0,
	] )->run();

	expect( $result['suggestions'] )->toHaveCount( 1 );
	expect( $result['suggestions'][0]['block_type'] )->toBe( 'core/list' );
} );

it( 'caps returned suggestions at 4', function (): void {
	$queued = [ 'suggestions' => [] ];
	for ( $i = 0; $i < 8; $i++ ) {
		$queued['suggestions'][] = [ 'block_type' => "core/type-{$i}", 'why' => 'ok' ];
	}
	$this->prompter->queue( $queued );

	$result = ContentBlockSuggestionAgent::for( [
		'existing_blocks' => [],
		'cursor_position' => 0,
	] )->run();

	expect( $result['suggestions'] )->toHaveCount( 4 );
} );

it( 'raises FeatureError on invalid input', function (): void {
	expect( fn () => ContentBlockSuggestionAgent::for( 'not-an-array' )->run() )
		->toThrow( FeatureError::class );

	expect( fn () => ContentBlockSuggestionAgent::for( [ 'existing_blocks' => [] ] )->run() )
		->toThrow( FeatureError::class );

	expect( fn () => ContentBlockSuggestionAgent::for( [ 'existing_blocks' => [], 'cursor_position' => -1 ] )->run() )
		->toThrow( FeatureError::class );
} );

it( 'forwards document_type into the prompter message when set', function (): void {
	$this->prompter->queue( [ 'suggestions' => [ [ 'block_type' => 'core/heading', 'why' => 'ok' ] ] ] );

	ContentBlockSuggestionAgent::for( [
		'existing_blocks' => [],
		'cursor_position' => 0,
		'document_type'   => 'landing-page',
	] )->run();

	$parts = collect( $this->prompter->calls[0]['message'] )->pluck( 'text' );
	expect( $parts->contains( fn ( string $t ): bool => str_contains( $t, 'landing-page' ) ) )->toBeTrue();
} );
