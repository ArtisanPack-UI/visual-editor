<?php

/**
 * Registry of block style variations registered in code (#876).
 *
 * Hosts, packages and themes register variations through
 * {@see \ArtisanPackUI\VisualEditor\VisualEditor::registerBlockStyle()}.
 * {@see BlockStyleResolver} merges these with the variations a theme
 * declares in `theme.json` and `styles/*.json` partials; a code
 * registration wins on a block + slug collision.
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

use InvalidArgumentException;

class BlockStyleRegistry
{
	/**
	 * Registered variations keyed by block name, then slug.
	 *
	 * @var array<string, array<string, array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>>
	 */
	protected array $styles = [];

	/**
	 * Register a style variation for a block. Re-registering the same
	 * block + slug replaces the earlier entry.
	 *
	 * @since 1.15.0
	 *
	 * @param  string                $block      Block name, e.g. `core/group`.
	 * @param  string                $slug       Variation slug; the block gets `is-style-{slug}`.
	 * @param  string                $label      Label shown in the editor's Styles picker.
	 * @param  array<string, mixed>  $style      A theme.json style node (`color`, `spacing`, `elements`, …).
	 * @param  bool                  $isDefault  Whether the picker treats it as the default style.
	 *
	 * @throws InvalidArgumentException When the block name or slug is malformed.
	 */
	public function register( string $block, string $slug, string $label, array $style = [], bool $isDefault = false ): void
	{
		$block = BlockStyleVariationParser::normaliseBlockName( $block );
		$slug  = BlockStyleVariationParser::normaliseSlug( $slug );

		if ( null === $block ) {
			throw new InvalidArgumentException( 'Block style variations need a `namespace/name` block name.' );
		}

		if ( null === $slug ) {
			throw new InvalidArgumentException( 'Block style variations need a non-empty slug.' );
		}

		$label = trim( $label );

		$this->styles[ $block ][ $slug ] = [
			'block'     => $block,
			'slug'      => $slug,
			'label'     => '' !== $label ? $label : BlockStyleVariationParser::labelFromSlug( $slug ),
			'style'     => $style,
			'isDefault' => $isDefault,
		];
	}

	/**
	 * Remove a registered variation. A no-op when nothing is registered.
	 *
	 * @since 1.15.0
	 */
	public function unregister( string $block, string $slug ): void
	{
		$block = BlockStyleVariationParser::normaliseBlockName( $block );
		$slug  = BlockStyleVariationParser::normaliseSlug( $slug );

		if ( null === $block || null === $slug ) {
			return;
		}

		unset( $this->styles[ $block ][ $slug ] );

		if ( [] === ( $this->styles[ $block ] ?? null ) ) {
			unset( $this->styles[ $block ] );
		}
	}

	/**
	 * Whether a variation is registered.
	 *
	 * @since 1.15.0
	 */
	public function has( string $block, string $slug ): bool
	{
		$block = BlockStyleVariationParser::normaliseBlockName( $block );
		$slug  = BlockStyleVariationParser::normaliseSlug( $slug );

		return null !== $block && null !== $slug && isset( $this->styles[ $block ][ $slug ] );
	}

	/**
	 * Every registered variation, in registration order.
	 *
	 * @since 1.15.0
	 *
	 * @return list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>
	 */
	public function all(): array
	{
		$entries = [];

		foreach ( $this->styles as $variations ) {
			foreach ( $variations as $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}
}
