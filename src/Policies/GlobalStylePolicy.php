<?php

/**
 * Global Style Policy.
 *
 * Provides authorization checks for global style management
 * within the site editor. Uses applyFilters for extensibility.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Policies
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for global style operations in the site editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Policies
 *
 * @since      1.0.0
 */
class GlobalStylePolicy
{
	/**
	 * Determine whether the user can update global styles.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function update( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.global-style.update', 'visual-editor.manage-styles' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can reset global styles to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function reset( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.global-style.reset', 'visual-editor.manage-styles' );

		return $user->can( $permission );
	}
}
