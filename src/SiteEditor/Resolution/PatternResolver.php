<?php

/**
 * Site-editor pattern resolver.
 *
 * Consumes the merged `ap.visualEditor.patterns` filter result. Patterns
 * cover both theme-shipped (`source: 'theme'`, read-only) and user-authored
 * (`source: 'user'`, editable) entries — the value object's `synced` field
 * distinguishes WP `wp_block` (synced) from `wp_block_pattern` (unsynced).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 *
 * @extends AbstractMapResolver<ResolvedPattern>
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Resolution;

class PatternResolver extends AbstractMapResolver
{
	/**
	 * @since 1.0.0
	 */
	protected static function filterName(): string
	{
		return 'ap.visualEditor.patterns';
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $entry
	 */
	protected static function normalizeEntry( string $key, array $entry ): ResolvedPattern
	{
		$entry['slug'] = $entry['slug'] ?? $key;

		return ResolvedPattern::fromArray( $entry );
	}

	/**
	 * @since 1.5.0
	 *
	 * @param  ResolvedPattern  $entry
	 */
	protected static function identifierOf( object $entry ): string
	{
		return $entry->slug;
	}
}
