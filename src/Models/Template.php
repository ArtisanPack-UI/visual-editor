<?php

/**
 * Template model.
 *
 * Represents a page template definition including template parts,
 * content area settings, and styles.
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
 * Template model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property string                          $slug
 * @property string                          $name
 * @property string|null                     $description
 * @property string                          $type
 * @property string|null                     $for_content_type
 * @property array                           $template_parts
 * @property array                           $content_area_settings
 * @property array|null                      $styles
 * @property bool                            $is_custom
 * @property bool                            $is_active
 * @property array|null                      $lock
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Template extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_templates';

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
		'description',
		'type',
		'for_content_type',
		'template_parts',
		'content_area_settings',
		'styles',
		'is_custom',
		'is_active',
		'lock',
	];

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include active templates.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Template> $query The query builder instance.
	 *
	 * @return Builder<Template>
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'is_active', true );
	}

	/**
	 * Scopes a query to only include custom templates.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Template> $query The query builder instance.
	 *
	 * @return Builder<Template>
	 */
	public function scopeCustom( Builder $query ): Builder
	{
		return $query->where( 'is_custom', true );
	}

	/**
	 * Scopes a query to templates of a specific type.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Template> $query The query builder instance.
	 * @param string            $type  The template type.
	 *
	 * @return Builder<Template>
	 */
	public function scopeOfType( Builder $query, string $type ): Builder
	{
		return $query->where( 'type', $type );
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
			'template_parts'        => 'array',
			'content_area_settings' => 'array',
			'styles'                => 'array',
			'lock'                  => 'array',
			'is_custom'             => 'boolean',
			'is_active'             => 'boolean',
		];
	}
}
