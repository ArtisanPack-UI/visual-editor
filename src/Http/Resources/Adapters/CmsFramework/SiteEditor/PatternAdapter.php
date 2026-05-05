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
	 *     block_types: array<int, string>
	 * }
	 */
	public function toArray( ResolvedPattern $pattern ): array
	{
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
				'raw'    => $pattern->rawContent,
				'blocks' => $pattern->blocks,
			],
			'source'      => $pattern->source,
			'synced'      => $pattern->synced,
			'categories'  => $pattern->categories,
			'block_types' => $pattern->blockTypes,
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
