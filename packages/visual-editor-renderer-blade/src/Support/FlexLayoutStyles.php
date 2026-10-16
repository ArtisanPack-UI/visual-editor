<?php

/**
 * Flex Layout token rules built from the active breakpoints (#895).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

/**
 * The static `frontend/flex-layout.css` styles the Flex panel's token
 * classes (`ap-flex`, `md:ap-flex-col`, …) at the package's default
 * breakpoint widths. When a host moves its breakpoints (config or
 * `theme.json`), {@see self::css()} builds the same rules from the
 * registry, so the token classes switch at the host's widths, as the
 * arbitrary-value rules {@see FlexSupport} emits already do. With the
 * default widths it returns `''` and the static stylesheet is linked
 * instead, so the default output doesn't change.
 *
 * Mirrors `flexLayoutCss()` in the React and Vue renderers and the
 * editor canvas.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.15.1
 */
class FlexLayoutStyles
{
	/**
	 * Unprefixed rules, as the static stylesheet's base section ships them.
	 *
	 * @since 1.15.1
	 *
	 * @var list<string>
	 */
	public const BASE_RULES = [
		'.ap-flex { display: flex; }',
		'.ap-flex-none { display: block; }',
		':where(.ap-flex) > * + * { margin-block-start: 0; }',
		'.ap-flex-row { flex-direction: row; }',
		'.ap-flex-row-reverse { flex-direction: row-reverse; }',
		'.ap-flex-col { flex-direction: column; }',
		'.ap-flex-col-reverse { flex-direction: column-reverse; }',
		'.ap-flex-nowrap { flex-wrap: nowrap; }',
		'.ap-flex-wrap { flex-wrap: wrap; }',
		'.ap-flex-wrap-reverse { flex-wrap: wrap-reverse; }',
		'.ap-justify-start { justify-content: flex-start; }',
		'.ap-justify-center { justify-content: center; }',
		'.ap-justify-end { justify-content: flex-end; }',
		'.ap-justify-between { justify-content: space-between; }',
		'.ap-justify-around { justify-content: space-around; }',
		'.ap-justify-evenly { justify-content: space-evenly; }',
		'.ap-justify-left { justify-content: left; }',
		'.ap-justify-right { justify-content: right; }',
		'.ap-justify-stretch { justify-content: stretch; }',
		'.ap-items-stretch { align-items: stretch; }',
		'.ap-items-start { align-items: flex-start; }',
		'.ap-items-end { align-items: flex-end; }',
		'.ap-items-center { align-items: center; }',
		'.ap-items-baseline { align-items: baseline; }',
		'.ap-items-self-start { align-items: self-start; }',
		'.ap-items-self-end { align-items: self-end; }',
		'.ap-items-first-baseline { align-items: first baseline; }',
		'.ap-items-last-baseline { align-items: last baseline; }',
		'.ap-content-stretch { align-content: stretch; }',
		'.ap-content-start { align-content: flex-start; }',
		'.ap-content-end { align-content: flex-end; }',
		'.ap-content-center { align-content: center; }',
		'.ap-content-between { align-content: space-between; }',
		'.ap-content-around { align-content: space-around; }',
		'.ap-content-evenly { align-content: space-evenly; }',
		'.ap-content-baseline { align-content: baseline; }',
		'.ap-self-auto { align-self: auto; }',
		'.ap-self-start { align-self: flex-start; }',
		'.ap-self-end { align-self: flex-end; }',
		'.ap-self-center { align-self: center; }',
		'.ap-self-stretch { align-self: stretch; }',
		'.ap-self-baseline { align-self: baseline; }',
		'.ap-self-self-start { align-self: self-start; }',
		'.ap-self-self-end { align-self: self-end; }',
		'.ap-grow-0 { flex-grow: 0; }',
		'.ap-grow-1 { flex-grow: 1; }',
		'.ap-grow-2 { flex-grow: 2; }',
		'.ap-grow-3 { flex-grow: 3; }',
		'.ap-grow-4 { flex-grow: 4; }',
		'.ap-grow-5 { flex-grow: 5; }',
		'.ap-grow-6 { flex-grow: 6; }',
		'.ap-grow-7 { flex-grow: 7; }',
		'.ap-grow-8 { flex-grow: 8; }',
		'.ap-grow-9 { flex-grow: 9; }',
		'.ap-shrink-0 { flex-shrink: 0; }',
		'.ap-shrink-1 { flex-shrink: 1; }',
		'.ap-shrink-2 { flex-shrink: 2; }',
		'.ap-shrink-3 { flex-shrink: 3; }',
		'.ap-shrink-4 { flex-shrink: 4; }',
		'.ap-shrink-5 { flex-shrink: 5; }',
		'.ap-shrink-6 { flex-shrink: 6; }',
		'.ap-shrink-7 { flex-shrink: 7; }',
		'.ap-shrink-8 { flex-shrink: 8; }',
		'.ap-shrink-9 { flex-shrink: 9; }',
		'.ap-basis-auto { flex-basis: auto; }',
		'.ap-basis-0 { flex-basis: 0; }',
		'.ap-basis-full { flex-basis: 100%; }',
		'.ap-basis-fit-content { flex-basis: fit-content; }',
		'.ap-basis-max-content { flex-basis: max-content; }',
		'.ap-basis-min-content { flex-basis: min-content; }',
		'.ap-order-0 { order: 0; }',
		'.ap-order-1 { order: 1; }',
		'.ap-order-2 { order: 2; }',
		'.ap-order-3 { order: 3; }',
		'.ap-order-4 { order: 4; }',
		'.ap-order-5 { order: 5; }',
		'.ap-order-6 { order: 6; }',
		'.ap-order-7 { order: 7; }',
		'.ap-order-8 { order: 8; }',
		'.ap-order-9 { order: 9; }',
		'.ap-order-\\-1 { order: -1; }',
	];

