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
