<?php

/**
 * Test user that composes a minimal capability check.
 *
 * The Font Library's {@see \ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy}
 * gates its mutating actions on `$user->hasCapability( 'manage_fonts' )`,
 * guarded by `method_exists()` so host user models without an RBAC trait
 * degrade to a plain denial. The package's default {@see \Tests\TestUser} has no
 * such method (standing in for that host), so the capability tests act as this
 * subclass to exercise the granted path.
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

class FontCapabilityUser extends TestUser
{
	protected $table = 'users';

	/**
	 * The capabilities this user has been granted.
	 *
	 * @var array<int, string>
	 */
	public array $grantedCapabilities = [];

	/**
	 * Whether this user holds the given capability.
	 *
	 * @since 1.7.0
	 */
	public function hasCapability( string $capability ): bool
	{
		return in_array( $capability, $this->grantedCapabilities, true );
	}
}
