<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use Tests\TestUser;

function validGlobalStylesPayload( array $overrides = [] ): array
{
	return array_replace_recursive( [
		'version'  => 3,
		'settings' => [
			'color'      => [
				'palette' => [
					[ 'slug' => 'primary', 'name' => 'Primary', 'color' => '#3b82f6' ],
					[ 'slug' => 'secondary', 'name' => 'Secondary', 'color' => '#6366f1' ],
				],
			],
			'typography' => [
				'fontSizes' => [
					[ 'slug' => 'small', 'name' => 'Small', 'size' => '0.875rem' ],
					[ 'slug' => 'medium', 'name' => 'Medium', 'size' => '1rem' ],
				],
			],
		],
		'styles'   => [
			'color' => [ 'background' => '#ffffff' ],
		],
	], $overrides );
}

function actingAsGlobalStylesUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Global Styles Tester',
		'email'    => 'global-styles+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 when unauthenticated on lookup', function () {
	$this->getJson( '/visual-editor/api/global-styles/lookup' )->assertUnauthorized();
} );

it( 'lookup resolves (or creates) the singleton and returns its id', function () {
	actingAsGlobalStylesUser();

	expect( VisualEditorGlobalStyles::count() )->toBe( 0 );

	$response = $this->getJson( '/visual-editor/api/global-styles/lookup' )
		->assertOk();

	$id = $response->json( 'id' );

	expect( $id )->toBeInt()
		->and( VisualEditorGlobalStyles::count() )->toBe( 1 )
		->and( VisualEditorGlobalStyles::find( $id )->theme )->toBe( 'artisanpack-base' );
} );

it( 'lookup is idempotent — repeat calls return the same id', function () {
	actingAsGlobalStylesUser();

	$first  = $this->getJson( '/visual-editor/api/global-styles/lookup' )->assertOk()->json( 'id' );
	$second = $this->getJson( '/visual-editor/api/global-styles/lookup' )->assertOk()->json( 'id' );

	expect( $first )->toBe( $second )
		->and( VisualEditorGlobalStyles::count() )->toBe( 1 );
} );

it( 'lookup respects the configured active theme', function () {
	actingAsGlobalStylesUser();

	config()->set( 'artisanpack.visual-editor.global_styles.theme', 'custom-theme' );

	$id = $this->getJson( '/visual-editor/api/global-styles/lookup' )->assertOk()->json( 'id' );

	expect( VisualEditorGlobalStyles::find( $id )->theme )->toBe( 'custom-theme' );
} );

it( 'returns 401 when unauthenticated on show', function () {
	$record = VisualEditorGlobalStyles::resolveSingleton( 'artisanpack-base', [
		'version'  => 3,
		'settings' => [],
		'styles'   => [],
	] );

	$this->getJson( "/visual-editor/api/global-styles/{$record->id}" )->assertUnauthorized();
} );

it( 'returns the full record shape on show', function () {
	actingAsGlobalStylesUser();

	$record = VisualEditorGlobalStyles::resolveSingleton( 'artisanpack-base', [
		'version'  => 3,
		'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'x', 'name' => 'X', 'color' => '#000' ] ] ] ],
		'styles'   => [ 'color' => [ 'background' => '#fff' ] ],
	] );

	$this->getJson( "/visual-editor/api/global-styles/{$record->id}" )
		->assertOk()
		->assertJsonPath( 'id', $record->id )
		->assertJsonPath( 'version', 3 )
		->assertJsonPath( 'settings.color.palette.0.slug', 'x' )
		->assertJsonPath( 'styles.color.background', '#fff' );
} );

it( 'returns 404 when the id does not exist on show', function () {
	actingAsGlobalStylesUser();

	$this->getJson( '/visual-editor/api/global-styles/999' )->assertNotFound();
} );

it( 'updates the record with a valid payload', function () {
	actingAsGlobalStylesUser();

	$id = $this->getJson( '/visual-editor/api/global-styles/lookup' )->json( 'id' );

	$payload = validGlobalStylesPayload( [
		'styles' => [ 'color' => [ 'background' => '#111111' ] ],
	] );

	$this->putJson( "/visual-editor/api/global-styles/{$id}", $payload )
		->assertOk()
		->assertJsonPath( 'id', $id )
		->assertJsonPath( 'styles.color.background', '#111111' )
		->assertJsonPath( 'settings.color.palette.0.slug', 'primary' );

	expect( VisualEditorGlobalStyles::find( $id )->styles['color']['background'] )->toBe( '#111111' );
} );

it( 'rejects an update whose version does not match the pinned schema', function () {
	actingAsGlobalStylesUser();

	$id = $this->getJson( '/visual-editor/api/global-styles/lookup' )->json( 'id' );

	$this->putJson( "/visual-editor/api/global-styles/{$id}", validGlobalStylesPayload( [ 'version' => 2 ] ) )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'version' );
} );

it( 'rejects an update with a missing version', function () {
	actingAsGlobalStylesUser();

	$id      = $this->getJson( '/visual-editor/api/global-styles/lookup' )->json( 'id' );
	$payload = validGlobalStylesPayload();
	unset( $payload['version'] );

	$this->putJson( "/visual-editor/api/global-styles/{$id}", $payload )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'version' );
} );

