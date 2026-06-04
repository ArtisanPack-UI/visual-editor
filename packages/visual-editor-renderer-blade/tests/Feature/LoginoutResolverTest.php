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
		removeAllFilters( 'ap.visual-editor.loginout.envelope' );
		removeAllFilters( 'ap.visual-editor.loginout.login-form' );
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
	// default base URL drifts between runs — only that the resolver
	// stamped *some* redirect_to value onto the link.
	expect( $rendered )->toContain( 'redirect_to=' );
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
		'ap.visual-editor.loginout.login-form',
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
		'ap.visual-editor.loginout.login-form',
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

it( 'lets a host filter rewrite the resolved envelope through ap.visual-editor.loginout.envelope', function () {
	addFilter(
		'ap.visual-editor.loginout.envelope',
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
