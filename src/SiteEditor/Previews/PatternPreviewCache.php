<?php

/**
 * Pattern preview cache.
 *
 * Caches the front-end HTML {@see PatternPreviewRenderer} produces for each
 * pattern card preview (#832), so browsing the inserter or the Site Editor
 * pattern grid doesn't re-render every pattern on every open.
 *
 * The key covers everything the rendered HTML depends on:
 *
 * - the pattern's slug and renderable content (a hash of the filtered raw
 *   markup and the block tree), so editing a theme pattern file re-renders;
 * - the active theme, so switching themes re-renders;
 * - the viewing user, because block visibility rules (role / user
 *   conditions) and user-aware blocks such as Login/out render per user —
 *   a shared entry would show one user's render to another;
 * - the global-styles version (a hash of the resolved settings + styles);
 * - the renderer version (the installed package reference).
 *
 * Two counters make explicit invalidation cheap without tracking old keys:
 * a per-slug version bumped by {@see forgetPattern()} (pattern update or
 * delete) and a global generation bumped by {@see flush()} (global-styles
 * change). Entries also expire after the configured TTL so dynamic blocks
 * (Query loops, latest posts) pick up new site content.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Previews;

use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedGlobalStyles;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;
use Composer\InstalledVersions;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

class PatternPreviewCache
{
	/**
	 * Prefix shared by every key this cache writes.
	 *
	 * @since 1.13.0
	 */
	public const PREFIX = 'visual-editor.pattern-preview';

	/**
	 * Default lifetime of a cached preview, in seconds.
	 *
	 * @since 1.13.0
	 */
	public const DEFAULT_TTL = 3600;

	/**
	 * @since 1.13.0
	 */
	public function __construct( protected CacheRepository $cache )
	{
	}

	/**
	 * Return the cached HTML for a pattern, rendering and storing it on a
	 * miss. A render that throws is not cached, so the next request retries.
	 *
	 * @since 1.13.0
	 *
	 * @param  string                  $content  The renderable source the key hashes.
	 * @param  callable(): string      $render   Renders the pattern on a cache miss.
	 */
	public function remember( ResolvedPattern $pattern, string $content, string $theme, callable $render ): string
	{
		$key = $this->key( $pattern, $content, $theme );

		$cached = $this->cache->get( $key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$html = $render();

		$this->cache->put( $key, $html, $this->ttl() );

		return $html;
	}

	/**
	 * Build the cache key for a pattern's preview.
	 *
	 * @since 1.13.0
	 *
	 * @param  string  $content  The renderable source (filtered raw markup + block tree).
	 */
	public function key( ResolvedPattern $pattern, string $content, string $theme ): string
	{
		$fingerprint = implode( '|', [
			$pattern->slug,
			(string) $this->counter( $this->slugVersionKey( $pattern->slug ) ),
			sha1( $content ),
			$theme,
			$this->viewerIdentity(),
			$this->globalStylesVersion(),
			$this->rendererVersion(),
		] );

		return sprintf(
			'%s:%d:%s',
			self::PREFIX,
			$this->counter( $this->generationKey() ),
			sha1( $fingerprint ),
		);
	}

	/**
	 * Invalidate every cached preview of one pattern. Accepts either slug
	 * form; user patterns are keyed by their `user/`-prefixed storage slug.
	 *
	 * @since 1.13.0
	 */
	public function forgetPattern( string $slug ): void
	{
		$this->bump( $this->slugVersionKey( $slug ) );

		if ( ! str_starts_with( $slug, 'user/' ) ) {
			$this->bump( $this->slugVersionKey( 'user/' . $slug ) );
		}
	}

	/**
	 * Invalidate every cached preview — used when global styles change.
	 *
	 * @since 1.13.0
	 */
	public function flush(): void
	{
		$this->bump( $this->generationKey() );
	}

	/**
	 * Lifetime of a cached preview in seconds, from
	 * `artisanpack.visual-editor.pattern_previews.cache_ttl`.
	 *
	 * @since 1.13.0
	 */
	protected function ttl(): int
	{
		$ttl = config( 'artisanpack.visual-editor.pattern_previews.cache_ttl', self::DEFAULT_TTL );

		return is_numeric( $ttl ) && (int) $ttl > 0 ? (int) $ttl : self::DEFAULT_TTL;
	}

	/**
	 * The authenticated user's identifier, or an empty string for guests.
	 * Keeps cached renders per viewer, since visibility rules and
	 * user-aware blocks make the HTML user-specific.
	 *
	 * @since 1.13.0
	 */
	protected function viewerIdentity(): string
	{
		$id = auth()->id();

		return is_scalar( $id ) ? (string) $id : '';
	}

	/**
	 * Hash of the resolved global styles, or an empty string when none
	 * resolve (no cms-framework, no active theme).
	 *
	 * @since 1.13.0
	 */
	protected function globalStylesVersion(): string
	{
		$resolved = app( GlobalStylesResolver::class )->get();

		if ( ! $resolved instanceof ResolvedGlobalStyles ) {
			return '';
		}

		return sha1( (string) json_encode( [ $resolved->theme, $resolved->settings, $resolved->styles ] ) );
	}

	/**
	 * Installed package version + reference, so a package upgrade (or a
	 * new commit on a symlinked dev checkout) re-renders every preview.
	 *
	 * @since 1.13.0
	 */
	protected function rendererVersion(): string
	{
		try {
			return InstalledVersions::getVersion( 'artisanpack-ui/visual-editor' )
				. '@' . InstalledVersions::getReference( 'artisanpack-ui/visual-editor' );
		} catch ( Throwable ) {
			return 'unknown';
		}
	}

	/**
	 * @since 1.13.0
	 */
	protected function counter( string $key ): int
	{
		$value = $this->cache->get( $key );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * @since 1.13.0
	 */
	protected function bump( string $key ): void
	{
		$this->cache->forever( $key, $this->counter( $key ) + 1 );
	}

	/**
	 * @since 1.13.0
	 */
	protected function generationKey(): string
	{
		return self::PREFIX . ':generation';
	}

	/**
	 * @since 1.13.0
	 */
	protected function slugVersionKey( string $slug ): string
	{
		return self::PREFIX . ':slug:' . sha1( $slug );
	}
}
