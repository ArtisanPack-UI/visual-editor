<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

/**
 * Test fixture for the #833 bindings field-picker tests. Mirrors a host's
 * shared generic content model (e.g. Keystone's `DynamicContentEditorModel`)
 * that declares no table of its own — the real table is only set per record
 * — so the content type can't be inferred from the class's `getTable()`.
 */
class TestGenericContentModel extends Model
{
	use HasBlockContent;

	protected $guarded = [];
}
