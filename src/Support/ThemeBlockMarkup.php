<?php

/**
 * Theme block-markup helpers shared by the site editor and the post
 * editor's composed view.
 *
 * Templates and template parts that live on disk carry their content as
 * raw block markup, not as a parsed tree — `ResolvedTemplate::$blocks` is
 * empty for every theme-file source. #674/#675 fixed that for the site
 * editor inside {@see \ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\TemplateAdapter},
 * and #655 needed the same parse for the composed view's applied-template
 * endpoint, so it lives here rather than in either caller.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

class ThemeBlockMarkup
{
	/**
	 * cms-framework's block-markup parser.
	 *
	 * Referenced by name rather than imported: visual-editor's post editor
	 * works standalone, and only the site-editor route is hard-coupled to
	 * cms-framework. A missing parser is a dev-environment signal, not a
	 * runtime failure to swallow.
	 *
	 * @since 1.6.0
	 */
	public const PARSER_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Support\\BlockMarkupParser';

	/**
	 * Parse raw block markup into the editor-shape block tree the shim's
	 * `BlockEditorProvider` expects.
	 *
	 * `BlockMarkupParser::parse()` returns the `parse_blocks()` shape
	 * (`{blockName, attrs, innerBlocks, ...}`); this renames recursively to
	 * `{name, attributes, innerBlocks}` so the editor mounts the tree
	 * without shape-sniffing. Returns `[]` when the parser is absent.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function parseToEditorBlocks( string $raw ): array
	{
		if ( '' === trim( $raw ) ) {
			return [];
		}

		$parserFqcn = self::PARSER_FQCN;

		if ( ! class_exists( $parserFqcn ) ) {
			return [];
		}

		return self::convertParseBlocksTree( $parserFqcn::parse( $raw ) );
	}

	/**
	 * Recursively convert `parse_blocks()`-shape blocks into the editor
	 * shape. Drops freeform siblings (`blockName === null`) — they'd land
	 * in the editor as `core/freeform` mystery blocks; the raw markup stays
	 * available to callers that need to reserialize.
	 *
	 * @since 1.6.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function convertParseBlocksTree( array $tree ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = $block['blockName'] ?? null;

			if ( ! is_string( $name ) || '' === $name ) {
				continue;
			}

			$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];

			$out[] = [
				'name'        => $name,
				'attributes'  => is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [],
				'innerBlocks' => self::convertParseBlocksTree( $inner ),
			];
		}

		return $out;
	}

	/**
	 * Rewrite every `core/x` block name to its `artisanpack/x` fork,
	 * recursing through `innerBlocks`.
	 *
	 * The editor has registered only the `artisanpack/*` namespace since
	 * the I7 cutover (#415), while theme templates on disk are authored in
	 * core markup. Without this every chrome block mounts as an
	 * unregistered type and renders nothing.
	 *
	 * Deliberately unconditional rather than gated on a registry lookup:
	 * {@see \ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry} only
	 * knows server-rendered PHP blocks, so gating would skip nearly every
	 * block. A core name with no fork is unregistered under either
	 * namespace, so the blanket rewrite costs nothing there.
	 *
	 * @since 1.6.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function rewriteCoreToFork( array $blocks ): array
	{
		$out = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				$out[] = $block;
				continue;
			}

			$name = $block['name'] ?? null;

			if ( is_string( $name ) && str_starts_with( $name, 'core/' ) ) {
				$block['name'] = 'artisanpack/' . substr( $name, strlen( 'core/' ) );
			}

			if ( is_array( $block['innerBlocks'] ?? null ) && [] !== $block['innerBlocks'] ) {
				$block['innerBlocks'] = self::rewriteCoreToFork( $block['innerBlocks'] );
			}

			$out[] = $block;
		}

		return $out;
	}
}
