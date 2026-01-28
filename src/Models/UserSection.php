<?php

/**
 * User Section model.
 *
 * Represents a user-created reusable section template
 * with blocks, styles, and sharing capabilities.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User Section model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property string                          $name
 * @property string|null                     $description
 * @property string|null                     $category
 * @property array                           $blocks
 * @property array|null                      $styles
 * @property string|null                     $preview_image
 * @property bool                            $is_shared
 * @property int                             $use_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UserSection extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_user_sections';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'user_id',
		'name',
		'description',
		'category',
		'blocks',
		'styles',
		'preview_image',
		'is_shared',
		'use_count',
	];

	// =========================================
	// Relationships
	// =========================================

	/**
	 * Gets the user who created this section.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo<Model, $this>
	 */
	public function user(): BelongsTo
	{
		$userModel = config( 'auth.providers.users.model', 'App\\Models\\User' );

		return $this->belongsTo( $userModel, 'user_id' );
	}

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include shared sections.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<UserSection> $query The query builder instance.
	 *
	 * @return Builder<UserSection>
	 */
	public function scopeShared( Builder $query ): Builder
	{
		return $query->where( 'is_shared', true );
	}

	/**
	 * Scopes a query to sections of a specific category.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<UserSection> $query    The query builder instance.
	 * @param string               $category The category name.
	 *
	 * @return Builder<UserSection>
	 */
	public function scopeInCategory( Builder $query, string $category ): Builder
	{
		return $query->where( 'category', $category );
	}

	/**
	 * Gets the attributes that should be cast.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'blocks'    => 'array',
			'styles'    => 'array',
			'is_shared' => 'boolean',
		];
	}
}
