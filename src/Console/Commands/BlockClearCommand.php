<?php

/**
 * Block Clear Command.
 *
 * Clears the cached block discovery manifest.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Console\Commands;

use ArtisanPackUI\VisualEditor\Blocks\BlockDiscoveryService;
use Illuminate\Console\Command;

/**
 * Artisan command to clear the cached block manifest.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @since      2.0.0
 */
class BlockClearCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $signature = 've:clear';

	/**
	 * The console command description.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $description = 'Clear the cached visual editor block metadata';

	/**
	 * Execute the console command.
	 *
	 * @since 2.0.0
	 *
	 * @param BlockDiscoveryService $discovery The block discovery service.
	 *
	 * @return int
	 */
	public function handle( BlockDiscoveryService $discovery ): int
	{
		$path = $discovery->manifestPath();

		if ( file_exists( $path ) ) {
			unlink( $path );
		}

		$this->components->info( __( 'visual-editor::ve.block_cache_cleared' ) );

		return self::SUCCESS;
	}
}
