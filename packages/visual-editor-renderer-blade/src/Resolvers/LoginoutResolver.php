<?php

/**
 * Loginout resolver for the Blade renderer (#522).
 *
 * Returns the auth-state envelope consumed by the
 * `artisanpack/loginout` block partial: whether the current viewer is
 * authenticated, the login or logout URL appropriate to that state,
 * the human-readable label for the link, and (logged-out only) the
 * pre-rendered login form HTML when the block opts into the form
 * display.
 *
 * Login / logout / register URLs come from the package config
 * (`config('artisanpack.visual-editor.loginout.*')`), so a host app
 * can point them at its own auth stack — the package itself does not
 * register login routes. The defaults match Laravel's standard
 * starter-kit route names (`login`, `logout`) and degrade to literal
 * paths (`/login`, `/logout`) when those names are not registered.
 *
 * Hosts that need fully custom resolution (per-tenant URLs, third-
 * party SSO, etc.) override the envelope through the
 * `ap.visual-editor.loginout.envelope` filter hook — the resolver
 * applies the filter against the resolved defaults before returning.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Resolvers;

use Illuminate\Http\Request;
use Throwable;

class LoginoutResolver
{
	/**
	 * Resolves the auth-state envelope for a single render.
	 *
	 * Stateless on purpose — the loggedIn flag, the current URL, and
	 * the resolved label depend on the in-flight request and on the
	 * block's `redirectToCurrent` / `displayLoginAsForm` attributes,
	 * so caching the envelope across calls would produce wrong markup
	 * for the next block on the same request. URL lookups are cheap
	 * (one container resolve + one route lookup) so the per-call cost
	 * is negligible.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes  Block attributes (read-only).
	 *
	 * @return array{
	 *     isUserLoggedIn: bool,
	 *     url: string,
	 *     label: string,
	 *     classes: string,
	 *     loginFormHtml: string
	 * }
	 */
	public function resolve( array $attributes ): array
	{
		$redirectToCurrent = ! array_key_exists( 'redirectToCurrent', $attributes )
			|| false !== $attributes['redirectToCurrent'];
		$displayLoginAsForm = isset( $attributes['displayLoginAsForm'] ) && true === $attributes['displayLoginAsForm'];

		$isUserLoggedIn = $this->isUserLoggedIn();
		$currentUrl     = $redirectToCurrent ? $this->resolveCurrentUrl() : '';

		$url = $isUserLoggedIn
			? $this->resolveLogoutUrl( $currentUrl )
			: $this->resolveLoginUrl( $currentUrl );

		$label = $isUserLoggedIn
			? __( 'Log out' )
			: __( 'Log in' );

		// Pre-render the inline login form when the block opts into
		// the form view AND the viewer is logged-out — upstream skips
		// the form for logged-in viewers so the link stays a one-shot
		// logout action.
		$loginFormHtml = ( ! $isUserLoggedIn && $displayLoginAsForm )
			? $this->resolveLoginFormHtml( $currentUrl )
			: '';

		// Gate `has-login-form` on the resolved HTML being non-empty so
		// the wrapper class stays consistent with what the partial
		// actually renders. The Blade / React / Vue renderers all fall
		// back to the link variant when the form HTML is empty, even
		// with `displayLoginAsForm` on — the class needs to match.
		$classes = $isUserLoggedIn ? 'logged-in' : 'logged-out';
		if ( ! $isUserLoggedIn && $displayLoginAsForm && '' !== $loginFormHtml ) {
			$classes .= ' has-login-form';
		}

		$envelope = [
			'isUserLoggedIn' => $isUserLoggedIn,
			'url'            => $this->coerceString( $url ),
			'label'          => $this->coerceString( $label ),
			'classes'        => $classes,
			'loginFormHtml'  => $this->coerceString( $loginFormHtml ),
		];

		if ( function_exists( 'applyFilters' ) ) {
			$filtered = applyFilters( 'ap.visual-editor.loginout.envelope', $envelope, $attributes );

			if ( is_array( $filtered ) ) {
				$envelope = array_merge( $envelope, $filtered );
			}
		}

		return $envelope;
	}

	/**
	 * Reads the current viewer's auth state from the configured guard.
	 *
	 * Defaults to the framework's default guard. Hosts on a different
	 * guard (e.g. `sanctum`, `api`) override via
	 * `config('artisanpack.visual-editor.loginout.guard')`.
	 *
	 * @since 1.0.0
	 */
	protected function isUserLoggedIn(): bool
	{
		try {
			$guard = $this->configString( 'guard' );

			if ( '' === $guard ) {
				return (bool) auth()->check();
			}

			return (bool) auth()->guard( $guard )->check();
		} catch ( Throwable ) {
			return false;
		}
	}

	/**
	 * Resolves the absolute URL of the current request — used as the
	 * `redirect_to` query parameter on the login / logout link when
	 * the block has `redirectToCurrent` set.
	 *
	 * @since 1.0.0
	 */
	protected function resolveCurrentUrl(): string
	{
		try {
			$request = app( Request::class );

			return $this->coerceString( $request->fullUrl() );
		} catch ( Throwable ) {
			return '';
		}
	}

	/**
	 * Resolves the login URL, honoring the config-defined route name
	 * and falling back to a literal path. Appends `redirect` /
	 * `redirect_to` query parameters when `$currentUrl` is non-empty.
	 *
	 * @since 1.0.0
	 */
	protected function resolveLoginUrl( string $currentUrl ): string
	{
		$base = $this->resolveRoutedUrl(
			$this->configString( 'login_route', 'login' ),
			$this->configString( 'login_path', '/login' )
		);

		return '' === $currentUrl
			? $base
			: $this->appendRedirectParam( $base, $currentUrl );
	}

	/**
	 * Resolves the logout URL. Mirrors {@see resolveLoginUrl} but
	 * against the logout route / path config keys.
	 *
	 * @since 1.0.0
	 */
	protected function resolveLogoutUrl( string $currentUrl ): string
	{
		$base = $this->resolveRoutedUrl(
			$this->configString( 'logout_route', 'logout' ),
			$this->configString( 'logout_path', '/logout' )
		);

		return '' === $currentUrl
			? $base
			: $this->appendRedirectParam( $base, $currentUrl );
	}

	/**
	 * Resolves the pre-rendered login-form HTML when present.
	 *
	 * The package ships no login form of its own; hosts return their
	 * preferred form (typically a Blade view) via the
	 * `ap.visual-editor.loginout.login-form` filter. The empty string
	 * disables the form display and the partial falls back to the
	 * link variant.
	 *
	 * @since 1.0.0
	 */
	protected function resolveLoginFormHtml( string $currentUrl ): string
	{
		if ( ! function_exists( 'applyFilters' ) ) {
			return '';
		}

		$html = applyFilters( 'ap.visual-editor.loginout.login-form', '', $currentUrl );

		return $this->coerceString( $html );
	}

	/**
	 * Appends a `redirect_to` query parameter to a URL without
	 * clobbering any existing query string.
	 *
	 * @since 1.0.0
	 */
	protected function appendRedirectParam( string $url, string $redirectTo ): string
	{
		if ( '' === $url || '' === $redirectTo ) {
			return $url;
		}

		$paramName = $this->configString( 'redirect_param', 'redirect_to' );
		$separator = str_contains( $url, '?' ) ? '&' : '?';

		return $url . $separator . $paramName . '=' . rawurlencode( $redirectTo );
	}

	/**
	 * Resolves a configured route name to an absolute URL when it is
	 * registered; otherwise falls back to the literal path.
	 *
	 * @since 1.0.0
	 */
	protected function resolveRoutedUrl( string $routeName, string $fallbackPath ): string
	{
		if ( '' !== $routeName ) {
			try {
				$router = app( 'router' );

				if ( is_object( $router ) && method_exists( $router, 'has' ) && $router->has( $routeName ) ) {
					$url = route( $routeName );

					if ( is_string( $url ) && '' !== $url ) {
						return $url;
					}
				}
			} catch ( Throwable ) {
				// fall through to the literal-path fallback below.
			}
		}

		if ( '' === $fallbackPath ) {
			return '';
		}

		try {
			$url = url( $fallbackPath );

			return is_string( $url ) ? $url : $fallbackPath;
		} catch ( Throwable ) {
			return $fallbackPath;
		}
	}

	/**
	 * Reads a string-shaped config entry under
	 * `artisanpack.visual-editor.loginout.*`, returning the default
	 * when the entry is missing or non-string.
	 *
	 * @since 1.0.0
	 */
	protected function configString( string $key, string $default = '' ): string
	{
		try {
			$value = config( 'artisanpack.visual-editor.loginout.' . $key, $default );
		} catch ( Throwable ) {
			return $default;
		}

		return $this->coerceString( $value );
	}

	/**
	 * Coerce arbitrary scalar values to a string, preserving the empty
	 * string for null / non-scalar input.
	 *
	 * @since 1.0.0
	 */
	protected function coerceString( mixed $value ): string
	{
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
