<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\GlobalStylesAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedGlobalStyles;

function makeResolvedGlobalStyles( array $overrides = [] ): ResolvedGlobalStyles
{
	$defaults = [
		'theme'      => 'digital-shopfront',
		'settings'   => [ 'color' => [ 'palette' => [] ] ],
		'styles'     => [ 'typography' => [ 'fontSize' => '16px' ] ],
		'variations' => [],
		'wpId'       => null,
	];

	$args = array_merge( $defaults, $overrides );

	return new ResolvedGlobalStyles(
		theme      : $args['theme'],
		settings   : $args['settings'],
		styles     : $args['styles'],
		variations : $args['variations'],
		wpId       : $args['wpId'],
	);
}

it( 'uses the singleton sentinel id when no DB row backs the active theme', function (): void {
	$out = ( new GlobalStylesAdapter() )->toArray( makeResolvedGlobalStyles() );

	expect( $out['id'] )->toBe( '__base__' )
		->and( $out['theme'] )->toBe( 'digital-shopfront' )
		->and( $out['settings'] )->toHaveKey( 'color' )
		->and( $out['styles']['typography']['fontSize'] )->toBe( '16px' )
		->and( $out['variations'] )->toBe( [] );
} );

it( 'surfaces the wp_id when the user has customized global styles', function (): void {
	$out = ( new GlobalStylesAdapter() )->toArray( makeResolvedGlobalStyles( [ 'wpId' => 17 ] ) );

	expect( $out['id'] )->toBe( 17 );
} );

it( 'preserves theme-declared variations as-is', function (): void {
	$variations = [
		[ 'title' => 'Dark', 'styles' => [ 'color' => [ 'background' => '#000' ] ] ],
		[ 'title' => 'High Contrast', 'styles' => [] ],
	];

	$out = ( new GlobalStylesAdapter() )->toArray( makeResolvedGlobalStyles( [ 'variations' => $variations ] ) );

	expect( $out['variations'] )->toBe( $variations );
} );
