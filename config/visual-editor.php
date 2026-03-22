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

	/*
	|----------------------------------------------------------------------
	| Global Styles: Spacing Scale
	|----------------------------------------------------------------------
	|
	| Override the default spacing scale for the global styles system.
	| When empty, the defaults from SpacingScaleManager::DEFAULT_SCALE
	| are used. Defines named spacing steps used for padding, margin,
	| and gap across all blocks.
	|
	| 'scale':     A slug => CSS value map for spacing steps.
	| 'blockGap':  The spacing step slug used as the default gap
	|              between blocks (default: 'md').
	|
	| Example:
	|   'spacing_scale' => [
	|       'scale' => [
	|           'xs'  => '0.25rem',
	|           'sm'  => '0.5rem',
	|           'md'  => '1rem',
	|           'lg'  => '1.5rem',
	|           'xl'  => '2rem',
	|           '2xl' => '3rem',
	|           '3xl' => '4rem',
	|       ],
	|       'blockGap' => 'md',
	|   ],
	|
	*/
	'spacing_scale' => [],

	/*
	|----------------------------------------------------------------------
	| Global Styles: CSS Compilation
	|----------------------------------------------------------------------
	|
	| Configuration for the unified CSS custom properties compilation
	| engine that aggregates colors, typography, and spacing into a
	| single CSS output.
	|
	| output_mode:          How to deliver CSS: 'inline' (<style> tag),
	|                       'file' (static CSS file), or 'both'.
	| output_path:          Relative path for the static CSS file
	|                       (used when output_mode is 'file' or 'both').
	| output_disk:          Laravel filesystem disk to use for file
	|                       output. Null uses public_path().
	| minify:               Whether to minify the compiled CSS output.
	| cache.enabled:        Enable CSS output caching.
	| cache.key:            Cache key for the compiled CSS.
	| cache.ttl:            Cache time-to-live in seconds.
	| cache.store:          Cache store driver name (null = default).
	| debug_comments:       Include section comments in the compiled
	|                       output (e.g. Colors, Typography, Spacing).
	| include_color_shades: Include light/dark shade variations for
	|                       palette colors.
	| root_selector:        The CSS selector for the root block
	|                       (default: ':root').
	| template_overrides:   Keyed by template slug, each entry is an
	|                       array of per-manager overrides for scoped CSS.
	|
	*/
	'global_styles' => [
		'output_mode'          => 'inline',
		'output_path'          => 'css/ve-global-styles.css',
		'output_disk'          => null,
		'minify'               => false,
		'cache'                => [
			'enabled' => false,
			'key'     => 've-global-styles',
			'ttl'     => 3600,
			'store'   => null,
		],
		'debug_comments'       => false,
		'include_color_shades' => true,
		'root_selector'        => ':root',
		'template_overrides'   => [],
	],

	/*
	|----------------------------------------------------------------------
	| Site Editor
	|----------------------------------------------------------------------
	|
	| Configuration for the site editor hub and navigation shell.
	|
	| route_prefix:   URL prefix for site editor routes (default: 'site-editor').
	| middleware:     Middleware applied to site editor routes.
	| permission:     Gate/policy ability checked before accessing the hub.
	| hub_page:       Livewire component class for the hub page. Swap this
	|                 to use a custom hub page implementation.
	| layout:         Blade component class for the site editor layout shell.
	|                 Swap this to use a custom layout implementation.
	|
	*/
	'site_editor' => [
		'route_prefix'  => 'site-editor',
		'middleware'    => [ 'web', 'auth' ],
		'permission'    => 'visual-editor.access-site-editor',
		'hub_page'      => ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage::class,
		'layout'        => ArtisanPackUI\VisualEditor\View\Components\SiteEditorLayout::class,
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
