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
			->and( $out['content'] )->toBe( [
				'raw'    => '<!-- wp:post-title /-->',
				'blocks' => [],
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
