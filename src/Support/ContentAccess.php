<?php

/**
 * Authorization for post-editor content authoring endpoints.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Who may call the content-authoring API endpoints that sit outside any
 * model policy, such as the icon picker and the custom SVG sanitizer
 * (#834). Unlike `EnsureSiteEditorAccess`, this is a post-editor-level
 * check, so authors who can't reach the site editor still pass.
 *
 * By default any authenticated user is allowed. When the
 * `artisanpack.visual-editor.content_access.capability` config names a
 * capability, the user needs it through an RBAC `hasCapability()` /
 * `hasPermissionTo()` / `hasPermission()` method. A user model with none
 * of those falls back to Laravel's `$user->can( $capability )` and a
 * warning is logged (once per request), since that usually means the
 * capability config doesn't match the host's authorization setup. A
 * non-string capability is ignored. Hosts can redefine the
 * {@see self::ABILITY} gate instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.13.0
 */
class ContentAccess
{
	/**
	 * Gate ability guarding the content-authoring endpoints.
	 *
	 * @since 1.13.0
	 */
	public const ABILITY = 'visual-editor.edit-content';

	/**
	 * Request attribute marking that the missing-RBAC warning was logged.
	 *
	 * @since 1.13.0
	 */
	protected const WARNED_ATTRIBUTE = 'visual-editor.content-access.rbac-warning-logged';

	/**
	 * Default gate callback.
	 *
	 * @since 1.13.0
	 *
	 * @param  Authenticatable|null  $user  Current user.
	 *
	 * @return bool Whether the user may author content.
	 */
	public static function allows( ?Authenticatable $user ): bool
	{
		if ( null === $user ) {
			return false;
		}

		$capability = config( 'artisanpack.visual-editor.content_access.capability', '' );
		$capability = is_string( $capability ) ? trim( $capability ) : '';

		if ( '' === $capability ) {
			return true;
		}

		foreach ( [ 'hasCapability', 'hasPermissionTo', 'hasPermission' ] as $method ) {
			if ( method_exists( $user, $method ) ) {
				return (bool) $user->{$method}( $capability );
			}
		}

		self::warnMissingRbac( $user, $capability );

		// Checking this gate's own ability through can() would recurse.
		if ( self::ABILITY === $capability || ! method_exists( $user, 'can' ) ) {
			return false;
		}

		return (bool) $user->can( $capability );
	}

	/**
	 * Log, once per request, that the configured capability can't be
	 * checked through an RBAC method on the user model.
	 *
	 * @since 1.13.0
	 *
	 * @param  Authenticatable  $user        Current user.
	 * @param  string           $capability  The configured capability.
	 */
	protected static function warnMissingRbac( Authenticatable $user, string $capability ): void
	{
		$request = app()->bound( 'request' ) ? request() : null;

		if ( null !== $request && true === $request->attributes->get( self::WARNED_ATTRIBUTE ) ) {
			return;
		}

		$request?->attributes->set( self::WARNED_ATTRIBUTE, true );

		Log::warning( 'visual-editor: content_access.capability is set, but the user model has no hasCapability(), hasPermissionTo() or hasPermission() method. Falling back to Gate checks via $user->can().', [
			'capability' => $capability,
			'user_class' => get_class( $user ),
		] );
	}
}
