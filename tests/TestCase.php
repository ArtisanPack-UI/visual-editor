<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	/**
	 * @param  \Illuminate\Foundation\Application  $app
	 *
	 * @return array<int, class-string>
	 */
	protected function getPackageProviders( $app ): array
	{
		return [
			VisualEditorServiceProvider::class,
		];
	}

	/**
	 * @param  \Illuminate\Foundation\Application  $app
	 */
	protected function defineEnvironment( $app ): void
	{
		$app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );

		$app['config']->set( 'database.default', 'testbench' );
		$app['config']->set( 'database.connections.testbench', [
			'driver'                  => 'sqlite',
			'database'                => ':memory:',
			'prefix'                  => '',
			'foreign_key_constraints' => true,
		] );

		$app['config']->set( 'auth.providers.users.model', TestUser::class );
	}

	protected function defineDatabaseMigrations(): void
	{
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations/testing' );
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
	}

	/**
	 * @param  \Illuminate\Routing\Router  $router
	 */
	protected function defineRoutes( $router ): void
	{
		$router->get( '/login', fn () => 'Login' )->name( 'login' );
	}
}
