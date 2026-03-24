<?php

/**
 * Template Site Editor Policy.
 *
 * Provides authorization checks for template management
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

use ArtisanPackUI\VisualEditor\Models\Template;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for template operations in the site editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Policies
 *
 * @since      1.0.0
 */
class TemplateSiteEditorPolicy
{
	/**
	 * Determine whether the user can view any templates.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function viewAny( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.template.viewAny', 'visual-editor.manage-templates' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can create templates.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 *
	 * @return bool
	 */
	public function create( Authenticatable $user ): bool
	{
		$permission = veApplyFilters( 've.template.create', 'visual-editor.manage-templates' );

		return $user->can( $permission );
	}

	/**
	 * Determine whether the user can update the template.
	 *
	 * Locked templates require the lock-content permission.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user     The authenticated user.
	 * @param Template        $template The template to update.
	 *
	 * @return bool
	 */
	public function update( Authenticatable $user, Template $template ): bool
	{
		$permission = veApplyFilters( 've.template.update', 'visual-editor.manage-templates' );

		if ( ! $user->can( $permission ) ) {
			return false;
		}

		if ( $template->is_locked ) {
			return $user->can( 'visual-editor.lock-content' );
		}

		return true;
	}

	/**
	 * Determine whether the user can delete the template.
	 *
	 * Locked templates cannot be deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user     The authenticated user.
	 * @param Template        $template The template to delete.
	 *
	 * @return bool
	 */
	public function delete( Authenticatable $user, Template $template ): bool
	{
		if ( $template->is_locked ) {
			return false;
		}

		$permission = veApplyFilters( 've.template.delete', 'visual-editor.manage-templates' );

		return $user->can( $permission );
	}
}
