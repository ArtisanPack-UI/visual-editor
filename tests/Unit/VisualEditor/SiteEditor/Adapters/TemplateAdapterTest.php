<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\TemplateAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate;

function makeResolvedTemplate( array $overrides = [] ): ResolvedTemplate
{
	$defaults = [
		'slug'           => 'single',
		'theme'          => 'digital-shopfront',
		'title'          => 'Single Post',
		'description'    => 'Single post template',
		'status'         => 'publish',
		'source'         => 'theme',
		'rawContent'     => '<!-- wp:post-title /-->',
		'blocks'         => [],
		'hasThemeFile'   => true,
		'isCustom'       => false,
		'wpId'           => null,
		'authorId'       => null,
		'modifiedAt'     => null,
	];

	$args = array_merge( $defaults, $overrides );

	return new ResolvedTemplate(
		slug         : $args['slug'],
		theme        : $args['theme'],
		title        : $args['title'],
		description  : $args['description'],
		status       : $args['status'],
		source       : $args['source'],
		rawContent   : $args['rawContent'],
		blocks       : $args['blocks'],
		hasThemeFile : $args['hasThemeFile'],
		isCustom     : $args['isCustom'],
		wpId         : $args['wpId'],
		authorId     : $args['authorId'],
		modifiedAt   : $args['modifiedAt'],
	);
}

