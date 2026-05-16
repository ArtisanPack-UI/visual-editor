<?php

/**
 * Resolves the compiled global-styles CSS that the `<x-ve-blocks>` and
 * `<x-ve-template>` Blade components emit on first render of a request.
 *
 * Delegates to cms-framework's `GlobalStylesEmitter` when the package is
 * installed (Phase H integration); returns an empty string otherwise so
 * the renderer keeps working without cms-framework — the Phase H install
 * gate at the SPA layer is the user-facing surface for the missing-
 * package case, but the front-end renderer stays defensive too.
 *
 * Replaces the legacy parent-package `GlobalStylesCssProvider` +
 * `GlobalStylesCacheInvalidator` (deleted in #434). The cms-framework
 * emitter manages its own cache + invalidation via a model observer; the
 * renderer no longer needs to know about either.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class GlobalStylesEmissionResolver
{
	/**
	 * cms-framework's emitter class. Resolved through the container so
	 * its dependency on `GlobalStylesResolver` (which depends on
	 * `ThemeManager`) lands through normal Laravel service-resolution
	 * rather than this class having to know the full graph.
	 */
	protected const EMITTER_CLASS = '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Emission\\GlobalStylesEmitter';

	/**
	 * Return the compiled global-styles CSS for the active theme, or an
	 * empty string when cms-framework isn't installed (or its emitter
	 * resolves to an empty document — no active theme, no global-styles
	 * record).
	 *
	 * @since 1.1.0
	 */
	public function emit(): string
	{
		if ( ! class_exists( self::EMITTER_CLASS ) ) {
			return '';
		}

		$css = app( self::EMITTER_CLASS )->emit();

		return is_string( $css ) ? $css : '';
	}
}
