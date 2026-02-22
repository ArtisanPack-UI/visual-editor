<?php

/**
 * Heading Block.
 *
 * Renders a heading element (h1-h6) with configurable level,
 * alignment, colors, and typography settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Heading block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text
 *
 * @since      1.0.0
 */
class HeadingBlock extends BaseBlock
{
	/**
	 * The block type identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $type = 'heading';

	/**
	 * The human-readable block name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $name = 'Heading';

	/**
	 * The block description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $description = 'Add a heading to your content';

	/**
	 * The block icon identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $icon = 'h1';

	/**
	 * The block category.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $category = 'text';

	/**
	 * Searchable keywords for the block.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected array $keywords = [ 'title', 'h1', 'h2', 'h3', 'header' ];

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
			'text'  => [
				'type'        => 'rich_text',
				'label'       => __( 'visual-editor::ve.block_heading_placeholder' ),
				'placeholder' => __( 'visual-editor::ve.block_heading_placeholder' ),
				'toolbar'     => [ 'bold', 'italic', 'link' ],
				'default'     => '',
			],
			'level' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.heading_level' ),
				'options' => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'default' => 'h2',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [
			'alignment'       => [
				'type'    => 'alignment',
				'label'   => __( 'visual-editor::ve.text_alignment' ),
				'options' => [ 'left', 'center', 'right' ],
				'default' => 'left',
			],
			'textColor'       => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => null,
			],
			'backgroundColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.background_color' ),
				'default' => null,
			],
			'fontSize'        => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.font_size' ),
				'options' => [
					'small' => __( 'visual-editor::ve.small' ),
					'base'  => __( 'visual-editor::ve.normal' ),
					'large' => __( 'visual-editor::ve.large' ),
					'xl'    => __( 'visual-editor::ve.extra_large' ),
				],
				'default' => null,
			],
		];
	}

	/**
	 * Get available block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [
			'paragraph' => [
				'text' => 'text',
			],
			'quote'     => [
				'text' => 'text',
			],
		];
	}

	/**
	 * Get the block's supported features.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
	{
		return [
			'align'      => [ 'left', 'center', 'right', 'wide', 'full' ],
			'color'      => [
				'text'       => true,
				'background' => true,
			],
			'typography' => [
				'fontSize'   => true,
				'fontFamily' => false,
			],
			'spacing'    => [
				'margin'  => false,
				'padding' => false,
			],
			'border'     => false,
			'anchor'     => true,
			'className'  => true,
		];
	}
}
