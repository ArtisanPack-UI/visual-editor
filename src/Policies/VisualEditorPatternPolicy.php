<?php

/**
 * VisualEditorPattern policy.
 *
 * Authorization policy for the `wp_block` REST endpoints. The V1 default
 * allows any authenticated user to read and mutate patterns, mirroring
 * how the existing {@see VisualEditorTemplatePolicy} handles the
 * site-level editor entities; host apps that need tighter access should
 * swap the binding in their own service provider.
 *
 * Patterns have no "owner" — they're site-level content the editor and
 * inserter surface to every author — so the `restrict_by_owner` config
 * flag that governs posts doesn't apply here.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class VisualEditorPatternPolicy
{
	use HandlesAuthorization;

	public function viewAny( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function view( ?Authenticatable $user, VisualEditorPattern $pattern ): bool
	{
		return null !== $user;
	}

	public function create( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, VisualEditorPattern $pattern ): bool
	{
		return null !== $user;
	}

	public function delete( ?Authenticatable $user, VisualEditorPattern $pattern ): bool
	{
		return null !== $user;
	}
}
