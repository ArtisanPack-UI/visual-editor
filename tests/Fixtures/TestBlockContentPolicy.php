<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

class TestBlockContentPolicy
{
	public function view( ?Authenticatable $user, TestBlockContentModel $model ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, TestBlockContentModel $model ): bool
	{
		if ( null === $user ) {
			return false;
		}

		return null === $model->author_id
			|| (int) $model->author_id === (int) $user->getAuthIdentifier();
	}
}
