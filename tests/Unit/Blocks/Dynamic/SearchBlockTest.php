<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\Search\SearchBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\SearchBlockComponent;

test( 'search block has correct type', function (): void {
	$block = new SearchBlock();

	expect( $block->getType() )->toBe( 'search' );
} );

test( 'search block is dynamic', function (): void {
	$block = new SearchBlock();

	expect( $block->isDynamic() )->toBeTrue();
} );

test( 'search block has correct category', function (): void {
	$block = new SearchBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'search block returns correct component', function (): void {
	$block = new SearchBlock();

	expect( $block->getComponent() )->toBe( SearchBlockComponent::class );
} );

test( 'search block has content schema with form fields', function (): void {
	$block  = new SearchBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [
		'placeholder', 'buttonText', 'buttonPosition',
		'buttonIcon', 'showLabel', 'label',
		'resultsPerPage', 'searchScope', 'displayStyle',
	] );
} );

test( 'search block default content has correct values', function (): void {
	$block    = new SearchBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['buttonPosition'] )->toBe( 'outside' )
		->and( $defaults['showLabel'] )->toBeTrue()
		->and( $defaults['resultsPerPage'] )->toBe( 10 )
		->and( $defaults['searchScope'] )->toBe( 'all' )
		->and( $defaults['displayStyle'] )->toBe( 'inline' );
} );

test( 'search block has toolbar controls', function (): void {
	$block    = new SearchBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'displayStyle' );
} );

test( 'search block toArray includes dynamic metadata', function (): void {
	$block = new SearchBlock();
	$array = $block->toArray();

	expect( $array['dynamic'] )->toBeTrue()
		->and( $array['component'] )->toBe( SearchBlockComponent::class )
		->and( $array['type'] )->toBe( 'search' );
} );

test( 'search block has keywords', function (): void {
	$block = new SearchBlock();

	expect( $block->getKeywords() )->toContain( 'search' )
		->and( $block->getKeywords() )->toContain( 'find' );
} );
