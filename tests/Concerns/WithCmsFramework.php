<?php

/**
 * WithCmsFramework — boots the cms-framework service provider + migrations
 * in a visual-editor test.
 *
 * Use on Pest test files that exercise H6's REST surface against the real
 * cms-framework H1–H4 modules. Standalone-install tests (those that assert
 * the editor still works without cms-framework) should NOT use this trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Concerns;

use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\Hooks\Providers\HooksServiceProvider;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Foundation\Application;

trait WithCmsFramework
{
	/**
	 * Append cms-framework's service provider to the package providers Orchestra
	 * Testbench loads. Called by the testbench before the application boots,
	 * so registrations land in the same lifecycle as visual-editor's own
	 * provider.
	 *
	 * cms-framework's `ThemeManager` reaches into `SettingsManager` which
	 * reaches into the `security` container binding — so `SecurityServiceProvider`
	 * needs to load alongside it. `HooksServiceProvider` and `ScrambleServiceProvider`
	 * are likewise transitive cms-framework deps that Testbench would otherwise
	 * skip because package auto-discovery doesn't fire in test mode. Mirrors
	 * cms-framework's own `tests/TestCase::getPackageProviders()` set.
	 *
	 * @param  Application  $app
	 *
	 * @return array<int, class-string>
	 */
	protected function getPackageProviders( $app ): array
	{
		return array_merge( parent::getPackageProviders( $app ), [
			SecurityServiceProvider::class,
			ScrambleServiceProvider::class,
			HooksServiceProvider::class,
			CMSFrameworkServiceProvider::class,
		] );
	}

	/**
	 * Load cms-framework's migrations alongside visual-editor's. The
	 * cms-framework `users` migration is idempotent (`if ( ! Schema::hasTable )`)
	 * so it's safe to co-load with visual-editor's testing-only users table —
	 * whichever runs first wins; the second is a no-op.
	 */
	protected function defineDatabaseMigrations(): void
	{
		parent::defineDatabaseMigrations();

		$this->loadMigrationsFrom(
			dirname( __DIR__, 2 ) . '/vendor/artisanpack-ui/cms-framework/database/migrations'
		);
	}
}
