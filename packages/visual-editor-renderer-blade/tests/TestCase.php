<?php

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Tests;

use ArtisanPackUI\Hooks\Providers\HooksServiceProvider;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use ArtisanPackUI\VisualEditorRendererBlade\VisualEditorRendererBladeServiceProvider;
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
			// Registered first, and ahead of the visual-editor provider,
			// because it binds `HookDeprecations` as a singleton. Without
			// it every `app( HookDeprecations::class )` resolves a fresh
			// instance, so the aliases `VisualEditorServiceProvider::boot()`
			// registers land on a throwaway object and no legacy hook name
			// resolves — silently, since the un-aliased name still fires.
			// The root suite's TestCase has always registered it; this one
			// had not, which is why block partials that fire a renamed hook
			// went uncovered here (cms-framework#245).
			HooksServiceProvider::class,
			VisualEditorServiceProvider::class,
			VisualEditorRendererBladeServiceProvider::class,
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

	/**
	 * Loads the host package's migrations so tests that touch the
	 * `visual_editor_*` tables (template-part inlining, template
	 * resolution) can persist fixtures via Eloquent.
	 *
	 * @since 1.0.0
	 */
	protected function defineDatabaseMigrations(): void
	{
		$this->loadMigrationsFrom( __DIR__ . '/../../../database/migrations' );
	}

	/**
	 * Collapse insignificant whitespace so snapshot comparisons aren't brittle.
	 *
	 * Only strips whitespace directly between tags (e.g. "</p>\n\t<p>" → "</p><p>")
	 * while preserving spaces inside text content.
	 *
	 * @since 1.0.0
	 */
	protected function normalizeHtml( string $html ): string
	{
		$betweenTags = (string) preg_replace( '/>\s+</', '><', $html );

		$attrCollapsed = (string) preg_replace( '/\s+/', ' ', $betweenTags );

		return trim( $attrCollapsed );
	}

	/**
	 * Strips the auto-emitted `<style data-ve-global-styles>...</style>`
	 * block so existing block-output assertions can compare against the
	 * actual block HTML without dragging in the global-styles CSS.
	 * Tests that exercise the emission itself should not call this.
	 *
	 * @since 1.0.0
	 */
	protected function stripGlobalStyles( string $html ): string
	{
		return (string) preg_replace(
			'#<style data-ve-global-styles>.*?</style>#s',
			'',
			$html
		);
	}
}