it( 'rejects an update with missing settings or styles', function () {
	actingAsGlobalStylesUser();

	$id = $this->getJson( '/visual-editor/api/global-styles/lookup' )->json( 'id' );

	$this->putJson( "/visual-editor/api/global-styles/{$id}", [
		'version' => 3,
		'styles'  => [],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'settings' );

	$this->putJson( "/visual-editor/api/global-styles/{$id}", [
		'version'  => 3,
		'settings' => [],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'styles' );
} );

it( 'rejects an update with duplicate palette slugs', function () {
	actingAsGlobalStylesUser();

	$id = $this->getJson( '/visual-editor/api/global-styles/lookup' )->json( 'id' );

	$payload = validGlobalStylesPayload( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'primary', 'name' => 'Primary', 'color' => '#3b82f6' ],
					[ 'slug' => 'primary', 'name' => 'Primary Dup', 'color' => '#2563eb' ],
				],
			],
		],
	] );

	$this->putJson( "/visual-editor/api/global-styles/{$id}", $payload )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'settings.color.palette' );
} );

it( 'rejects an update with a malformed palette entry', function () {
	actingAsGlobalStylesUser();

	$id = $this->getJson( '/visual-editor/api/global-styles/lookup' )->json( 'id' );

	// Build the payload from scratch — array_replace_recursive would
	// merge the default palette entries' `color` key back onto the
	// override, hiding the missing-field case we're trying to test.
	$payload = [
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'primary', 'name' => 'Primary' ],
				],
			],
		],
		'styles'   => [],
	];

	$this->putJson( "/visual-editor/api/global-styles/{$id}", $payload )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'settings.color.palette.0.color' );
} );

it( 'returns 401 when unauthenticated on update', function () {
	$record = VisualEditorGlobalStyles::resolveSingleton( 'artisanpack-base', [
		'version'  => 3,
		'settings' => [],
		'styles'   => [],
	] );

	$this->putJson( "/visual-editor/api/global-styles/{$record->id}", validGlobalStylesPayload() )
		->assertUnauthorized();
} );

it( 'returns 401 when unauthenticated on base', function () {
	$this->getJson( '/visual-editor/api/global-styles/base' )->assertUnauthorized();
} );

it( 'returns a theme.json-shaped default payload on base', function () {
	actingAsGlobalStylesUser();

	$this->getJson( '/visual-editor/api/global-styles/base' )
		->assertOk()
		->assertJsonPath( 'version', 3 )
		->assertJsonStructure( [
			'version',
			'settings' => [
				'color'      => [ 'palette' ],
				'typography' => [ 'fontFamilies', 'fontSizes' ],
				'layout'     => [ 'contentSize', 'wideSize' ],
			],
			'styles'   => [ 'color', 'typography', 'elements', 'blocks' ],
		] );
} );

it( 'base respects the base_path config override', function () {
	actingAsGlobalStylesUser();

	$override = tempnam( sys_get_temp_dir(), 'base-' ) . '.php';
	file_put_contents( $override, '<?php return [ "version" => 3, "settings" => [ "custom" => true ], "styles" => [ "custom" => true ] ];' );

	try {
		config()->set( 'artisanpack.visual-editor.global_styles.base_path', $override );

		$this->getJson( '/visual-editor/api/global-styles/base' )
			->assertOk()
			->assertJsonPath( 'settings.custom', true )
			->assertJsonPath( 'styles.custom', true );
	} finally {
		@unlink( $override );
	}
} );

it( 'round-trips the B2 global-styles fixture through show and update', function () {
	actingAsGlobalStylesUser();

	$fixturePath = dirname( __DIR__, 2 ) . '/Fixtures/sample-content/global-styles/default.json';
	$fixture     = json_decode( (string) file_get_contents( $fixturePath ), true, flags: JSON_THROW_ON_ERROR );

	expect( $fixture )->toBeArray();

	$id = $this->getJson( '/visual-editor/api/global-styles/lookup' )->json( 'id' );

	$payload = [
		'version'  => $fixture['version'],
		'settings' => $fixture['settings'],
		'styles'   => $fixture['styles'],
	];

	// Use toEqual (value equality) rather than assertJsonPath, which
	// uses assertSame and requires identical array key ordering — the
	// shim treats settings/styles as opaque JSON blobs, so ordering is
	// incidental to the contract.
	$updateResponse = $this->putJson( "/visual-editor/api/global-styles/{$id}", $payload )
		->assertOk()
		->assertJsonPath( 'version', $fixture['version'] );

	expect( $updateResponse->json( 'settings' ) )->toEqual( $fixture['settings'] )
		->and( $updateResponse->json( 'styles' ) )->toEqual( $fixture['styles'] );

	$showResponse = $this->getJson( "/visual-editor/api/global-styles/{$id}" )->assertOk();

	expect( $showResponse->json( 'settings' ) )->toEqual( $fixture['settings'] )
		->and( $showResponse->json( 'styles' ) )->toEqual( $fixture['styles'] );
} );
