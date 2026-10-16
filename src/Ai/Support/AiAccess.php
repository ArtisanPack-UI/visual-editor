<?php

/**
 * Authorization for the visual-editor AI features.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.12.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Ai\Support;

use ArtisanPackUI\VisualEditor\Support\CapabilityCheck;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may run the AI features. Each call spends the site's AI
 * credentials, so access is denied by default: the user needs the
 * `artisanpack.visual-editor.ai.capability` capability (default
 * `use_ai_features`) through an RBAC `checkPermissionTo()` /
 * `hasCapability()` / `hasPermissionTo()` / `hasPermission()` method (see
 * {@see CapabilityCheck}; a backend that throws denies). Hosts can
 * redefine the {@see self::ABILITY} gate instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.12.1
 */
class AiAccess
{
	/**
	 * Gate ability guarding the `/ai/*` routes and the `AiTools`
	 * Livewire listeners.
	 *
	 * @since 1.12.1
	 */
	public const ABILITY = 'visual-editor.use-ai';

	/**
	 * Capability checked when the config doesn't name one.
	 *
	 * @since 1.12.1
	 */
	public const DEFAULT_CAPABILITY = 'use_ai_features';

	/**
	 * Default gate callback.
	 *
	 * @since 1.12.1
	 * @since 1.15.0 Prefers `checkPermissionTo()` and denies when the permission backend throws.
	 *
	 * @param  Authenticatable|null  $user  Current user.
	 */
	public static function allows( ?Authenticatable $user ): bool
	{
		if ( null === $user ) {
			return false;
		}

		$capability = (string) config( 'artisanpack.visual-editor.ai.capability', self::DEFAULT_CAPABILITY );

		if ( '' === $capability ) {
			return false;
		}

		return true === CapabilityCheck::check( $user, $capability );
	}
}
