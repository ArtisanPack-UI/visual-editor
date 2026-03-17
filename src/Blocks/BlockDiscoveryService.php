<?php

/**
 * Block Discovery Service.
 *
 * Provides filesystem auto-discovery of block types and production
 * caching via a PHP manifest file. Supports additional discovery
 * paths for third-party block packages.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

/**
 * Service for discovering block types from the filesystem and
 * managing the production block manifest cache.
 *
 * Third-party packages can register additional discovery paths
 * via `addDiscoveryPath()` or the `ap.visualEditor.discoveryPaths`
 * filter hook.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @since      2.0.0
 */
class BlockDiscoveryService
{
	/**
	 * The base namespace for core block classes.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected string $baseNamespace = 'ArtisanPackUI\\VisualEditor\\Blocks';

	/**
	 * The core block categories to scan.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, string>
	 */
	protected array $categories = [ 'Text', 'Media', 'Layout', 'Interactive', 'Embed', 'Dynamic' ];

	/**
	 * Additional discovery paths registered by third-party packages.
	 *
	 * Each entry maps a filesystem path to a base namespace.
	 *
	 * @since 2.1.0
	 *
	 * @var array<int, array{path: string, namespace: string}>
	 */
	protected array $additionalPaths = [];

	/**
	 * Register an additional path for block discovery.
	 *
	 * Third-party packages should call this method in their service
	 * provider to have their blocks discovered alongside core blocks.
	 * The path should contain category subdirectories, each with
	 * block subdirectories containing a block.json file.
	 *
	 * @since 2.1.0
	 *
	 * @param string $path      The absolute path to the blocks directory.
	 * @param string $namespace The base PHP namespace for block classes.
	 *
	 * @return void
	 */
	public function addDiscoveryPath( string $path, string $namespace ): void
	{
		$this->additionalPaths[] = [
			'path'      => $path,
			'namespace' => $namespace,
		];
	}

	/**
	 * Discover all block types by scanning the filesystem.
	 *
	 * Scans core block directories and any additional registered paths.
	 * For each directory, looks for {Category}/{Block}/block.json files,
	 * resolves the FQCN from the directory structure, and returns an
	 * array of block entries.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array{type: string, class: class-string, dir: string, metadata: array<string, mixed>}>
	 */
	public function discover(): array
	{
		$coreBlocksDir = dirname( __DIR__ ) . '/Blocks';

		$discoveryPaths = [
			[
				'path'       => $coreBlocksDir,
				'namespace'  => $this->baseNamespace,
				'categories' => $this->categories,
			],
		];

		foreach ( $this->additionalPaths as $additional ) {
			$discoveryPaths[] = [
				'path'       => $additional['path'],
				'namespace'  => $additional['namespace'],
				'categories' => $this->discoverCategories( $additional['path'] ),
			];
		}

		$discoveryPaths = veApplyFilters( 'ap.visualEditor.discoveryPaths', $discoveryPaths );

		$blocks = [];

		foreach ( $discoveryPaths as $discoveryPath ) {
			$discovered = $this->discoverInPath(
				$discoveryPath['path'],
				$discoveryPath['namespace'],
				$discoveryPath['categories'],
			);

			$blocks = array_merge( $blocks, $discovered );
		}

		return $blocks;
	}

	/**
	 * Get the path to the block manifest cache file.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function manifestPath(): string
	{
		return app()->bootstrapPath( 'cache/visual-editor-blocks.php' );
	}

	/**
	 * Check whether the manifest cache file exists.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function manifestExists(): bool
	{
		return file_exists( $this->manifestPath() );
	}

	/**
	 * Load the cached manifest file.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array{type: string, class: class-string, dir: string, metadata: array<string, mixed>}>|null
	 */
	public function loadManifest(): ?array
	{
		$path = $this->manifestPath();

		if ( ! file_exists( $path ) ) {
			return null;
		}

		$manifest = require $path;

		if ( ! is_array( $manifest ) ) {
			return null;
		}

		return $manifest;
	}

	/**
	 * Discover blocks within a specific base path.
	 *
	 * @since 2.1.0
	 *
	 * @param string              $basePath   The base directory to scan.
	 * @param string              $namespace  The base PHP namespace.
	 * @param array<int, string>  $categories The category subdirectories to scan.
	 *
	 * @return array<int, array{type: string, class: class-string, dir: string, metadata: array<string, mixed>}>
	 */
	protected function discoverInPath( string $basePath, string $namespace, array $categories ): array
	{
		$blocks = [];

		foreach ( $categories as $category ) {
			$categoryDir = $basePath . '/' . $category;

			if ( ! is_dir( $categoryDir ) ) {
				continue;
			}

			$entries = scandir( $categoryDir );

			if ( false === $entries ) {
				continue;
			}

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}

				$blockDir = $categoryDir . '/' . $entry;
				$jsonPath = $blockDir . '/block.json';

				if ( ! file_exists( $jsonPath ) ) {
					continue;
				}

				$contents = file_get_contents( $jsonPath );

				if ( false === $contents ) {
					continue;
				}

				$metadata = json_decode( $contents, true );

				if ( ! is_array( $metadata ) || ! isset( $metadata['type'] ) ) {
					continue;
				}

				$className = $namespace . '\\' . $category . '\\' . $entry . '\\' . $entry . 'Block';

				if ( ! class_exists( $className ) ) {
					$className = $namespace . '\\' . $category . '\\' . $entry . '\\' . $entry;

					if ( ! class_exists( $className ) ) {
						continue;
					}
				}

				$blocks[] = [
					'type'     => $metadata['type'],
					'class'    => $className,
					'dir'      => $blockDir,
					'metadata' => $metadata,
				];
			}
		}

		return $blocks;
	}

	/**
	 * Discover category subdirectories in a given path.
	 *
	 * Used for third-party discovery paths where categories are
	 * not known ahead of time.
	 *
	 * @since 2.1.0
	 *
	 * @param string $path The base directory to scan for categories.
	 *
	 * @return array<int, string>
	 */
	protected function discoverCategories( string $path ): array
	{
		if ( ! is_dir( $path ) ) {
			return [];
		}

		$entries    = scandir( $path );
		$categories = [];

		if ( false === $entries ) {
			return [];
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			if ( is_dir( $path . '/' . $entry ) ) {
				$categories[] = $entry;
			}
		}

		return $categories;
	}
}
