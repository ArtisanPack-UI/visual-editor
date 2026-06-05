<?php

/**
 * Responsive class / @media emitter — Blade renderer (#487).
 *
 * Bridges the discriminated `{base, sm, md, …}` attribute storage to
 * the markup the server-side Blade partials produce. There are two
 * output modes per attribute:
 *
 *  - **Token mode** — when the value at every distinct breakpoint maps
 *    to a Tailwind utility (via a caller-supplied token → class map),
 *    the resolver emits a Tailwind class string using the registered
 *    breakpoint prefixes (e.g. `px-4 sm:px-6 lg:px-8`).
 *  - **Custom mode** — when at least one value can't be tokenized, the
 *    resolver emits a generated wrapper class plus a `<style>` block
 *    containing `@media (min-width: …) { .ctx-class { prop: value } }`
 *    rules scoped to that class.
 *
 * Renderers call {@see emit()} with the attribute, a CSS property
 * name, and an optional token map. The result is `{ class, css }` —
 * the class merges into the wrapper class list; the CSS string is
 * accumulated and emitted once in a single per-block `<style>` block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Responsive;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;

class ResponsiveClassResolver
{
	public function __construct(
		protected BreakpointRegistry $registry,
		protected ResponsiveValueResolver $resolver,
	) {}

	/**
	 * Emit class string + CSS for a single attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed                            $attribute  Either a scalar
	 *                                                      or a `{base, sm, …}`
	 *                                                      object.
	 * @param  string                           $property   CSS property to emit
	 *                                                      when falling back to
	 *                                                      `@media` rules
	 *                                                      (e.g. `padding`,
	 *                                                      `font-size`).
	 * @param  array<string, string>|callable   $tokenMap   When an array, maps
	 *                                                      stored value → Tailwind
	 *                                                      utility (e.g.
	 *                                                      `['4' => 'px-4']`).
	 *                                                      When a callable, called
	 *                                                      as `(value) => ?string`
	 *                                                      returning the utility
	 *                                                      or `null` to fall back
	 *                                                      to custom CSS.
	 *
	 * @return array{class: string, css: string}
	 */
	public function emit( $attribute, string $property, $tokenMap = [] ): array
	{
		$distinct = $this->resolver->distinctOverrides( $attribute );

		if ( [] === $distinct ) {
			return [ 'class' => '', 'css' => '' ];
		}

		// First pass: try the token route for every distinct value.
		$tokens      = [];
		$canTokenize = true;

		foreach ( $distinct as $key => $value ) {
			$utility = $this->lookupToken( $value, $tokenMap );

			if ( null === $utility ) {
				$canTokenize = false;
				break;
			}

			$tokens[ $key ] = $utility;
		}

		if ( $canTokenize ) {
			return [
				'class' => $this->buildTailwindClasses( $tokens ),
				'css'   => '',
			];
		}

		// Custom mode — scope under a generated class.
		$scope = $this->generateScopeClass( $attribute, $property );

		return [
			'class' => $scope,
			'css'   => $this->buildMediaCss( $scope, $property, $distinct ),
		];
	}

	/**
	 * Returns a Tailwind class string ordered by ascending breakpoint
	 * width, using the registered prefixes (e.g. `sm:`, `md:`).
	 *
	 * @param  array<string, string>  $tokens  `[breakpoint => utility]`.
	 */
	protected function buildTailwindClasses( array $tokens ): string
	{
		$pieces = [];

		// Ensure stable, ascending order regardless of the input order.
		// `distinctOverrides()` already walks the registry, but custom
		// callers may pass a hand-rolled token map.
		foreach ( $this->registry->keysWithBase() as $key ) {
			if ( ! array_key_exists( $key, $tokens ) ) {
				continue;
			}

			$pieces[] = BreakpointRegistry::BASE_KEY === $key
				? $tokens[ $key ]
				: $key . ':' . $tokens[ $key ];
		}

		return implode( ' ', $pieces );
	}

	/**
	 * @param  array<string, mixed>  $distinct
	 */
	protected function buildMediaCss( string $scope, string $property, array $distinct ): string
	{
		$rules = [];

		foreach ( $distinct as $key => $value ) {
			$stringified = $this->stringifyValue( $value );

			// `stringifyValue` returns '' for arrays and other
			// non-castable shapes — skip those slots rather than
			// emitting a malformed `padding:;` declaration.
			if ( '' === $stringified ) {
				continue;
			}

			$safe = self::sanitizeCssValue( $stringified );

			if ( '' === $safe ) {
				continue;
			}

			$declaration = sprintf( '%s:%s', $property, $safe );

			if ( BreakpointRegistry::BASE_KEY === $key ) {
				$rules[] = sprintf( '.%s{%s}', $scope, $declaration );
				continue;
			}

			$minWidth = $this->registry->get( $key );

			if ( null === $minWidth ) {
				continue;
			}

			$rules[] = sprintf(
				'@media (min-width:%dpx){.%s{%s}}',
				$minWidth,
				$scope,
				$declaration
			);
		}

		return implode( '', $rules );
	}

	/**
	 * Whitelists characters legal in a CSS value expression — letters,
	 * digits, the units / operators / punctuation real CSS values use
	 * (including calc() operators `+`, `-`, `*`, `/`), and whitespace.
	 * Drops everything else so values can never close out of the
	 * declaration (`;`, `}`) or escape the `<style>` tag (`<`, `>`).
	 * Returns an empty string when the input contains no allowed
	 * characters.
	 *
	 * @since 1.0.0
	 */
	protected static function sanitizeCssValue( string $value ): string
	{
		return (string) preg_replace( '/[^a-zA-Z0-9_+\-*\/.,()%#\s]/', '', $value );
	}

	/**
	 * @param  mixed                          $value
	 * @param  array<string, string>|callable $tokenMap
	 */
	protected function lookupToken( $value, $tokenMap ): ?string
	{
		if ( is_callable( $tokenMap ) ) {
			$result = $tokenMap( $value );

			return is_string( $result ) && '' !== $result ? $result : null;
		}

		if ( ! is_array( $tokenMap ) ) {
			return null;
		}

		$key = is_scalar( $value ) ? (string) $value : null;

		if ( null === $key ) {
			return null;
		}

		return $tokenMap[ $key ] ?? null;
	}

	protected function stringifyValue( $value ): string
	{
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_array( $value ) ) {
			return '';
		}

		return (string) $value;
	}

	/**
	 * Generates a stable, content-derived class name so the same
	 * attribute value re-renders to the same scope (cache-friendly).
	 */
	protected function generateScopeClass( $attribute, string $property ): string
	{
		$hash = substr(
			hash( 'xxh3', $property . '|' . json_encode( $attribute ) ),
			0,
			10
		);

		return 've-r-' . $hash;
	}
}
