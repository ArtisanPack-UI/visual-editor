<?php

/**
 * Save-time guard for per-block Custom CSS.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Effects;

use ArtisanPackUI\VisualEditor\Support\BlockShape;
use Illuminate\Support\Facades\Gate;

/**
 * Enforces {@see BlockCssAccess} on incoming block trees (#881).
 *
 * A user with the ability saves Custom CSS as sent. For anyone else, each
 * block's `artisanpackEffects.customCss` is kept only when the same value
 * is already stored somewhere on the record being saved, and removed
 * otherwise. So an editor without the ability can move or keep a block an
 * administrator styled, but can't add or change Custom CSS. Values are
 * compared after {@see CustomCssSanitizer} normalisation. Blocks may use
 * the editor (`attributes`) or parse (`attrs`) shape — both are read and
 * stripped, since the renderer falls back to `attrs`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
class CustomCssGuard
{
	/**
	 * Filter an incoming block tree for the current user.
	 *
	 * The values found in `$stored` are also vouched for with
	 * {@see CustomCssModelGuard::approve()}, so the model-level guard that
	 * runs when the record is saved keeps them too, even on a new row
	 * seeded from a theme file.
	 *
	 * @since 1.15.0
	 *
	 * @param  mixed  $blocks  Incoming block array (anything else passes through).
	 * @param  mixed  $stored  The record's stored block array (or the theme file's
	 *                         blocks for a file→DB upsert), or null for a new record.
	 */
	public function filter( mixed $blocks, mixed $stored = null ): mixed
	{
		if ( ! is_array( $blocks ) || Gate::allows( BlockCssAccess::ABILITY ) ) {
			return $blocks;
		}

		$allowed = [];

		if ( is_array( $stored ) ) {
			$this->collect( $stored, $allowed );
		}

		app( CustomCssModelGuard::class )->approve( array_keys( $allowed ) );

		return $this->strip( $blocks, $allowed );
	}

	/**
	 * Filter an incoming block tree against a stored tree plus extra
	 * already-normalised values, without vouching for anything. Used by
	 * {@see CustomCssModelGuard} on model save.
	 *
	 * @since 1.15.0
	 *
	 * @param  mixed                $blocks    Incoming block array (anything else passes through).
	 * @param  mixed                $stored    The record's stored block array, or null for a new record.
	 * @param  array<int, string>  $approved  Normalised values vouched for earlier in the request.
	 */
	public function filterAgainst( mixed $blocks, mixed $stored, array $approved = [] ): mixed
	{
		if ( ! is_array( $blocks ) || Gate::allows( BlockCssAccess::ABILITY ) ) {
			return $blocks;
		}

		$allowed = array_fill_keys( array_filter( $approved, 'is_string' ), true );

		if ( is_array( $stored ) ) {
			$this->collect( $stored, $allowed );
		}

		return $this->strip( $blocks, $allowed );
	}

	/**
	 * @param  array<mixed>  $blocks
	 * @param  array<string, bool>  $allowed
	 */
	protected function collect( array $blocks, array &$allowed ): void
	{
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			[ , $attributes ] = BlockShape::readAttrs( $block );

			$value = $this->normalisedValue( $attributes );

			if ( null !== $value ) {
				$allowed[ $value ] = true;
			}

			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$this->collect( $block['innerBlocks'], $allowed );
			}
		}
	}

	/**
	 * @param  array<mixed>  $blocks
	 * @param  array<string, bool>  $allowed
	 *
	 * @return array<mixed>
	 */
	protected function strip( array $blocks, array $allowed ): array
	{
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			// Both bags: the renderer reads a parse-shape `attrs` bag when
			// a block has no `attributes`.
			foreach ( [ 'attributes', 'attrs' ] as $key ) {
				if ( is_array( $block[ $key ] ?? null ) ) {
					$block[ $key ] = $this->stripAttributes( $block[ $key ], $allowed );
				}
			}

			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$block['innerBlocks'] = $this->strip( $block['innerBlocks'], $allowed );
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}

	/**
	 * @param  array<string, mixed>  $attributes
	 * @param  array<string, bool>  $allowed
	 *
	 * @return array<string, mixed>
	 */
	protected function stripAttributes( array $attributes, array $allowed ): array
	{
		$effects = $attributes[ EffectsCompiler::ATTRIBUTE ] ?? null;

		if ( ! is_array( $effects ) || ! array_key_exists( 'customCss', $effects ) ) {
			return $attributes;
		}

		$value = $this->normalisedValue( $attributes );

		if ( null !== $value && isset( $allowed[ $value ] ) ) {
			return $attributes;
		}

		unset( $effects['customCss'] );

		$attributes[ EffectsCompiler::ATTRIBUTE ] = $effects;

		return $attributes;
	}

	/**
	 * A block's normalised, non-empty Custom CSS, or null.
	 */
	protected function normalisedValue( mixed $attributes ): ?string
	{
		if ( ! is_array( $attributes ) || ! is_array( $attributes[ EffectsCompiler::ATTRIBUTE ] ?? null ) ) {
			return null;
		}

		$value = CustomCssSanitizer::sanitize( $attributes[ EffectsCompiler::ATTRIBUTE ]['customCss'] ?? null );

		return null === $value || '' === $value ? null : $value;
	}
}
