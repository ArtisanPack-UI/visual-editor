<?php

/**
 * Default `base` global-styles payload.
 *
 * Returned by `GET /visual-editor/api/global-styles/base` when the host
 * app has not overridden `artisanpack.visual-editor.global_styles.base_path`
 * in config. The shape mirrors theme.json as of the pinned schema
 * version documented in `docs/global-styles.md`. The B2 global-styles
 * fixture (`tests/Fixtures/sample-content/global-styles/default.json`)
 * is kept in sync with this payload so shim round-trips exercise the
 * real defaults.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

return [
	'version'  => 3,
	'settings' => [
		'color'      => [
			'palette' => [
				[ 'slug' => 'primary', 'name' => 'Primary', 'color' => '#3b82f6' ],
				[ 'slug' => 'secondary', 'name' => 'Secondary', 'color' => '#6366f1' ],
				[ 'slug' => 'accent', 'name' => 'Accent', 'color' => '#10b981' ],
				[ 'slug' => 'base', 'name' => 'Base', 'color' => '#ffffff' ],
				[ 'slug' => 'contrast', 'name' => 'Contrast', 'color' => '#111827' ],
			],
		],
		'typography' => [
			'fontFamilies' => [
				[
					'slug'       => 'sans',
					'name'       => 'Sans',
					'fontFamily' => "'Inter', system-ui, sans-serif",
				],
				[
					'slug'       => 'serif',
					'name'       => 'Serif',
					'fontFamily' => "'Source Serif 4', Georgia, serif",
				],
			],
			'fontSizes'    => [
				[ 'slug' => 'small', 'name' => 'Small', 'size' => '0.875rem' ],
				[ 'slug' => 'medium', 'name' => 'Medium', 'size' => '1rem' ],
				[ 'slug' => 'large', 'name' => 'Large', 'size' => '1.25rem' ],
				[ 'slug' => 'x-large', 'name' => 'Extra Large', 'size' => '1.75rem' ],
			],
		],
		'layout'     => [
			'contentSize' => '720px',
			'wideSize'    => '1120px',
		],
		// #607 — Box shadow presets. Themes can extend, override, or
		// clear this list; the box-shadow inspector surfaces every entry
		// as a preset chip and the resolver expands `style.shadow.preset`
		// slug references to `var(--wp--preset--shadow--{slug})` at
		// render time.
		'shadow'     => [
			'presets' => [
				[ 'slug' => 'shadow-sm', 'name' => 'Small', 'shadow' => '0 1px 2px 0 rgba(0,0,0,0.05)' ],
				[ 'slug' => 'shadow-md', 'name' => 'Medium', 'shadow' => '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)' ],
				[ 'slug' => 'shadow-lg', 'name' => 'Large', 'shadow' => '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)' ],
				[ 'slug' => 'shadow-elevated', 'name' => 'Elevated', 'shadow' => '0 25px 50px -12px rgba(0,0,0,0.25)' ],
			],
		],
		// #595 — flex layout panel defaults. Themes can disable the
		// panel entirely by setting `enable` to false (already-saved
		// content keeps rendering through the wrapper classes).
		'artisanpack' => [
			'flex'      => [
				'enable'                => true,
				'defaultDirection'      => 'row',
				'defaultJustifyContent' => null,
				'defaultAlignItems'     => null,
				'defaultGap'            => [ 'row' => null, 'column' => null ],
			],
			// #594 — Photo Grid container support. Themes can disable
			// the feature entirely by setting `enable` to false; already-
			// saved content continues to render because the renderer
			// reads the per-block `photoGrid` attribute independently
			// of the inspector visibility flag.
			'photoGrid' => [
				'enable'                => true,
				'defaultAspectRatio'    => '1/1',
				'defaultObjectFit'      => 'cover',
				'defaultObjectPosition' => '50% 50%',
			],
		],
	],
	'styles'   => [
		'color'      => [
			'background' => 'var(--wp--preset--color--base)',
			'text'       => 'var(--wp--preset--color--contrast)',
		],
		'typography' => [
			'fontFamily' => 'var(--wp--preset--font-family--sans)',
			'fontSize'   => 'var(--wp--preset--font-size--medium)',
			'lineHeight' => '1.6',
		],
		'elements'   => [
			'link'    => [
				'color' => [ 'text' => 'var(--wp--preset--color--primary)' ],
			],
			'heading' => [
				'typography' => [
					'fontFamily' => 'var(--wp--preset--font-family--serif)',
					'fontWeight' => '600',
				],
			],
		],
		'blocks'     => [
			'core/button' => [
				'color'  => [
					'background' => 'var(--wp--preset--color--primary)',
					'text'       => 'var(--wp--preset--color--base)',
				],
				'border' => [ 'radius' => '0.5rem' ],
			],
		],
	],
];
