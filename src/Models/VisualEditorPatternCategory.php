<?php

/**
 * VisualEditorPatternCategory model.
 *
 * Lookup model for the many-to-many pattern categories relationship. A
 * category is identified by its `slug` in the REST payload (the shape
 * the B2 fixtures ship — `categories: ["featured"]`) and carries an
 * optional human-readable `name` the site editor and inserter surfaces.
 *
 * Categories are auto-created on first use from the pattern store /
 * update requests, matching WordPress's UX and the B2 fixture shape.
 * Hosts that want a DBA-style lock-down can override the behavior in
 * their own service provider.
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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VisualEditorPatternCategory extends Model
{
	protected $table = 'visual_editor_pattern_categories';

	/**
	 * @var array<int, string>
	 */
	protected $fillable = [
		'slug',
		'name',
	];

	/**
	 * The patterns associated with this category.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsToMany<VisualEditorPattern>
	 */
	public function patterns(): BelongsToMany
	{
		return $this->belongsToMany(
			VisualEditorPattern::class,
			'visual_editor_pattern_category',
			'pattern_category_id',
			'pattern_id'
		);
	}
}
