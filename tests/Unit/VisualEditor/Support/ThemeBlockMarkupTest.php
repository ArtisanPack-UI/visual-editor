<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\ThemeBlockMarkup;

// `parseToEditorBlocks` needs cms-framework 2.5+'s `BlockMarkupParser`
// (PHP 8.3+), which isn't resolvable on PHP 8.2 CI — those assertions live
// in the controller/adapter suites behind a skip guard. Everything below is
// parser-independent and always runs.

describe( 'convertParseBlocksTree', function (): void {
	it( 'renames parse_blocks shape to the editor shape', function (): void {
		$out = ThemeBlockMarkup::convertParseBlocksTree( [
			[
				'blockName'   => 'core/group',
				'attrs'       => [ 'tagName' => 'main' ],
				'innerBlocks' => [],
			],
		] );

		expect( $out )->toBe( [
			[
				'name'        => 'core/group',
				'attributes'  => [ 'tagName' => 'main' ],
				'innerBlocks' => [],
			],
		] );
	} );

	it( 'converts nested innerBlocks recursively', function (): void {
		$out = ThemeBlockMarkup::convertParseBlocksTree( [
			[
				'blockName'   => 'core/group',
				'attrs'       => [],
				'innerBlocks' => [
					[
						'blockName'   => 'core/heading',
						'attrs'       => [ 'level' => 2 ],
						'innerBlocks' => [],
					],
				],
			],
		] );

		expect( $out[0]['innerBlocks'][0]['name'] )->toBe( 'core/heading' )
			->and( $out[0]['innerBlocks'][0]['attributes'] )->toBe( [ 'level' => 2 ] );
	} );

	it( 'drops freeform siblings with a null blockName', function (): void {
		// `parse_blocks()` emits these for the whitespace between blocks.
		// They would land in the editor as `core/freeform` mystery blocks.
		$out = ThemeBlockMarkup::convertParseBlocksTree( [
			[ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [] ],
			[ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [] ],
			[ 'blockName' => '', 'attrs' => [], 'innerBlocks' => [] ],
		] );

		expect( $out )->toHaveCount( 1 )
			->and( $out[0]['name'] )->toBe( 'core/paragraph' );
	} );

	it( 'drops entries whose blockName is not a non-empty string', function (): void {
		// A parser that ever hands back a non-string name must not reach
		// the editor as a block with a numeric or array `name`.
		$out = ThemeBlockMarkup::convertParseBlocksTree( [
			[ 'blockName' => 42, 'attrs' => [], 'innerBlocks' => [] ],
			[ 'blockName' => [ 'x' ], 'attrs' => [], 'innerBlocks' => [] ],
			[ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [] ],
		] );

		expect( $out )->toHaveCount( 1 )
			->and( $out[0]['name'] )->toBe( 'core/paragraph' );
	} );

	it( 'defaults missing or non-array attrs to an empty array', function (): void {
		$out = ThemeBlockMarkup::convertParseBlocksTree( [
			[ 'blockName' => 'core/separator' ],
			[ 'blockName' => 'core/spacer', 'attrs' => 'nope' ],
		] );

		expect( $out[0]['attributes'] )->toBe( [] )
			->and( $out[1]['attributes'] )->toBe( [] );
	} );
} );

describe( 'rewriteCoreToFork', function (): void {
	it( 'rewrites core names to the artisanpack fork', function (): void {
		$out = ThemeBlockMarkup::rewriteCoreToFork( [
			[ 'name' => 'core/group', 'attributes' => [], 'innerBlocks' => [] ],
		] );

		expect( $out[0]['name'] )->toBe( 'artisanpack/group' );
	} );

	it( 'recurses through innerBlocks', function (): void {
		$out = ThemeBlockMarkup::rewriteCoreToFork( [
			[
				'name'        => 'core/group',
				'attributes'  => [],
				'innerBlocks' => [
					[
						'name'        => 'core/heading',
						'attributes'  => [],
						'innerBlocks' => [
							[ 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
						],
					],
				],
			],
		] );

		expect( $out[0]['innerBlocks'][0]['name'] )->toBe( 'artisanpack/heading' )
			->and( $out[0]['innerBlocks'][0]['innerBlocks'][0]['name'] )->toBe( 'artisanpack/paragraph' );
	} );

	it( 'leaves already-forked and third-party namespaces alone', function (): void {
		$out = ThemeBlockMarkup::rewriteCoreToFork( [
			[ 'name' => 'artisanpack/callout', 'attributes' => [], 'innerBlocks' => [] ],
			[ 'name' => 'acme/widget', 'attributes' => [], 'innerBlocks' => [] ],
		] );

		expect( $out[0]['name'] )->toBe( 'artisanpack/callout' )
			->and( $out[1]['name'] )->toBe( 'acme/widget' );
	} );

	it( 'only rewrites on the namespace boundary', function (): void {
		// `corefoo/bar` shares the prefix but not the namespace.
		$out = ThemeBlockMarkup::rewriteCoreToFork( [
			[ 'name' => 'corefoo/bar', 'attributes' => [], 'innerBlocks' => [] ],
		] );

		expect( $out[0]['name'] )->toBe( 'corefoo/bar' );
	} );

	it( 'preserves attributes untouched', function (): void {
		$out = ThemeBlockMarkup::rewriteCoreToFork( [
			[
				'name'        => 'core/template-part',
				'attributes'  => [ 'slug' => 'header', 'theme' => 'dev-sample' ],
				'innerBlocks' => [],
			],
		] );

		expect( $out[0]['attributes'] )->toBe( [ 'slug' => 'header', 'theme' => 'dev-sample' ] );
	} );

	it( 'passes non-array entries through rather than dropping them', function (): void {
		$out = ThemeBlockMarkup::rewriteCoreToFork( [ 'junk', [ 'name' => 'core/group' ] ] );

		expect( $out[0] )->toBe( 'junk' )
			->and( $out[1]['name'] )->toBe( 'artisanpack/group' );
	} );

	it( 'returns an empty list unchanged', function (): void {
		expect( ThemeBlockMarkup::rewriteCoreToFork( [] ) )->toBe( [] );
	} );
} );

it( 'returns an empty tree for blank raw markup without needing the parser', function (): void {
	expect( ThemeBlockMarkup::parseToEditorBlocks( '' ) )->toBe( [] )
		->and( ThemeBlockMarkup::parseToEditorBlocks( "  \n\t " ) )->toBe( [] );
} );

describe( 'parseToEditorBlocks on malformed markup', function (): void {
	// A hand-edited theme file is the normal way this arrives. Whatever the
	// parser makes of it, the boundary has to hand back a well-shaped array
	// rather than throwing through every template read.
	it( 'returns an array for an unclosed block comment', function (): void {
		expect( ThemeBlockMarkup::parseToEditorBlocks( '<!-- wp:group {"a":1}' ) )
			->toBeArray();
	} );

	it( 'returns an array for invalid attribute JSON', function (): void {
		expect( ThemeBlockMarkup::parseToEditorBlocks( '<!-- wp:group {bad json} /-->' ) )
			->toBeArray();
	} );

	it( 'returns an array for a nameless block delimiter', function (): void {
		expect( ThemeBlockMarkup::parseToEditorBlocks( '<!-- wp: /-->' ) )
			->toBeArray();
	} );
} )->skip(
	fn () => ! class_exists( ThemeBlockMarkup::PARSER_FQCN ),
	'requires cms-framework 2.5+ (PHP 8.3+)'
);
