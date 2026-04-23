<?php

/**
 * VisualEditorNavigation policy.
 *
 * Authorization policy for the `wp_navigation` REST endpoints. The V1
 * default allows any authenticated user to read and mutate navigation
 * records, mirroring how the existing `VisualEditorTemplatePolicy`
 * handles its entity; host apps that need tighter access should swap
 * the binding in their own service provider.
 *
 * Navigations have no "owner" — they're site-level records, not
 * per-user content — so the `restrict_by_owner` config flag that
 * governs posts doesn't apply here.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class VisualEditorNavigationPolicy
{
	use HandlesAuthorization;

	public function viewAny( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function view( ?Authenticatable $user, VisualEditorNavigation $navigation ): bool
	{
		return null !== $user;
	}

	public function create( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, VisualEditorNavigation $navigation ): bool
	{
		return null !== $user;
	}

	public function delete( ?Authenticatable $user, VisualEditorNavigation $navigation ): bool
	{
		return null !== $user;
	}
}
