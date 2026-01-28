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
}
