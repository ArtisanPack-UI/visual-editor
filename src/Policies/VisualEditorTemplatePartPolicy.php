<?php

/**
 * VisualEditorTemplatePart policy.
 *
 * Authorization policy for the `wp_template_part` REST endpoints. The
 * V1 default allows any authenticated user to read and mutate template
 * parts, mirroring how {@see VisualEditorTemplatePolicy} handles the
 * sibling `wp_template` entity; host apps that need tighter access
 * should swap the binding in their own service provider.
 *
 * Template parts have no "owner" — they're theme or site-level records,
 * not per-user content — so the `restrict_by_owner` config flag that
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class VisualEditorTemplatePartPolicy
{
	use HandlesAuthorization;

	public function viewAny( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function view( ?Authenticatable $user, VisualEditorTemplatePart $templatePart ): bool
	{
		return null !== $user;
	}

	public function create( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, VisualEditorTemplatePart $templatePart ): bool
	{
		return null !== $user;
	}

	public function delete( ?Authenticatable $user, VisualEditorTemplatePart $templatePart ): bool
	{
		return null !== $user;
	}
}
