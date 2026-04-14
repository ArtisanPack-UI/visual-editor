<?php

/**
 * VisualEditorPost model.
 *
 * Represents a single block-tree document stored in ve_contents.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Model;

class VisualEditorPost extends Model
{
	protected $table = 've_contents';

	protected $guarded = [];

	/**
	 * @inheritDoc
	 */
	protected function casts(): array
	{
		return [
			'blocks' => 'array',
		];
	}
}
