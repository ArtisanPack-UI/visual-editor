<?php

/**
 * File Block.
 *
 * Displays a downloadable file with filename, size, and download button.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * File block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @since      1.0.0
 */
class FileBlock extends BaseBlock
{
	protected string $type = 'file';

	protected string $name = 'File';

	protected string $description = 'Add a downloadable file';

	protected string $icon = 'document';

	protected string $category = 'media';

	protected array $keywords = [ 'download', 'attachment', 'pdf' ];

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
