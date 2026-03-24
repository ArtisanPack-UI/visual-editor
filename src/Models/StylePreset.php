<?php

/**
 * Style Preset Model.
 *
 * Persists named style presets in the local library, enabling users
 * to save exported configurations and quickly switch between them.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Style Preset model for persisting named style configurations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int              $id
 * @property string           $name
 * @property string           $slug
 * @property string|null      $description
 * @property array|null       $palette
 * @property array|null       $typography
 * @property array|null       $spacing
 * @property int|null         $user_id
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @since      1.0.0
 */
class StylePreset extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 'visual_editor_style_presets';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'name',
		'slug',
		'description',
		'palette',
		'typography',
		'spacing',
		'user_id',
	];

	/**
	 * Get the user that created the preset.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo
	 */
	public function user(): BelongsTo
	{
		$userModel = config( 'artisanpack.visual-editor.user_model', 'App\\Models\\User' );

		return $this->belongsTo( $userModel );
	}

	/**
	 * Scope a query to find a preset by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 * @param string  $slug  The preset slug.
	 *
	 * @return Builder
	 */
	public function scopeBySlug( Builder $query, string $slug ): Builder
	{
		return $query->where( 'slug', $slug );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'palette'    => 'array',
			'typography' => 'array',
			'spacing'    => 'array',
			'user_id'    => 'integer',
		];
	}
}
