<?php

/**
 * Font Library authorization policy.
 *
 * Gates the Font Library's mutating actions behind a single, dedicated
 * capability so a host can grant font management on its own — independently of
 * whoever the bound {@see \ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate}
 * lets into the rest of the site editor.
 *
 * Every mutation (install, upload, uninstall, bulk uninstall) resolves to
 * {@see manage()}, which reads the capability name from
 * `artisanpack.visual-editor.fonts.capability` and checks it against the
 * authenticated user's `hasCapability()` method. The `hasCapability()` call is
 * guarded so the globally registered policy degrades to a plain denial on host
 * user models that do not compose an RBAC trait, rather than fataling with
 * "Call to undefined method" — mirroring cms-framework's own capability
 * policies.
 *
 * Browsing is intentionally ungated: {@see viewAny()} always allows, so a user
 * without the capability can still open the Font Library modal read-only.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class FontPolicy
{
	use HandlesAuthorization;

	/**
	 * The capability name assumed when `fonts.capability` is unset.
	 */
	public const DEFAULT_CAPABILITY = 'manage_fonts';

	/**
	 * Anyone may browse the installed library and provider catalog. The
	 * read-only signal on the read endpoints, not this gate, tells the modal
	 * whether to disable its mutating controls.
	 *
	 * @since 1.7.0
	 */
	public function viewAny( ?Authenticatable $user ): bool
	{
		return true;
	}

	/**
	 * Whether the user may perform any mutating Font Library action — install,
	 * upload, uninstall, or bulk uninstall.
	 *
	 * @since 1.7.0
	 */
	public function manage( ?Authenticatable $user ): bool
	{
		if ( null === $user ) {
			return false;
		}

		$capability = (string) config(
			'artisanpack.visual-editor.fonts.capability',
			self::DEFAULT_CAPABILITY
		);

		if ( '' === $capability ) {
			return false;
		}

		return method_exists( $user, 'hasCapability' ) && (bool) $user->hasCapability( $capability );
	}
}
