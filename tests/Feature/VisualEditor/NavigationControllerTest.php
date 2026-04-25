<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use Tests\TestUser;

function createNavigation( array $overrides = [] ): VisualEditorNavigation
{
	return VisualEditorNavigation::create( array_merge( [
		'slug'       => 'primary-nav',
		'title'      => 'Primary',
		'content'    => [
			'raw'    => '<!-- wp:navigation-link {"label":"Home","url":"/"} /-->',
			'blocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
			],
		],
		'status'     => 'publish',
		'menu_order' => 0,
	], $overrides ) );
}

function actingAsNavigationUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Navigation Tester',
		'email'    => 'navigation+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 when unauthenticated on index', function () {
	$this->getJson( '/visual-editor/api/navigation' )->assertUnauthorized();
} );

it( 'returns an empty data/meta envelope when no navigations exist', function () {
	actingAsNavigationUser();

	$this->getJson( '/visual-editor/api/navigation' )
		->assertOk()
		->assertJsonPath( 'data', [] )
		->assertJsonPath( 'meta.total', 0 )
		->assertJsonPath( 'meta.last_page', 1 );
} );

it( 'lists navigations ordered by menu_order', function () {
	actingAsNavigationUser();

	createNavigation( [ 'slug' => 'footer-nav', 'title' => 'Footer', 'menu_order' => 5 ] );
	createNavigation( [ 'slug' => 'primary-nav', 'title' => 'Primary', 'menu_order' => 0 ] );

	$this->getJson( '/visual-editor/api/navigation' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonPath( 'meta.total', 2 )
		->assertJsonPath( 'data.0.slug', 'primary-nav' )
		->assertJsonPath( 'data.0.type', 'wp_navigation' )
		->assertJsonPath( 'data.0.title.rendered', 'Primary' )
		->assertJsonPath( 'data.0.menu_order', 0 )
		->assertJsonPath( 'data.1.slug', 'footer-nav' )
		->assertJsonPath( 'data.1.menu_order', 5 );
} );

it( 'filters the index by slug and status', function () {
	actingAsNavigationUser();

	createNavigation( [ 'slug' => 'primary-nav', 'status' => 'publish' ] );
	createNavigation( [ 'slug' => 'footer-nav', 'status' => 'draft' ] );
	createNavigation( [ 'slug' => 'secondary-nav', 'status' => 'publish' ] );

	$this->getJson( '/visual-editor/api/navigation?status=publish' )
		->assertOk()
		->assertJsonCount( 2, 'data' );

	$this->getJson( '/visual-editor/api/navigation?slug=footer-nav' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.slug', 'footer-nav' );
} );

it( 'returns 401 when unauthenticated on show', function () {
	$navigation = createNavigation();

	$this->getJson( "/visual-editor/api/navigation/{$navigation->id}" )->assertUnauthorized();
} );

it( 'returns the content envelope and rendered title on show', function () {
	actingAsNavigationUser();

	$navigation = createNavigation();

	$this->getJson( "/visual-editor/api/navigation/{$navigation->id}" )
		->assertOk()
		->assertJsonPath( 'id', $navigation->id )
		->assertJsonPath( 'slug', 'primary-nav' )
		->assertJsonPath( 'type', 'wp_navigation' )
		->assertJsonPath( 'title.rendered', 'Primary' )
		->assertJsonPath( 'content.raw', '<!-- wp:navigation-link {"label":"Home","url":"/"} /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/navigation-link' )
		->assertJsonPath( 'status', 'publish' )
		->assertJsonPath( 'menu_order', 0 );
} );

it( 'returns 404 when the navigation id does not exist', function () {
	actingAsNavigationUser();

	$this->getJson( '/visual-editor/api/navigation/999' )->assertNotFound();
} );

it( 'creates a navigation with a 201 Created response', function () {
	actingAsNavigationUser();

	$payload = [
		'slug'       => 'footer-nav',
		'title'      => 'Footer',
		'content'    => [
			'raw'    => '<!-- wp:navigation-link {"label":"Privacy","url":"/privacy"} /-->',
			'blocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Privacy', 'url' => '/privacy' ],
					'innerBlocks' => [],
				],
			],
		],
		'status'     => 'publish',
		'menu_order' => 2,
	];

	$this->postJson( '/visual-editor/api/navigation', $payload )
		->assertCreated()
		->assertJsonPath( 'slug', 'footer-nav' )
		->assertJsonPath( 'menu_order', 2 )
		->assertJsonPath( 'content.raw', '<!-- wp:navigation-link {"label":"Privacy","url":"/privacy"} /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/navigation-link' );

	expect( VisualEditorNavigation::where( 'slug', 'footer-nav' )->first() )
		->not->toBeNull()
		->getBlocks()->toEqual( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Privacy', 'url' => '/privacy' ],
				'innerBlocks' => [],
			],
		] );
} );

