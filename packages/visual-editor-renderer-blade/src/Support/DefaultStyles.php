<?php

/**
 * Baseline front-end stylesheet for theme-less installs (#821).
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

use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;

/**
 * A small baseline the editor canvas already shows (its
 * `DEFAULT_CANVAS_STYLES`) but the public site lacked without a theme:
 * content typography, a heading scale, and root padding for constrained
 * post content.
 *
 * Scoped to block output (`.wp-block-post-content`, `.wp-block-heading`)
 * rather than `body` / bare `h1`…`h6`. The rules are unlayered, and
 * unlayered CSS beats a Tailwind host's `@layer` preflight and utilities
 * whatever the specificity, so page-wide selectors would restyle the
 * host's own chrome. Within block content, `:where()` keeps specificity
 * at zero so any unlayered theme rule still wins.
 *
 * Controlled by `artisanpack.visual-editor.default_styles`: `'auto'`
 * (default) emits it only when no theme is active, `true` always,
 * `false` never.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.12.1
 */
class DefaultStyles
{
	/**
	 * Baseline CSS. Values mirror `DEFAULT_CANVAS_STYLES` in
	 * `resources/js/visual-editor/editor-settings.ts`.
	 *
	 * @since 1.12.1
	 */
	public const CSS = <<<'CSS'
:where(.wp-block-post-content) {
	font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	font-size: 16px;
	line-height: 1.6;
	color: #1f2937;
}
:where(.wp-block-heading) { font-weight: 700; line-height: 1.2; }
:where(h1.wp-block-heading) { font-size: 2.5rem; }
:where(h2.wp-block-heading) { font-size: 2rem; }
:where(h3.wp-block-heading) { font-size: 1.5rem; }
:where(h4.wp-block-heading) { font-size: 1.25rem; }
:where(h5.wp-block-heading) { font-size: 1.125rem; }
:where(h6.wp-block-heading) { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; }
:where(.wp-block-post-content.is-layout-constrained, .has-global-padding) {
	padding-inline-start: var(--wp--style--root--padding-left, 1.5rem);
	padding-inline-end: var(--wp--style--root--padding-right, 1.5rem);
}
:where(.wp-block-post-content.is-layout-constrained, .has-global-padding) > .alignfull {
	margin-inline-start: calc(var(--wp--style--root--padding-left, 1.5rem) * -1);
	margin-inline-end: calc(var(--wp--style--root--padding-right, 1.5rem) * -1);
}
CSS;

	/**
	 * Whether the baseline should be emitted.
	 *
	 * @since 1.12.1
	 *
	 * @param  array<string, mixed>|null  $themeJson  The theme.json passed to the styles component.
	 */
	public static function enabled( ?array $themeJson ): bool
	{
		$setting = config( 'artisanpack.visual-editor.default_styles', 'auto' );

		if ( is_bool( $setting ) ) {
			return $setting;
		}

		if ( null !== $themeJson && [] !== $themeJson ) {
			return false;
		}

		return null === PresetRegistry::activeThemeSettings();
	}
}
