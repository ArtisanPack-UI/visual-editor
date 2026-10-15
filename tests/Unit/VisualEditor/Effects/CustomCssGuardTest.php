<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\BlockCssAccess;
use ArtisanPackUI\VisualEditor\Effects\CustomCssGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

function cssBlock( ?string $css, array $innerBlocks = [] ): array
{
	$effects = [ 'opacity' => 50 ];

	if ( null !== $css ) {
		$effects['customCss'] = $css;
	}

	return [ 'name' => 'core/group', 'attributes' => [ 'artisanpackEffects' => $effects ], 'innerBlocks' => $innerBlocks ];
}

it( 'keeps Custom CSS for users with the ability', function (): void {
	Gate::define( BlockCssAccess::ABILITY, fn ( ?Authenticatable $user = null ): bool => true );

	$blocks = [ cssBlock( 'color: red;' ) ];

	expect( app( CustomCssGuard::class )->filter( $blocks ) )->toBe( $blocks );
} );

it( 'strips new Custom CSS for users without the ability, at any depth', function (): void {
	Gate::define( BlockCssAccess::ABILITY, fn ( ?Authenticatable $user = null ): bool => false );

	$filtered = app( CustomCssGuard::class )->filter( [ cssBlock( 'color: red;', [ cssBlock( 'color: blue;' ) ] ) ] );

	expect( $filtered[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] )
		->and( $filtered[0]['innerBlocks'][0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'keeps Custom CSS already stored on the record for users without the ability', function (): void {
	Gate::define( BlockCssAccess::ABILITY, fn ( ?Authenticatable $user = null ): bool => false );

	$stored   = [ cssBlock( 'color: red;' ) ];
	$incoming = [ cssBlock( null, [ cssBlock( "color:   red;\n" ) ] ), cssBlock( 'color: green;' ) ];
	$filtered = app( CustomCssGuard::class )->filter( $incoming, $stored );

	expect( $filtered[0]['innerBlocks'][0]['attributes']['artisanpackEffects']['customCss'] )->toBe( "color:   red;\n" )
		->and( $filtered[1]['attributes']['artisanpackEffects'] )->not->toHaveKey( 'customCss' );
} );

it( 'strips Custom CSS from a parse-shape attrs bag and reads stored parse-shape trees', function (): void {
	Gate::define( BlockCssAccess::ABILITY, fn ( ?Authenticatable $user = null ): bool => false );

	$parse = static fn ( string $css ): array => [ 'blockName' => 'core/group', 'attrs' => [ 'artisanpackEffects' => [ 'customCss' => $css ] ], 'innerBlocks' => [] ];

	$filtered = app( CustomCssGuard::class )->filter(
		[ $parse( 'display: none;' ), cssBlock( 'color: red;' ) ],
		[ $parse( 'color: red;' ) ],
	);

	expect( $filtered[0]['attrs']['artisanpackEffects'] )->toBe( [] )
		->and( $filtered[1]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' );
} );

it( 'denies the default gate without the capability', function (): void {
	expect( BlockCssAccess::allows( null ) )->toBeFalse()
		->and( Gate::allows( BlockCssAccess::ABILITY ) )->toBeFalse();
} );
