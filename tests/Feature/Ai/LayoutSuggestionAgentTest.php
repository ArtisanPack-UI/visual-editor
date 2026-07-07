<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns whitelisted matches sorted as provided by the model', function (): void {
	$this->prompter->queue( [
		'matches' => [
			[ 'pattern_slug' => 'hero-two-column', 'confidence' => 0.9, 'rationale' => 'side-by-side' ],
			[ 'pattern_slug' => 'feature-grid-3', 'confidence' => 0.6, 'rationale' => 'three parallel items' ],
		],
	] );

	$result = LayoutSuggestionAgent::for( [
		'section_content'    => [ [ 'type' => 'core/heading' ] ],
		'available_patterns' => [ 'hero-two-column', 'feature-grid-3', 'testimonial' ],
	] )->run();

	expect( $result['matches'] )->toHaveCount( 2 );
	expect( $result['matches'][0]['pattern_slug'] )->toBe( 'hero-two-column' );
} );

it( 'drops matches whose slug is not in the whitelist', function (): void {
	$this->prompter->queue( [
		'matches' => [
			[ 'pattern_slug' => 'hero-two-column', 'confidence' => 0.9, 'rationale' => 'ok' ],
			[ 'pattern_slug' => 'made-up-pattern', 'confidence' => 0.8, 'rationale' => 'hallucinated' ],
		],
	] );

	$result = LayoutSuggestionAgent::for( [
		'section_content'    => [],
		'available_patterns' => [ 'hero-two-column' ],
	] )->run();

	expect( $result['matches'] )->toHaveCount( 1 );
	expect( $result['matches'][0]['pattern_slug'] )->toBe( 'hero-two-column' );
} );

it( 'clamps confidence values to [0, 1]', function (): void {
	$this->prompter->queue( [
		'matches' => [
			[ 'pattern_slug' => 'p1', 'confidence' => 1.5, 'rationale' => 'over' ],
			[ 'pattern_slug' => 'p2', 'confidence' => -0.2, 'rationale' => 'under' ],
		],
	] );

	$result = LayoutSuggestionAgent::for( [
		'section_content'    => [],
		'available_patterns' => [ 'p1', 'p2' ],
	] )->run();

	expect( $result['matches'][0]['confidence'] )->toBe( 1.0 );
	expect( $result['matches'][1]['confidence'] )->toBe( 0.0 );
} );

it( 'raises FeatureError when available_patterns is empty', function (): void {
	expect( fn () => LayoutSuggestionAgent::for( [
		'section_content'    => [],
		'available_patterns' => [],
	] )->run() )->toThrow( FeatureError::class );
} );

it( 'raises FeatureError when section_content is not an array', function (): void {
	expect( fn () => LayoutSuggestionAgent::for( [
		'section_content'    => 'oops',
		'available_patterns' => [ 'x' ],
	] )->run() )->toThrow( FeatureError::class );
} );
