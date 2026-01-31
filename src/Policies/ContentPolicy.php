<?php

/**
 * Content Policy.
 *
 * Authorizes user actions on Content models such as
 * update, publish, unpublish, and schedule.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Policies
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Policies;

use ArtisanPackUI\VisualEditor\Models\Content;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Content policy class.
 *
 * Provides authorization logic for content operations.
 * By default, the content author is authorized for all actions.
 * Applications can extend or override this policy as needed.
 *
 * @since 1.0.0
 */
class ContentPolicy
{
	use HandlesAuthorization;

	/**
	 * Determines whether the user can update the content.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user    The authenticated user.
	 * @param Content         $content The content being updated.
	 *
	 * @return bool
	 */
	public function update( Authenticatable $user, Content $content ): bool
	{
		return $user->getAuthIdentifier() === $content->author_id;
	}

	/**
	 * Determines whether the user can publish the content.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user    The authenticated user.
	 * @param Content         $content The content being published.
	 *
	 * @return bool
	 */
	public function publish( Authenticatable $user, Content $content ): bool
	{
		return $user->getAuthIdentifier() === $content->author_id;
	}

	/**
	 * Determines whether the user can unpublish the content.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user    The authenticated user.
	 * @param Content         $content The content being unpublished.
	 *
	 * @return bool
	 */
	public function unpublish( Authenticatable $user, Content $content ): bool
	{
		return $user->getAuthIdentifier() === $content->author_id;
	}

	/**
	 * Determines whether the user can schedule the content.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user    The authenticated user.
	 * @param Content         $content The content being scheduled.
	 *
	 * @return bool
	 */
	public function schedule( Authenticatable $user, Content $content ): bool
	{
		return $user->getAuthIdentifier() === $content->author_id;
	}
}
