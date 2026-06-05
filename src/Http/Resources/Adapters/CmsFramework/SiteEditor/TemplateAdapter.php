<?php

/**
 * Template adapter — WP `wp_template` REST shape.
 *
 * Converts a {@see ResolvedTemplate} value object (produced by H5's
 * {@see TemplateResolver}) into the single-record envelope WP's
 * `/wp/v2/templates` endpoint emits. The shim's `core-data` registry
 * consumes this shape via `addEntities` (see H6 issue #431) so the
 * editor's existing Gutenberg packages can fetch + cache templates
 * with no source-of-truth-specific glue.
 *
 * Has no awareness of where the data came from — that's cms-framework's
 * H1 templates module. This adapter only translates shape.
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

use ArtisanPackUI\VisualEditor\SiteEditor\NavigationBlockRefResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate;

class TemplateAdapter
{
	/**
	 * Single-record WP `wp_template` envelope.
	 *
	 * Shape mirrors WP REST `/wp/v2/templates/{id}` so the shim can hand
	 * the response straight to `dispatch( 'core' ).receiveEntityRecords()`
	 * without an intermediate transform.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     id: int|string,
	 *     slug: string,
	 *     type: string,
	 *     source: string,
	 *     origin: string|null,
	 *     title: array{rendered: string, raw: string},
	 *     description: string,
	 *     content: array{raw: string, blocks: array<int, array<string, mixed>>},
	 *     status: string,
	 *     theme: string,
	 *     has_theme_file: bool,
	 *     is_custom: bool,
	 *     author: int|null,
	 *     modified: string|null
	 * }
	 */
	public function toArray( ResolvedTemplate $template ): array
	{
		// Stamp `ref` on any nested `core/navigation` block whose
		// `__unstableLocation` matches an assigned menu location
		// (Keystone #48). Gutenberg's current nav block doesn't
		// auto-resolve `__unstableLocation` to a `ref`, so without
		// this projection a themed seed of `{"__unstableLocation":
		// "primary"}` lands in the editor as "no menu selected" and
		// the picker shows "This Navigation Menu is empty."
		$resolvedBlocks = ( new NavigationBlockRefResolver() )->resolve(
			$template->blocks,
			$template->theme,
		);

		return [
			'id'             => $template->wpId > 0 ? $template->wpId : $template->slug,
			'slug'           => $template->slug,
			'type'           => 'wp_template',
			'source'         => $template->source,
			'origin'         => $template->isCustom ? null : 'theme',
			'title'          => [
				'rendered' => $template->title,
				'raw'      => $template->title,
			],
			'description'    => $template->description,
			'content'        => [
				'raw'    => $template->rawContent,
				'blocks' => $resolvedBlocks,
			],
			'status'         => $template->status,
			'theme'          => $template->theme,
			'has_theme_file' => $template->hasThemeFile,
			'is_custom'      => $template->isCustom,
			'author'         => $template->authorId,
			'modified'       => $template->modifiedAt,
		];
	}

	/**
	 * Index envelope — flat list of single-record envelopes, no pagination
	 * wrapper. The shim's `getEntityRecords` selector tolerates either a
	 * flat array or a `{ data, meta }` paginated wrapper; H1 currently
	 * resolves the full set in-process so a flat list is the cheaper
	 * default. Pagination can land later as a non-breaking additive change.
	 *
	 * @since 1.0.0
	 *
	 * @param  iterable<ResolvedTemplate>  $templates
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function collection( iterable $templates ): array
	{
		$out = [];

		foreach ( $templates as $template ) {
			$out[] = $this->toArray( $template );
		}

		return $out;
	}
}
