<?php

/**
 * Block Cache Command.
 *
 * Caches the block discovery manifest for production performance.
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
 * Artisan command to cache block metadata for production.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @since      2.0.0
 */
class BlockCacheCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $signature = 've:cache';

	/**
	 * The console command description.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $description = 'Cache the visual editor block metadata for faster registration';

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
		$this->components->info( __( 'visual-editor::ve.caching_block_metadata' ) );

		$blocks   = $discovery->discover();
		$manifest = '<?php return ' . var_export( $blocks, true ) . ';' . PHP_EOL;
		$path     = $discovery->manifestPath();

		$dir = dirname( $path );

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}

		file_put_contents( $path, $manifest );

		$this->components->info(
			__( 'visual-editor::ve.block_metadata_cached', [ 'count' => count( $blocks ) ] ),
		);

		return self::SUCCESS;
	}
}
