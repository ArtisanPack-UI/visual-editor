<?php

declare( strict_types=1 );

namespace Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
	protected $table = 'users';

	protected $guarded = [];

	public $timestamps = true;
}
