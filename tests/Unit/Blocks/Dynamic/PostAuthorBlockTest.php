<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostAuthor\PostAuthorBlock;

test( 'post author block has correct type', function (): void {
	$block = new PostAuthorBlock();

	expect( $block->getType() )->toBe( 'post-author' );
} );

test( 'post author block has correct category', function (): void {
	$block = new PostAuthorBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post author block has content schema with all fields', function (): void {
	$block  = new PostAuthorBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'showAvatar', 'avatarSize', 'showBio', 'byline', 'isLink' ] )
		->and( $schema['showAvatar']['type'] )->toBe( 'toggle' )
		->and( $schema['showAvatar']['default'] )->toBeTrue()
		->and( $schema['avatarSize']['type'] )->toBe( 'select' )
		->and( $schema['avatarSize']['default'] )->toBe( 'md' )
		->and( $schema['showBio']['type'] )->toBe( 'toggle' )
		->and( $schema['showBio']['default'] )->toBeTrue()
		->and( $schema['byline']['type'] )->toBe( 'text' )
		->and( $schema['byline']['default'] )->toBe( 'by' )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeFalse();
} );

test( 'post author block avatar size options include sm md and lg', function (): void {
	$block   = new PostAuthorBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['avatarSize']['options'] );

	expect( $options )->toContain( 'sm', 'md', 'lg' );
} );

test( 'post author block default content has correct values', function (): void {
	$block    = new PostAuthorBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['showAvatar'] )->toBeTrue()
		->and( $defaults['avatarSize'] )->toBe( 'md' )
		->and( $defaults['showBio'] )->toBeTrue()
		->and( $defaults['byline'] )->toBe( 'by' )
		->and( $defaults['isLink'] )->toBeFalse();
} );

test( 'post author block has keywords', function (): void {
	$block = new PostAuthorBlock();

	expect( $block->getKeywords() )->toContain( 'author' )
		->and( $block->getKeywords() )->toContain( 'avatar' );
} );

test( 'post author block supports typography', function (): void {
	$block    = new PostAuthorBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post author block supports color', function (): void {
	$block    = new PostAuthorBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post author block supports spacing', function (): void {
	$block    = new PostAuthorBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post author block is marked as dynamic', function (): void {
	$block = new PostAuthorBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
