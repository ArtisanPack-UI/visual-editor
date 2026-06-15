<?php

/**
 * Font Awesome Free icon-set discovery + filter registration.
 *
 * Phase 3 of the Icon Block feature (#494, issue #554). The actual SVGs
 * are mirrored from `@fortawesome/fontawesome-free` into
 * `resources/icons/font-awesome/{fas,far,fab}/` by `scripts/sync-fa-icons.mjs`.
 * This helper turns the on-disk layout into icon-set entries that the
 * `artisanpack-ui/icons` registry consumes via the
 * `ap.icons.register-icon-sets` filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Icon;

use ArtisanPackUI\Icons\Registries\IconSetRegistration;

/**
 * The discovery + registration step is split from the service provider so
 * Pest can drive it against fixture directories without booting Testbench.
 * `discover()` is the pure path-collection step; `register()` walks the
 * discovered sets into the registry and tolerates a missing base directory
 * (e.g. a fresh checkout that hasn't run `npm run build` yet — boot must
 * not fail in that case).
 */
final class FontAwesomeFreeIconSets
{
	/**
	 * @var array<int, string>
	 */
	public const SET_PREFIXES = [ 'fas', 'far', 'fab' ];

	/**
	 * Fully-qualified class name of the `owenvoke/blade-fontawesome`
	 * service provider. Kept as a literal string so the symbol is never
	 * imported (and thus never triggers an autoload attempt) just to test
	 * for the package's presence.
	 *
	 * @since 1.1.1
	 */
	private const BLADE_FONTAWESOME_PROVIDER = 'OwenVoke\\BladeFontAwesome\\BladeFontAwesomeServiceProvider';

	/**
	 * Return the absolute path for each FA Free set whose directory exists
	 * under `$baseDir`. Missing sets are silently skipped so a partial
	 * sync still surfaces the sets that did land.
	 *
	 * @return array<string, string> Map of prefix → absolute path.
	 */
	public static function discover( string $baseDir ): array
	{
		$found = [];
		foreach ( self::SET_PREFIXES as $prefix ) {
			$path = $baseDir . DIRECTORY_SEPARATOR . $prefix;
			if ( is_dir( $path ) ) {
				$found[ $prefix ] = $path;
			}
		}

		return $found;
	}

	/**
	 * Register each discovered set on the supplied `IconSetRegistration`.
	 *
	 * Returns the same registry instance so this method can be used as the
	 * body of an `ap.icons.register-icon-sets` filter callback. The
	 * registry's `addSet()` throws when handed a non-existent path, which
	 * is exactly why `discover()` filters them first — we want to no-op,
	 * not blow up, when the FA Free directory is missing.
	 *
	 * When `owenvoke/blade-fontawesome` is installed, it already claims
	 * the `fas` / `far` / `fab` prefixes via its own service provider and
	 * `BladeUI\Icons\Factory::add()` rejects the duplicate registration
	 * (issue #587). The blade-fontawesome bundle ships the same FA Free
	 * SVG set, so deferring to it is a no-UX-regression workaround.
	 *
	 * `$bladeFontAwesomeProvider` is a test seam: callers can override
	 * the class name probed by `class_exists()` so the skip path can be
	 * exercised without loading the real package (or any stub of it) into
	 * the autoloader.
	 *
	 * @param  string|null  $bladeFontAwesomeProvider  FQCN to probe for the
	 *                                                 blade-fontawesome
	 *                                                 service provider.
	 *                                                 Defaults to the real
	 *                                                 class name when null.
	 */
	public static function register(
		IconSetRegistration $registry,
		string $baseDir,
		?string $bladeFontAwesomeProvider = null,
	): IconSetRegistration {
		$providerClass = $bladeFontAwesomeProvider ?? self::BLADE_FONTAWESOME_PROVIDER;
		if ( class_exists( $providerClass ) ) {
			return $registry;
		}

		foreach ( self::discover( $baseDir ) as $prefix => $path ) {
			$registry->addSet( $path, $prefix );
		}

		return $registry;
	}
}
