<?php

/**
 * Maps asset paths to their published, fingerprinted names (#1033).
 *
 * `php artisan visual-editor:publish-assets` writes
 * `public/vendor/visual-editor-renderer-blade/manifest.json`. While that file
 * matches the installed package, `<x-ve-blocks-styles />` links to the
 * fingerprinted copies, which the web server serves without PHP. With no
 * manifest, or a stale one, every path passes through unchanged and the
 * package's asset route serves the current file.
 *
 * Scoped so a long-lived worker re-reads the manifest for each request and
 * picks up a publish that happened while it was running.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

use ArtisanPackUI\VisualEditorRendererBlade\Support\FrontendAssets;

class FrontendAssetManifest
{
	/**
	 * Manifest file name inside the public assets directory.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const FILE = 'manifest.json';

	/**
	 * Public path => fingerprinted path, loaded on first use.
	 *
	 * @var array<string, string>|null
	 */
	protected ?array $files = null;

	/**
	 * Absolute path of the manifest file.
	 *
	 * @since 1.19.0
	 */
	public static function path(): string
	{
		return public_path( FrontendAssets::PUBLIC_DIRECTORY . '/' . self::FILE );
	}

	/**
	 * Path to link to for an asset: its fingerprinted name when published,
	 * otherwise the path it was given.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $path  Public path, e.g. `frontend/tabs.css`.
	 */
	public function resolve( string $path ): string
	{
		$this->files ??= $this->load();

		return $this->files[ $path ] ?? $path;
	}

	/**
	 * Read the manifest, ignoring one that doesn't match the installed
	 * package: linking to it would serve old CSS after an update nobody
	 * published.
	 *
	 * @since 1.19.0
	 *
	 * @return array<string, string>
	 */
	protected function load(): array
	{
		$file = self::path();

		if ( ! is_file( $file ) ) {
			return [];
		}

		$manifest = json_decode( (string) file_get_contents( $file ), true );

		if ( ! is_array( $manifest )
			|| ! is_array( $manifest['files'] ?? null )
			|| FrontendAssets::signature() !== ( $manifest['signature'] ?? null )
		) {
			return [];
		}

		return $manifest['files'];
	}
}
