<?php

declare( strict_types=1 );

/**
 * Base Test Case for Visual Editor Package.
 *
 * Provides the testing foundation using Orchestra Testbench for package testing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests
 *
 * @since      1.0.0
 */

namespace Tests;

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Blade;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base test case class.
 *
 * @since 1.0.0
 */
abstract class TestCase extends BaseTestCase
{
	/**
	 * Set up the test environment.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Register stubs for artisanpack-ui/livewire-ui-components used in views.
		Blade::component( 'artisanpack-icon', Stubs\IconComponentStub::class );
		Blade::component( 'artisanpack-button', Stubs\ButtonComponentStub::class );
		Blade::component( 'artisanpack-badge', Stubs\BadgeComponentStub::class );
		Blade::component( 'artisanpack-heading', Stubs\HeadingComponentStub::class );
		Blade::component( 'artisanpack-input', Stubs\InputComponentStub::class );
		Blade::component( 'artisanpack-separator', Stubs\SeparatorComponentStub::class );
		Blade::component( 'artisanpack-drawer', Stubs\DrawerComponentStub::class );
		Blade::component( 'artisanpack-alert', Stubs\AlertComponentStub::class );
		Blade::component( 'artisanpack-select', Stubs\SelectComponentStub::class );
		Blade::component( 'artisanpack-toggle', Stubs\ToggleComponentStub::class );
		Blade::component( 'artisanpack-colorpicker', Stubs\ColorpickerComponentStub::class );
	}

	/**
	 * Get package providers.
	 *
	 * @since 1.0.0
	 *
	 * @param \Illuminate\Foundation\Application $app The application instance.
	 *
	 * @return array<int, class-string>
	 */
	protected function getPackageProviders( $app ): array
	{
		return [
			LivewireServiceProvider::class,
			VisualEditorServiceProvider::class,
		];
	}

	/**
	 * Get package aliases.
	 *
	 * @since 1.0.0
	 *
	 * @param \Illuminate\Foundation\Application $app The application instance.
	 *
	 * @return array<string, class-string>
	 */
	protected function getPackageAliases( $app ): array
	{
		return [
			'VisualEditor' => \ArtisanPackUI\VisualEditor\Facades\VisualEditor::class,
		];
	}

	/**
	 * Define environment setup.
	 *
	 * @since 1.0.0
	 *
	 * @param \Illuminate\Foundation\Application $app The application instance.
	 *
	 * @return void
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
		$app['config']->set( 'auth.providers.users.model', Models\User::class );
		$app['config']->set( 'artisanpack.visual-editor.api.auth_guard', 'web' );
	}

	/**
	 * Define database migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function defineDatabaseMigrations(): void
	{
		// Load testing migrations (users table - only for tests)
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations/testing' );

		// Load main package migrations (ve_* tables)
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
	}
}
