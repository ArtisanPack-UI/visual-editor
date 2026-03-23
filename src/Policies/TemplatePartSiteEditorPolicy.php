<?php

/**
 * Template Part Site Editor Policy.
 *
 * Provides authorization checks for template part management
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

use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for template part operations in the site editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Policies
 *
 * @since      1.0.0
 */
class TemplatePartSiteEditorPolicy
{
	/**
	 * Determine whether the user can view any template parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function viewAny( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.template-part.viewAny', 'visual-editor.manage-parts' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can create template parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function create( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.template-part.create', 'visual-editor.manage-parts' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can update the template part.
	 *
	 * Locked template parts require the lock-content permission.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user         The authenticated user.
	 * @param TemplatePart    $templatePart The template part to update.
	 *
	 * @return bool
	 */
	public function update( Authenticatable $user, TemplatePart $templatePart ): bool
	{
		if ( $templatePart->is_locked ) {
			return $user->can( 'visual-editor.lock-content' );
		}

		$permission = veApplyFilters( 've.template-part.update', 'visual-editor.manage-parts' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can delete the template part.
	 *
	 * Locked template parts cannot be deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user         The authenticated user.
	 * @param TemplatePart    $templatePart The template part to delete.
	 *
	 * @return bool
	 */
	public function delete( Authenticatable $user, TemplatePart $templatePart ): bool
	{
		if ( $templatePart->is_locked ) {
			return false;
		}

		$permission = veApplyFilters( 've.template-part.delete', 'visual-editor.manage-parts' );

		return $user->can( $permission );
	}
}
