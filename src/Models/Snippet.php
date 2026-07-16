<?php

/**
 * Snippet model.
 *
 * Reusable block-tree fragment referenced by the `artisanpack/snippet`
 * block. Cycle detection is delegated to
 * {@see \ArtisanPackUI\VisualEditor\Services\DynamicContent\SnippetCycleGuard}
 * — the model just persists the tree.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $slug
 * @property string      $title
 * @property array|null  $blocks
 * @property int|null    $author_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Snippet extends Model
{
	use HasFactory;

	protected $table = 've_snippets';

	protected $fillable = [
		'slug',
		'title',
		'blocks',
		'author_id',
	];

	protected function casts(): array
	{
		return [
			'blocks'    => 'array',
			'author_id' => 'integer',
		];
	}

	/**
	 * Slug rule mirrors cms-framework's dynamic-content type slug —
	 * lowercase letter first, then letters/digits/underscore, max 64.
	 *
	 * @since 1.4.0
	 */
	public static function slugPattern(): string
	{
		return '/^[a-z][a-z0-9_]{0,63}$/';
	}

	/**
	 * Package factories don't live under Laravel's default
	 * `Database\Factories` root, so point HasFactory at the package's
	 * namespaced factory explicitly.
	 *
	 * @since 1.4.0
	 */
	protected static function newFactory(): \ArtisanPackUI\VisualEditor\Database\Factories\SnippetFactory
	{
		return \ArtisanPackUI\VisualEditor\Database\Factories\SnippetFactory::new();
	}
}
