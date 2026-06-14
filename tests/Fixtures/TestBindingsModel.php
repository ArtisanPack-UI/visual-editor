<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestUser;

/**
 * Test fixture used by the #504 block-bindings tests. Reuses the
 * existing `test_block_content_models` table so we do not have to add a
 * second migration, and layers an `author` relation on top so the
 * resolver's eager-load path is exercisable.
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $excerpt
 * @property string $status
 * @property int|null $author_id
 * @property array<int, array<string, mixed>>|null $content
 */
class TestBindingsModel extends Model
{
	use HasBlockContent;

	protected $table = 'test_block_content_models';

	protected $guarded = [];

	public function author(): BelongsTo
	{
		return $this->belongsTo( TestUser::class, 'author_id' );
	}
}
