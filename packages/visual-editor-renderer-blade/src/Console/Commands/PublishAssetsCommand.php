<?php

/**
 * `visual-editor:publish-assets` Artisan command (#1033).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Console\Commands;

use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendAssetPublisher;
use ArtisanPackUI\VisualEditorRendererBlade\Support\FrontendAssets;
use Illuminate\Console\Command;

class PublishAssetsCommand extends Command
{
	/**
	 * @var string
	 */
	protected $signature = 'visual-editor:publish-assets';

	/**
	 * @var string
	 */
	protected $description = 'Publish the Blade renderer assets to public/ as fingerprinted static files';

	/**
	 * Publish the assets. Later `composer install` / `composer update` runs
	 * refresh them on their own.
	 *
	 * @since 1.19.0
	 *
	 * @param  FrontendAssetPublisher  $publisher  Writes the files and manifest.
	 */
	public function handle( FrontendAssetPublisher $publisher ): int
	{
		$published = $publisher->publish();

		$this->components->info( sprintf(
			'Published %d assets to public/%s.',
			count( $published ),
			FrontendAssets::PUBLIC_DIRECTORY,
		) );

		return self::SUCCESS;
	}
}
