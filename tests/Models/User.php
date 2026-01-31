<?php

declare( strict_types=1 );

namespace Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Test User Model
 *
 * A simple user model for testing authentication and authorization.
 *
 * @since 1.0.0
 */
class User extends Authenticatable
{
	use HasFactory;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'name',
		'email',
		'password',
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var array<int, string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * Create a new factory instance for the model.
	 *
	 * @return \Tests\Factories\UserFactory
	 */
	protected static function newFactory(): \Tests\Factories\UserFactory
	{
		return \Tests\Factories\UserFactory::new();
	}
}
