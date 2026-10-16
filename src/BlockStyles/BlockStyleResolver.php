<?php

/**
 * Resolves the block style variations available on a request (#876).
 *
 * Sources, lowest precedence first:
 *
 *  1. `styles.blocks.{block}.variations` in the resolved global styles
 *     (the active theme's `theme.json` merged with user customizations,
 *     as delivered through the `ap.visualEditor.globalStyles` filter).
 *  2. `styles/*.json` partials with `blockTypes` in the active theme's
 *     `styles/` directory (cms-framework), plus any directory listed in
 *     `artisanpack.visual-editor.block_styles.directories`.
 *  3. Code registrations through `VisualEditor::registerBlockStyle()`.
 *
 * A later source replaces an earlier one on the same block + slug; a
 * code registration without a style keeps the theme's style and only
 * relabels it. The merged list then passes through the
 * `ap.visualEditor.blockStyles` filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\BlockStyles;

use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\GlobalStylesResolver;
use Throwable;

class BlockStyleResolver
{
	/**
	 * cms-framework's theme manager, resolved by name so visual-editor
	 * stays installable without cms-framework.
	 *
	 * @since 1.15.0
	 */
	protected const THEME_MANAGER = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

	/**
	 * Memoized result for the request.
	 *
	 * @since 1.15.0
	 *
	 * @var list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>|null
	 */
	protected ?array $resolved = null;

	/**
	 * Build the resolver over the code registry, the partial parser and
	 * the CSS compiler.
	 *
	 * @since 1.15.0
	 *
	 * @param  list<string>  $directories  Extra partial directories from config.
	 */
	public function __construct(
		protected BlockStyleRegistry $registry,
		protected BlockStyleVariationParser $parser,
		protected BlockStyleCssCompiler $compiler,
		protected array $directories = [],
	) {
	}

	/**
	 * Every variation available on this request.
	 *
	 * @since 1.15.0
	 *
	 * @return list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>
	 */
	public function all(): array
	{
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$merged = [];

		$this->merge( $merged, $this->parser->fromStyles( $this->globalStyles() ) );

		foreach ( $this->partialDirectories() as $directory ) {
			$this->merge( $merged, $this->parser->fromDirectory( $directory ) );
		}

		$this->merge( $merged, $this->registry->all() );

		$filtered = function_exists( 'applyFilters' )
			? applyFilters( 'ap.visualEditor.blockStyles', array_values( $merged ) )
			: array_values( $merged );

		return $this->resolved = $this->sanitise( is_array( $filtered ) ? $filtered : array_values( $merged ) );
	}

	/**
	 * The editor bootstrap payload: what the Styles picker needs, without
	 * the style nodes (their CSS reaches the canvas through the
	 * global-styles stylesheet).
	 *
	 * @since 1.15.0
	 *
	 * @return list<array{name: string, slug: string, label: string, isDefault: bool}>
	 */
	public function editorPayload(): array
	{
		return array_map(
			static fn ( array $entry ): array => [
				'name'      => $entry['block'],
				'slug'      => $entry['slug'],
				'label'     => $entry['label'],
				'isDefault' => $entry['isDefault'],
			],
			$this->all(),
		);
	}

	/**
	 * The compiled `.is-style-*` CSS for every variation.
	 *
	 * @since 1.15.0
	 */
	public function css(): string
	{
		return $this->compiler->compile( $this->all() );
	}

	/**
	 * Drop the memoized result, e.g. after registering a style mid-request.
	 *
	 * @since 1.15.0
	 */
	public function flush(): void
	{
		$this->resolved = null;
	}

	/**
	 * The resolved global styles' `styles` node, or `[]`.
	 *
	 * @since 1.15.0
	 *
	 * @return array<string, mixed>
	 */
	protected function globalStyles(): array
	{
		try {
			$raw = app( GlobalStylesResolver::class )->raw();
		} catch ( Throwable ) {
			return [];
		}

		return is_array( $raw['styles'] ?? null ) ? $raw['styles'] : [];
	}

	/**
	 * The active cms-framework theme's `styles/` directory (when
	 * installed) followed by the configured directories.
	 *
	 * @since 1.15.0
	 *
	 * @return list<string>
	 */
	protected function partialDirectories(): array
	{
		$directories = [];

		if ( class_exists( self::THEME_MANAGER ) && app()->bound( self::THEME_MANAGER ) ) {
			try {
				$manager = app( self::THEME_MANAGER );
				$theme   = $manager->getActiveTheme();
				$slug    = is_array( $theme ) && is_string( $theme['slug'] ?? null ) ? $theme['slug'] : '';

				if ( '' !== $slug && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $slug ) ) {
					$directories[] = rtrim( (string) $manager->getThemesPath(), '/' ) . '/' . $slug . '/styles';
				}
			} catch ( Throwable ) {
				// No active theme (or the themes table isn't migrated yet).
			}
		}

		foreach ( $this->directories as $directory ) {
			if ( is_string( $directory ) && '' !== trim( $directory ) ) {
				$directories[] = $directory;
			}
		}

		return array_values( array_unique( $directories ) );
	}

	/**
	 * Merge entries in, later entries winning per block + slug.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, array<string, mixed>>  $merged
	 * @param  list<array<string, mixed>>           $entries
	 */
	protected function merge( array &$merged, array $entries ): void
	{
		foreach ( $entries as $entry ) {
			$key   = $entry['block'] . '|' . $entry['slug'];
			$style = is_array( $entry['style'] ?? null ) ? $entry['style'] : [];

			if ( [] === $style && isset( $merged[ $key ] ) ) {
				$entry['style'] = $merged[ $key ]['style'];
			}

			$merged[ $key ] = $entry;
		}
	}

	/**
	 * Re-validate the (possibly filtered) list so a filter callback can't
	 * hand the compiler or the editor a malformed entry.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<mixed>  $entries
	 *
	 * @return list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>
	 */
	protected function sanitise( array $entries ): array
	{
		$clean = [];

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$block = is_string( $entry['block'] ?? null ) ? BlockStyleVariationParser::normaliseBlockName( $entry['block'] ) : null;
			$slug  = is_string( $entry['slug'] ?? null ) ? BlockStyleVariationParser::normaliseSlug( $entry['slug'] ) : null;

			if ( null === $block || null === $slug ) {
				continue;
			}

			$label = is_string( $entry['label'] ?? null ) ? trim( $entry['label'] ) : '';

			$clean[ $block . '|' . $slug ] = [
				'block'     => $block,
				'slug'      => $slug,
				'label'     => '' !== $label ? $label : BlockStyleVariationParser::labelFromSlug( $slug ),
				'style'     => is_array( $entry['style'] ?? null ) ? $entry['style'] : [],
				'isDefault' => true === ( $entry['isDefault'] ?? false ),
			];
		}

		return array_values( $clean );
	}
}
