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

/**
 * Who may call the content-authoring API endpoints that sit outside any
 * model policy, such as the icon picker and the custom SVG sanitizer
 * (#834). Unlike `EnsureSiteEditorAccess`, this is a post-editor-level
 * check, so authors who can't reach the site editor still pass.
 *
 * By default any authenticated user is allowed. When the
 * `artisanpack.visual-editor.content_access.capability` config names a
 * capability, the user needs it through an RBAC `hasCapability()` /
 * `hasPermissionTo()` / `hasPermission()` method. Hosts can redefine
 * the {@see self::ABILITY} gate instead.
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

		$capability = (string) config( 'artisanpack.visual-editor.content_access.capability', '' );

		if ( '' === $capability ) {
			return true;
		}

		foreach ( [ 'hasCapability', 'hasPermissionTo', 'hasPermission' ] as $method ) {
			if ( method_exists( $user, $method ) ) {
				return (bool) $user->{$method}( $capability );
			}
		}

		return false;
	}
}
