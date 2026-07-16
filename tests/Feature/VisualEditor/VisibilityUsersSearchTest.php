<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestUser;

// The default `SiteEditorAccessGate` binding is `DenyByDefaultGate`,
// which returns a `Response` for every request. To exercise the
// controller's happy paths under Pest we swap in a null-returning
// gate; a separate test exercises the deny path.
function bindAllowAllVisibilityGate(): void
{
	app()->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate
		{
			public function check( Request $request ): ?Response
			{
				return null;
			}
		};
	} );
}

it( 'returns 403 when the site-editor access gate denies the request', function () {
	// Default binding is `DenyByDefaultGate` — no rebinding needed.
	$user = TestUser::create( [ 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => bcrypt( 'x' ) ] );

	$this->actingAs( $user )
		->getJson( '/visual-editor/api/users/search?q=ada' )
		->assertStatus( 403 );
} );

it( 'returns an empty result for an empty search term', function () {
	bindAllowAllVisibilityGate();

	$user = TestUser::create( [ 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => bcrypt( 'x' ) ] );

	$this->actingAs( $user )
		->getJson( '/visual-editor/api/users/search?q=' )
		->assertOk()
		->assertJson( [ 'data' => [] ] );
} );

it( 'returns matching users by email substring', function () {
	bindAllowAllVisibilityGate();

	TestUser::create( [ 'name' => 'Ada Lovelace',    'email' => 'ada@example.com',    'password' => bcrypt( 'x' ) ] );
	TestUser::create( [ 'name' => 'Grace Hopper',    'email' => 'grace@example.com',  'password' => bcrypt( 'x' ) ] );
	TestUser::create( [ 'name' => 'Alan Turing',     'email' => 'alan@example.com',   'password' => bcrypt( 'x' ) ] );

	$requester = TestUser::create( [ 'name' => 'Me', 'email' => 'me@example.com', 'password' => bcrypt( 'x' ) ] );

	$response = $this->actingAs( $requester )
		->getJson( '/visual-editor/api/users/search?q=ada' )
		->assertOk()
		->json( 'data' );

	expect( $response )->toBeArray()->and( count( $response ) )->toBeGreaterThan( 0 );
	expect( collect( $response )->pluck( 'email' )->all() )->toContain( 'ada@example.com' );
} );

it( 'respects the limit query parameter', function () {
	bindAllowAllVisibilityGate();

	for ( $i = 1; $i <= 5; $i++ ) {
		TestUser::create( [ 'name' => "User $i", 'email' => "user$i@example.com", 'password' => bcrypt( 'x' ) ] );
	}

	$requester = TestUser::create( [ 'name' => 'Me', 'email' => 'me@example.com', 'password' => bcrypt( 'x' ) ] );

	$response = $this->actingAs( $requester )
		->getJson( '/visual-editor/api/users/search?q=user&limit=2' )
		->assertOk()
		->json( 'data' );

	expect( $response )->toHaveCount( 2 );
} );

it( 'escapes LIKE metacharacters so ?q=% cannot dump the table', function () {
	bindAllowAllVisibilityGate();

	// Populate distinct users so a naive `%%%` LIKE would match all
	// of them; the escape should force a literal `%` search and
	// return zero rows.
	TestUser::create( [ 'name' => 'One',   'email' => 'one@example.com',   'password' => bcrypt( 'x' ) ] );
	TestUser::create( [ 'name' => 'Two',   'email' => 'two@example.com',   'password' => bcrypt( 'x' ) ] );
	TestUser::create( [ 'name' => 'Three', 'email' => 'three@example.com', 'password' => bcrypt( 'x' ) ] );

	$requester = TestUser::create( [ 'name' => 'Me', 'email' => 'me@example.com', 'password' => bcrypt( 'x' ) ] );

	$response = $this->actingAs( $requester )
		->getJson( '/visual-editor/api/users/search?q=%25' )
		->assertOk()
		->json( 'data' );

	expect( $response )->toBeArray()->and( $response )->toBeEmpty();
} );
