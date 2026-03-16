<?php

/**
 * Media & Text Block.
 *
 * Displays media (image or video) side-by-side with text content.
 * Supports configurable media position, width, vertical alignment,
 * image fill mode with focal point, and mobile stacking.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\MediaText
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media\MediaText;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Media & Text block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\MediaText
 *
 * @since      1.0.0
 */
class MediaTextBlock extends BaseBlock
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
			'mediaType' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.media_text_media_type' ),
				'options' => [
					'image' => __( 'visual-editor::ve.cover_type_image' ),
					'video' => __( 'visual-editor::ve.cover_type_video' ),
				],
				'default' => 'image',
			],
			'mediaUrl'  => [
				'type'    => 'media_picker',
				'label'   => __( 'visual-editor::ve.media_text_media_url' ),
				'default' => '',
			],
			'mediaAlt'  => [
				'type'      => 'textarea',
				'label'     => __( 'visual-editor::ve.alt_text' ),
				'hint'      => __( 'visual-editor::ve.alt_text_help' ),
				'default'   => '',
				'condition' => [ 'mediaType', '==', 'image' ],
				'panel'     => __( 'visual-editor::ve.media_text_panel_media' ),
			],
			'focalPoint' => [
				'type'      => 'focal_point',
				'label'     => __( 'visual-editor::ve.cover_focal_point' ),
				'default'   => [ 'x' => 0.5, 'y' => 0.5 ],
				'condition' => [ 'imageFill', '==', true ],
				'panel'     => __( 'visual-editor::ve.media_text_panel_media' ),
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Media & Text-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'mediaPosition'          => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.media_text_position' ),
				'options' => [
					'left'  => __( 'visual-editor::ve.left' ),
					'right' => __( 'visual-editor::ve.right' ),
				],
				'default' => 'left',
				'panel'   => __( 'visual-editor::ve.media_text_panel_layout' ),
			],
			'mediaWidth'             => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.media_text_media_width' ),
				'min'     => 25,
				'max'     => 75,
				'step'    => 5,
				'suffix'  => '%',
				'default' => 50,
				'panel'   => __( 'visual-editor::ve.media_text_panel_layout' ),
			],
			'verticalAlignment'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.vertical_alignment' ),
				'options' => [
					'top'    => __( 'visual-editor::ve.top' ),
					'center' => __( 'visual-editor::ve.center' ),
					'bottom' => __( 'visual-editor::ve.bottom' ),
				],
				'default' => 'top',
				'panel'   => __( 'visual-editor::ve.media_text_panel_layout' ),
			],
			'isStackedOnMobile'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.stack_on_mobile' ),
				'default' => true,
				'panel'   => __( 'visual-editor::ve.media_text_panel_layout' ),
			],
			'gridGap'                => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.media_text_gap' ),
				'placeholder' => '0',
				'default'     => '0',
				'panel'       => __( 'visual-editor::ve.media_text_panel_layout' ),
			],
			'imageFill'              => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.media_text_image_fill' ),
				'hint'    => __( 'visual-editor::ve.media_text_image_fill_hint' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.media_text_panel_image' ),
			],
			'mediaBorderRadius'      => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.border_radius' ),
				'placeholder' => '0',
				'default'     => '0',
				'panel'       => __( 'visual-editor::ve.media_text_panel_image' ),
			],
			'contentPadding'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.padding' ),
				'default' => '1rem',
				'panel'   => __( 'visual-editor::ve.media_text_panel_content' ),
			],
			'contentBackgroundColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.background_color' ),
				'default' => null,
				'panel'   => __( 'visual-editor::ve.media_text_panel_content' ),
			],
		] );
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * Adds media position toggle, vertical alignment, and media replace controls.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		$controls = parent::getToolbarControls();

		$controls[] = [
			'group'    => 'media-text-layout',
			'controls' => [
				[
					'type'  => 'button',
					'field' => 'mediaPosition',
					'label' => __( 'visual-editor::ve.media_text_toggle_position' ),
					'icon'  => 'arrows-right-left',
				],
				[
					'type'  => 'block-alignment',
					'field' => 'verticalAlignment',
					'label' => __( 'visual-editor::ve.vertical_alignment' ),
					'icon'  => 'bars-3',
				],
			],
		];

		$controls[] = [
			'group'    => 'media-text-media',
			'controls' => [
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
	 * Pre-populates with a Paragraph for the text content area.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getDefaultInnerBlocks(): array
	{
		return [
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
			'cover'   => [
				'mediaUrl'  => 'mediaUrl',
				'mediaAlt'  => 'alt',
				'mediaType' => 'mediaType',
			],
			'columns' => [],
		];
	}
}
