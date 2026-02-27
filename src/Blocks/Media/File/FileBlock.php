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
			'url'      => [
				'type'    => 'url',
				'label'   => __( 'visual-editor::ve.file_url' ),
				'default' => '',
			],
			'filename' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.filename' ),
				'default' => '',
			],
			'fileSize' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.file_size' ),
				'default' => '',
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
			'showDownloadButton' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_download' ),
				'default' => true,
			],
		];
	}
}
