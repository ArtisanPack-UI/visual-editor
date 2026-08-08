<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\TemplatePartAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;

function makeResolvedTemplatePart( array $overrides = [] ): ResolvedTemplatePart
{
	$defaults = [
		'slug'         => 'header',
		'theme'        => 'digital-shopfront',
		'title'        => 'Header',
		'description'  => 'Site header',
		'status'       => 'publish',
		'source'       => 'theme',
		'rawContent'   => '<!-- wp:site-title /-->',
		'blocks'       => [],
		'hasThemeFile' => true,
		'isCustom'     => false,
		'wpId'         => null,
		'authorId'     => null,
		'modifiedAt'   => null,
		'area'         => 'header',
	];

	$args = array_merge( $defaults, $overrides );

	return new ResolvedTemplatePart(
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
		area         : $args['area'],
	);
}

it( 'switches the type discriminator to wp_template_part and surfaces area', function (): void {
	$part = makeResolvedTemplatePart( [ 'area' => 'footer' ] );

	$out = ( new TemplatePartAdapter() )->toArray( $part );

	expect( $out['type'] )->toBe( 'wp_template_part' )
		->and( $out['area'] )->toBe( 'footer' )
		->and( $out['slug'] )->toBe( 'header' );
} );

it( 'inherits the parent envelope shape (title, content, source, has_theme_file)', function (): void {
	$part = makeResolvedTemplatePart( [
		'source'     => 'db',
		'wpId'       => 11,
		'rawContent' => '<!-- wp:paragraph /-->',
		'blocks'     => [ [ 'name' => 'core/paragraph' ] ],
	] );

	$out = ( new TemplatePartAdapter() )->toArray( $part );

	expect( $out['id'] )->toBe( 11 )
		->and( $out['source'] )->toBe( 'db' )
		->and( $out['title']['raw'] )->toBe( 'Header' )
		->and( $out['content']['raw'] )->toBe( '<!-- wp:paragraph /-->' )
		->and( $out['content']['blocks'] )->toHaveCount( 1 )
		->and( $out['has_theme_file'] )->toBeTrue();
} );

it( 'rewrites every core block name in a part to its artisanpack fork', function (): void {
	// The composed view's template-part chrome rendered blank because
	// this endpoint — the one the `artisanpack/template-part` fork block
	// resolves through since #675 — served theme-authored `core/*` names
	// straight through. The I7 cutover (#415) left those unregistered, so
	// the part resolved but mounted nothing. #674 fixed the same hole for
	// `core/template-part` only; this covers the whole namespace.
	$part = makeResolvedTemplatePart( [
		'rawContent' => '',
		'blocks'     => [
			[
				'name'        => 'core/group',
				'attributes'  => [],
				'innerBlocks' => [
					[ 'name' => 'core/site-title', 'attributes' => [], 'innerBlocks' => [] ],
					[ 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
				],
			],
		],
	] );

	$out = ( new TemplatePartAdapter() )->toArray( $part );

	expect( $out['content']['blocks'][0]['name'] )->toBe( 'artisanpack/group' )
		->and( $out['content']['blocks'][0]['innerBlocks'][0]['name'] )->toBe( 'artisanpack/site-title' )
		->and( $out['content']['blocks'][0]['innerBlocks'][1]['name'] )->toBe( 'artisanpack/paragraph' );
} );

it( 'parses a raw-only theme part and forks the names it finds', function (): void {
	// Theme parts on disk arrive with `rawContent` populated and `blocks`
	// empty. Both legs — the parse and the rewrite — have to run or the
	// part is empty chrome again.
	$part = makeResolvedTemplatePart( [
		'rawContent' => '<!-- wp:site-title /-->',
		'blocks'     => [],
	] );

	$out = ( new TemplatePartAdapter() )->toArray( $part );

	expect( $out['content']['blocks'] )->toHaveCount( 1 )
		->and( $out['content']['blocks'][0]['name'] )->toBe( 'artisanpack/site-title' );
} )->skip( fn () => ! templatePartParserAvailable(), 'requires cms-framework 2.5+ (PHP 8.3+)' );

it( 'rejects a plain ResolvedTemplate to keep the area field invariant', function (): void {
	$bareTemplate = new ResolvedTemplate(
		slug         : 'index',
		theme        : 'digital-shopfront',
		title        : 'Index',
		description  : '',
		status       : 'publish',
		source       : 'theme',
		rawContent   : '',
		blocks       : [],
		hasThemeFile : true,
		isCustom     : false,
		wpId         : null,
		authorId     : null,
		modifiedAt   : null,
	);

	expect( fn () => ( new TemplatePartAdapter() )->toArray( $bareTemplate ) )
		->toThrow( InvalidArgumentException::class );
} );
