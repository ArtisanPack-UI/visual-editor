<?php

/**
 * Test user whose permission backend throws for an unknown permission.
 *
 * Mirrors Spatie Permission's `hasPermissionTo()`, which throws
 * `PermissionDoesNotExist` when the permission was never seeded. Set
 * `$withCheckPermissionTo` to also expose Spatie's non-throwing
 * `checkPermissionTo()` variant.
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

use RuntimeException;
use Tests\TestUser;

class ThrowingPermissionUser extends TestUser
{
	protected $table = 'users';

	/**
	 * Throws like Spatie's `hasPermissionTo()` for an unseeded permission.
	 *
	 * @since 1.15.0
	 *
	 * @throws RuntimeException Always.
	 */
	public function hasPermissionTo( string $permission ): bool
	{
		throw new RuntimeException( "There is no permission named `{$permission}`." );
	}
}
