<?php

/**
 * Publish Views Command.
 *
 * Artisan command for publishing visual editor views to the application's
 * resources directory. Supports granular publishing via --tag for different
 * view groups (site-editor, listings, editors, styles, components).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Artisan command to publish visual editor views.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @since      1.0.0
 */
class PublishViewsCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $signature = 've:publish
		{--views : Publish view files}
		{--tag= : Publish only a specific view group (site-editor, listings, editors, styles, components)}
		{--force : Overwrite existing published files}';

	/**
	 * The console command description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'Publish visual editor views for customization';

	/**
	 * The base path for package views.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $sourcePath = '';

	/**
	 * Execute the console command.
	 *
	 * @since 1.0.0
	 *
	 * @param Filesystem $files The filesystem instance.
	 *
	 * @return int
	 */
	public function handle( Filesystem $files ): int
	{
		if ( ! $this->option( 'views' ) ) {
			$this->components->error( __( 'visual-editor::ve.publish_views_flag_required' ) );

			return self::FAILURE;
		}

		$this->sourcePath = dirname( __DIR__, 3 ) . '/resources/views';
		$destinationBase  = resource_path( 'views/vendor/visual-editor' );
		$tag              = $this->option( 'tag' );
		$force            = (bool) $this->option( 'force' );

		if ( null !== $tag && '' !== $tag ) {
			$tagMap = $this->getTagMap();

			if ( ! isset( $tagMap[ $tag ] ) ) {
				$this->components->error(
					__( 'visual-editor::ve.publish_unknown_tag', [ 'tag' => $tag, 'tags' => implode( ', ', array_keys( $tagMap ) ) ] ),
				);

				return self::FAILURE;
			}

			$publishMap = $tagMap[ $tag ];
		} else {
			$publishMap = $this->getAllViewsMap();
		}

		$publishedCount = 0;

		try {
			foreach ( $publishMap as $source => $destination ) {
				$dest = $destinationBase . '/' . $destination;

				if ( $files->isDirectory( $source ) ) {
					$publishedCount += $this->publishDirectory( $files, $source, $dest, $force );
				} elseif ( $files->exists( $source ) ) {
					$publishedCount += $this->publishFile( $files, $source, $dest, $force );
				} else {
					$this->components->warn( __( 'visual-editor::ve.publish_views_source_skipped', [ 'source' => $source ] ) );
				}
			}
		} catch ( RuntimeException $e ) {
			$this->components->error( $e->getMessage() );

			return self::FAILURE;
		}

		$this->components->info(
			__( 'visual-editor::ve.publish_views_complete', [ 'count' => $publishedCount ] ),
		);

		return self::SUCCESS;
	}

	/**
	 * Get the tag-to-paths mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	protected function getTagMap(): array
	{
		$source = $this->sourcePath;

		return [
			'site-editor' => [
				$source . '/livewire/site-editor/hub.blade.php'       => 'livewire/site-editor/hub.blade.php',
				$source . '/layouts/site-editor.blade.php'            => 'layouts/site-editor.blade.php',
			],
			'listings' => [
				$source . '/livewire/site-editor/template-listing.blade.php' => 'livewire/site-editor/template-listing.blade.php',
				$source . '/livewire/site-editor/part-listing.blade.php'     => 'livewire/site-editor/part-listing.blade.php',
				$source . '/livewire/site-editor/pattern-listing.blade.php'  => 'livewire/site-editor/pattern-listing.blade.php',
			],
			'editors' => [
				$source . '/livewire/site-editor/part-editor.blade.php'         => 'livewire/site-editor/part-editor.blade.php',
				$source . '/livewire/site-editor/pattern-editor.blade.php'      => 'livewire/site-editor/pattern-editor.blade.php',
				$source . '/livewire/site-editor/template-editor.blade.php'     => 'livewire/site-editor/template-editor.blade.php',
				$source . '/components/template-part-editor.blade.php'          => 'components/template-part-editor.blade.php',
				$source . '/components/pattern-editor.blade.php'                => 'components/pattern-editor.blade.php',
			],
			'styles' => [
				$source . '/livewire/site-editor/global-styles-page.blade.php' => 'livewire/site-editor/global-styles-page.blade.php',
			],
			'components' => [
				$source . '/components' => 'components',
			],
		];
	}

	/**
	 * Get all views as a flat publish map.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function getAllViewsMap(): array
	{
		$maps = $this->getTagMap();
		$all  = [];
		$seen = [];

		foreach ( $maps as $entries ) {
			foreach ( $entries as $source => $destination ) {
				if ( isset( $seen[ $destination ] ) ) {
					continue;
				}

				$all[ $source ]       = $destination;
				$seen[ $destination ] = true;
			}
		}

		return $all;
	}

	/**
	 * Publish a directory recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param Filesystem $files       The filesystem instance.
	 * @param string     $source      The source directory path.
	 * @param string     $destination The destination directory path.
	 * @param bool       $force       Whether to overwrite existing files.
	 *
	 * @return int The number of files published.
	 */
	protected function publishDirectory( Filesystem $files, string $source, string $destination, bool $force ): int
	{
		$count       = 0;
		$sourceFiles = $files->allFiles( $source );

		foreach ( $sourceFiles as $file ) {
			$relativePath = $file->getRelativePathname();
			$destPath     = $destination . '/' . $relativePath;

			$count += $this->publishFile( $files, $file->getPathname(), $destPath, $force );
		}

		return $count;
	}

	/**
	 * Publish a single file.
	 *
	 * @since 1.0.0
	 *
	 * @param Filesystem $files       The filesystem instance.
	 * @param string     $source      The source file path.
	 * @param string     $destination The destination file path.
	 * @param bool       $force       Whether to overwrite existing files.
	 *
	 * @return int 1 if published, 0 if skipped.
	 */
	protected function publishFile( Filesystem $files, string $source, string $destination, bool $force ): int
	{
		if ( $files->exists( $destination ) && ! $force ) {
			return 0;
		}

		$files->ensureDirectoryExists( dirname( $destination ) );

		if ( ! $files->copy( $source, $destination ) ) {
			throw new RuntimeException( "Failed to copy '{$source}' to '{$destination}'." );
		}

		return 1;
	}
}
