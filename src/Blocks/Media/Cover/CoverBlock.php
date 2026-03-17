<?php

/**
 * Cover Block.
 *
 * Displays an image, video, or color background with text overlay content.
 * Supports focal point picker, overlay color/opacity, parallax scrolling,
 * and configurable content alignment.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Cover
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media\Cover;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Cover block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Cover
 *
 * @since      1.0.0
 */
class CoverBlock extends BaseBlock
{
	/**
	 * Get the content field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'mediaType'       => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.cover_media_type' ),
				'options' => [
					'image' => __( 'visual-editor::ve.cover_type_image' ),
					'video' => __( 'visual-editor::ve.cover_type_video' ),
					'color' => __( 'visual-editor::ve.cover_type_color' ),
				],
				'default' => 'image',
			],
			'mediaUrl'        => [
				'type'      => 'media_picker',
				'label'     => __( 'visual-editor::ve.cover_media_url' ),
				'default'   => '',
				'condition' => [ 'mediaType', '!=', 'color' ],
			],
			'alt'             => [
				'type'      => 'textarea',
				'label'     => __( 'visual-editor::ve.alt_text' ),
				'hint'      => __( 'visual-editor::ve.alt_text_help' ),
				'default'   => '',
				'condition' => [ 'mediaType', '==', 'image' ],
				'panel'     => __( 'visual-editor::ve.cover_panel_media' ),
			],
			'focalPoint'      => [
				'type'      => 'focal_point',
				'label'     => __( 'visual-editor::ve.cover_focal_point' ),
				'default'   => [ 'x' => 0.5, 'y' => 0.5 ],
				'inspector' => false,
			],
			'hasParallax'     => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.cover_fixed_background' ),
				'hint'      => __( 'visual-editor::ve.cover_parallax_hint' ),
				'default'   => false,
				'condition' => [ 'mediaType', '==', 'image' ],
				'panel'     => __( 'visual-editor::ve.cover_panel_effects' ),
			],
			'isRepeated'      => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.cover_repeated_background' ),
				'default'   => false,
				'condition' => [ 'mediaType', '==', 'image' ],
				'panel'     => __( 'visual-editor::ve.cover_panel_effects' ),
			],
			'overlayColor'    => [
				'type'      => 'color',
				'label'     => __( 'visual-editor::ve.cover_overlay_color' ),
				'default'   => '#000000',
				'inspector' => false,
			],
			'overlayOpacity'  => [
				'type'      => 'range',
				'label'     => __( 'visual-editor::ve.cover_overlay_opacity' ),
				'min'       => 0,
				'max'       => 100,
				'step'      => 5,
				'suffix'    => '%',
				'default'   => 50,
				'inspector' => false,
			],
			'useContentWidth' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.use_content_width' ),
				'hint'    => __( 'visual-editor::ve.use_content_width_hint' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.cover_panel_content' ),
			],
			'contentMaxWidth' => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.content_width' ),
				'placeholder' => __( 'visual-editor::ve.auto' ),
				'default'     => '',
				'condition'   => [ 'useContentWidth', '==', true ],
				'panel'       => __( 'visual-editor::ve.cover_panel_content' ),
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Cover-specific fields.
	 * Content alignment and full width are toolbar-only (inspector => false).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'overlayColor'     => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.cover_overlay_color' ),
				'default' => '#000000',
			],
			'overlayOpacity'   => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.cover_overlay_opacity' ),
				'min'     => 0,
				'max'     => 100,
				'step'    => 5,
				'suffix'  => '%',
				'default' => 50,
			],
			'minHeight'        => [
				'type'      => 'text',
				'label'     => __( 'visual-editor::ve.min_height' ),
				'default'   => '430px',
				'inspector' => false,
			],
			'minHeightUnit'    => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.cover_min_height_unit' ),
				'options'   => [
					'px' => 'px',
					'vh' => 'vh',
					'vw' => 'vw',
				],
				'default'   => 'px',
				'inspector' => false,
			],
			'contentAlignment' => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.cover_content_alignment' ),
				'options'   => [
					'top-left'      => __( 'visual-editor::ve.top_left' ),
					'top-center'    => __( 'visual-editor::ve.cover_top_center' ),
					'top-right'     => __( 'visual-editor::ve.top_right' ),
					'center-left'   => __( 'visual-editor::ve.cover_center_left' ),
					'center'        => __( 'visual-editor::ve.center' ),
					'center-right'  => __( 'visual-editor::ve.cover_center_right' ),
					'bottom-left'   => __( 'visual-editor::ve.bottom_left' ),
					'bottom-center' => __( 'visual-editor::ve.cover_bottom_center' ),
					'bottom-right'  => __( 'visual-editor::ve.bottom_right' ),
				],
				'default'   => 'center',
				'inspector' => false,
			],
			'fullWidth'        => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.full_width' ),
				'default'   => false,
				'inspector' => false,
			],
		] );
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * Adds content position (3x3 grid), full width toggle, and media replace.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		$controls = parent::getToolbarControls();

		$controls[] = [
			'group'    => 'cover-position',
			'controls' => [
				[
					'type'  => 'content-position',
					'field' => 'contentAlignment',
					'label' => __( 'visual-editor::ve.cover_content_position' ),
					'icon'  => 'arrows-pointing-in',
				],
			],
		];

		$controls[] = [
			'group'    => 'cover-actions',
			'controls' => [
				[
					'type'  => 'button',
					'field' => 'fullHeight',
					'label' => __( 'visual-editor::ve.cover_full_height' ),
					'icon'  => 'arrows-pointing-out',
				],
				[
					'type'  => 'button',
					'field' => 'replace',
					'label' => __( 'visual-editor::ve.replace_image' ),
					'icon'  => 'arrow-path',
				],
			],
		];

		return $controls;
	}

	/**
	 * Get default inner blocks.
	 *
	 * Pre-populates with a Heading and Paragraph for overlay content.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getDefaultInnerBlocks(): array
	{
		return [
			[
				'type'       => 'heading',
				'attributes' => [ 'level' => 2, 'content' => '' ],
			],
			[
				'type'       => 'paragraph',
				'attributes' => [ 'content' => '' ],
			],
		];
	}

	/**
	 * Get block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getTransforms(): array
	{
		return [
			'image' => [
				'mediaUrl' => 'url',
				'alt'      => 'alt',
			],
			'group' => [],
		];
	}
}
