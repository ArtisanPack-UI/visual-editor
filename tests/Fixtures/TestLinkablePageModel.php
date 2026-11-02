<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

/**
 * Page fixture with a public URL, for searches that span two tables.
 *
 * @property int $id
 * @property string $title
 */
class TestLinkablePageModel extends TestBlockContentPageModel
{
	public function getPermalinkAttribute(): string
	{
		return url( '/guides/' . $this->id );
	}
}
