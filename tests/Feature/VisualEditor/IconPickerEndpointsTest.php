<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\IconCatalog;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use Tests\TestUser;

function actingAsIconPickerUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Icon Picker Tester',
		'email'    => 'icon+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function bindIconCatalogFixture(): void
{
	app()->instance(
		IconCatalog::class,
		new IconCatalog( static fn (): array => [
			'sets'  => [
				[ 'prefix' => 'fas', 'label' => 'Solid', 'source' => 'solid' ],
				[ 'prefix' => 'fab', 'label' => 'Brands', 'source' => 'brands' ],
			],
			'icons' => [
				[ 'name' => 'home', 'set' => 'fas', 'label' => 'Home', 'terms' => [ 'house' ] ],
				[ 'name' => 'user', 'set' => 'fas', 'label' => 'User', 'terms' => [ 'profile' ] ],
				[ 'name' => 'github', 'set' => 'fab', 'label' => 'GitHub', 'terms' => [ 'octocat' ] ],
			],
		] ),
	);
}

it( 'returns the registered icon sets', function () {
	actingAsIconPickerUser();
	bindIconCatalogFixture();

	$this->getJson( '/visual-editor/api/icons/sets' )
		->assertOk()
		->assertJsonPath( 'data.0.prefix', 'fas' )
		->assertJsonPath( 'data.0.label', 'Solid' )
		->assertJsonPath( 'data.1.prefix', 'fab' );
} );

it( 'returns paginated search results matching the query', function () {
	actingAsIconPickerUser();
	bindIconCatalogFixture();

	$this->getJson( '/visual-editor/api/icons/search?q=home' )
		->assertOk()
		->assertJsonPath( 'total', 1 )
		->assertJsonPath( 'data.0.name', 'home' )
		->assertJsonPath( 'data.0.set', 'fas' );
} );

it( 'returns every icon when no query is supplied', function () {
	actingAsIconPickerUser();
	bindIconCatalogFixture();

	$this->getJson( '/visual-editor/api/icons/search' )
		->assertOk()
		->assertJsonPath( 'total', 3 );
} );

it( 'restricts search results to the requested set', function () {
	actingAsIconPickerUser();
	bindIconCatalogFixture();

	$this->getJson( '/visual-editor/api/icons/search?set=fab' )
		->assertOk()
		->assertJsonPath( 'total', 1 )
		->assertJsonPath( 'data.0.set', 'fab' )
		->assertJsonPath( 'data.0.name', 'github' );
} );

it( 'matches against the term aliases shipped with each icon', function () {
	actingAsIconPickerUser();
	bindIconCatalogFixture();

	$this->getJson( '/visual-editor/api/icons/search?q=octocat' )
		->assertOk()
		->assertJsonPath( 'total', 1 )
		->assertJsonPath( 'data.0.name', 'github' );
} );

it( 'decorates search results with inline svg markup', function () {
	actingAsIconPickerUser();
	bindIconCatalogFixture();

	$base = sys_get_temp_dir() . '/icon-picker-search-' . bin2hex( random_bytes( 4 ) );
	mkdir( $base . '/fas', 0o755, true );
	file_put_contents( $base . '/fas/home.svg', '<svg id="home"/>' );

	app()->instance(
		IconSvgResolver::class,
		new IconSvgResolver( [ 'fas' => $base . '/fas' ] ),
	);

	try {
		$this->getJson( '/visual-editor/api/icons/search?q=home' )
			->assertOk()
			->assertJsonPath( 'data.0.svg', '<svg id="home"/>' );
	} finally {
		unlink( $base . '/fas/home.svg' );
		rmdir( $base . '/fas' );
		rmdir( $base );
	}
} );

it( 'returns the resolved svg for a known (set, name) via the svg endpoint', function () {
	actingAsIconPickerUser();

	$base = sys_get_temp_dir() . '/icon-picker-svg-' . bin2hex( random_bytes( 4 ) );
	mkdir( $base . '/fab', 0o755, true );
	file_put_contents( $base . '/fab/github.svg', '<svg id="github"/>' );

	app()->instance(
		IconSvgResolver::class,
		new IconSvgResolver( [ 'fab' => $base . '/fab' ] ),
	);

	try {
		$this->getJson( '/visual-editor/api/icons/svg?set=fab&name=github' )
			->assertOk()
			->assertJsonPath( 'svg', '<svg id="github"/>' );
	} finally {
		unlink( $base . '/fab/github.svg' );
		rmdir( $base . '/fab' );
		rmdir( $base );
	}
} );

it( 'returns 404 from the svg endpoint when the icon is unknown', function () {
	actingAsIconPickerUser();

	app()->instance( IconSvgResolver::class, new IconSvgResolver( [] ) );

	$this->getJson( '/visual-editor/api/icons/svg?set=fab&name=nope' )
		->assertNotFound()
		->assertJsonPath( 'svg', null );
} );

it( 'returns 400 from the svg endpoint when set or name is missing', function () {
	actingAsIconPickerUser();

	$this->getJson( '/visual-editor/api/icons/svg' )
		->assertStatus( 400 )
		->assertJsonPath( 'svg', null );
} );

// Phase 5 (#556) — custom SVG paste/upload sanitize endpoint.
it( 'strips a malicious svg and reports warnings via the sanitize endpoint', function () {
	actingAsIconPickerUser();

	$hostile = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
		. '<script>steal()</script>'
		. '<path d="M0 0h10v10H0z" onclick="alert(2)"/>'
		. '</svg>';

	$response = $this->postJson(
		'/visual-editor/api/icons/svg/sanitize',
		[ 'svg' => $hostile ],
	)->assertOk();

	$sanitized = $response->json( 'svg' );
	$warnings  = $response->json( 'warnings' );

	expect( $sanitized )->toBeString()
		->not->toContain( '<script' )
		->not->toContain( 'onload' )
		->not->toContain( 'onclick' )
		->not->toContain( 'alert' )
		->toContain( '<path' );

	expect( $warnings )->toBeArray()->not->toBeEmpty();
	expect( implode( "\n", $warnings ) )->toContain( '<script>' );
} );

it( 'returns 422 from the sanitize endpoint when svg is not a string', function () {
	actingAsIconPickerUser();

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => [ 'not', 'a', 'string' ] ] )
		->assertStatus( 422 )
		->assertJsonPath( 'svg', '' );
} );

it( 'returns 413 from the sanitize endpoint when the payload exceeds the size limit', function () {
	actingAsIconPickerUser();

	// 256 KB cap + 1 byte. The endpoint never even calls the parser.
	$oversize = '<svg>' . str_repeat( 'x', 262_144 ) . '</svg>';

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => $oversize ] )
		->assertStatus( 413 )
		->assertJsonPath( 'svg', '' );
} );

it( 'returns 413 when the raw request body exceeds the cap even if `svg` decodes smaller', function () {
	actingAsIconPickerUser();

	// JSON escaping of a string of double-quotes blows the wire size
	// well past 256 KB even though the decoded `svg` value is shorter.
	$bigField = str_repeat( '\"x\"', 80_000 );

	$this->call(
		'POST',
		'/visual-editor/api/icons/svg/sanitize',
		[],
		[],
		[],
		[
			'HTTP_ACCEPT'       => 'application/json',
			'CONTENT_TYPE'      => 'application/json',
		],
		'{"svg":"' . $bigField . '"}',
	)->assertStatus( 413 );
} );
