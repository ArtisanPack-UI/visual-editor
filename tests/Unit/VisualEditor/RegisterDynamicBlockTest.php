<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\ClosureDynamicBlock;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use Tests\Fixtures\TestDynamicBlock;

it( 'registers a class-style dynamic block via the container', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$block = app( DynamicBlockRegistry::class )->get( 'tests/hello' );

	expect( $block )->toBeInstanceOf( TestDynamicBlock::class );
} );

it( 'registers a closure-style dynamic block with just a render callback', function () {
	VisualEditor::registerDynamicBlock( 'acme/badge', [
		'render' => static fn ( array $attrs ): string => '<span>' . $attrs['text'] . '</span>',
	] );

	$block = app( DynamicBlockRegistry::class )->get( 'acme/badge' );

	expect( $block )->toBeInstanceOf( ClosureDynamicBlock::class )
		->and( $block->name() )->toBe( 'acme/badge' )
		->and( $block->render( [ 'text' => 'new' ] ) )->toBe( '<span>new</span>' );
} );

it( 'wires optional closure callbacks for search, validate, and authorize', function () {
	VisualEditor::registerDynamicBlock( 'acme/callout', [
		'render'         => static fn ( array $attrs ): string => '<b>' . ( $attrs['text'] ?? '' ) . '</b>',
		'searchableText' => static fn ( array $attrs ): string => 'search:' . ( $attrs['text'] ?? '' ),
		'validateAttrs'  => static fn ( array $attrs ): array => [ 'text' => strtoupper( (string) ( $attrs['text'] ?? '' ) ) ],
		'authorize'      => static fn ( $user, array $attrs ): bool => 'no' !== ( $attrs['text'] ?? null ),
	] );

	$block = app( DynamicBlockRegistry::class )->get( 'acme/callout' );

	expect( $block->searchableText( [ 'text' => 'hi' ] ) )->toBe( 'search:hi' )
		->and( $block->validateAttrs( [ 'text' => 'hi' ] ) )->toBe( [ 'text' => 'HI' ] )
		->and( $block->authorize( null, [ 'text' => 'hi' ] ) )->toBeTrue()
		->and( $block->authorize( null, [ 'text' => 'no' ] ) )->toBeFalse();
} );

it( 'accepts a pre-built DynamicBlock instance', function () {
	$instance = new TestDynamicBlock();

	VisualEditor::registerDynamicBlock( $instance );

	expect( app( DynamicBlockRegistry::class )->get( 'tests/hello' ) )->toBe( $instance );
} );

it( 'rejects a closure config without a render callback', function () {
	VisualEditor::registerDynamicBlock( 'acme/bad', [
		'searchableText' => static fn () => '',
	] );
} )->throws( InvalidArgumentException::class, 'render' );

it( 'rejects a closure config with a non-callable searchableText entry', function () {
	VisualEditor::registerDynamicBlock( 'acme/bad', [
		'render'         => static fn (): string => '<p></p>',
		'searchableText' => 'not a callable',
	] );
} )->throws( InvalidArgumentException::class, 'searchableText' );

it( 'rejects a closure config with a non-callable validateAttrs entry', function () {
	VisualEditor::registerDynamicBlock( 'acme/bad', [
		'render'        => static fn (): string => '<p></p>',
		'validateAttrs' => 'not a callable',
	] );
} )->throws( InvalidArgumentException::class, 'validateAttrs' );

it( 'rejects a closure config with a non-callable authorize entry', function () {
	VisualEditor::registerDynamicBlock( 'acme/bad', [
		'render'    => static fn (): string => '<p></p>',
		'authorize' => 'not a callable',
	] );
} )->throws( InvalidArgumentException::class, 'authorize' );

it( 'rejects a non-existent class name', function () {
	VisualEditor::registerDynamicBlock( 'Tests\\Fixtures\\NoSuchBlock' );
} )->throws( InvalidArgumentException::class, 'does not exist' );

it( 'rejects a class that does not extend DynamicBlock', function () {
	VisualEditor::registerDynamicBlock( \stdClass::class );
} )->throws( InvalidArgumentException::class, 'must extend' );

it( 'returns an empty string from the default searchableText()', function () {
	$block = new class extends DynamicBlock {
		public function name(): string
		{
			return 'tests/default-search';
		}

		public function render( array $attrs )
		{
			return '';
		}
	};

	$text = $block->searchableText( [
		'title'  => 'Hello',
		'nested' => [ 'line' => 'World', 'ignored' => 42 ],
	] );

	expect( $text )->toBe( '' );
} );
