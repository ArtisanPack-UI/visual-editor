<?php

/**
 * Template Part model.
 *
 * Represents a reusable template section like a header, footer,
 * or sidebar with blocks, styles, and variations.
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

/**
 * Template Part model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property string                          $slug
 * @property string                          $name
 * @property string                          $area
 * @property array                           $blocks
 * @property array|null                      $styles
 * @property string|null                     $variation
 * @property bool                            $is_custom
 * @property bool                            $is_active
 * @property array|null                      $lock
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TemplatePart extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_template_parts';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'slug',
		'name',
		'area',
		'blocks',
		'styles',
		'variation',
		'is_custom',
		'is_active',
		'lock',
	];

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include active template parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<TemplatePart> $query The query builder instance.
	 *
	 * @return Builder<TemplatePart>
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'is_active', true );
	}

	/**
	 * Scopes a query to template parts of a specific area.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<TemplatePart> $query The query builder instance.
	 * @param string                $area  The area name.
	 *
	 * @return Builder<TemplatePart>
	 */
	public function scopeForArea( Builder $query, string $area ): Builder
	{
		return $query->where( 'area', $area );
	}

	// =========================================
	// Methods
	// =========================================

	/**
	 * Gets the route key for the model.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getRouteKeyName(): string
	{
		return 'slug';
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
			'lock'      => 'array',
			'is_custom' => 'boolean',
			'is_active' => 'boolean',
		];
	}
}
