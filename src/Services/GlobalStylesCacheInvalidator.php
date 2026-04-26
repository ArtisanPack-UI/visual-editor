<?php

/**
 * Wires the {@see VisualEditorGlobalStyles} model's `saved` and
 * `deleted` events to {@see GlobalStylesCssProvider::forget()} so a
 * mutation always evicts the prior compiled CSS.
 *
 * The provider already keys cache entries on (id, updated_at) so a
 * fresh `save()` would naturally miss the cache regardless. The
 * explicit invalidation is belt-and-suspenders: it forgets both the
 * new key (in case a stale entry somehow exists) and the prior
 * `updated_at` key so cold reads after a long quiet period do not
 * leave dangling entries behind.
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
use Illuminate\Support\Carbon;

class GlobalStylesCacheInvalidator
{
	/**
	 * Tracks whether the model event listeners have been attached so
	 * `register()` is genuinely idempotent — Eloquent's
	 * `Model::saved()` and `Model::deleted()` resolvers accumulate
	 * closures on every call, so a second `register()` would otherwise
	 * fire the cache forget twice per save.
	 */
	protected bool $listenersRegistered = false;

	public function __construct(
		protected GlobalStylesCssProvider $provider,
	) {
	}

	/**
	 * Registers the model event listeners. Safe to call more than once
	 * — subsequent invocations are no-ops thanks to the
	 * `$listenersRegistered` guard.
	 *
	 * @since 1.0.0
	 */
	public function register(): void
	{
		if ( $this->listenersRegistered ) {
			return;
		}

		VisualEditorGlobalStyles::saved( function ( VisualEditorGlobalStyles $record ): void {
			$this->provider->forget(
				$record,
				$this->originalUpdatedAt( $record )
			);
		} );

		VisualEditorGlobalStyles::deleted( function ( VisualEditorGlobalStyles $record ): void {
			$this->provider->forget(
				$record,
				$this->originalUpdatedAt( $record )
			);
		} );

		$this->listenersRegistered = true;
	}

	/**
	 * Pulls the pre-save `updated_at` so the *previous* cache entry can
	 * be evicted alongside the new one.
	 *
	 * @since 1.0.0
	 */
	protected function originalUpdatedAt( VisualEditorGlobalStyles $record ): ?string
	{
		$original = $record->getOriginal( 'updated_at' );

		if ( $original instanceof Carbon ) {
			return $original->format( 'YmdHis' );
		}

		if ( is_string( $original ) && '' !== $original ) {
			try {
				return Carbon::parse( $original )->format( 'YmdHis' );
			} catch ( \Throwable $e ) {
				return null;
			}
		}

		return null;
	}
}
