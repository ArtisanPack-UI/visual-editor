<?php

/**
 * Pattern Model.
 *
 * Represents a reusable block pattern that can be saved and inserted
 * into the visual editor.
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
 * Pattern model for storing reusable block patterns.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property array       $blocks
 * @property string|null $category
 * @property int|null    $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @since      1.0.0
 */
class Pattern extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 'visual_editor_patterns';

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
		'blocks',
		'category',
		'user_id',
	];

	/**
	 * Scope a query to patterns in a specific category.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder     $query    The query builder instance.
	 * @param string|null $category The category to filter by.
	 *
	 * @return Builder
	 */
	public function scopeByCategory( Builder $query, ?string $category ): Builder
	{
		return $query->where( 'category', $category );
	}

	/**
	 * Scope a query to patterns created by a specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder  $query  The query builder instance.
	 * @param int|null $userId The user ID to filter by.
	 *
	 * @return Builder
	 */
	public function scopeByUser( Builder $query, ?int $userId ): Builder
	{
		return $query->where( 'user_id', $userId );
	}

	/**
	 * Get the user that created the pattern.
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
	 * Get the attributes that should be cast.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'blocks'  => 'array',
			'user_id' => 'integer',
		];
	}
}
