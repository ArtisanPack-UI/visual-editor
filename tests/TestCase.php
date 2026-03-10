<?php

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

declare( strict_types=1 );

namespace Tests;

use ArtisanPack\LivewireUiComponents\LivewireUiComponentsServiceProvider;
use ArtisanPackUI\MediaLibrary\MediaLibraryServiceProvider;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
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

		$this->app['view']->share( 'errors', ( new ViewErrorBag() )->put( 'default', new MessageBag() ) );

		// Register the media library's Livewire namespace so that namespaced
		// components (media::media-modal, etc.) can be resolved by Livewire 4's
		// Finder, which requires classNamespaces entries for namespace resolution.
		if ( $this->app->bound( 'livewire' ) ) {
			Livewire::addNamespace(
				'media',
				classNamespace: 'ArtisanPackUI\\MediaLibrary\\Livewire\\Components',
			);
		}
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
			BladeIconsServiceProvider::class,
			LivewireServiceProvider::class,
			LivewireUiComponentsServiceProvider::class,
			MediaLibraryServiceProvider::class,
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
	}

	/**
	 * Define database migrations for testing.
	 *
	 * Creates the users table required by foreign key constraints
	 * in the visual editor migrations.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	protected function defineDatabaseMigrations(): void
	{
		Schema::create( 'users', function ( Blueprint $table ): void {
			$table->id();
			$table->string( 'name' );
			$table->string( 'email' )->unique();
			$table->timestamps();
		} );
	}
}
