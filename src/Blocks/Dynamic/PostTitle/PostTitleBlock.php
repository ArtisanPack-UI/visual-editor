<?php

/**
 * Post Title Block.
 *
 * Renders the content item's title dynamically from the current
 * content context with configurable heading level and optional link.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTitle
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTitle;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Title block for the visual editor.
 *
 * Displays the current content item's title with configurable heading
 * level (h1-h6, p, span) and optional link to the content page.
 * Resolves the title from the content context via filter hooks,
 * allowing applications to provide data from any model.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTitle
 *
 * @since      2.0.0
 */
class PostTitleBlock extends BaseBlock
{
	/**
	 * Toolbar-format level options shared between inspector and toolbar.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, array{value: string, label: string}>
	 */
	private const LEVEL_OPTIONS = [
		[ 'value' => 'h1', 'label' => 'H1' ],
		[ 'value' => 'h2', 'label' => 'H2' ],
		[ 'value' => 'h3', 'label' => 'H3' ],
		[ 'value' => 'h4', 'label' => 'H4' ],
		[ 'value' => 'h5', 'label' => 'H5' ],
		[ 'value' => 'h6', 'label' => 'H6' ],
		[ 'value' => 'p', 'label' => 'P' ],
		[ 'value' => 'span', 'label' => 'Span' ],
	];

	/**
	 * Get the content field schema for the inspector panel.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'level'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.html_element' ),
				'options' => $this->inspectorLevelOptions(),
				'default' => 'h1',
			],
			'isLink'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_title_make_link' ),
				'default' => false,
			],
			'linkTarget' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.link_target' ),
				'options' => [
					'_self'  => __( 'visual-editor::ve.same_window' ),
					'_blank' => __( 'visual-editor::ve.new_window' ),
				],
				'default' => '_self',
			],
			'rel'        => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_title_link_rel' ),
				'default' => '',
			],
		];
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		return [
			[
				'group'    => 'block',
				'controls' => [
					[
						'type'    => 'select',
						'field'   => 'level',
						'source'  => 'content',
						'options' => self::LEVEL_OPTIONS,
					],
				],
			],
		];
	}

	/**
	 * Build inspector select options from the shared level list.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, string>
	 */
	private function inspectorLevelOptions(): array
	{
		$options = [];

		foreach ( self::LEVEL_OPTIONS as $opt ) {
			$label = $opt['label'];

			if ( 'P' === $label ) {
				$label = __( 'visual-editor::ve.paragraph' );
			} elseif ( 'Span' === $label ) {
				$label = __( 'visual-editor::ve.span' );
			}

			$options[ $opt['value'] ] = $label;
		}

		return $options;
	}
}
