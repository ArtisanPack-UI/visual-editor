<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy fixture that lets a user view only published records, for the
 * binding-context record checks (F-13).
 *
 * @since 1.15.0
 */
class TestPublishedOnlyPolicy
{
	/**
	 * Only published records are viewable.
	 *
	 * @since 1.15.0
	 */
	public function view( ?Authenticatable $user, Model $model ): bool
	{
		return null !== $user && 'published' === $model->getAttribute( 'status' );
	}
}
