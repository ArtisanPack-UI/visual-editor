<?php

/**
 * Test stub: policy that denies all revision actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Stubs;

use ArtisanPackUI\VisualEditor\Models\Revision;

class DenyRevisionPolicy
{
	public function restore( $user, Revision $revision ): bool
	{
		return false;
	}

	public function delete( $user, Revision $revision ): bool
	{
		return false;
	}
}
