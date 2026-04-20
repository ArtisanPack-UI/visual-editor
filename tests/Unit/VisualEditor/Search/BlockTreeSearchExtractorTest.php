<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\Search\BlockTreeSearchExtractor;

it( 'extracts text from known static attribute keys only', function () {
	$tree = [
		[
			'clientId'    => 'a',
			'name'        => 'core/paragraph',
			'attributes'  => [
				'content'    => 'Hello world',
				'dropCap'    => true,
				'className'  => 'my-class',
				'anchor'     => 'section-1',
			],
			'innerBlocks' => [],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Hello world' );
} );

it( 'extracts caption, alt, and title on an image block', function () {
	$tree = [
		[
			'clientId'    => 'img',
			'name'        => 'core/image',
			'attributes'  => [
				'url'     => 'https://example.test/cat.jpg',
				'alt'     => 'A sleeping cat',
				'caption' => 'Our cat Felix napping',
				'title'   => 'Felix',
			],
			'innerBlocks' => [],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Our cat Felix napping A sleeping cat Felix' );
} );

it( 'strips HTML tags from RichText content', function () {
	$tree = [
		[
			'clientId'    => 'p',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Hello <strong>brave</strong> <em>world</em>' ],
			'innerBlocks' => [],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Hello brave world' );
} );

it( 'recurses into innerBlocks', function () {
	$tree = [
		[
			'clientId'    => 'group',
			'name'        => 'core/group',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'clientId'    => 'heading',
					'name'        => 'core/heading',
					'attributes'  => [ 'content' => 'A Heading' ],
					'innerBlocks' => [],
				],
				[
					'clientId'    => 'paragraph',
					'name'        => 'core/paragraph',
					'attributes'  => [ 'content' => 'A paragraph.' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'A Heading A paragraph.' );
} );

it( 'delegates to DynamicBlock::searchableText() for registered dynamic blocks', function () {
	$block = new class extends DynamicBlock {
		public function name(): string
		{
			return 'acme/latest-products';
		}

		public function render( array $attrs ): string
		{
			return '';
		}

		public function searchableText( array $attrs ): string
		{
			return 'Product Alpha Product Beta';
		}
	};

	VisualEditor::registerDynamicBlock( $block );

	$tree = [
		[
			'clientId'    => 'latest',
			'name'        => 'acme/latest-products',
			'attributes'  => [ 'productIds' => [ 1, 2, 3 ] ],
			'innerBlocks' => [],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Product Alpha Product Beta' );
} );

it( 'returns empty string for a dynamic block that has not overridden searchableText()', function () {
	$block = new class extends DynamicBlock {
		public function name(): string
		{
			return 'acme/silent';
		}

		public function render( array $attrs ): string
		{
			return '';
		}
	};

	VisualEditor::registerDynamicBlock( $block );

	$tree = [
		[
			'clientId'    => 's',
			'name'        => 'acme/silent',
			'attributes'  => [ 'content' => 'This should not leak' ],
			'innerBlocks' => [],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( '' );
} );

it( 'combines static and dynamic block contributions in tree order', function () {
	VisualEditor::registerDynamicBlock( 'acme/shoutout', [
		'render'         => static fn ( array $attrs ): string => '',
		'searchableText' => static fn ( array $attrs ): string => 'Partner spotlight',
	] );

	$tree = [
		[
			'clientId'    => 'p1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Intro copy.' ],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'shout',
			'name'        => 'acme/shoutout',
			'attributes'  => [ 'partnerId' => 42 ],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'p2',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Outro copy.' ],
			'innerBlocks' => [],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Intro copy. Partner spotlight Outro copy.' );
} );

it( 'walks innerBlocks even when the parent is a dynamic block', function () {
	VisualEditor::registerDynamicBlock( 'acme/wrapper', [
		'render'         => static fn ( array $attrs ): string => '',
		'searchableText' => static fn ( array $attrs ): string => 'Wrapper label',
	] );

	$tree = [
		[
			'clientId'    => 'wrap',
			'name'        => 'acme/wrapper',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'clientId'    => 'inner',
					'name'        => 'core/paragraph',
					'attributes'  => [ 'content' => 'Inner paragraph text.' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Wrapper label Inner paragraph text.' );
} );

it( 'returns an empty string for an empty tree', function () {
	$extracted = app( BlockTreeSearchExtractor::class )->extract( [] );

	expect( $extracted )->toBe( '' );
} );

it( 'skips malformed entries instead of throwing', function () {
	$tree = [
		'not-an-array',
		[
			'clientId'    => 'p',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Valid text.' ],
			'innerBlocks' => [],
		],
		[ 'name' => 'core/paragraph' ],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Valid text.' );
} );

it( 'swallows exceptions thrown by a dynamic block extractor', function () {
	VisualEditor::registerDynamicBlock( 'acme/broken', [
		'render'         => static fn ( array $attrs ): string => '',
		'searchableText' => static function ( array $attrs ): string {
			throw new RuntimeException( 'boom' );
		},
	] );

	$tree = [
		[
			'clientId'    => 'ok',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Before' ],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'bad',
			'name'        => 'acme/broken',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'ok2',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'After' ],
			'innerBlocks' => [],
		],
	];

	$extracted = app( BlockTreeSearchExtractor::class )->extract( $tree );

	expect( $extracted )->toBe( 'Before After' );
} );
