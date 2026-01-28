<?php

declare( strict_types=1 );

/**
 * ArtisanPack UI - Visual Editor Configuration
 *
 * This configuration file defines settings for the Visual Editor package.
 * Settings are merged into the main artisanpack.php config file under the
 * 'visual-editor' key, following ArtisanPack UI package conventions.
 *
 * After publishing, this file can be found at: config/artisanpack/visual-editor.php
 *
 * @package    ArtisanPackUI\VisualEditor
 *
 * @since      1.0.0
 */
return [
	/*
	|--------------------------------------------------------------------------
	| Editor Settings
	|--------------------------------------------------------------------------
	|
	| Core settings for the visual editor interface.
	|
	*/
	'editor' => [
		'autosave_interval'   => env( 'VE_AUTOSAVE_INTERVAL', 60 ),
		'max_history_states'  => 50,
		'default_template'    => 'default',
		'show_preview_button' => true,
		'preview_mode'        => 'new_tab', // new_tab, modal, iframe
	],

	/*
	|--------------------------------------------------------------------------
	| Content Settings
	|--------------------------------------------------------------------------
	|
	| Settings related to content management within the editor.
	|
	*/
	'content' => [
		'default_status'         => 'draft',
		'require_featured_image' => false,
		'enable_revisions'       => true,
		'enable_scheduling'      => true,
		'slug_generation'        => 'auto', // auto, manual
	],

	/*
	|--------------------------------------------------------------------------
	| Block Settings
	|--------------------------------------------------------------------------
	|
	| Configuration for the block system, including custom blocks and
	| block restrictions.
	|
	*/
	'blocks' => [
		'enable_custom_blocks' => true,
		'allowed_blocks'       => [], // Empty = all allowed
		'disallowed_blocks'    => [],
		'default_supports'     => [ 'align', 'spacing' ],
	],

	/*
	|--------------------------------------------------------------------------
	| Section Settings
	|--------------------------------------------------------------------------
	|
	| Configuration for the section system, including user-created sections
	| and sharing capabilities.
	|
	*/
	'sections' => [
		'enable_user_sections'    => true,
		'max_user_sections'       => 50,
		'enable_section_sharing'  => true,
		'enable_section_patterns' => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Template Settings
	|--------------------------------------------------------------------------
	|
	| Configuration for page templates and template parts.
	|
	*/
	'templates' => [
		'enable_custom_templates'  => true,
		'enable_template_editing'  => true,
		'default_template_parts'   => [ 'header', 'footer' ],
		'template_parts_directory' => 'template-parts',
	],

	/*
	|--------------------------------------------------------------------------
	| Global Styles
	|--------------------------------------------------------------------------
	|
	| Configuration for the global styles system, including CSS output
	| and Tailwind CSS export.
	|
	*/
	'styles' => [
		'enable_style_editing'   => true,
		'enable_tailwind_export' => true,
		'css_output_path'        => 'css/visual-editor-styles.css',
		'custom_properties'      => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Revision Settings
	|--------------------------------------------------------------------------
	|
	| Configuration for content revisions and autosave retention.
	|
	*/
	'revisions' => [
		'autosave_retention_hours'   => 24,
		'manual_retention_days'      => 30,
		'max_autosaves_per_content'  => 10,
		'keep_all_named'             => true,
		'keep_all_publish'           => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Permissions
	|--------------------------------------------------------------------------
	|
	| Permission configuration for the visual editor. Integrates with
	| cms-framework when available.
	|
	*/
	'permissions' => [
		'use_cms_framework'   => true, // Use cms-framework permissions
		'custom_capabilities' => [
			'visual_editor.access',
			'visual_editor.edit_content',
			'visual_editor.publish',
			'visual_editor.edit_templates',
			'visual_editor.edit_styles',
			'visual_editor.manage_blocks',
			'visual_editor.edit_locked',
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Locking
	|--------------------------------------------------------------------------
	|
	| Configuration for content and block locking to prevent concurrent edits.
	|
	*/
	'locking' => [
		'enable_content_locking' => true,
		'enable_block_locking'   => true,
		'heartbeat_interval'     => 30, // seconds
		'lock_timeout'           => 120, // seconds
	],

	/*
	|--------------------------------------------------------------------------
	| AI Assistant
	|--------------------------------------------------------------------------
	|
	| Configuration for AI-powered features within the editor.
	| Override the default provider via VE_AI_PROVIDER and models
	| via VE_OPENAI_MODEL / VE_ANTHROPIC_MODEL environment variables.
	|
	*/
	'ai' => [
		'enabled'          => env( 'VE_AI_ENABLED', false ),
		'default_provider' => env( 'VE_AI_PROVIDER', 'openai' ), // 'openai' or 'anthropic'
		'providers'        => [
			'openai'    => [
				'api_key' => env( 'OPENAI_API_KEY' ),
				'model'   => env( 'VE_OPENAI_MODEL', 'gpt-4.1-mini' ), // Override via VE_OPENAI_MODEL
			],
			'anthropic' => [
				'api_key' => env( 'ANTHROPIC_API_KEY' ),
				'model'   => env( 'VE_ANTHROPIC_MODEL', 'claude-opus-4-1' ), // Override via VE_ANTHROPIC_MODEL
			],
		],
	],

	/*
	|--------------------------------------------------------------------------
	| A/B Testing
	|--------------------------------------------------------------------------
	|
	| Configuration for content experiments and A/B testing.
	|
	*/
	'experiments' => [
		'enabled'             => env( 'VE_EXPERIMENTS_ENABLED', false ),
		'cookie_duration'     => 30, // days
		'minimum_sample_size' => 100,
	],

	/*
	|--------------------------------------------------------------------------
	| Accessibility
	|--------------------------------------------------------------------------
	|
	| Configuration for accessibility checks and validation.
	|
	*/
	'accessibility' => [
		'enabled'                 => true,
		'minimum_score'           => 80,
		'block_publish_on_errors' => false,
		'checks'                  => [
			'images'         => true,
			'headings'       => true,
			'links'          => true,
			'color_contrast' => true,
			'forms'          => true,
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Performance
	|--------------------------------------------------------------------------
	|
	| Configuration for performance optimizations.
	|
	*/
	'performance' => [
		'enable_lazy_loading'       => true,
		'enable_asset_optimization' => true,
		'cache_rendered_content'    => true,
		'cache_ttl'                 => 3600, // seconds
	],

	/*
	|--------------------------------------------------------------------------
	| Media Library Integration
	|--------------------------------------------------------------------------
	|
	| Configuration for integration with the media-library package.
	|
	*/
	'media' => [
		'picker_default_view'  => 'grid',
		'picker_per_page'      => 24,
		'enable_inline_upload' => true,
		'show_recently_used'   => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Drag and Drop
	|--------------------------------------------------------------------------
	|
	| Configuration for the drag and drop functionality.
	| Uses @artisanpack-ui/livewire-drag-and-drop NPM package.
	|
	*/
	'drag_drop' => [
		'animation_duration' => 150,
		'scroll_sensitivity' => 100,
		'scroll_speed'       => 10,
	],
];
