<?php

/**
 * Visual Editor configuration.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

return [
	'persistence' => [
		'draft_ttl'          => 86400,
		'max_revisions'      => 50,
		'autosave_revisions' => false,
	],

	/*
	|----------------------------------------------------------------------
	| Rendering
	|----------------------------------------------------------------------
	|
	| Configuration for the front-end block rendering engine.
	|
	| class_prefix: The CSS class prefix applied to rendered block
	|               wrapper elements (default: 've-block-').
	|
	| max_depth:    Maximum recursion depth for nested inner blocks.
	|               Prevents stack overflows from deeply nested content.
	|
	*/
	'rendering' => [
		'class_prefix' => 've-block-',
		'max_depth'    => 100,
	],

	'user_model' => 'App\\Models\\User',

	/*
	|----------------------------------------------------------------------
	| Templates
	|----------------------------------------------------------------------
	|
	| Configuration for the template system.
	|
	| default_template:        The default template slug for new content.
	| allow_custom_templates:  Whether users can create custom templates.
	| locked_templates:        Slugs of templates that cannot be edited.
	|
	*/
	'templates' => [
		'default_template'       => 'blank',
		'allow_custom_templates' => true,
		'locked_templates'       => [],
	],

	/*
	|----------------------------------------------------------------------
	| Template Presets
	|----------------------------------------------------------------------
	|
	| Configuration for the template preset system.
	|
	| allow_custom_presets:  Whether users can save their own presets.
	| categories:            Available preset categories with labels.
	|
	*/
	'template_presets' => [
		'allow_custom_presets' => true,
		'categories'           => [
			'blog'      => [
				'label' => 'Blog',
				'icon'  => 'o-document-text',
			],
			'marketing' => [
				'label' => 'Marketing',
				'icon'  => 'o-megaphone',
			],
			'portfolio' => [
				'label' => 'Portfolio',
				'icon'  => 'o-squares-2x2',
			],
			'general'   => [
				'label' => 'General',
				'icon'  => 'o-document',
			],
		],
	],

	/*
	|----------------------------------------------------------------------
	| Template Parts
	|----------------------------------------------------------------------
	|
	| Configuration for reusable template parts (headers, footers, sidebars).
	|
	| allow_custom_parts:  Whether users can create custom template parts.
	| locked_parts:        Slugs of template parts that cannot be edited.
	| areas:               Registered template part area types.
	|
	*/
	'template_parts' => [
		'allow_custom_parts' => true,
		'locked_parts'       => [],
		'areas'              => [ 'header', 'footer', 'sidebar', 'custom' ],
	],

	/*
	|----------------------------------------------------------------------
	| Global Styles: Color Palette
	|----------------------------------------------------------------------
	|
	| Override the default color palette for the global styles system.
	| When empty, the defaults from ColorPaletteManager::DEFAULT_PALETTE
	| are used. Each entry defines a named color slot with a display name,
	| unique slug (used in CSS custom properties), and hex value.
	|
	| Blocks reference these colors via 'palette:{slug}' which resolves
	| to the CSS custom property --ve-color-{slug}.
	|
	| Example:
	|   'color_palette' => [
	|       'brand' => [
	|           'name'  => 'Brand Blue',
	|           'slug'  => 'brand',
	|           'color' => '#1e40af',
	|       ],
	|   ],
	|
	*/
	'color_palette' => [],

	/*
	|----------------------------------------------------------------------
	| Global Styles: Typography Presets
	|----------------------------------------------------------------------
	|
	| Override the default typography presets for the global styles system.
	| When empty, the defaults from TypographyPresetsManager are used.
	|
	| 'fontFamilies': Keyed by slot (heading, body, mono) with CSS
	|                 font-family values.
	|
	| 'elements':     Keyed by element (h1-h6, body, small, caption,
	|                 blockquote, code) with typography properties:
	|                 fontSize, fontWeight, lineHeight, letterSpacing,
	|                 fontStyle.
	|
	| Example:
	|   'typography_presets' => [
	|       'fontFamilies' => [
	|           'heading' => '"Playfair Display", serif',
	|       ],
	|       'elements' => [
	|           'h1' => ['fontSize' => '3rem', 'fontWeight' => '800'],
	|       ],
	|   ],
	|
	*/
	'typography_presets' => [
		// 'fontFamilies' => [],
		// 'elements'     => [],
		// 'fonts'        => [
		//     'brand-font' => [
		//         'name'     => 'Brand Font',
		//         'family'   => '"Brand Font", sans-serif',
		//         'category' => 'all',    // 'all', 'heading', or 'body'
		//         'source'   => 'custom', // 'system', 'custom', or 'google'
		//     ],
		// ],
	],

	'blocks' => [
		'core'     => [
			'heading'   => true,
			'paragraph' => true,
			'list'      => true,
			'quote'     => true,
			'image'     => true,
			'gallery'   => true,
			'video'     => true,
			'audio'     => true,
			'file'      => true,
			'columns'   => true,
			'column'    => true,
			'grid'      => true,
			'grid-item' => true,
			'group'     => true,
			'spacer'    => true,
			'divider'   => true,
			'button'    => true,
			'code'      => true,
		],
		'disabled' => [],

		/*
		|----------------------------------------------------------------------
		| Block Categories
		|----------------------------------------------------------------------
		|
		| Define block categories with labels and icons. Categories are
		| automatically derived from registered blocks, but you can provide
		| metadata here for display purposes. Third-party packages can
		| add their own categories via the `ap.visualEditor.blockCategories`
		| filter hook.
		|
		*/
		'categories' => [
			'text'        => [
				'label' => 'Text',
				'icon'  => 'o-document-text',
			],
			'media'       => [
				'label' => 'Media',
				'icon'  => 'o-photo',
			],
			'layout'      => [
				'label' => 'Layout',
				'icon'  => 'o-view-columns',
			],
			'interactive' => [
				'label' => 'Interactive',
				'icon'  => 'o-cursor-arrow-rays',
			],
			'embed'       => [
				'label' => 'Embed',
				'icon'  => 'o-code-bracket',
			],
			'dynamic'     => [
				'label' => 'Dynamic',
				'icon'  => 'o-bolt',
			],
		],
	],
];
