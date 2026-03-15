<?php

/**
 * Block Discovery Service.
 *
 * Provides filesystem auto-discovery of block types and production
 * caching via a PHP manifest file.
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
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @since      2.0.0
 */
class BlockDiscoveryService
{
	/**
	 * The base namespace for block classes.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected string $baseNamespace = 'ArtisanPackUI\\VisualEditor\\Blocks';

	/**
	 * The block categories to scan.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, string>
	 */
	protected array $categories = [ 'Text', 'Media', 'Layout', 'Interactive', 'Embed' ];

	/**
	 * Discover all block types by scanning the filesystem.
	 *
	 * Scans src/Blocks/{Category}/{Block}/block.json files, resolves the
	 * FQCN from the directory structure, and returns an array of block entries.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array{type: string, class: class-string, dir: string, metadata: array<string, mixed>}>
	 */
	public function discover(): array
	{
		$blocksDir = dirname( __DIR__ ) . '/Blocks';
		$blocks    = [];

		foreach ( $this->categories as $category ) {
			$categoryDir = $blocksDir . '/' . $category;

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

				$blockDir  = $categoryDir . '/' . $entry;
				$jsonPath  = $blockDir . '/block.json';

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

				$className = $this->baseNamespace . '\\' . $category . '\\' . $entry . '\\' . $entry . 'Block';

				if ( ! class_exists( $className ) ) {
					$className = $this->baseNamespace . '\\' . $category . '\\' . $entry . '\\' . $entry;

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
}
