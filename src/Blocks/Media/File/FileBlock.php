<?php

/**
 * File Block.
 *
 * Displays a downloadable file with filename, size, and download button.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\File
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media\File;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * File block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\File
 *
 * @since      1.0.0
 */
class FileBlock extends BaseBlock
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
			'url'                => [
				'type'    => 'media_picker',
				'label'   => __( 'visual-editor::ve.file_url' ),
				'default' => '',
			],
			'downloadButtonText' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.download_button_text' ),
				'default' => 'Download',
			],
			'showDownloadButton' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_download' ),
				'default' => true,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'openInNewTab'       => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.open_in_new_tab' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'displayPreview'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.display_pdf_preview' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'previewHeight'      => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.pdf_preview_height' ),
				'hint'    => __( 'visual-editor::ve.pdf_preview_height_hint' ),
				'default' => 600,
				'min'     => 200,
				'max'     => 2000,
				'step'    => 10,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
		];
	}
}
