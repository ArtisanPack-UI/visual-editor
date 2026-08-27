<?php

/**
 * Custom upload font source provider.
 *
 * Represents the user's own uploaded fonts as a Font Library source so they sit
 * beside Google and Bunny in the modal. Unlike a catalog provider it has nothing
 * to browse: fonts arrive as file uploads rather than being fetched from a
 * remote index, so {@see searchCatalog()} is always empty and {@see getFamily()}
 * always misses. Ingestion happens through
 * {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller::installUpload()},
 * which self-hosts the uploaded bytes and reads any variable-axis metadata from
 * the files themselves via {@see \ArtisanPackUI\VisualEditor\Fonts\Support\VariableFontMetadataParser}.
 *
 * The provider is still {@see isSelfHostable()} — uploaded faces are stored on
 * the configured disk exactly like fetched ones — but {@see fetchFace()} throws:
 * there is no remote origin to re-fetch a face from, so the standard
 * {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller::install()} path
 * does not apply to it.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Providers;

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;

class CustomUploadProvider implements FontProvider
{
	/**
	 * The registry key uploaded fonts are persisted under as their `provider`.
	 */
	public const KEY = 'custom';

	/**
	 * @since 1.7.0
	 */
	public function key(): string
	{
		return self::KEY;
	}

	/**
	 * @since 1.7.0
	 */
	public function label(): string
	{
		return __( 'Custom Upload' );
	}

	/**
	 * Uploaded faces are stored on the configured disk, so the source is
	 * self-hostable — but faces are ingested by
	 * {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller::installUpload()},
	 * not fetched through {@see fetchFace()}.
	 *
	 * @since 1.7.0
	 */
	public function isSelfHostable(): bool
	{
		return true;
	}

	/**
	 * Custom uploads have no browsable catalog.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $query  Ignored.
	 * @param  int     $page   The echoed page number.
	 *
	 * @return array{families: array<int, array<string, mixed>>, page: int, has_more: bool}
	 */
	public function searchCatalog( string $query, int $page = 1 ): array
	{
		return [
			'families' => [],
			'page'     => max( 1, $page ),
			'has_more' => false,
		];
	}

	/**
	 * Custom uploads are not resolvable by slug against a catalog.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $slug  Ignored.
	 */
	public function getFamily( string $slug ): ?array
	{
		return null;
	}

	/**
	 * Uploaded faces have no remote origin to fetch from.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $slug    Ignored.
	 * @param  string  $weight  Ignored.
	 * @param  string  $style   Ignored.
	 */
	public function fetchFace( string $slug, string $weight, string $style ): string
	{
		throw new FontProviderException(
			'Custom fonts are ingested from uploaded files, not fetched from a remote source.'
		);
	}
}
