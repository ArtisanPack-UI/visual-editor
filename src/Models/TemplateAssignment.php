<?php

/**
 * Template Assignment Model.
 *
 * Represents a default template assignment for a content type.
 * Each content type can have one default template assignment.
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
 * Template assignment model for mapping content types to default templates.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int            $id
 * @property string         $content_type
 * @property int            $template_id
 * @property int|null       $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @since      1.0.0
 */
class TemplateAssignment extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 'visual_editor_template_assignments';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'content_type',
		'template_id',
		'user_id',
	];

	/**
	 * Scope a query to assignments for a specific content type.
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
		return $query->where( 'content_type', $contentType );
	}

	/**
	 * Get the template assigned to this content type.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo
	 */
	public function template(): BelongsTo
	{
		return $this->belongsTo( Template::class );
	}

	/**
	 * Get the user who created this assignment.
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
			'template_id' => 'integer',
			'user_id'     => 'integer',
		];
	}
}
