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
