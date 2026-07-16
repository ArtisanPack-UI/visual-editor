<?php

/**
 * Exercises the BlockRenderer's inline-token pass end-to-end. Uses a
 * fake DynamicContentResolver bound as the class instance so we
 * verify the pass fires without pulling cms-framework's live resolver.
 *
 * @since 1.4.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

class FakeInlineResolver
{
	public function render( string $content, array $context = [] ): string
	{
		return preg_replace_callback(
			'/\{\{\s*([^{}]+?)\s*\}\}/',
			fn ( array $m ) => match ( trim( $m[1] ) ) {
				'business_info.phone' => '(555) 123-4567',
				'business_info.email' => 'hi@example.com',
				'team[0].role'        => 'CTO',
				default               => '',
			},
			$content
		) ?? $content;
	}

	public function signatureFor( string $content ): string
	{
		return 'test-sig';
	}
}

beforeEach( function () {
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentResolver',
		new FakeInlineResolver()
	);

	$this->renderer = app( BlockRenderer::class );
} );

it( 'resolves inline tokens in string attrs', function () {
	$tree = [
		[
			'name'  => 'artisanpack/paragraph',
			'attrs' => [
				'content' => 'Call us at {{business_info.phone}} — CTO is {{team[0].role}}.',
			],
			'innerBlocks' => [],
		],
	];

	$resolved = $this->renderer->resolveInlineTokens( $tree );

	expect( $resolved[0]['attrs']['content'] )
		->toBe( 'Call us at (555) 123-4567 — CTO is CTO.' );
} );

it( 'leaves attrs without tokens untouched', function () {
	$tree = [
		[
			'name'  => 'artisanpack/paragraph',
			'attrs' => [ 'content' => 'Static text only.' ],
			'innerBlocks' => [],
		],
	];

	$resolved = $this->renderer->resolveInlineTokens( $tree );

	expect( $resolved[0]['attrs']['content'] )->toBe( 'Static text only.' );
} );

it( 'recurses into inner blocks', function () {
	$tree = [
		[
			'name'  => 'artisanpack/group',
			'attrs' => [],
			'innerBlocks' => [
				[
					'name'  => 'artisanpack/paragraph',
					'attrs' => [ 'content' => 'Email: {{business_info.email}}' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$resolved = $this->renderer->resolveInlineTokens( $tree );

	expect( $resolved[0]['innerBlocks'][0]['attrs']['content'] )
		->toBe( 'Email: hi@example.com' );
} );

it( 'resolves tokens in Gutenberg-shape blocks (attributes key)', function () {
	$tree = [
		[
			'name'       => 'artisanpack/paragraph',
			'attributes' => [ 'content' => 'Phone: {{business_info.phone}}' ],
			'innerBlocks' => [],
		],
	];

	$resolved = $this->renderer->resolveInlineTokens( $tree );

	expect( $resolved[0]['attributes']['content'] )->toBe( 'Phone: (555) 123-4567' );
	// Verify the `attrs` fallback key wasn't accidentally introduced.
	expect( isset( $resolved[0]['attrs'] ) )->toBeFalse();
} );

it( 'passes through non-string attrs untouched', function () {
	$tree = [
		[
			'name'  => 'artisanpack/heading',
			'attrs' => [
				'level'   => 2,
				'content' => 'CTO is {{team[0].role}}',
			],
			'innerBlocks' => [],
		],
	];

	$resolved = $this->renderer->resolveInlineTokens( $tree );

	expect( $resolved[0]['attrs']['level'] )->toBe( 2 );
	expect( $resolved[0]['attrs']['content'] )->toBe( 'CTO is CTO' );
} );
