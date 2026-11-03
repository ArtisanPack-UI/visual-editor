<?php

/**
 * Authorization for saving unfiltered block markup.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may save block markup without filtering (#1062). Block content is
 * printed unescaped on the public site, so access is denied by default:
 * the user needs the
 * `artisanpack.visual-editor.content_filter.unfiltered_html_capability`
 * capability (default `unfiltered_html`) through an RBAC method (see
 * {@see CapabilityCheck}). Hosts can redefine the {@see self::ABILITY} gate
 * instead.
 *
 * Without the ability every save path runs the incoming blocks through
 * {@see BlockHtmlGuard}, and the editor leaves Custom HTML out of the
 * inserter.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class UnfilteredHtmlAccess
{
	/**
	 * Gate ability guarding unfiltered block markup.
	 *
	 * @since 1.20.0
	 */
	public const ABILITY = 'visual-editor.unfiltered-html';

	/**
	 * Capability checked when the config doesn't name one.
	 *
	 * @since 1.20.0
	 */
	public const DEFAULT_CAPABILITY = 'unfiltered_html';

	/**
	 * Default gate callback.
	 *
	 * @since 1.20.0
	 *
	 * @param  Authenticatable|null  $user  Current user.
	 *
	 * @return bool Whether the user may save unfiltered markup.
	 */
	public static function allows( ?Authenticatable $user ): bool
	{
		if ( null === $user ) {
			return false;
		}

		$capability = (string) config( 'artisanpack.visual-editor.content_filter.unfiltered_html_capability', self::DEFAULT_CAPABILITY );

		if ( '' === $capability ) {
			return false;
		}

		return true === CapabilityCheck::check( $user, $capability );
	}
}
