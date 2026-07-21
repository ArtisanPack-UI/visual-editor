<?php

/**
 * Pattern adapter — WP `wp_block` REST shape.
 *
 * Translates a {@see ResolvedPattern} value object into the single-record
 * envelope WP's `/wp/v2/blocks` endpoint emits. The same envelope serves
 * both synced (live-edited) and unsynced (snapshot) patterns — the
 * `synced` flag carried in the response lets the inserter decide how to
 * surface the pattern.
 *
 * Theme-shipped patterns (`source: 'theme'`) carry the slug as their `id`
 * because they have no DB row backing. User patterns carry their `wp_id`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor;

use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;

class PatternAdapter
{
	/**
	 * Single-record `wp_block` envelope.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     id: int|string,
	 *     slug: string,
	 *     type: string,
	 *     status: string,
	 *     title: array{rendered: string, raw: string},
	 *     content: array{raw: string, blocks: array<int, array<string, mixed>>},
	 *     source: string,
	 *     synced: bool,
	 *     categories: array<int, string>,
	 *     block_types: array<int, string>,
	 *     post_types: array<int, string>|null
	 * }
	 */
	public function toArray( ResolvedPattern $pattern ): array
	{
		// `ap.visualEditor.patternRender` — filter the rendered raw
		// content of a pattern before it ships to the editor. Runs on
		// every read so subscribers can inject dynamic content (shortcode
		// expansion, per-request personalization tokens, etc.) without
		// having to mutate the underlying block tree. The context array
		// carries pattern-level metadata (source, synced flag) so hosts
		// can gate their transform on theme-vs-user patterns cheaply.
		// Non-string returns are discarded so a misbehaving callback
		// can't blank the pattern.
		$filtered = applyFilters(
			'ap.visualEditor.patternRender',
			$pattern->rawContent,
			$pattern->slug,
			[
				'source'      => $pattern->source,
				'synced'      => $pattern->synced,
				'categories'  => $pattern->categories,
				'block_types' => $pattern->blockTypes,
				'post_types'  => $pattern->postTypes,
			],
		);

		$rawContent = is_string( $filtered ) ? $filtered : $pattern->rawContent;

		return [
			'id'          => $pattern->wpId > 0 ? $pattern->wpId : $pattern->slug,
			'slug'        => $pattern->slug,
			'type'        => 'wp_block',
			'status'      => 'publish',
			'title'       => [
				'rendered' => $pattern->title,
				'raw'      => $pattern->title,
			],
			'content'     => [
				'raw'    => $rawContent,
				'blocks' => $pattern->blocks,
			],
			'source'      => $pattern->source,
			'synced'      => $pattern->synced,
			'categories'  => $pattern->categories,
			'block_types' => $pattern->blockTypes,
			// `null` means "available everywhere" (Gutenberg convention);
			// preserve it verbatim so the editor can distinguish an
			// unscoped pattern from a pattern that explicitly scoped
			// itself to zero post types.
			'post_types'  => $pattern->postTypes,
		];
	}

	/**
	 * Index envelope — flat list of single-record envelopes. See
	 * {@see TemplateAdapter::collection()} for the rationale on skipping
	 * pagination at this stage.
	 *
	 * @since 1.0.0
	 *
	 * @param  iterable<ResolvedPattern>  $patterns
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function collection( iterable $patterns ): array
	{
		$out = [];

		foreach ( $patterns as $pattern ) {
			$out[] = $this->toArray( $pattern );
		}

		return $out;
	}
}
