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
