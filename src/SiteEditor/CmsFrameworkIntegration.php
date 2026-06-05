<?php

/**
 * cms-framework integration probe — H7 (#432).
 *
 * Centralizes the "is artisanpack-ui/cms-framework installed and its
 * SiteEditor module booted?" check used by the H7 install-gate route
 * closure. Per plan 14 §2.1 the site-editor surface is hard-coupled to
 * cms-framework — the gate exists so a standalone visual-editor install
 * lands on a clear "install cms-framework to enable the site editor"
 * page instead of an SPA-shaped 404 cascade.
 *
 * The H6 controllers (`Http/Controllers/SiteEditor/*`) each carry their
 * own per-entity `cmsFrameworkAvailable()` test against the entity's
 * resolver binding. Those stay — the route gate is coarser (any one
 * resolver binding bound is enough to say "the module is wired") and
 * deliberately does not require all four resolvers since cms-framework
 * registers them in a single provider boot.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor;

class CmsFrameworkIntegration
{
	/**
	 * Fully-qualified class name of cms-framework's `Template` model.
	 *
	 * Stored as a string so this file compiles without cms-framework on
	 * the classpath. The H1 module ships this class first; its presence
	 * is the canonical "cms-framework SiteEditor module is on the
	 * classpath" signal.
	 *
	 * @since 1.0.0
	 */
	protected const CMS_TEMPLATE_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\Template';

	/**
	 * Container binding cms-framework's SiteEditor provider declares for
	 * the templates resolver. Its presence indicates the provider's
	 * `boot()` has run — the same signal the H6 controllers use as the
	 * "module is wired up" sentinel.
	 *
	 * Stored without a leading backslash so the string matches the
	 * `$app->bound()` lookup key, which the container normalizes to the
	 * bare FQCN.
	 *
	 * @since 1.0.0
	 */
	protected const CMS_RESOLVER_BINDING = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver';

	/**
	 * True when cms-framework's SiteEditor module is on the classpath
	 * AND its provider has booted. False otherwise — including the test
	 * scenario where the class is autoloaded (because cms-framework is
	 * in `require-dev`) but the provider hasn't been booted yet.
	 *
	 * @since 1.0.0
	 */
	public static function isAvailable(): bool
	{
		if ( ! class_exists( self::CMS_TEMPLATE_FQCN ) ) {
			return false;
		}

		return app()->bound( self::CMS_RESOLVER_BINDING );
	}
}
