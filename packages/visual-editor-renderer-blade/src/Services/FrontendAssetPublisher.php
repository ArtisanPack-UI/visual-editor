<?php

/**
 * Publishes the renderer's assets as static, fingerprinted files (#1033).
 *
 * Copies each bundled file to `public/vendor/visual-editor-renderer-blade/`
 * under a content-hashed name and writes the manifest
 * {@see FrontendAssetManifest} reads. Because the names change with the
 * content, the files can be cached forever. The web server sends the cache
 * headers for static files, so an Apache `.htaccess` is written alongside
 * them. nginx hosts add the equivalent `location` block themselves.
 *
 * Fingerprinted names never collide with the plain paths the asset route
 * answers, so a host that hasn't published is unaffected.
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
use Illuminate\Filesystem\Filesystem;

class FrontendAssetPublisher
{
	/**
	 * Apache rules for the published directory: `tabs.<hash>.css`, and the
	 * generated `<hash>.css`.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	protected const HTACCESS = <<<'APACHE'
		<IfModule mod_headers.c>
			<FilesMatch "(^|\.)[a-f0-9]{12,}\.(css|js)$">
				Header set Cache-Control "public, max-age=31536000, immutable"
			</FilesMatch>
		</IfModule>

		APACHE;

	public function __construct( protected Filesystem $files )
	{
	}

	/**
	 * Whether the host has published the assets, and so opted in to static
	 * delivery.
	 *
	 * @since 1.19.0
	 */
	public function isPublished(): bool
	{
		return is_file( FrontendAssetManifest::path() );
	}

	/**
	 * Copy every asset to its fingerprinted name and write the manifest.
	 * The manifest is written last, so a failed copy leaves pages linking
	 * to the previous set. Files from the previous publish are kept for
	 * any page cache still pointing at them; older ones are removed.
	 *
	 * @since 1.19.0
	 *
	 * @return array<string, string> Public path => fingerprinted path.
	 */
	public function publish(): array
	{
		$directory = public_path( FrontendAssets::PUBLIC_DIRECTORY );
		$previous  = $this->publishedFiles();
		$published = [];

		foreach ( FrontendAssets::sources() as $path => $source ) {
			$fingerprinted = FrontendAssets::fingerprintedPath( $path, FrontendAssets::hash( $source ) );
			$target        = $directory . '/' . $fingerprinted;

			$this->files->ensureDirectoryExists( dirname( $target ) );
			$this->files->copy( $source, $target );

			$published[ $path ] = $fingerprinted;
		}

		$this->files->put( $directory . '/.htaccess', self::HTACCESS );

		$manifest = FrontendAssetManifest::path();

		$this->files->put( $manifest . '.tmp', (string) json_encode( [
			'signature' => FrontendAssets::signature(),
			'files'     => $published,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$this->files->move( $manifest . '.tmp', $manifest );

		$this->prune( $directory, [ ...array_values( $previous ), ...array_values( $published ) ] );

		return $published;
	}

	/**
	 * The file map of the current manifest, if any.
	 *
	 * @since 1.19.0
	 *
	 * @return array<string, string>
	 */
	protected function publishedFiles(): array
	{
		if ( ! $this->isPublished() ) {
			return [];
		}

		$manifest = json_decode( (string) $this->files->get( FrontendAssetManifest::path() ), true );

		return is_array( $manifest['files'] ?? null ) ? $manifest['files'] : [];
	}

	/**
	 * Remove fingerprinted files that aren't in the keep list.
	 *
	 * @since 1.19.0
	 *
	 * @param  string              $directory  Published assets directory.
	 * @param  array<int, string>  $keep       Fingerprinted paths to keep.
	 */
	protected function prune( string $directory, array $keep ): void
	{
		foreach ( [ '', 'frontend/' ] as $prefix ) {
			foreach ( [ 'css', 'js' ] as $extension ) {
				foreach ( $this->files->glob( $directory . '/' . $prefix . '*.' . $extension ) as $file ) {
					$path = $prefix . basename( $file );

					if ( null !== FrontendAssets::parseFingerprint( $path ) && ! in_array( $path, $keep, true ) ) {
						$this->files->delete( $file );
					}
				}
			}
		}
	}
}
