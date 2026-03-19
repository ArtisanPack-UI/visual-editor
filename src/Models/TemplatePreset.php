<?php

/**
 * Template Preset Model.
 *
 * Represents a reusable starter template that users can select
 * when creating a new template, providing pre-configured content,
 * settings, and template part mappings.
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
 * Template preset model for storing reusable starter templates.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int              $id
 * @property string           $name
 * @property string           $slug
 * @property string|null      $description
 * @property string|null      $category
 * @property string|null      $thumbnail
 * @property array            $content
 * @property array|null       $content_area_settings
 * @property array|null       $styles
 * @property array|null       $template_parts
 * @property string           $type
 * @property string|null      $for_content_type
 * @property bool             $is_custom
 * @property int|null         $user_id
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @since      1.0.0
 */
class TemplatePreset extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 'visual_editor_template_presets';

	/**
	 * The model's default values for attributes.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	protected $attributes = [
		'type'      => 'page',
		'is_custom' => false,
	];

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
		'category',
		'thumbnail',
		'content',
		'content_area_settings',
		'styles',
		'template_parts',
		'type',
		'for_content_type',
	];

	/**
	 * Scope a query to presets in a specific category.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query    The query builder instance.
	 * @param string  $category The category to filter by.
	 *
	 * @return Builder
	 */
	public function scopeByCategory( Builder $query, string $category ): Builder
	{
		return $query->where( 'category', $category );
	}

	/**
	 * Scope a query to presets of a specific type.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 * @param string  $type  The template type to filter by.
	 *
	 * @return Builder
	 */
	public function scopeByType( Builder $query, string $type ): Builder
	{
		return $query->where( 'type', $type );
	}

	/**
	 * Scope a query to presets for a specific content type.
	 *
	 * Includes presets with no content type restriction (null)
	 * as well as presets explicitly targeting the given type.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query       The query builder instance.
	 * @param string  $contentType The content type to filter by.
	 *
	 * @return Builder
	 */
	public function scopeForContentType( Builder $query, string $contentType ): Builder
	{
		return $query->where( function ( Builder $q ) use ( $contentType ): void {
			$q->whereNull( 'for_content_type' )
				->orWhere( 'for_content_type', $contentType );
		} );
	}

	/**
	 * Scope a query to custom (user-created) presets.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeCustom( Builder $query ): Builder
	{
		return $query->where( 'is_custom', true );
	}

	/**
	 * Scope a query to built-in (non-custom) presets.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeBuiltIn( Builder $query ): Builder
	{
		return $query->where( 'is_custom', false );
	}

	/**
	 * Scope a query to presets created by a specific user.
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
	 * Get the attributes that should be cast.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'content'               => 'array',
			'content_area_settings' => 'array',
			'styles'                => 'array',
			'template_parts'        => 'array',
			'is_custom'             => 'boolean',
			'user_id'               => 'integer',
		];
	}
}
