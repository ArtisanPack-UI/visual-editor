<?php

/**
 * Shared RBAC capability probe for the visual-editor gates.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Checks a capability against whichever RBAC method the host user model
 * exposes, without ever throwing.
 *
 * The methods are tried in {@see self::METHODS} order and the first one the
 * user defines decides. Spatie Permission's non-throwing
 * `checkPermissionTo()` comes first, because its `hasPermissionTo()` throws
 * `PermissionDoesNotExist` for a permission that was never seeded. Any
 * other throw from the backend is reported and treated as a denial, so a
 * misconfigured permission store fails closed instead of turning every
 * gated page into an HTTP 500.
 *
 * Used by {@see \ArtisanPackUI\VisualEditor\Effects\BlockCssAccess},
 * {@see ContentAccess}, {@see \ArtisanPackUI\VisualEditor\Ai\Support\AiAccess}
 * and {@see \ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
final class CapabilityCheck
{
	/**
	 * Capability methods probed on the user, in priority order.
	 *
	 * @since 1.15.0
	 *
	 * @var list<string>
	 */
	public const METHODS = [ 'checkPermissionTo', 'hasCapability', 'hasPermissionTo', 'hasPermission' ];

	/**
	 * Whether the user holds the capability.
	 *
	 * @since 1.15.0
	 *
	 * @param  Authenticatable  $user        The user to check.
	 * @param  string           $capability  The capability or permission name.
	 *
	 * @return bool|null The decision, or null when the user exposes none of
	 *                   {@see self::METHODS}.
	 */
	public static function check( Authenticatable $user, string $capability ): ?bool
	{
		foreach ( self::METHODS as $method ) {
			if ( ! method_exists( $user, $method ) ) {
				continue;
			}

			try {
				return (bool) $user->{$method}( $capability );
			} catch ( Throwable $e ) {
				report( $e );

				return false;
			}
		}

		return null;
	}
}
