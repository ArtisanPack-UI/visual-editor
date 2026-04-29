<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Permissive policy fixture for the G3 (cms-framework Post/Page)
 * controller tests. Allows all CRUD actions to any authenticated user
 * so the tests can focus on the controllers' WP-shape contract instead
 * of the auth surface (which the existing TestBlockContentPolicy
 * already exercises for the `/content` route).
 */
class TestG3Policy
{
	public function viewAny( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function view( ?Authenticatable $user, Model $model ): bool
	{
		return null !== $user;
	}

	public function create( ?Authenticatable $user ): bool
	{
		return null !== $user;
	}

	public function update( ?Authenticatable $user, Model $model ): bool
	{
		return null !== $user;
	}

	public function delete( ?Authenticatable $user, Model $model ): bool
	{
		return null !== $user;
	}
}
