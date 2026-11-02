<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\SocialLinkServices;

it( 'draws author and share platforms with their service glyph', function ( string $method, string $slug, string $service, string $label ): void {
	$glyph = SocialLinkServices::SERVICES[ $service ];

	expect( SocialLinkServices::$method( $slug ) )->toBe( [
		'label'   => $label,
		'viewBox' => $glyph['viewBox'],
		'path'    => $glyph['path'],
	] );
} )->with( [
	'author facebook' => [ 'author', 'facebook', 'facebook', 'Facebook' ],
	'author twitter'  => [ 'author', 'twitter', 'x', 'X' ],
	'author email'    => [ 'author', 'email', 'mail', 'Email' ],
	'author website'  => [ 'author', 'website', 'chain', 'Website' ],
	'share twitter'   => [ 'share', 'twitter', 'x', 'X' ],
	'share linkedin'  => [ 'share', 'linkedin', 'linkedin', 'LinkedIn' ],
	'share email'     => [ 'share', 'email', 'mail', 'Email' ],
	'share copy link' => [ 'share', 'copyLink', 'chain', 'Copy link' ],
] );

it( 'keeps the slugs each block saves', function (): void {
	expect( array_keys( SocialLinkServices::AUTHOR_PLATFORMS ) )
		->toBe( [ 'facebook', 'twitter', 'mastodon', 'instagram', 'tumblr', 'email', 'website' ] )
		->and( array_keys( SocialLinkServices::SHARE_PLATFORMS ) )
		->toBe( [ 'facebook', 'twitter', 'linkedin', 'mastodon', 'reddit', 'pinterest', 'email', 'copyLink' ] );
} );

it( 'has no chip for a platform the block does not offer', function ( string $method, string $slug ): void {
	expect( SocialLinkServices::$method( $slug ) )->toBeNull();
} )->with( [
	'unknown author'   => [ 'author', 'unknown' ],
	'share-only slug'  => [ 'author', 'reddit' ],
	'unknown share'    => [ 'share', 'unknown' ],
	'author-only slug' => [ 'share', 'website' ],
] );

it( 'names only known services', function (): void {
	$platforms = [ ...array_values( SocialLinkServices::AUTHOR_PLATFORMS ), ...array_values( SocialLinkServices::SHARE_PLATFORMS ) ];

	foreach ( $platforms as $platform ) {
		expect( SocialLinkServices::SERVICES )->toHaveKey( $platform['service'] );
	}
} );
