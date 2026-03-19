<?php

/**
 * Test stub: fake document model for fallback authorization tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

class FakeDocument extends Model
{
	protected $table = 'fake_documents';

	protected $guarded = [];
}
