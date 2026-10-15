<?php

/**
 * Test user exposing Spatie Permission's non-throwing `checkPermissionTo()`.
 *
 * `hasPermissionTo()` throws as it does in Spatie for an unseeded
 * permission, so a test passes only if `checkPermissionTo()` is preferred.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace Tests\Support;

class CheckPermissionUser extends ThrowingPermissionUser
{
	/**
	 * The permissions this user has been granted.
	 *
	 * @since 1.15.0
	 *
	 * @var array<int, string>
	 */
	public array $grantedPermissions = [];

	/**
	 * Spatie's non-throwing permission check.
	 *
	 * @since 1.15.0
	 */
	public function checkPermissionTo( string $permission ): bool
	{
		return in_array( $permission, $this->grantedPermissions, true );
	}
}
