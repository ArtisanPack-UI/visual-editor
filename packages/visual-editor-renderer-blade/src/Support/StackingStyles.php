<?php

/**
 * Columns / Media & Text stacking tied to the mobile breakpoint (#820).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.12.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

/**
 * The bundled block-library CSS stacks Columns at 781px and Media &
 * Text at 600px, neither of which matches the viewport switcher's
 * Mobile breakpoint. These rules move both "stack on mobile" behaviors
 * to the registry's `mobile` max-width so the editor's Mobile preview
 * and the front end agree. Emitted after the block-library links, so
 * they win the cascade on source order.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.12.1
 */
class StackingStyles
{
	/**
	 * Fallback mobile threshold when the registry has no `mobile` key.
	 *
	 * @since 1.12.1
	 */
	public const DEFAULT_MOBILE_MAX_WIDTH = 767;

	/**
	 * Build the stacking rules for the registry's mobile threshold.
	 *
	 * @since 1.12.1
	 */
	public static function css( BreakpointRegistry $registry ): string
	{
		$mobile = $registry->maxWidth( 'mobile' ) ?? self::DEFAULT_MOBILE_MAX_WIDTH;
		$above  = $mobile + 1;

		return "@media (max-width: {$mobile}px) {\n"
			. "\t.wp-block-columns:not(.is-not-stacked-on-mobile) { flex-wrap: wrap !important; }\n"
			. "\t.wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column { flex-basis: 100% !important; }\n"
			. "\t.wp-block-media-text.is-stacked-on-mobile { grid-template-columns: 100% !important; }\n"
			. "\t.wp-block-media-text.is-stacked-on-mobile > .wp-block-media-text__media { grid-column: 1; grid-row: 1; }\n"
			. "\t.wp-block-media-text.is-stacked-on-mobile > .wp-block-media-text__content { grid-column: 1; grid-row: 2; }\n"
			. "}\n"
			. "@media (min-width: {$above}px) {\n"
			. "\t.wp-block-columns { flex-wrap: nowrap !important; }\n"
			. "\t.wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column:not([style*=flex-basis]) { flex-basis: 0 !important; flex-grow: 1; }\n"
			. "\t.wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column[style*=flex-basis] { flex-grow: 0; }\n"
			. '}';
	}
}
