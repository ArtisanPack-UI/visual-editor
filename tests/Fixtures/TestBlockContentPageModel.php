<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture that stores content under a non-default column to exercise the
 * `$blockContentColumn` override on the trait.
 *
 * @property int $id
 * @property string $title
 * @property int|null $author_id
 * @property array<int, array<string, mixed>>|null $body
 */
class TestBlockContentPageModel extends Model
{
	use HasBlockContent;

	protected $table = 'test_block_content_pages';

	protected $guarded = [];

	protected string $blockContentColumn = 'body';
}
