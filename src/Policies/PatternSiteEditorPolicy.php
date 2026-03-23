<?php

/**
 * Pattern Site Editor Policy.
 *
 * Provides authorization checks for pattern management
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

use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for pattern operations in the site editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Policies
 *
 * @since      1.0.0
 */
class PatternSiteEditorPolicy
{
	/**
	 * Determine whether the user can view any patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function viewAny( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.pattern.viewAny', 'visual-editor.manage-patterns' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can create patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function create( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.pattern.create', 'visual-editor.manage-patterns' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can update the pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user    The authenticated user.
	 * @param Pattern         $pattern The pattern to update.
	 *
	 * @return bool
	 */
	public function update( Authenticatable $user, Pattern $pattern ): bool
	{
		$permission = veApplyFilters( 've.pattern.update', 'visual-editor.manage-patterns' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can delete the pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user    The authenticated user.
	 * @param Pattern         $pattern The pattern to delete.
	 *
	 * @return bool
	 */
	public function delete( Authenticatable $user, Pattern $pattern ): bool
	{
		$permission = veApplyFilters( 've.pattern.delete', 'visual-editor.manage-patterns' );

		return $user->can( $permission );
	}
}
