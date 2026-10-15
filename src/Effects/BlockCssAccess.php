<?php

/**
 * Authorization for per-block Custom CSS.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Effects;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may write a block's Custom CSS (#881). The CSS is printed unescaped
 * on the public site, so access is denied by default: the user needs the
 * `artisanpack.visual-editor.effects.custom_css_capability` capability
 * (default `edit_block_css`) through an RBAC `hasCapability()` /
 * `hasPermissionTo()` / `hasPermission()` method. Hosts can redefine the
 * {@see self::ABILITY} gate instead.
 *
 * Without the ability the editor hides the field, and every save path
 * runs the incoming blocks through {@see CustomCssGuard}, which keeps only
 * the Custom CSS already stored on the record.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
class BlockCssAccess
{
	/**
	 * Gate ability guarding a block's Custom CSS.
	 *
	 * @since 1.15.0
	 */
	public const ABILITY = 'visual-editor.edit-block-css';

	/**
	 * Capability checked when the config doesn't name one.
	 *
	 * @since 1.15.0
	 */
	public const DEFAULT_CAPABILITY = 'edit_block_css';

	/**
	 * Default gate callback.
	 *
	 * @since 1.15.0
	 *
	 * @param  Authenticatable|null  $user  Current user.
	 */
	public static function allows( ?Authenticatable $user ): bool
	{
		if ( null === $user ) {
			return false;
		}

		$capability = (string) config( 'artisanpack.visual-editor.effects.custom_css_capability', self::DEFAULT_CAPABILITY );

		if ( '' === $capability ) {
			return false;
		}

		foreach ( [ 'hasCapability', 'hasPermissionTo', 'hasPermission' ] as $method ) {
			if ( method_exists( $user, $method ) ) {
				return (bool) $user->{$method}( $capability );
			}
		}

		return false;
	}
}
