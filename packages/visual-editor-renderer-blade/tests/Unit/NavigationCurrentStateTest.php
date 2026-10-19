<?php

/**
 * Unit tests for {@see NavigationCurrentState} (#975).
 *
 * The URL cases live in `packages/renderer-markup-parity/navigation-current-cases.json`,
 * which the React / Vue `matchNavigationUrl()` suite reads too, so the
 * twins agree.
 *
 * @since 1.15.2
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\NavigationCurrentState;

/**
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function navigationCurrentCases(): array
{
	$json = json_decode(
		(string) file_get_contents( dirname( __DIR__, 3 ) . '/renderer-markup-parity/navigation-current-cases.json' ),
		true,
		512,
		JSON_THROW_ON_ERROR,
	);

	$cases = [];

	foreach ( $json['cases'] as $index => [ $link, $current, $expected ] ) {
		$cases[ sprintf( '#%d %s on %s', $index, json_encode( $link ), json_encode( $current ) ) ] = [ $link, $current, $expected ];
	}

	return $cases;
}

it( 'matches a link URL against the current URL', function ( string $link, string $current, string $expected ): void {
	expect( NavigationCurrentState::match( $link, $current ) )->toBe( $expected );
} )->with( navigationCurrentCases() );

it( 'finds a current or ancestor link anywhere below a submenu', function (): void {
	$tree = [
		[ 'name' => 'core/navigation-link', 'attributes' => [ 'url' => '/about' ], 'innerBlocks' => [] ],
		[
			'name'        => 'core/navigation-submenu',
			'attributes'  => [ 'url' => '/services' ],
			'innerBlocks' => [
				[ 'name' => 'core/navigation-link', 'attributes' => [ 'url' => '/services/design' ], 'innerBlocks' => [] ],
			],
		],
	];

	expect( NavigationCurrentState::hasMatchingDescendant( $tree, 'https://example.test/services/design' ) )->toBeTrue()
		->and( NavigationCurrentState::hasMatchingDescendant( $tree, 'https://example.test/about/team' ) )->toBeTrue()
		->and( NavigationCurrentState::hasMatchingDescendant( $tree, 'https://example.test/contact' ) )->toBeFalse()
		->and( NavigationCurrentState::hasMatchingDescendant( [ [ 'name' => 'core/paragraph', 'attributes' => [ 'url' => '/contact' ] ] ], 'https://example.test/contact' ) )->toBeFalse();
} );
