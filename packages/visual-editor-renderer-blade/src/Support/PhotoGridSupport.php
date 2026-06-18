<?php

/**
 * Photo Grid wrapper serializer — Blade renderer (#594).
 *
 * Mirrors `resources/js/visual-editor/blocks/_shared/photo-grid/
 * wrapper.ts` exactly. Returns the wrapper class list plus the inline
 * CSS variable declarations (`--ap-photo-grid-aspect`, `--ap-photo-
 * grid-fit`, `--ap-photo-grid-position`) that the matching
 * `photo-grid.css` stylesheet picks up to size image-bearing
 * descendants.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;

class PhotoGridSupport
{
	/**
	 * Convenience used by block partials. Resolves the wrapper props,
	 * pushes the CSS-variable declarations into the per-request CSS
	 * accumulator under a scoped class, and returns the class list
	 * to splice into `BlockSupports::wrapperAttrs()`. Returns an
	 * empty array when the feature is off.
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<int, string>
	 */
	public static function wrapperForBlock( array $attributes ): array
	{
		$result = self::wrapper( $attributes );

		if ( [] === $result[ 'classes' ] ) {
			return [];
		}

		$styles = $result[ 'styles' ];
		if ( [] === $styles ) {
			return $result[ 'classes' ];
		}

		// Scope-class names follow the same convention as the flex
		// arbitrary-style emitter — `photo-grid-{12-char-sha1}` keyed
		// on the rendered declaration string so identical configs
		// collide on one rule rather than emitting N duplicates.
		$declaration = self::inlineStyle( $styles );
		$scope       = 'photo-grid-' . substr( sha1( $declaration ), 0, 12 );
		$classes     = array_merge( $result[ 'classes' ], [ $scope ] );

		try {
			$css = '.' . $scope . '{' . $declaration . '}';
			app( ResponsiveCssAccumulator::class )->push( $scope, $css );
		} catch ( \Throwable $e ) {
			// Accumulator not booted (early or test path) — silently drop.
		}

		return $classes;
	}


	/**
	 * Compute the wrapper props for a block's Photo Grid attribute.
	 *
	 * Returns an associative array with two keys:
	 *
	 *   - `classes` — array<int, string>: classes to merge into the
	 *     block's wrapper class list. Empty when the feature is off.
	 *   - `styles`  — array<string, string>: CSS custom property
	 *     declarations to splice into the wrapper's inline `style`
	 *     attribute (`var => value`). Empty when the feature is off.
	 *
	 * @param  array<string, mixed>  $attributes  Raw block attributes.
	 *
	 * @return array{classes: array<int, string>, styles: array<string, string>}
	 */
	public static function wrapper( array $attributes ): array
	{
		$photoGrid = $attributes[ 'photoGrid' ] ?? null;

		if ( ! is_array( $photoGrid ) ) {
			return [ 'classes' => [], 'styles' => [] ];
		}

		$enabled = $photoGrid[ 'enabled' ] ?? false;
		if ( true !== $enabled ) {
			return [ 'classes' => [], 'styles' => [] ];
		}

		$aspect   = self::normaliseAspectRatio( $photoGrid[ 'aspectRatio' ] ?? null );
		$fit      = self::normaliseObjectFit( $photoGrid[ 'objectFit' ] ?? null );
		$position = self::normaliseObjectPosition( $photoGrid[ 'objectPosition' ] ?? null );

		$styles = [
			'--ap-photo-grid-fit'      => $fit,
			'--ap-photo-grid-position' => $position,
		];

		if ( null !== $aspect ) {
			$styles[ '--ap-photo-grid-aspect' ] = $aspect;
		}

		return [
			'classes' => [ 'has-photo-grid' ],
			'styles'  => $styles,
		];
	}

	/**
	 * Render the `styles` array as a `key:value;…` inline-style
	 * declaration string. Returns an empty string when there is
	 * nothing to emit.
	 *
	 * @param  array<string, string>  $styles
	 */
	public static function inlineStyle( array $styles ): string
	{
		if ( [] === $styles ) {
			return '';
		}

		$parts = [];
		foreach ( $styles as $property => $value ) {
			$parts[] = $property . ':' . $value;
		}

		return implode( ';', $parts ) . ';';
	}

	/**
	 * Validate an aspect ratio token. Accepts `null` / `''` / `'auto'`
	 * / `'inherit'` as "no aspect ratio", a positive `W/H` numeric pair
	 * as a valid ratio, and rejects everything else (returns `null`).
	 *
	 * @param  mixed  $value
	 */
	private static function normaliseAspectRatio( $value ): ?string
	{
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$trimmed = trim( $value );
		if ( '' === $trimmed || 'auto' === $trimmed || 'inherit' === $trimmed ) {
			return null;
		}
		if ( 1 !== preg_match( '#^\d+(\.\d+)?/\d+(\.\d+)?$#', $trimmed ) ) {
			return null;
		}

		[ $w, $h ] = explode( '/', $trimmed );
		if ( (float) $w <= 0 || (float) $h <= 0 ) {
			return null;
		}

		return $trimmed;
	}

	/**
	 * @param  mixed  $value
	 */
	private static function normaliseObjectFit( $value ): string
	{
		return 'contain' === $value ? 'contain' : 'cover';
	}

	/**
	 * @param  mixed  $value
	 */
	private static function normaliseObjectPosition( $value ): string
	{
		if ( ! is_string( $value ) ) {
			return '50% 50%';
		}
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '50% 50%';
		}
		// Allowlist: digits, percent, decimal, sign, whitespace, and the
		// CSS keywords (top/right/bottom/left/center). Anything else —
		// including `(` `)` `/` `*` `\` — falls back to the default so a
		// tampered `objectPosition` cannot break out of the declaration
		// and inject sibling rules (or open an unterminated comment).
		if ( 1 !== preg_match( '/^[0-9%.+\-\s a-z]+$/i', $trimmed ) ) {
			return '50% 50%';
		}

		return $trimmed;
	}
}
