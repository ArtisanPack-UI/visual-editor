<?php

/**
 * Test Post Model Stub.
 *
 * A minimal Eloquent model implementing EditorContent for testing
 * the HasVisualEditorContent trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Concerns\Stubs
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Unit\Concerns\Stubs;

use ArtisanPackUI\VisualEditor\Concerns\HasVisualEditorContent;
use ArtisanPackUI\VisualEditor\Contracts\EditorContent;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub model for testing HasVisualEditorContent.
 *
 * @since 1.0.0
 *
 * @property int         $id
 * @property array|null  $blocks
 * @property string|null $status
 * @property string|null $scheduled_at
 */
class TestPost extends Model implements EditorContent
{
	use HasVisualEditorContent;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'test_posts';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'blocks',
		'status',
		'scheduled_at',
	];
}