	/**
	 * Rules repeated inside each breakpoint's `@media` block; `{p}` is the
	 * escaped `{key}:` class prefix.
	 *
	 * @since 1.15.1
	 *
	 * @var list<string>
	 */
	public const BREAKPOINT_RULES = [
		':where(.{p}ap-flex) > * + * { margin-block-start: 0; }',
		'.{p}ap-flex { display: flex; }',
		'.{p}ap-flex-none { display: block; }',
		'.{p}ap-flex-row { flex-direction: row; }',
		'.{p}ap-flex-row-reverse { flex-direction: row-reverse; }',
		'.{p}ap-flex-col { flex-direction: column; }',
		'.{p}ap-flex-col-reverse { flex-direction: column-reverse; }',
		'.{p}ap-flex-nowrap { flex-wrap: nowrap; }',
		'.{p}ap-flex-wrap { flex-wrap: wrap; }',
		'.{p}ap-flex-wrap-reverse { flex-wrap: wrap-reverse; }',
		'.{p}ap-justify-start { justify-content: flex-start; }',
		'.{p}ap-justify-center { justify-content: center; }',
		'.{p}ap-justify-end { justify-content: flex-end; }',
		'.{p}ap-justify-between { justify-content: space-between; }',
		'.{p}ap-justify-around { justify-content: space-around; }',
		'.{p}ap-justify-evenly { justify-content: space-evenly; }',
		'.{p}ap-items-stretch { align-items: stretch; }',
		'.{p}ap-items-start { align-items: flex-start; }',
		'.{p}ap-items-end { align-items: flex-end; }',
		'.{p}ap-items-center { align-items: center; }',
		'.{p}ap-items-baseline { align-items: baseline; }',
		'.{p}ap-content-stretch { align-content: stretch; }',
		'.{p}ap-content-start { align-content: flex-start; }',
		'.{p}ap-content-end { align-content: flex-end; }',
		'.{p}ap-content-center { align-content: center; }',
		'.{p}ap-content-between { align-content: space-between; }',
		'.{p}ap-self-auto { align-self: auto; }',
		'.{p}ap-self-start { align-self: flex-start; }',
		'.{p}ap-self-end { align-self: flex-end; }',
		'.{p}ap-self-center { align-self: center; }',
		'.{p}ap-self-stretch { align-self: stretch; }',
		'.{p}ap-grow-0 { flex-grow: 0; }',
		'.{p}ap-grow-1 { flex-grow: 1; }',
		'.{p}ap-grow-2 { flex-grow: 2; }',
		'.{p}ap-shrink-0 { flex-shrink: 0; }',
		'.{p}ap-shrink-1 { flex-shrink: 1; }',
		'.{p}ap-basis-auto { flex-basis: auto; }',
		'.{p}ap-basis-full { flex-basis: 100%; }',
		'.{p}ap-order-0 { order: 0; }',
		'.{p}ap-order-1 { order: 1; }',
	];

	/**
	 * The stylesheet for the default breakpoints, built once.
	 *
	 * @since 1.15.1
	 */
	protected static ?string $defaultCss = null;

	/**
	 * The token stylesheet for a registry, or `''` when its breakpoints
	 * match the package defaults (the static stylesheet covers those).
	 *
	 * @since 1.15.1
	 */
	public static function css( BreakpointRegistry $registry ): string
	{
		$css = self::build( $registry );

		self::$defaultCss ??= self::build( new BreakpointRegistry( BreakpointRegistry::DEFAULTS ) );

		return $css === self::$defaultCss ? '' : $css;
	}

	/**
	 * The full token stylesheet for a registry: the base rules, then one
	 * `@media` block per breakpoint in the registry's emission order
	 * (legacy keys ascending, then devices largest first, so narrower
	 * overrides win).
	 *
	 * @since 1.15.1
	 */
	public static function build( BreakpointRegistry $registry ): string
	{
		$blocks = [ implode( "\n", self::BASE_RULES ) ];

		foreach ( $registry->prefixes() as $key ) {
			$query = $registry->mediaQuery( $key, true );

			if ( null === $query ) {
				continue;
			}

			$prefix = self::escapePrefix( $key );
			$rules  = array_map(
				static fn ( string $rule ): string => "\t" . str_replace( '{p}', $prefix, $rule ),
				self::BREAKPOINT_RULES,
			);

			$blocks[] = '@media ' . $query . " {\n" . implode( "\n", $rules ) . "\n}";
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * The `{key}:` class prefix as a selector: the colon escaped, and a
	 * leading digit written as a hex escape (`2xl` → `\32xl\:`), with the
	 * separating space CSS needs when the next character is a hex digit.
	 *
	 * @since 1.15.1
	 */
	public static function escapePrefix( string $key ): string
	{
		if ( '' !== $key && ctype_digit( $key[0] ) ) {
			$rest = substr( $key, 1 );
			$key  = '\\3' . $key[0] . ( 1 === preg_match( '/^[0-9a-f]/i', $rest ) ? ' ' : '' ) . $rest;
		}

		return $key . '\\:';
	}
}
