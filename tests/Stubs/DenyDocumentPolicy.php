<?php

/**
 * Test stub: policy that denies update on the fake document model.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Stubs;

class DenyDocumentPolicy
{
	public function update( $user, FakeDocument $document ): bool
	{
		return false;
	}
}
