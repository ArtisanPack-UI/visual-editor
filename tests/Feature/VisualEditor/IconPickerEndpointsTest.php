<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Middleware\EnsureContentEditorAccess;
use ArtisanPackUI\VisualEditor\Services\Icon\IconCatalog;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Support\ContentAccess;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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

it( 'refuses an external-entity payload via the sanitize endpoint without leaking file contents', function () {
	actingAsIconPickerUser();

	$secret = tempnam( sys_get_temp_dir(), 've-xxe-' );
	file_put_contents( $secret, 'VE_XXE_CANARY_SECRET' );

	$payload = '<!DOC<!DOCTYPE x>TYPE svg [<!ENTITY xxe SYSTEM "file://' . $secret . '">]>'
		. '<svg xmlns="http://www.w3.org/2000/svg"><title>&xxe;</title><path d="M0 0"/></svg>';

	try {
		$response = $this->postJson(
			'/visual-editor/api/icons/svg/sanitize',
			[ 'svg' => $payload ],
		)->assertOk();
	} finally {
		@unlink( $secret );
	}

	expect( $response->getContent() )->not->toContain( 'VE_XXE_CANARY_SECRET' );
	expect( $response->json( 'svg' ) )->toBe( '' );
	expect( $response->json( 'warnings' ) )->toContain( 'svg contains a DOCTYPE or ENTITY declaration' );
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

// #834 — content-authoring access gate on the icon endpoints.

/**
 * A user whose RBAC grants exactly the given capabilities.
 *
 * @param  array<int, string>  $capabilities
 */
function iconRbacUser( array $capabilities = [] ): GenericUser
{
	return new class( [ 'id' => 1 ], $capabilities ) extends GenericUser {
		/** @param array<int, string> $capabilities */
		public function __construct( array $attributes, private array $capabilities )
		{
			parent::__construct( $attributes );
		}

		public function hasCapability( string $capability ): bool
		{
			return in_array( $capability, $this->capabilities, true );
		}
	};
}

dataset( 'icon endpoints', [
	'sets'     => [ 'GET', '/visual-editor/api/icons/sets' ],
	'search'   => [ 'GET', '/visual-editor/api/icons/search' ],
	'svg'      => [ 'GET', '/visual-editor/api/icons/svg?set=fas&name=home' ],
	'sanitize' => [ 'POST', '/visual-editor/api/icons/svg/sanitize' ],
] );

it( 'gates every icon route on the content-access middleware', function () {
	foreach ( [ 'sets', 'search', 'svg', 'svg.sanitize' ] as $name ) {
		$middleware = Route::getRoutes()->getByName( 'visual-editor.api.icons.' . $name )->gatherMiddleware();

		expect( $middleware )->toContain( EnsureContentEditorAccess::class );
	}
} );

it( 'throttles the sanitize endpoint with the configured limit in its own bucket', function () {
	$middleware = Route::getRoutes()->getByName( 'visual-editor.api.icons.svg.sanitize' )->gatherMiddleware();

	expect( $middleware )->toContain( 'throttle:60,1,ve-icon-sanitize' );
} );

/**
 * Re-register the API routes so they read the current config, the way a
 * fresh boot (or `route:cache`) would.
 */
function reloadIconApiRoutes(): void
{
	( fn () => $this->registerApiRoutes() )->call( new VisualEditorServiceProvider( app() ) );

	Route::getRoutes()->refreshNameLookups();
}

it( 'falls back to the default sanitize throttle when the config is empty', function ( mixed $configured ) {
	config()->set( 'artisanpack.visual-editor.content_access.sanitize_throttle', $configured );
	reloadIconApiRoutes();

	$middleware = Route::getRoutes()->getByName( 'visual-editor.api.icons.svg.sanitize' )->gatherMiddleware();

	expect( $middleware )->toContain( 'throttle:60,1,ve-icon-sanitize' );

	actingAsIconPickerUser();

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>' ] )
		->assertOk();
} )->with( [
	'empty string' => [ '' ],
	'whitespace'   => [ '   ' ],
	'null'         => [ null ],
	'array'        => [ [ '60', '1' ] ],
] );

it( 'skips the sanitize throttle when the config is false', function () {
	config()->set( 'artisanpack.visual-editor.content_access.sanitize_throttle', false );
	reloadIconApiRoutes();

	$middleware = Route::getRoutes()->getByName( 'visual-editor.api.icons.svg.sanitize' )->gatherMiddleware();

	expect( implode( ' ', $middleware ) )->not->toContain( 'throttle' )
		->and( $middleware )->toContain( EnsureContentEditorAccess::class );

	actingAsIconPickerUser();

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>' ] )
		->assertOk();
} );

it( 'keeps the sanitize throttle separate from other throttled routes', function () {
	config()->set( 'artisanpack.visual-editor.content_access.sanitize_throttle', '2,1' );
	reloadIconApiRoutes();

	actingAsIconPickerUser();

	foreach ( [ 1, 2 ] as $attempt ) {
		$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>' ] )
			->assertOk();
	}

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>' ] )
		->assertTooManyRequests();

	expect( $this->postJson( '/visual-editor/api/dynamic-content/resolve', [] )->status() )->not->toBe( 429 );
} );

it( 'treats a non-string capability as unset instead of failing', function () {
	config()->set( 'artisanpack.visual-editor.content_access.capability', [ 'edit_content' ] );

	actingAsIconPickerUser();

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>' ] )
		->assertOk();

	expect( ContentAccess::allows( new GenericUser( [ 'id' => 1 ] ) ) )->toBeTrue();
} );

it( 'denies and logs a warning once when the user model has no RBAC method', function () {
	config()->set( 'artisanpack.visual-editor.content_access.capability', 'edit_content' );

	Log::spy();

	actingAsIconPickerUser();

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg/>' ] )
		->assertForbidden();

	// A second check in the same request doesn't log again.
	ContentAccess::allows( auth()->user() );

	Log::shouldHaveReceived( 'warning' )
		->once()
		->withArgs( fn ( string $message, array $context ): bool => str_contains( $message, 'content_access.capability' ) && 'edit_content' === $context['capability'] );
} );

it( 'falls back to the user\'s Gate abilities when the model has no RBAC method', function () {
	config()->set( 'artisanpack.visual-editor.content_access.capability', 'edit_content' );
	Gate::define( 'edit_content', fn ( $user ) => true );

	actingAsIconPickerUser();

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>' ] )
		->assertOk();
} );

it( 'never recurses when the capability names the content gate itself', function () {
	config()->set( 'artisanpack.visual-editor.content_access.capability', ContentAccess::ABILITY );

	expect( ContentAccess::allows( actingAsIconPickerUser() ) )->toBeFalse();
} );

it( 'answers 401 to a guest on the icon endpoints', function ( string $method, string $uri ) {
	$this->json( $method, $uri, [ 'svg' => '<svg/>' ] )->assertUnauthorized();
} )->with( 'icon endpoints' );

it( 'answers a JSON 403 when the gate denies the user', function ( string $method, string $uri ) {
	actingAsIconPickerUser();
	Gate::define( ContentAccess::ABILITY, fn ( $user = null ) => false );

	$this->json( $method, $uri, [ 'svg' => '<svg/>' ] )
		->assertForbidden()
		->assertJsonPath( 'message', 'You are not allowed to edit content.' );
} )->with( 'icon endpoints' );

it( 'answers a JSON 403 even when the client does not ask for JSON', function () {
	actingAsIconPickerUser();
	Gate::define( ContentAccess::ABILITY, fn ( $user = null ) => false );

	$this->post( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg/>' ] )
		->assertForbidden()
		->assertHeader( 'Content-Type', 'application/json' );
} );

it( 'lets any authenticated user sanitize by default', function () {
	actingAsIconPickerUser();

	$this->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>' ] )
		->assertOk()
		->assertJsonPath( 'warnings', [] );
} );

it( 'requires the configured capability when one is set', function () {
	config()->set( 'artisanpack.visual-editor.content_access.capability', 'edit_content' );

	$this->actingAs( iconRbacUser() )
		->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg/>' ] )
		->assertForbidden();

	$this->actingAs( iconRbacUser( [ 'edit_content' ] ) )
		->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg/>' ] )
		->assertOk();
} );

it( 'resolves the default gate from the configured capability', function () {
	expect( ContentAccess::allows( null ) )->toBeFalse();
	expect( ContentAccess::allows( iconRbacUser() ) )->toBeTrue();
	expect( ContentAccess::allows( new GenericUser( [ 'id' => 1 ] ) ) )->toBeTrue();

	config()->set( 'artisanpack.visual-editor.content_access.capability', 'edit_content' );

	expect( ContentAccess::allows( iconRbacUser() ) )->toBeFalse();
	expect( ContentAccess::allows( iconRbacUser( [ 'edit_content' ] ) ) )->toBeTrue();
	expect( ContentAccess::allows( new GenericUser( [ 'id' => 1 ] ) ) )->toBeFalse();
} );

it( 'lets a host-defined gate allow users the default would deny', function () {
	config()->set( 'artisanpack.visual-editor.content_access.capability', 'edit_content' );
	Gate::define( ContentAccess::ABILITY, fn ( $user = null ) => null !== $user );

	$this->actingAs( iconRbacUser() )
		->postJson( '/visual-editor/api/icons/svg/sanitize', [ 'svg' => '<svg/>' ] )
		->assertOk();
} );

it( 'does not replace a gate the host already defined', function () {
	Gate::define( ContentAccess::ABILITY, fn ( $user = null ) => false );

	$provider = new VisualEditorServiceProvider( app() );
	( fn () => $this->registerContentGate() )->call( $provider );

	expect( Gate::forUser( iconRbacUser() )->allows( ContentAccess::ABILITY ) )->toBeFalse();
} );
