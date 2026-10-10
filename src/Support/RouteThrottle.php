<?php

/**
 * Throttle middleware strings with per-route rate-limit buckets.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

/**
 * Builds `throttle:max,decay,prefix` middleware strings for the package's
 * API routes.
 *
 * Laravel's plain `throttle:max,decay` keys its limiter on the user alone
 * (the default prefix is empty), so every route throttled that way shares
 * one counter per user and checks it against its own limit — browsing
 * pattern previews could then 429 the user's next AI call or font
 * install. Each route group passes its own prefix here so its counter is
 * separate.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.13.0
 */
class RouteThrottle
{
	/**
	 * Build a throttle middleware string from a configured value.
	 *
	 * - A `"max,decay"` or `"max"` string gets `$prefix` appended (a bare
	 *   max uses a one-minute decay).
	 * - A value that already carries a third (prefix) argument is used
	 *   as-is.
	 * - A non-numeric string is treated as a named rate limiter
	 *   (`RateLimiter::for()`) and passed through, since named limiters
	 *   build their own keys.
	 * - `false` disables throttling: null is returned and the caller skips
	 *   the middleware.
	 * - Anything else (null, '', a non-string) falls back to `$default`.
	 *
	 * @since 1.13.0
	 *
	 * @param  mixed   $configured  The configured `throttle` arguments.
	 * @param  string  $default     Fallback `"max,decay"` arguments.
	 * @param  string  $prefix      Rate-limit bucket prefix for this route.
	 *
	 * @return string|null The middleware string, or null for no throttle.
	 */
	public static function middleware( mixed $configured, string $default, string $prefix ): ?string
	{
		if ( false === $configured ) {
			return null;
		}

		$arguments = is_string( $configured ) || is_int( $configured ) ? trim( (string) $configured ) : '';

		if ( '' === $arguments ) {
			$arguments = $default;
		}

		$parts = array_map( 'trim', explode( ',', $arguments ) );

		if ( ! is_numeric( $parts[0] ) ) {
			return 'throttle:' . $arguments;
		}

		if ( count( $parts ) >= 3 ) {
			return 'throttle:' . implode( ',', $parts );
		}

		$decay = $parts[1] ?? '';

		return 'throttle:' . $parts[0] . ',' . ( '' !== $decay ? $decay : '1' ) . ',' . $prefix;
	}

	/**
	 * Middleware list for a route: the throttle when one applies,
	 * otherwise empty.
	 *
	 * @since 1.13.0
	 *
	 * @param  mixed   $configured  The configured `throttle` arguments.
	 * @param  string  $default     Fallback `"max,decay"` arguments.
	 * @param  string  $prefix      Rate-limit bucket prefix for this route.
	 *
	 * @return array<int, string>
	 */
	public static function middlewareList( mixed $configured, string $default, string $prefix ): array
	{
		$middleware = self::middleware( $configured, $default, $prefix );

		return null === $middleware ? [] : [ $middleware ];
	}
}
