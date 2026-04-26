<?php

/**
 * Resolves the active global-styles record for the current theme,
 * compiles it to CSS via {@see GlobalStylesCompiler}, and caches the
 * result keyed on the record's id + updated_at timestamp.
 *
 * Cache key format: `visual-editor:global-styles:css:{id}:{updated_at}`.
 * Because the timestamp is part of the key, an Eloquent `save()` that
 * touches `updated_at` automatically misses the cache and recompiles
 * — a `saved` listener on the model then forgets the previous key so
 * stale entries do not pile up between updates.
 *
 * Missing-record fallback: if the singleton has not been created yet
 * (fresh install, before the first `lookup` request), the provider
 * compiles the bundled `default-base.php` payload — same defaults the
 * `/base` endpoint serves — so visitor pages never render unstyled
 * while the back-office is still being set up.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class GlobalStylesCssProvider
{
	protected const CACHE_PREFIX = 'visual-editor:global-styles:css';

	public function __construct(
		protected GlobalStylesCompiler $compiler,
		protected CacheRepository $cache,
		protected ConfigRepository $config,
	) {
	}

	/**
	 * Returns the compiled CSS for the active theme's record.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $theme  Override the active theme; defaults to
	 *                              `artisanpack.visual-editor.global_styles.theme`.
	 */
	public function css( ?string $theme = null ): string
	{
		$activeTheme = $theme ?? $this->activeTheme();

		$record = VisualEditorGlobalStyles::query()
			->where( 'theme', $activeTheme )
			->first();

		if ( null === $record ) {
			return $this->compiler->compile( $this->basePayload() );
		}

		$cacheKey = $this->cacheKey( $record );

		return $this->cache->rememberForever(
			$cacheKey,
			fn (): string => $this->compiler->compile( [
				'version'  => $record->version,
				'settings' => $record->settings ?? [],
				'styles'   => $record->styles ?? [],
			] )
		);
	}

	/**
	 * Forgets cached CSS for a given record. Called from the model
	 * `saved`/`deleted` listeners so the prior key does not linger.
	 *
	 * @since 1.0.0
	 */
	public function forget( VisualEditorGlobalStyles $record, ?string $previousUpdatedAt = null ): void
	{
		$this->cache->forget( $this->cacheKey( $record ) );

		if ( null !== $previousUpdatedAt && '' !== $previousUpdatedAt ) {
			$this->cache->forget( $this->cacheKeyFor( $record->getKey(), $previousUpdatedAt ) );
		}
	}

	/**
	 * Resolves the active theme slug from config; mirrors the controller
	 * fallback so the CSS layer scopes to the same record the API does.
	 *
	 * @since 1.0.0
	 */
	protected function activeTheme(): string
	{
		$theme = $this->config->get( 'artisanpack.visual-editor.global_styles.theme', 'artisanpack-base' );

		return is_string( $theme ) && '' !== $theme ? $theme : 'artisanpack-base';
	}

	/**
	 * Loads the bundled (or host-overridden) base payload — the
	 * fallback used when no record exists yet.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function basePayload(): array
	{
		$configured = $this->config->get( 'artisanpack.visual-editor.global_styles.base_path' );
		$path       = is_string( $configured ) && '' !== $configured
			? $configured
			: __DIR__ . '/../../resources/theme-json/default-base.php';

		if ( ! is_file( $path ) ) {
			return [
				'version'  => (int) $this->config->get( 'artisanpack.visual-editor.global_styles.schema_version', 3 ),
				'settings' => [],
				'styles'   => [],
			];
		}

		$payload = require $path;

		return is_array( $payload ) ? $payload : [];
	}

	/**
	 * Builds the cache key for a record at its current `updated_at`.
	 *
	 * @since 1.0.0
	 */
	protected function cacheKey( VisualEditorGlobalStyles $record ): string
	{
		$updatedAt = null !== $record->updated_at
			? $record->updated_at->format( 'YmdHis' )
			: '0';

		return $this->cacheKeyFor( $record->getKey(), $updatedAt );
	}

	/**
	 * Builds the cache key for a record id + timestamp pair.
	 *
	 * @since 1.0.0
	 */
	protected function cacheKeyFor( int|string|null $id, string $updatedAt ): string
	{
		return self::CACHE_PREFIX . ':' . (string) $id . ':' . $updatedAt;
	}
}
