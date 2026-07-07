<?php

/**
 * Test-case for visual-editor AI feature tests.
 *
 * Boots the ai package's service provider alongside the visual-editor
 * one so `FeatureRegistry`, `AgentPrompter`, and `CredentialResolver`
 * are all bindable.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\AiServiceProvider;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class AiTestCase extends BaseTestCase
{
	/**
	 * @param  \Illuminate\Foundation\Application  $app
	 *
	 * @return array<int, class-string>
	 */
	protected function getPackageProviders( $app ): array
	{
		return [
			AiServiceProvider::class,
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
	}
}
