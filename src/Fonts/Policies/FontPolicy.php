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
 * `artisanpack.visual-editor.fonts.capability` and checks it against whichever
 * RBAC contract the host user model exposes. cms-framework's own users compose
 * the rbac `HasPermissions` trait, whose public surface is `hasPermissionTo()`
 * (with the `hasPermission()` alias) — they never define `hasCapability()`, so
 * a policy that probed `hasCapability()` alone left every user permanently
 * unable to manage fonts on a stock cms-framework host. {@see manage()}
 * therefore probes the WordPress-style `hasCapability()` and the rbac
 * `hasPermissionTo()`/`hasPermission()` methods in turn, each guarded with
 * `method_exists()` so the globally registered policy degrades to a plain
 * denial on host user models that compose none of them, rather than fataling
 * with "Call to undefined method".
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

		return $this->userHasCapability( $user, $capability );
	}

	/**
	 * Resolve the capability against whichever RBAC contract the host user
	 * model exposes.
	 *
	 * The candidate methods are probed in priority order — the WordPress-style
	 * `hasCapability()` first, then the rbac `hasPermissionTo()` and its
	 * `hasPermission()` alias, which is what cms-framework's users actually
	 * expose. Each candidate is guarded with `method_exists()` first, and the
	 * first one the model defines decides the outcome, so a host model that
	 * composes none of them degrades to a plain denial instead of fataling with
	 * "Call to undefined method".
	 *
	 * @since 1.7.0
	 */
	protected function userHasCapability( Authenticatable $user, string $capability ): bool
	{
		foreach ( [ 'hasCapability', 'hasPermissionTo', 'hasPermission' ] as $method ) {
			if ( method_exists( $user, $method ) ) {
				return (bool) $user->{$method}( $capability );
			}
		}

		return false;
	}
}
