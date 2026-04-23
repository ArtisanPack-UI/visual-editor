<?php

/**
 * VisualEditorGlobalStyles policy.
 *
 * Authorization policy for the `globalStyles` REST endpoints. The V1
 * default allows any authenticated user to read the singleton and
 * write updates to it, mirroring how {@see VisualEditorTemplatePolicy}
 * and {@see VisualEditorTemplatePartPolicy} gate the sibling entities;
 * host apps that need tighter access (for example: restrict writes to
 * site admins) should swap the binding in their own service provider.
 *
 * `globalStyles` is a singleton — there is no `create` or `delete`
 * surface to gate. `viewAny` covers the instance-less endpoints
 * (`lookup`, `base`); `view` covers `show`; `update` covers `PUT`.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class VisualEditorGlobalStylesPolicy
{
	use HandlesAuthorization;

	public function viewAny( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function view( ?Authenticatable $user, VisualEditorGlobalStyles $globalStyle ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, VisualEditorGlobalStyles $globalStyle ): bool
	{
		return null !== $user;
	}
}
