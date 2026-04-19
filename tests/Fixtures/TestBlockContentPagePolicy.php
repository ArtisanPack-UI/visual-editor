<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

class TestBlockContentPagePolicy
{
	public function view( ?Authenticatable $user, TestBlockContentPageModel $page ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, TestBlockContentPageModel $page ): bool
	{
		if ( null === $user ) {
			return false;
		}

		return null === $page->author_id
			|| (int) $page->author_id === (int) $user->getAuthIdentifier();
	}
}
