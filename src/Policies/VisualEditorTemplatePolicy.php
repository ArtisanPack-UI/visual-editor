<?php

/**
 * VisualEditorTemplate policy.
 *
 * Authorization policy for the `wp_template` REST endpoints. The V1
 * default allows any authenticated user to read and mutate templates,
 * mirroring how the existing `VisualEditorPostPolicy` handles the
 * post-editor entity; host apps that need tighter access should swap
 * the binding in their own service provider.
 *
 * Templates have no "owner" — they're theme or site-level records, not
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class VisualEditorTemplatePolicy
{
	use HandlesAuthorization;

	public function viewAny( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function view( ?Authenticatable $user, VisualEditorTemplate $template ): bool
	{
		return null !== $user;
	}

	public function create( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, VisualEditorTemplate $template ): bool
	{
		return null !== $user;
	}

	public function delete( ?Authenticatable $user, VisualEditorTemplate $template ): bool
	{
		return null !== $user;
	}
}