it( 'rejects store when the slug already exists', function () {
	actingAsNavigationUser();

	createNavigation( [ 'slug' => 'primary-nav' ] );

	$this->postJson( '/visual-editor/api/navigation', [
		'slug' => 'primary-nav',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'slug' );
} );

it( 'rejects store when the block tree is malformed', function () {
	actingAsNavigationUser();

	$this->postJson( '/visual-editor/api/navigation', [
		'slug'    => 'broken-nav',
		'content' => [
			'raw'    => '',
			'blocks' => [
				[ 'not-a-block' => true ],
			],
		],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'content.blocks' );
} );

it( 'rejects store when status is outside the enum', function () {
	actingAsNavigationUser();

	$this->postJson( '/visual-editor/api/navigation', [
		'slug'   => 'maybe-nav',
		'status' => 'trashed',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'status' );
} );

it( 'updates a navigation with a partial payload', function () {
	actingAsNavigationUser();

	$navigation = createNavigation( [ 'title' => 'Before' ] );

	$this->putJson( "/visual-editor/api/navigation/{$navigation->id}", [
		'title' => 'After',
	] )
		->assertOk()
		->assertJsonPath( 'title.rendered', 'After' )
		->assertJsonPath( 'slug', 'primary-nav' );

	expect( $navigation->fresh()->title )->toBe( 'After' );
} );

it( 'persists a new content envelope on update', function () {
	actingAsNavigationUser();

	$navigation = createNavigation();

	$newContent = [
		'raw'    => '<!-- wp:navigation-link {"label":"About","url":"/about"} /-->',
		'blocks' => [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'About', 'url' => '/about' ],
				'innerBlocks' => [],
			],
		],
	];

	$this->putJson( "/visual-editor/api/navigation/{$navigation->id}", [
		'content' => $newContent,
	] )
		->assertOk()
		->assertJsonPath( 'content.raw', $newContent['raw'] )
		->assertJsonPath( 'content.blocks.0.attributes.label', 'About' );

	expect( $navigation->fresh()->getContentEnvelope() )->toEqual( $newContent );
} );

it( 'deletes a navigation', function () {
	actingAsNavigationUser();

	$navigation = createNavigation();

	$this->deleteJson( "/visual-editor/api/navigation/{$navigation->id}" )
		->assertNoContent();

	expect( VisualEditorNavigation::find( $navigation->id ) )->toBeNull();
} );

it( 'round-trips the nested.json fixture without reshaping the submenu', function () {
	actingAsNavigationUser();

	$fixturePath = dirname( __DIR__, 2 ) . '/Fixtures/sample-content/navigation/nested.json';
	$fixture     = json_decode( (string) file_get_contents( $fixturePath ), true, flags: JSON_THROW_ON_ERROR );

	$payload = [
		'slug'       => $fixture['slug'],
		'title'      => $fixture['title']['rendered'] ?? '',
		'content'    => $fixture['content'],
		'status'     => $fixture['status'],
		'menu_order' => $fixture['menu_order'],
	];

	$response = $this->postJson( '/visual-editor/api/navigation', $payload )
		->assertCreated()
		->assertJsonPath( 'slug', $fixture['slug'] )
		->assertJsonPath( 'content.raw', $fixture['content']['raw'] )
		->assertJsonPath( 'content.blocks', $fixture['content']['blocks'] );

	$id = $response->json( 'id' );

	$this->getJson( "/visual-editor/api/navigation/{$id}" )
		->assertOk()
		->assertJsonPath( 'content.blocks', $fixture['content']['blocks'] )
		->assertJsonPath( 'content.blocks.1.name', 'core/navigation-submenu' )
		->assertJsonPath( 'content.blocks.1.innerBlocks.0.name', 'core/navigation-link' )
		->assertJsonPath( 'content.blocks.1.innerBlocks.0.attributes.label', 'Featured' );
} );

it( 'round-trips all B2 navigation fixtures through store + show', function () {
	actingAsNavigationUser();

	$fixturesDir = dirname( __DIR__, 2 ) . '/Fixtures/sample-content/navigation';
	$files       = glob( $fixturesDir . '/*.json' );

	expect( $files )->not->toBeEmpty();

	foreach ( $files as $file ) {
		$fixture = json_decode( (string) file_get_contents( $file ), true, flags: JSON_THROW_ON_ERROR );

		$payload = [
			'slug'       => $fixture['slug'],
			'title'      => $fixture['title']['rendered'] ?? '',
			'content'    => $fixture['content'] ?? [ 'raw' => '', 'blocks' => [] ],
			'status'     => $fixture['status'] ?? 'publish',
			'menu_order' => $fixture['menu_order'] ?? 0,
		];

		$response = $this->postJson( '/visual-editor/api/navigation', $payload )
			->assertCreated()
			->assertJsonPath( 'slug', $fixture['slug'] );

		$id = $response->json( 'id' );

		$this->getJson( "/visual-editor/api/navigation/{$id}" )
			->assertOk()
			->assertJsonPath( 'slug', $fixture['slug'] )
			->assertJsonPath( 'content.blocks', $fixture['content']['blocks'] );
	}
} );

it( 'rejects a null title on store (column is non-nullable)', function () {
	actingAsNavigationUser();

	$this->postJson( '/visual-editor/api/navigation', [
		'slug'  => 'broken-nav',
		'title' => null,
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'title' );
} );

it( 'rejects a null title on update (column is non-nullable)', function () {
	actingAsNavigationUser();

	$navigation = createNavigation();

	$this->putJson( "/visual-editor/api/navigation/{$navigation->id}", [
		'title' => null,
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'title' );

	expect( $navigation->fresh()->title )->toBe( 'Primary' );
} );

it( 'rejects a bare-list content payload on store', function () {
	actingAsNavigationUser();

	$this->postJson( '/visual-editor/api/navigation', [
		'slug'    => 'broken-nav',
		'content' => [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [],
				'innerBlocks' => [],
			],
		],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'content' );
} );

it( 'rejects a bare-list content payload on update', function () {
	actingAsNavigationUser();

	$navigation = createNavigation();

	$this->putJson( "/visual-editor/api/navigation/{$navigation->id}", [
		'content' => [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [],
				'innerBlocks' => [],
			],
		],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'content' );
} );

it( 'rejects a slug-only update that would collide with an existing slug', function () {
	actingAsNavigationUser();

	$editing = createNavigation( [ 'slug' => 'primary-nav' ] );
	createNavigation( [ 'slug' => 'footer-nav' ] );

	$this->putJson( "/visual-editor/api/navigation/{$editing->id}", [
		'slug' => 'footer-nav',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'slug' );

	expect( $editing->fresh()->slug )->toBe( 'primary-nav' );
} );

it( 'allows updating a record without changing its slug', function () {
	actingAsNavigationUser();

	$navigation = createNavigation( [ 'slug' => 'primary-nav' ] );

	$this->putJson( "/visual-editor/api/navigation/{$navigation->id}", [
		'slug'  => 'primary-nav',
		'title' => 'Renamed',
	] )
		->assertOk()
		->assertJsonPath( 'slug', 'primary-nav' )
		->assertJsonPath( 'title.rendered', 'Renamed' );
} );

it( 'rejects unauthenticated store, update, and destroy', function () {
	$navigation = createNavigation();

	$this->postJson( '/visual-editor/api/navigation', [
		'slug' => 'new-nav',
	] )->assertUnauthorized();

	$this->putJson( "/visual-editor/api/navigation/{$navigation->id}", [
		'title' => 'Nope',
	] )->assertUnauthorized();

	$this->deleteJson( "/visual-editor/api/navigation/{$navigation->id}" )->assertUnauthorized();
} );

it( 'persists a configured location on store and exposes it in the response', function () {
	actingAsNavigationUser();

	config( [
		'artisanpack.visual-editor.navigation.locations' => [
			'primary' => [
				'slug'       => 'primary',
				'label'      => 'Primary Menu',
				'primary_id' => null,
			],
		],
	] );

	$this->postJson( '/visual-editor/api/navigation', [
		'slug'     => 'main-menu',
		'title'    => 'Main',
		'location' => 'primary',
	] )
		->assertCreated()
		->assertJsonPath( 'location', 'primary' );

	expect( VisualEditorNavigation::where( 'slug', 'main-menu' )->first()->location )
		->toBe( 'primary' );
} );

it( 'rejects an unknown location slug as a 422', function () {
	actingAsNavigationUser();

	config( [
		'artisanpack.visual-editor.navigation.locations' => [
			'primary' => [
				'slug'       => 'primary',
				'label'      => 'Primary',
				'primary_id' => null,
			],
		],
	] );

	$this->postJson( '/visual-editor/api/navigation', [
		'slug'     => 'main-menu',
		'location' => 'sidebar',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'location' );
} );

it( 'releases an existing location assignment when reassigning to another menu', function () {
	actingAsNavigationUser();

	config( [
		'artisanpack.visual-editor.navigation.locations' => [
			'primary' => [
				'slug'       => 'primary',
				'label'      => 'Primary',
				'primary_id' => null,
			],
		],
	] );

	$first  = createNavigation( [ 'slug' => 'first-nav', 'location' => 'primary' ] );
	$second = createNavigation( [ 'slug' => 'second-nav', 'location' => null ] );

	$this->putJson( "/visual-editor/api/navigation/{$second->id}", [
		'location' => 'primary',
	] )
		->assertOk()
		->assertJsonPath( 'location', 'primary' );

	expect( $first->fresh()->location )->toBeNull();
	expect( $second->fresh()->location )->toBe( 'primary' );
} );

it( 'clears the location when null is sent on update', function () {
	actingAsNavigationUser();

	config( [
		'artisanpack.visual-editor.navigation.locations' => [
			'primary' => [
				'slug'       => 'primary',
				'label'      => 'Primary',
				'primary_id' => null,
			],
		],
	] );

	$navigation = createNavigation( [ 'slug' => 'primary-nav', 'location' => 'primary' ] );

	$this->putJson( "/visual-editor/api/navigation/{$navigation->id}", [
		'location' => null,
	] )
		->assertOk()
		->assertJsonPath( 'location', null );

	expect( $navigation->fresh()->location )->toBeNull();
} );
