<?php

/**
 * Test user that mirrors cms-framework's rbac capability contract.
 *
 * cms-framework's user model composes the rbac `HasPermissions` trait, whose
 * public surface is `hasPermissionTo()` (with the `hasPermission()` alias) and
 * which never defines `hasCapability()`. This subclass stands in for such a
 * host so the {@see \ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy} tests
 * can exercise the rbac-granted and rbac-denied paths without pulling in the
 * full rbac package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace Tests\Support;

use Tests\TestUser;

class FontPermissionUser extends TestUser
{
	protected $table = 'users';

	/**
	 * The permissions this user has been granted.
	 *
	 * @var array<int, string>
	 */
	public array $grantedPermissions = [];

	/**
	 * Whether this user holds the given permission — the rbac primary API.
	 *
	 * @since 1.7.0
	 */
	public function hasPermissionTo( string $permission ): bool
	{
		return in_array( $permission, $this->grantedPermissions, true );
	}

	/**
	 * Backwards-compatible alias, matching the rbac trait's own surface.
	 *
	 * @since 1.7.0
	 */
	public function hasPermission( string $permission ): bool
	{
		return $this->hasPermissionTo( $permission );
	}
}
