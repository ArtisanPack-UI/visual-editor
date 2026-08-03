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
use ArtisanPackUI\VisualEditor\Support\ThemeBlockMarkup;

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
		$blocks = $template->blocks;

		// Theme-file sources (parts on disk, template files) reach us
		// with `rawContent` populated but `blocks` empty — cms-framework's
		// filter contributor doesn't parse the disk file. The shim's
		// `useEntityBlockEditor` fallback that would parse raw is
		// scoped to navigation blocks only (`parseNavigationContent`
		// drops everything except `core/navigation-link` /
		// `core/navigation-submenu`, Keystone #48), so leaving `blocks`
		// empty here means the canvas mounts nothing — the exact
		// failure #674 reports for `wp:template-part` references inside
		// templates. Parse server-side when cms-framework's parser is
		// available so the client receives a real tree.
		if ( [] === $blocks && '' !== trim( $template->rawContent ) ) {
			$blocks = self::parseRawContentToEditorBlocks( $template->rawContent );
		}

		// Rewrite `core/template-part` → `artisanpack/template-part` in
		// both the parsed tree and the raw string. The I7 cutover (#415)
		// replaced `registerCoreBlocks()` with an artisanpack-only
		// registration, so `core/template-part` blocks reach the editor
		// unregistered and get silently dropped — the visible half of
		// the #674 failure. The `artisanpack/template-part` fork IS
		// registered and, paired with its updated edit component, mounts
		// a real `<InnerBlocks>` surface fed by the shim's
		// `useEntityBlockEditor` composite-id resolver. Themes on disk
		// still write `<!-- wp:template-part /-->` (WP core convention),
		// so we translate here rather than requiring theme authors to
		// switch namespaces.
		$blocks     = self::rewriteTemplatePartToFork( $blocks );
		$rawContent = self::rewriteRawTemplatePartToFork( $template->rawContent );

		// Stamp `ref` on any nested `core/navigation` block whose
		// `__unstableLocation` matches an assigned menu location
		// (Keystone #48). Gutenberg's current nav block doesn't
		// auto-resolve `__unstableLocation` to a `ref`, so without
		// this projection a themed seed of `{"__unstableLocation":
		// "primary"}` lands in the editor as "no menu selected" and
		// the picker shows "This Navigation Menu is empty."
		$resolvedBlocks = ( new NavigationBlockRefResolver() )->resolve(
			$blocks,
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
				'raw'    => $rawContent,
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

	/**
	 * Parse raw block markup into the editor-shape block tree the
	 * shim's `BlockEditorProvider` expects.
	 *
	 * cms-framework's `BlockMarkupParser::parse()` returns the
	 * `parse_blocks()` shape (`{blockName, attrs, innerBlocks, ...}`);
	 * we rename to `{name, attributes, innerBlocks}` recursively so the
	 * editor mounts them without shape-sniffing. Returns `[]` if
	 * cms-framework is not on the classpath — visual-editor's post
	 * editor still works standalone; only the site-editor route is
	 * hard-coupled to cms-framework, so a missing parser here is a
	 * dev-environment signal, not a runtime failure to swallow.
	 *
	 * @since 1.5.1
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected static function parseRawContentToEditorBlocks( string $raw ): array
	{
		// Shared with the composed view's applied-template endpoint (#655),
		// which hit the same empty-`blocks` theme-file case.
		return ThemeBlockMarkup::parseToEditorBlocks( $raw );
	}

	/**
	 * Rewrite `core/template-part` block names to their registered
	 * `artisanpack/template-part` fork throughout an editor-shape
	 * tree. See {@see toArray()} for the I7 cutover rationale.
	 * Recurses into `innerBlocks` even though real template-part
	 * refs are self-closing, so a theme author's non-standard
	 * nesting doesn't leave a stray unregistered node behind.
	 *
	 * @since 1.5.1
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected static function rewriteTemplatePartToFork( array $tree ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				$out[] = $block;
				continue;
			}

			$name  = $block['name'] ?? null;
			$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];

			if ( 'core/template-part' === $name ) {
				$block['name'] = 'artisanpack/template-part';
			}

			if ( [] !== $inner ) {
				$block['innerBlocks'] = self::rewriteTemplatePartToFork( $inner );
			}

			$out[] = $block;
		}

		return $out;
	}

	/**
	 * Rewrite serialized `wp:template-part` delimiters to the
	 * `wp:artisanpack/template-part` fork. Anchored on the delimiter
	 * boundary — `wp:template-part` won't collide with a substring
	 * of e.g. `wp:template-parts-listing` because the delimiter regex
	 * requires whitespace or `/` immediately after the name segment.
	 * Handles both self-closing (`/-->`) and open (`-->`) forms plus
	 * the closer `<!-- /wp:template-part -->` for parity, though core
	 * template-part refs are always self-closing in practice.
	 *
	 * @since 1.5.1
	 */
	protected static function rewriteRawTemplatePartToFork( string $raw ): string
	{
		return (string) preg_replace(
			'/<!--(\s+\/?)wp:template-part(?=[\s\/}])/',
			'<!--$1wp:artisanpack/template-part',
			$raw,
		);
	}

	/**
	 * Recursively convert `parse_blocks()`-shape blocks
	 * (`{blockName, attrs, innerBlocks, ...}`) into the editor shape
	 * (`{name, attributes, innerBlocks}`). Drops freeform siblings
	 * (`blockName === null`) — they'd land in the editor as
	 * `core/freeform` mystery blocks; the raw stays available via
	 * `content.raw` if the editor needs to reserialize.
	 *
	 * @since 1.5.1
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected static function convertParseBlocksTree( array $tree ): array
	{
		return ThemeBlockMarkup::convertParseBlocksTree( $tree );
	}
}
