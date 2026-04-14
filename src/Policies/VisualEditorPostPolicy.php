<?php

/**
 * VisualEditorPost policy.
 *
 * Authorization policy for block-tree post view/update operations.
 * Default behavior requires an authenticated user and, when ownership
 * restriction is enabled, matches the author id against the user id.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Policies;

use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class VisualEditorPostPolicy
{
	use HandlesAuthorization;

	/**
	 * Determines whether the user can view the post.
	 *
	 * @since 1.0.0
	 */
	public function view( ?Authenticatable $user, VisualEditorPost $post ): bool
	{
		if ( null === $user ) {
			return false;
		}

		if ( $this->isOwnershipRestricted() ) {
			return $this->isOwnerOrAdmin( $user, $post );
		}

		return true;
	}

	/**
	 * Determines whether the user can update the post.
	 *
	 * @since 1.0.0
	 */
	public function update( ?Authenticatable $user, VisualEditorPost $post ): bool
	{
		if ( null === $user ) {
			return false;
		}

		if ( $this->isOwnershipRestricted() ) {
			return $this->isOwnerOrAdmin( $user, $post );
		}

		return true;
	}

	/**
	 * Checks whether ownership-based access control is enabled.
	 *
	 * @since 1.0.0
	 */
	protected function isOwnershipRestricted(): bool
	{
		return (bool) config( 'artisanpack.visual-editor.authorization.restrict_by_owner', false );
	}

	/**
	 * Checks whether the user is the post owner.
	 *
	 * Posts without an author are accessible to all authenticated users so
	 * legacy or package-seeded content remains editable.
	 *
	 * @since 1.0.0
	 */
	protected function isOwnerOrAdmin( Authenticatable $user, VisualEditorPost $post ): bool
	{
		if ( null === $post->author_id ) {
			return true;
		}

		return (int) $post->author_id === (int) $user->getAuthIdentifier();
	}
}
