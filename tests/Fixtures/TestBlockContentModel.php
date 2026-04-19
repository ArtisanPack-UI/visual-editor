<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $status
 * @property int|null $author_id
 * @property array<int, array<string, mixed>>|null $content
 */
class TestBlockContentModel extends Model
{
	use HasBlockContent;

	protected $table = 'test_block_content_models';

	protected $guarded = [];

	protected string $blockContentScope = 'published';

	public function scopePublished( Builder $query ): Builder
	{
		return $query->where( 'status', 'published' );
	}
}
