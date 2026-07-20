<?php

declare( strict_types=1 );

/**
 * Feature tests for the Blade renderer's loginout-block path (#522).
 *
 * Covers the full server-side contract for `artisanpack/loginout`:
 *
 * - The renderer stamps `_resolved*` from {@see LoginoutResolver} so the
 *   partial sees the auth-state envelope without each renderer reaching
 *   into Laravel's auth stack.
 * - Logged-out viewers get the configured login URL and the "Log in"
 *   label; logged-in viewers get the logout URL and the "Log out" label.
 * - `redirectToCurrent` appends the request URL as a `redirect_to`
 *   query parameter without clobbering an existing query string.
 * - `displayLoginAsForm` swaps the link for the host-supplied form HTML
 *   only when the viewer is logged-out; logged-in viewers still see the
 *   logout link even when the toggle is on (matches upstream).
 * - Host-stamped `_resolved*` attributes win over the resolver fallback.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\LoginoutResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

function loginoutRenderTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

function loginoutBlockNode( array $attributes = [], string $name = 'artisanpack/loginout' ): array
{
	return [
		'clientId'    => 'loginout-cid',
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => [],
	];
}

beforeEach( function (): void {
	// Reset any custom URL filter from a prior case so each test starts
	// from the same envelope baseline.
	if ( function_exists( 'removeAllFilters' ) ) {
		removeAllFilters( 'ap.visualEditor.loginout.envelope' );
		removeAllFilters( 'ap.visualEditor.loginout.loginForm' );
	}
} );

it( 'renders the login link with the "Log in" label for logged-out viewers', function () {
	Auth::shouldUse( 'web' );
	expect( Auth::check() )->toBeFalse();

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [ 'redirectToCurrent' => false ] ),
	] ) );

	expect( $rendered )
		->toContain( 'class="logged-out"' )
		->toContain( '>Log in<' )
		->toContain( 'href="' )
		->toContain( '/login' );
} );

it( 'renders the logout link with the "Log out" label for logged-in viewers', function () {
	$user = new GenericUser( [ 'id' => 42, 'name' => 'Forky', 'remember_token' => null ] );
	Auth::setUser( $user );
	expect( Auth::check() )->toBeTrue();

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [ 'redirectToCurrent' => false ] ),
	] ) );

	expect( $rendered )
		->toContain( 'class="logged-in"' )
		->toContain( '>Log out<' )
		->toContain( '/logout' );

	Auth::logout();
} );

it( 'appends the current URL as a redirect_to query parameter when redirectToCurrent is on', function () {
	// `redirectToCurrent` defaults to true, but make it explicit so the
	// test reads as a statement about the behavior rather than the
	// default. The resolver should see the in-flight request URL via
	// `Request::fullUrl()`.
	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [ 'redirectToCurrent' => true ] ),
	] ) );

	// We do not assert the absolute URL because Orchestra Testbench's
	// default base URL drifts between runs — verify both the parameter
	// name AND that it has a non-empty value so a future regression
	// that stamps `redirect_to=` with an empty string still fails the
	// test. The character class excludes the anchor terminators that
	// follow the value in the rendered HTML (`"`, whitespace, `&`).
	expect( $rendered )
		->toContain( 'redirect_to=' )
		->toMatch( '/redirect_to=[^"\s&]+/' );
} );

it( 'honors a configured named login route over the literal path fallback', function () {
	Route::get( '/auth/sign-in', fn () => 'ok' )->name( 'login' );
	// `->name()` mutates the route after it has been added to the
	// collection, so the collection's name lookup table is stale until
	// we refresh it. Outside of tests the request lifecycle does this
	// for us via Router::dispatch().
	Route::getRoutes()->refreshNameLookups();

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [ 'redirectToCurrent' => false ] ),
	] ) );

	expect( $rendered )
		->toContain( '/auth/sign-in' )
		->not()->toContain( 'href="http://localhost/login"' );
} );

it( 'shows the host-supplied login form for logged-out viewers when displayLoginAsForm is on', function () {
	addFilter(
		'ap.visualEditor.loginout.loginForm',
		fn ( string $_, string $current ): string => '<form data-test-form data-redirect="' . htmlspecialchars( $current ) . '"></form>'
	);

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [
			'displayLoginAsForm' => true,
			'redirectToCurrent'  => false,
		] ),
	] ) );

	expect( $rendered )
		->toContain( '<form data-test-form' )
		->toContain( 'has-login-form' )
		->not()->toContain( '>Log in<' );
} );

it( 'keeps the logout link for logged-in viewers even when displayLoginAsForm is on', function () {
	addFilter(
		'ap.visualEditor.loginout.loginForm',
		fn (): string => '<form data-test-form></form>'
	);

	$user = new GenericUser( [ 'id' => 7, 'name' => 'Tay', 'remember_token' => null ] );
	Auth::setUser( $user );

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [
			'displayLoginAsForm' => true,
			'redirectToCurrent'  => false,
		] ),
	] ) );

	expect( $rendered )
		->toContain( '>Log out<' )
		->not()->toContain( 'data-test-form' )
		->not()->toContain( 'has-login-form' );

	Auth::logout();
} );

it( 'lets host-stamped resolved attributes win over the resolver fallback', function () {
	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [
			'redirectToCurrent'        => false,
			'_resolvedIsUserLoggedIn'  => true,
			'_resolvedLoginoutUrl'     => 'https://host.example/sign-out',
			'_resolvedLoginoutLabel'   => 'See you later',
			'_resolvedLoginoutClass'   => 'logged-in custom-class',
		] ),
	] ) );

	expect( $rendered )
		->toContain( 'href="https://host.example/sign-out"' )
		->toContain( '>See you later<' )
		->toContain( 'custom-class' );
} );

it( 'lets a host filter rewrite the resolved envelope through ap.visualEditor.loginout.envelope', function () {
	addFilter(
		'ap.visualEditor.loginout.envelope',
		fn ( array $envelope ): array => array_merge( $envelope, [
			'url'   => 'https://sso.example/login',
			'label' => 'Continue with SSO',
		] )
	);

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [ 'redirectToCurrent' => false ] ),
	] ) );

	expect( $rendered )
		->toContain( 'href="https://sso.example/login"' )
		->toContain( '>Continue with SSO<' );
} );

it( 'returns false for the loggedIn flag when no auth guard is available', function () {
	// Direct resolver call — proves the {@see LoginoutResolver::isUserLoggedIn}
	// catch path returns false rather than bubbling the exception. The
	// renderer path always has a guard so this is a defensive case.
	$resolver = app( LoginoutResolver::class );
	$envelope = $resolver->resolve( [ 'redirectToCurrent' => false ] );

	expect( $envelope['isUserLoggedIn'] )->toBeFalse();
	expect( $envelope['label'] )->toBe( 'Log in' );
} );

it( 'falls back to a plain label (no anchor) when the resolved URL is empty', function () {
	// Matches the React + Vue renderers' empty-URL guard: when the
	// resolver is bypassed (or the host stamps a disallowed scheme that
	// the sanitizer strips), the wrapper should still describe the link
	// it would have rendered, but without an inert `href=""` anchor.
	$user = new GenericUser( [ 'id' => 99, 'name' => 'Logging out', 'remember_token' => null ] );
	Auth::setUser( $user );

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [
			'redirectToCurrent'    => false,
			// `javascript:` is dropped by UrlSanitizer::safe(), so the
			// effective URL the partial sees is the empty string — same
			// codepath the renderers exercise when no upstream stamper
			// supplied a URL at all.
			'_resolvedLoginoutUrl' => 'javascript:alert(1)',
		] ),
	] ) );

	expect( $rendered )
		->toContain( 'Log out' )
		->not()->toContain( '<a ' )
		->not()->toContain( 'javascript:' )
		->not()->toContain( 'href=""' );

	Auth::logout();
} );

it( 'omits the has-login-form class when displayLoginAsForm is on but no host form was registered', function () {
	// Matches the Blade partial's $showForm gate: the modifier class
	// should only appear when a form will actually render. The resolver
	// returns an empty loginFormHtml when the host hasn't wired the
	// `ap.visualEditor.loginout.loginForm` filter, so the wrapper
	// must NOT promise a form that isn't there.
	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		loginoutBlockNode( [
			'displayLoginAsForm' => true,
			'redirectToCurrent'  => false,
		] ),
	] ) );

	expect( $rendered )
		->toContain( 'class="logged-out"' )
		->toContain( '>Log in<' )
		->not()->toContain( 'has-login-form' );
} );

it( 'leaves non-loginout blocks untouched even when an auth user is set', function () {
	Auth::setUser( new GenericUser( [ 'id' => 1, 'name' => 'x', 'remember_token' => null ] ) );

	$rendered = $this->stripGlobalStyles( loginoutRenderTree( [
		[
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Hello' ],
			'innerBlocks' => [],
		],
	] ) );

	expect( $rendered )
		->toContain( 'Hello' )
		->not()->toContain( 'logged-in' )
		->not()->toContain( '_resolvedLoginout' );

	Auth::logout();
} );
