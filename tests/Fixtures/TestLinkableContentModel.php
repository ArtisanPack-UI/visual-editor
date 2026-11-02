<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

/**
 * Block-content model with a public URL and no editor scope, so entity
 * search reaches every status and the `view` policy decides what shows.
 * A record with no slug has no URL.
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string $status
 */
class TestLinkableContentModel extends Model
{
	use HasBlockContent;

	protected $table = 'test_block_content_models';

	protected $guarded = [];

	public function getPermalinkAttribute(): ?string
	{
		return null === $this->slug ? null : url( '/' . $this->slug );
	}
}
