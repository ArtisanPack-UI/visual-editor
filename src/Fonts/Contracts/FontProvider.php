<?php

/**
 * Font source provider contract.
 *
 * A font provider is a source the Font Library can browse and install from —
 * Google Fonts, Bunny Fonts, custom uploads, or any third-party catalog a
 * package chooses to register. Providers are keyed by {@see key()} and
 * collected in {@see \ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry},
 * which is seeded through the `ap.visualEditor.registerFontSources` filter so a
 * package can add a source with no core changes.
 *
 * Implementations expose three read operations the Font Library modal drives —
 * {@see searchCatalog()} for browsing, {@see getFamily()} for a family's
 * installable faces, and {@see fetchFace()} for the raw file bytes the
 * installer self-hosts. Catalog and family payloads are plain arrays rather
 * than Eloquent models so a provider never has to know about the Font Library's
 * storage schema.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Contracts;

interface FontProvider
{
	/**
	 * The provider's registry key.
	 *
	 * A short, stable slug (`google`, `bunny`, `custom`) used as the registry
	 * identifier and persisted on installed fonts as their `provider` column.
	 * Must be lowercase and match the registry's key pattern.
	 *
	 * @since 1.7.0
	 */
	public function key(): string;

	/**
	 * The human-readable provider name shown in the Font Library modal.
	 *
	 * Should be wrapped with `__()` by the implementation so it localizes.
	 *
	 * @since 1.7.0
	 */
	public function label(): string;

	/**
	 * Whether fonts from this provider can be fetched and served locally.
	 *
	 * `true` means {@see fetchFace()} returns downloadable file bytes the
	 * installer stores on the configured disk (GDPR-safe self-hosting). A
	 * provider that only offers runtime CDN links returns `false`, and the
	 * installer refuses to install from it in v1.
	 *
	 * @since 1.7.0
	 */
	public function isSelfHostable(): bool;

	/**
	 * Browse or search the provider's catalog, one page at a time.
	 *
	 * An empty query returns the provider's default/popular listing. Paging is
	 * one-based; a provider that cannot page may ignore it and report
	 * `has_more => false`.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $query  The search term, or an empty string to browse.
	 * @param  int     $page   The one-based page number.
	 *
	 * @return array{families: array<int, array<string, mixed>>, page: int, has_more: bool}
	 *         A page of family summaries. Each family entry carries at least a
	 *         `slug` and `family` (display name); providers may add `category`,
	 *         `variants`, and a `preview_url`.
	 */
	public function searchCatalog( string $query, int $page = 1 ): array;

	/**
	 * Resolve a single family and its installable faces by slug.
	 *
	 * Returns `null` when the provider does not recognize the slug.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $slug  The provider-scoped family slug.
	 *
	 * @return array<string, mixed>|null A family detail array. Carries at least
	 *         `slug`, `family`, `is_variable`, and a `faces` list of
	 *         `{weight, style}` variants; variable fonts may add an `axes` map.
	 */
	public function getFamily( string $slug ): ?array;

	/**
	 * Fetch the raw font-file bytes for a single face.
	 *
	 * The installer writes the returned bytes to the configured disk. Only
	 * called for providers where {@see isSelfHostable()} is `true`.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $slug    The provider-scoped family slug.
	 * @param  string  $weight  The face weight (e.g. `400`, `700`).
	 * @param  string  $style   The face style (`normal` or `italic`).
	 *
	 * @return string The raw binary contents of the face file (typically WOFF2).
	 */
	public function fetchFace( string $slug, string $weight, string $style ): string;
}