describe( 'single-record envelope', function (): void {
	it( 'mirrors the WP `wp_template` REST shape for a theme-file template', function (): void {
		$template = makeResolvedTemplate();
		$adapter  = new TemplateAdapter();

		$out = $adapter->toArray( $template );

		expect( $out )
			->toMatchArray( [
				'id'             => 'single',
				'slug'           => 'single',
				'type'           => 'wp_template',
				'source'         => 'theme',
				'origin'         => 'theme',
				'description'    => 'Single post template',
				'status'         => 'publish',
				'theme'          => 'digital-shopfront',
				'has_theme_file' => true,
				'is_custom'      => false,
				'author'         => null,
				'modified'       => null,
			] )
			->and( $out['title'] )->toBe( [
				'rendered' => 'Single Post',
				'raw'      => 'Single Post',
			] )
			->and( $out['content']['raw'] )->toBe( '<!-- wp:post-title /-->' )
			// Theme-file sources reach the adapter with `blocks: []` —
			// cms-framework's filter contributor doesn't parse the file.
			// The adapter parses `raw` server-side so the shim doesn't
			// fall through to the nav-only `parseNavigationContent`
			// fallback that drops every non-nav block (#674).
			->and( $out['content']['blocks'] )->toBe( [
				[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
			] );
	} );

	it( 'parses `raw` into editor-shape blocks when `blocks` is empty (#674)', function (): void {
		$template = makeResolvedTemplate( [
			'rawContent' => '<!-- wp:group {"tagName":"header"} --><header class="wp-block-group">'
				. '<!-- wp:site-title /-->'
				. '<!-- wp:navigation {"ref":42} /-->'
				. '</header><!-- /wp:group -->',
			'blocks'     => [],
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['content']['blocks'] )->toHaveCount( 1 )
			->and( $out['content']['blocks'][0]['name'] )->toBe( 'core/group' )
			->and( $out['content']['blocks'][0]['attributes'] )->toBe( [ 'tagName' => 'header' ] )
			->and( $out['content']['blocks'][0]['innerBlocks'] )->toHaveCount( 2 )
			->and( $out['content']['blocks'][0]['innerBlocks'][0]['name'] )->toBe( 'core/site-title' )
			->and( $out['content']['blocks'][0]['innerBlocks'][1]['name'] )->toBe( 'core/navigation' )
			->and( $out['content']['blocks'][0]['innerBlocks'][1]['attributes'] )->toBe( [ 'ref' => 42 ] );
	} );

	it( 'translates `core/template-part` to the artisanpack fork in both `raw` and `blocks` (#674)', function (): void {
		// The I7 cutover (#415) removed `registerCoreBlocks()`, so
		// `core/template-part` reaches the editor unregistered and gets
		// silently dropped — the visible half of #674. The adapter
		// rewrites both hydration paths (`content.raw` that
		// `hydrateBlocks` calls `parse()` on, and the pre-parsed
		// `content.blocks` tree the shim's `useEntityBlockEditor`
		// consumes) so the registered `artisanpack/template-part` fork
		// mounts instead.
		$template = makeResolvedTemplate( [
			'rawContent' => '<!-- wp:template-part {"slug":"header","theme":"dev-sample","tagName":"header"} /-->' . "\n"
				. '<!-- wp:artisanpack/group --><div class="wp-block-group"></div><!-- /wp:artisanpack/group -->' . "\n"
				. '<!-- wp:template-part {"slug":"footer","theme":"dev-sample","tagName":"footer"} /-->',
			'blocks'     => [],
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['content']['raw'] )->toContain( 'wp:artisanpack/template-part' )
			->and( $out['content']['raw'] )->not->toContain( 'wp:template-part ' )
			->and( $out['content']['blocks'] )->toHaveCount( 3 )
			->and( $out['content']['blocks'][0]['name'] )->toBe( 'artisanpack/template-part' )
			->and( $out['content']['blocks'][0]['attributes'] )->toBe( [
				'slug'    => 'header',
				'theme'   => 'dev-sample',
				'tagName' => 'header',
			] )
			->and( $out['content']['blocks'][2]['name'] )->toBe( 'artisanpack/template-part' )
			->and( $out['content']['blocks'][2]['attributes']['slug'] )->toBe( 'footer' );
	} );

	it( 'translates `core/template-part` in pre-parsed blocks even when raw is empty', function (): void {
		// Guards the innerBlocks recursion path — a theme author who
		// (non-standard but syntactically valid) nested template-parts
		// under another block would leave a stray unregistered node
		// behind if we only walked the top level.
		$template = makeResolvedTemplate( [
			'rawContent' => '',
			'blocks'     => [
				[
					'name'        => 'artisanpack/group',
					'attributes'  => [],
					'innerBlocks' => [
						[
							'name'        => 'core/template-part',
							'attributes'  => [ 'slug' => 'nested-header', 'theme' => 'dev-sample' ],
							'innerBlocks' => [],
						],
					],
				],
			],
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['content']['blocks'][0]['innerBlocks'][0]['name'] )
			->toBe( 'artisanpack/template-part' );
	} );

	it( 'does not rewrite delimiters whose name only starts with `template-part`', function (): void {
		// Defensive check for the raw-rewrite regex — a hypothetical
		// `wp:template-parts-listing` block (or any name that shares
		// the `template-part` prefix but has more chars after) must
		// not be munged. The delimiter regex anchors on `[\s\/}]` at
		// the end of the name segment.
		$template = makeResolvedTemplate( [
			'rawContent' => '<!-- wp:template-parts-listing {"foo":1} /-->',
			'blocks'     => [
				[ 'name' => 'core/template-parts-listing', 'attributes' => [ 'foo' => 1 ], 'innerBlocks' => [] ],
			],
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['content']['raw'] )->toBe( '<!-- wp:template-parts-listing {"foo":1} /-->' )
			->and( $out['content']['blocks'][0]['name'] )->toBe( 'core/template-parts-listing' );
	} );

	it( 'prefers the pre-parsed `blocks` array over re-parsing `raw` when both are populated', function (): void {
		$template = makeResolvedTemplate( [
			'rawContent' => '<!-- wp:paragraph -->Raw<!-- /wp:paragraph -->',
			'blocks'     => [
				[ 'name' => 'core/heading', 'attributes' => [ 'content' => 'FromBlocks' ], 'innerBlocks' => [] ],
			],
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['content']['blocks'] )->toBe( [
			[ 'name' => 'core/heading', 'attributes' => [ 'content' => 'FromBlocks' ], 'innerBlocks' => [] ],
		] );
	} );

	it( 'flips `source` to `db` and surfaces wp_id when a DB override exists', function (): void {
		$template = makeResolvedTemplate( [
			'source'       => 'db',
			'wpId'         => 42,
			'rawContent'   => '<!-- wp:paragraph -->Hello<!-- /wp:paragraph -->',
			'blocks'       => [ [ 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ] ],
			'hasThemeFile' => true,
			'authorId'     => 7,
			'modifiedAt'   => '2026-04-30T12:00:00+00:00',
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['id'] )->toBe( 42 )
			->and( $out['source'] )->toBe( 'db' )
			->and( $out['has_theme_file'] )->toBeTrue()
			->and( $out['author'] )->toBe( 7 )
			->and( $out['modified'] )->toBe( '2026-04-30T12:00:00+00:00' )
			->and( $out['content']['blocks'] )->toHaveCount( 1 );
	} );

	it( 'falls back from `wpId = 0` (file-only sentinel) to slug for `id` (#438)', function (): void {
		$template = makeResolvedTemplate( [
			'wpId' => 0,
			'slug' => 'page',
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['id'] )->toBe( 'page' );
	} );

	it( 'reports `origin` as null for custom templates with no theme backing', function (): void {
		$template = makeResolvedTemplate( [
			'isCustom'     => true,
			'hasThemeFile' => false,
			'source'       => 'db',
			'wpId'         => 99,
		] );

		$out = ( new TemplateAdapter() )->toArray( $template );

		expect( $out['origin'] )->toBeNull()
			->and( $out['is_custom'] )->toBeTrue()
			->and( $out['has_theme_file'] )->toBeFalse();
	} );
} );

describe( 'collection envelope', function (): void {
	it( 'returns a flat list of single-record envelopes in iteration order', function (): void {
		$templates = [
			makeResolvedTemplate( [ 'slug' => 'single' ] ),
			makeResolvedTemplate( [ 'slug' => 'page', 'title' => 'Page' ] ),
			makeResolvedTemplate( [ 'slug' => 'index', 'title' => 'Index' ] ),
		];

		$out = ( new TemplateAdapter() )->collection( $templates );

		expect( $out )->toHaveCount( 3 )
			->and( array_column( $out, 'slug' ) )->toBe( [ 'single', 'page', 'index' ] )
			->and( $out[1]['title']['raw'] )->toBe( 'Page' );
	} );

	it( 'returns an empty array for an empty iterable', function (): void {
		expect( ( new TemplateAdapter() )->collection( [] ) )->toBe( [] );
	} );
} );
