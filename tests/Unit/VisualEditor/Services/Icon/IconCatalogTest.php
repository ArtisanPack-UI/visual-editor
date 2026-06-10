<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\IconCatalog;

function makeManifest(): array
{
	return [
		'version' => '1.0.0',
		'sets'    => [
			[ 'prefix' => 'fas', 'label' => 'Solid', 'source' => 'solid' ],
			[ 'prefix' => 'fab', 'label' => 'Brands', 'source' => 'brands' ],
		],
		'icons'   => [
			[ 'name' => 'home', 'set' => 'fas', 'label' => 'Home', 'terms' => [ 'house', 'main' ] ],
			[ 'name' => 'user', 'set' => 'fas', 'label' => 'User', 'terms' => [ 'person', 'profile' ] ],
			[ 'name' => 'github', 'set' => 'fab', 'label' => 'GitHub', 'terms' => [ 'octocat', 'git' ] ],
			[ 'name' => 'gitlab', 'set' => 'fab', 'label' => 'GitLab', 'terms' => [ 'git' ] ],
		],
	];
}

function makeCatalog(): IconCatalog
{
	return new IconCatalog( static fn (): array => makeManifest() );
}

it( 'lists registered sets in declared order', function () {
	$sets = makeCatalog()->sets();

	expect( $sets )->toHaveCount( 2 );
	expect( $sets[0] )->toBe( [ 'prefix' => 'fas', 'label' => 'Solid' ] );
	expect( $sets[1] )->toBe( [ 'prefix' => 'fab', 'label' => 'Brands' ] );
} );

it( 'returns all icons when query is empty', function () {
	$result = makeCatalog()->search( '' );

	expect( $result['total'] )->toBe( 4 );
	expect( $result['data'] )->toHaveCount( 4 );
} );

it( 'matches against the name field', function () {
	$result = makeCatalog()->search( 'home' );

	expect( $result['total'] )->toBe( 1 );
	expect( $result['data'][0]['name'] )->toBe( 'home' );
} );

it( 'matches against the term aliases', function () {
	$result = makeCatalog()->search( 'octocat' );

	expect( $result['total'] )->toBe( 1 );
	expect( $result['data'][0]['name'] )->toBe( 'github' );
} );

it( 'filters by set when a prefix is provided', function () {
	$result = makeCatalog()->search( 'git', 'fab' );

	expect( $result['total'] )->toBe( 2 );
	$names = array_column( $result['data'], 'name' );
	expect( $names )->toContain( 'github' )->toContain( 'gitlab' );
} );

it( 'paginates the result set', function () {
	$page1 = makeCatalog()->search( '', null, 1, 2 );
	$page2 = makeCatalog()->search( '', null, 2, 2 );

	expect( $page1['total'] )->toBe( 4 );
	expect( $page1['data'] )->toHaveCount( 2 );
	expect( $page2['data'] )->toHaveCount( 2 );
	expect( $page1['data'][0]['name'] )->not->toBe( $page2['data'][0]['name'] );
} );

it( 'clamps per_page to the documented maximum', function () {
	$result = makeCatalog()->search( '', null, 1, IconCatalog::MAX_PER_PAGE + 50 );

	expect( $result['per_page'] )->toBe( IconCatalog::MAX_PER_PAGE );
} );

it( 'returns empty data when the manifest source is missing', function () {
	$catalog = new IconCatalog( '/nonexistent/index.json' );

	expect( $catalog->sets() )->toBe( [] );
	expect( $catalog->search( 'anything' )['total'] )->toBe( 0 );
} );
