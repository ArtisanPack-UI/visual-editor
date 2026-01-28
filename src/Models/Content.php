<?php

/**
 * Content model.
 *
 * Represents main content storage for the visual editor, including
 * page sections, settings, SEO metadata, and publishing status.
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Content model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property string                          $uuid
 * @property string                          $content_type
 * @property int|null                        $content_type_id
 * @property string                          $title
 * @property string                          $slug
 * @property string|null                     $excerpt
 * @property array                           $sections
 * @property array|null                      $settings
 * @property string|null                     $template
 * @property array|null                      $template_overrides
 * @property string                          $status
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property string|null                     $meta_title
 * @property string|null                     $meta_description
 * @property string|null                     $og_image
 * @property int|null                        $featured_media_id
 * @property int                             $author_id
 * @property array|null                      $lock
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Content extends Model
{
	use HasFactory;
	use SoftDeletes;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_contents';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'uuid',
		'content_type',
		'content_type_id',
		'title',
		'slug',
		'excerpt',
		'sections',
		'settings',
		'template',
		'template_overrides',
		'status',
		'published_at',
		'scheduled_at',
		'meta_title',
		'meta_description',
		'og_image',
		'featured_media_id',
		'author_id',
		'lock',
	];

	// =========================================
	// Relationships
	// =========================================

	/**
	 * Gets the author of this content.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo<Model, $this>
	 */
	public function author(): BelongsTo
	{
		$userModel = config( 'auth.providers.users.model', 'App\\Models\\User' );

		return $this->belongsTo( $userModel, 'author_id' );
	}

	/**
	 * Gets the revisions for this content.
	 *
	 * @since 1.0.0
	 *
	 * @return HasMany<ContentRevision, $this>
	 */
	public function revisions(): HasMany
	{
		return $this->hasMany( ContentRevision::class, 'content_id' );
	}

	/**
	 * Gets the experiments for this content.
	 *
	 * @since 1.0.0
	 *
	 * @return HasMany<Experiment, $this>
	 */
	public function experiments(): HasMany
	{
		return $this->hasMany( Experiment::class, 'content_id' );
	}

	/**
	 * Gets the active editor lock for this content.
	 *
	 * @since 1.0.0
	 *
	 * @return HasOne<EditorLock, $this>
	 */
	public function editorLock(): HasOne
	{
		return $this->hasOne( EditorLock::class, 'content_id' );
	}

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include published content.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Content> $query The query builder instance.
	 *
	 * @return Builder<Content>
	 */
	public function scopePublished( Builder $query ): Builder
	{
		return $query->where( 'status', 'published' );
	}

	/**
	 * Scopes a query to only include draft content.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Content> $query The query builder instance.
	 *
	 * @return Builder<Content>
	 */
	public function scopeDraft( Builder $query ): Builder
	{
		return $query->where( 'status', 'draft' );
	}

	/**
	 * Scopes a query to only include content of a specific type.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Content> $query The query builder instance.
	 * @param string           $type  The content type.
	 *
	 * @return Builder<Content>
	 */
	public function scopeOfType( Builder $query, string $type ): Builder
	{
		return $query->where( 'content_type', $type );
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
		return 'uuid';
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
			'sections'           => 'array',
			'settings'           => 'array',
			'template_overrides' => 'array',
			'lock'               => 'array',
			'published_at'       => 'datetime',
			'scheduled_at'       => 'datetime',
		];
	}

	/**
	 * Bootstraps the model and its traits.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected static function boot(): void
	{
		parent::boot();

		static::creating( function ( Content $content ): void {
			if ( empty( $content->uuid ) ) {
				$content->uuid = Str::uuid()->toString();
			}

			if ( empty( $content->content_type ) ) {
				$content->content_type = 'page';
			}

			if ( empty( $content->slug ) ) {
				$content->slug = static::generateUniqueSlug( $content->title, $content->content_type );
			}
		} );
	}

	/**
	 * Generates a unique slug from the given title within a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title       The title to generate a slug from.
	 * @param string $contentType The content type to scope uniqueness to.
	 *
	 * @return string The unique slug.
	 */
	protected static function generateUniqueSlug( string $title, string $contentType ): string
	{
		$slug         = Str::slug( $title );
		$originalSlug = $slug;
		$count        = 1;

		while ( static::where( 'slug', $slug )->where( 'content_type', $contentType )->exists() ) {
			$slug = $originalSlug . '-' . $count;
			$count++;
		}

		return $slug;
	}
}
