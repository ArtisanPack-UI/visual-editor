<?php

/**
 * Template Model.
 *
 * Represents a page template that defines the overall layout
 * structure for content in the visual editor.
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template model for storing page layout templates.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int              $id
 * @property string           $name
 * @property string           $slug
 * @property string|null      $description
 * @property string           $type
 * @property string|null      $for_content_type
 * @property array            $content
 * @property string           $status
 * @property array|null       $content_area_settings
 * @property array|null       $styles
 * @property bool             $is_custom
 * @property bool             $is_locked
 * @property int|null         $parent_id
 * @property int|null         $user_id
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @since      1.0.0
 */
class Template extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 'visual_editor_templates';

	/**
	 * The model's default values for attributes.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	protected $attributes = [
		'type'      => 'page',
		'status'    => 'active',
		'is_custom' => false,
		'is_locked' => false,
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
		'type',
		'for_content_type',
		'content',
		'status',
		'content_area_settings',
		'styles',
		'is_custom',
		'is_locked',
		'parent_id',
		'user_id',
	];

	/**
	 * Scope a query to templates of a specific type.
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
	 * Scope a query to active templates.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'status', 'active' );
	}

	/**
	 * Scope a query to draft templates.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeDraft( Builder $query ): Builder
	{
		return $query->where( 'status', 'draft' );
	}

	/**
	 * Scope a query to templates for a specific content type.
	 *
	 * Includes templates with no content type restriction (null)
	 * as well as templates explicitly targeting the given type.
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
	 * Scope a query to custom (user-created) templates.
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
	 * Scope a query to built-in (non-custom) templates.
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
	 * Scope a query to templates created by a specific user.
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
	 * Get the user that created the template.
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
	 * Create a revision snapshot of this template.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $userId The ID of the user creating the revision.
	 *
	 * @return Revision
	 */
	public function createRevision( ?int $userId = null ): Revision
	{
		return Revision::create( [
			'document_type' => 'template',
			'document_id'   => $this->id,
			'blocks'        => $this->content,
			'user_id'       => $userId,
			'created_at'    => now(),
		] );
	}

	/**
	 * Get all revisions for this template.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection<int, Revision>
	 */
	public function revisions(): Collection
	{
		return Revision::forDocument( 'template', $this->id )
			->orderByDesc( 'created_at' )
			->get();
	}

	/**
	 * Duplicate this template with a new slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $newSlug The slug for the duplicated template.
	 * @param string|null $newName Optional new name; defaults to original name with " (Copy)" appended.
	 *
	 * @return static
	 */
	public function duplicate( string $newSlug, ?string $newName = null ): static
	{
		return static::create( [
			'name'                  => $newName ?? $this->name . ' (' . __( 'Copy' ) . ')',
			'slug'                  => $newSlug,
			'description'           => $this->description,
			'type'                  => $this->type,
			'for_content_type'      => $this->for_content_type,
			'content'               => $this->content,
			'status'                => 'draft',
			'content_area_settings' => $this->content_area_settings,
			'styles'                => $this->styles,
			'is_custom'             => true,
			'is_locked'             => false,
			'user_id'               => $this->user_id,
		] );
	}

	/**
	 * Scope a query to only base templates (not variations).
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeBaseTemplates( Builder $query ): Builder
	{
		return $query->whereNull( 'parent_id' );
	}

	/**
	 * Scope a query to variations of a specific template.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query    The query builder instance.
	 * @param int     $parentId The parent template ID.
	 *
	 * @return Builder
	 */
	public function scopeVariationsOf( Builder $query, int $parentId ): Builder
	{
		return $query->where( 'parent_id', $parentId );
	}

	/**
	 * Get the parent template this is a variation of.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo
	 */
	public function parent(): BelongsTo
	{
		return $this->belongsTo( static::class, 'parent_id' );
	}

	/**
	 * Get all variations of this template.
	 *
	 * @since 1.0.0
	 *
	 * @return HasMany
	 */
	public function variations(): HasMany
	{
		return $this->hasMany( static::class, 'parent_id' );
	}

	/**
	 * Check if this template is a variation of another template.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isVariation(): bool
	{
		return null !== $this->parent_id;
	}

	/**
	 * Create a variation of this template.
	 *
	 * The user_id is not inherited from the parent by default. Callers
	 * should pass user_id via $overrides to attribute ownership.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug     The slug for the new variation.
	 * @param string               $name     The name for the new variation.
	 * @param array<string, mixed> $overrides Attribute overrides for the variation.
	 *
	 * @return static
	 */
	public function createVariation( string $slug, string $name, array $overrides = [] ): static
	{
		$forbidden = [ 'id', 'parent_id', 'slug', 'name' ];
		$overrides = array_diff_key( $overrides, array_flip( $forbidden ) );

		$defaults = [
			'name'                  => $name,
			'slug'                  => $slug,
			'description'           => $this->description,
			'type'                  => $this->type,
			'for_content_type'      => $this->for_content_type,
			'content'               => $this->content,
			'status'                => 'draft',
			'content_area_settings' => $this->content_area_settings,
			'styles'                => $this->styles,
			'is_custom'             => true,
			'is_locked'             => false,
			'parent_id'             => $this->id,
			'user_id'               => null,
		];

		return static::create( array_merge( $defaults, $overrides ) );
	}

	/**
	 * Resolve the effective content for this template.
	 *
	 * For variations, merges parent content with any overrides.
	 * For base templates, returns the template's own content.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function resolveContent(): array
	{
		if ( ! $this->isVariation() ) {
			return $this->content ?? [];
		}

		$ownContent = $this->content ?? [];

		if ( ! empty( $ownContent ) ) {
			return $ownContent;
		}

		$parent = $this->parent;

		if ( null === $parent ) {
			return [];
		}

		return $parent->resolveContent();
	}

	/**
	 * Resolve the effective content area settings for this template.
	 *
	 * For variations, recursively merges parent settings with any overrides
	 * so nested keys are preserved. For base templates, returns the
	 * template's own settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function resolveContentAreaSettings(): array
	{
		$ownSettings = $this->content_area_settings ?? [];

		if ( ! $this->isVariation() ) {
			return $ownSettings;
		}

		$parent = $this->parent;

		if ( null === $parent ) {
			return $ownSettings;
		}

		$parentSettings = $parent->resolveContentAreaSettings();

		return array_replace_recursive( $parentSettings, $ownSettings );
	}

	/**
	 * Resolve the effective styles for this template.
	 *
	 * For variations, recursively merges parent styles with any overrides
	 * so nested keys are preserved. For base templates, returns the
	 * template's own styles.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function resolveStyles(): array
	{
		$ownStyles = $this->styles ?? [];

		if ( ! $this->isVariation() ) {
			return $ownStyles;
		}

		$parent = $this->parent;

		if ( null === $parent ) {
			return $ownStyles;
		}

		$parentStyles = $parent->resolveStyles();

		return array_replace_recursive( $parentStyles, $ownStyles );
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
			'is_custom'             => 'boolean',
			'is_locked'             => 'boolean',
			'parent_id'             => 'integer',
			'user_id'               => 'integer',
		];
	}
}
