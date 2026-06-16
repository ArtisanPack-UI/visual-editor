<?php

/**
 * Flex layout serializer — Blade renderer (#595).
 *
 * Mirrors `resources/js/visual-editor/blocks/_shared/flex-controls/
 * serializer.ts` exactly. Byte-identical class strings against the
 * shared `fixtures.json` fixtures are asserted by the Pest suite.
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

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;

class FlexSupport
{
	/**
	 * Convenience used by block partials: serialize the block's
	 * `artisanpackFlex` attribute, push any arbitrary-value rules into
	 * the per-request CSS accumulator, and return the classes ready
	 * for `BlockSupports::wrapperAttrs()`.
	 *
	 * @param  array<string, mixed>  $attributes  Block attributes.
	 *
	 * @return array<int, string> Class list (possibly empty).
	 */
	public static function wrapperForBlock( array $attributes ): array
	{
		$flex = $attributes[ 'artisanpackFlex' ] ?? null;
		if ( ! is_array( $flex ) ) {
			return [];
		}

		try {
			$support = app( self::class );
		} catch ( \Throwable $e ) {
			return [];
		}

		$result = $support->serialize( $flex );

		$css = $support->buildArbitraryStyles( $result[ 'arbitraryRules' ] );
		if ( '' !== $css ) {
			try {
				// Per-block scope keyed by the CSS content so identical
				// rules dedupe but blocks with different arbitrary values
				// (e.g. `ap-gap-x-[16px]` vs `ap-gap-x-[24px]`) each land
				// in the accumulator instead of one overwriting the other.
				$scope = 'flex-arbitrary-' . substr( sha1( $css ), 0, 12 );
				app( ResponsiveCssAccumulator::class )->push( $scope, $css );
			} catch ( \Throwable $e ) {
				// Accumulator not booted (early or test path) — silently drop.
			}
		}

		return $result[ 'classes' ];
	}


	private const JUSTIFY_TOKEN = [
		'flex-start'    => 'start',
		'flex-end'      => 'end',
		'center'        => 'center',
		'space-between' => 'between',
		'space-around'  => 'around',
		'space-evenly'  => 'evenly',
		'start'         => 'start',
		'end'           => 'end',
		'left'          => 'left',
		'right'         => 'right',
		'stretch'       => 'stretch',
	];

	private const ALIGN_ITEMS_TOKEN = [
		'stretch'        => 'stretch',
		'flex-start'     => 'start',
		'flex-end'       => 'end',
		'center'         => 'center',
		'baseline'       => 'baseline',
		'start'          => 'start',
		'end'            => 'end',
		'self-start'     => 'self-start',
		'self-end'       => 'self-end',
		'first baseline' => 'first-baseline',
		'last baseline'  => 'last-baseline',
	];

	private const ALIGN_CONTENT_TOKEN = [
		'stretch'       => 'stretch',
		'flex-start'    => 'start',
		'flex-end'      => 'end',
		'center'        => 'center',
		'space-between' => 'between',
		'space-around'  => 'around',
		'space-evenly'  => 'evenly',
		'start'         => 'start',
		'end'           => 'end',
		'baseline'      => 'baseline',
	];

	private const ALIGN_SELF_TOKEN = [
		'auto'       => 'auto',
		'flex-start' => 'start',
		'flex-end'   => 'end',
		'center'     => 'center',
		'stretch'    => 'stretch',
		'baseline'   => 'baseline',
		'start'      => 'start',
		'end'        => 'end',
		'self-start' => 'self-start',
		'self-end'   => 'self-end',
	];

	private const DIRECTION_TOKEN = [
		'row'            => 'row',
		'row-reverse'    => 'row-reverse',
		'column'         => 'col',
		'column-reverse' => 'col-reverse',
	];

	private const WRAP_TOKEN = [
		'nowrap'       => 'nowrap',
		'wrap'         => 'wrap',
		'wrap-reverse' => 'wrap-reverse',
	];

	private const NUMERIC_GROW_SHRINK = [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ];

	private const NUMERIC_ORDER = [ -1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ];

	private const BASIS_KEYWORDS = [
		'auto',
		'0',
		'full',
		'fit-content',
		'max-content',
		'min-content',
	];

	public function __construct(
		protected BreakpointRegistry $registry,
		protected ResponsiveValueResolver $resolver,
	) {}

	/**
	 * Serialize a flex container attribute slice.
	 *
	 * @param  array<string, mixed>|null  $flex
	 *
	 * @return array{classes: array<int, string>, arbitraryRules: array<int, array<string, string>>}
	 */
	public function serializeContainer( ?array $flex ): array
	{
		$container = $flex[ 'container' ] ?? null;
		$result    = [ 'classes' => [], 'arbitraryRules' => [] ];

		if ( ! is_array( $container ) ) {
			return $result;
		}

		// Emit a matching `ap-flex-none` reset for `false` overrides so a
		// cascade like `{ base: true, md: false }` actually un-flexes at
		// `md+`. See serializer.ts for the TS-side mirror.
		$this->emitForEachBreakpoint( $container[ 'enabled' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			if ( true === $value ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-flex';
			} elseif ( false === $value ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-flex-none';
			}
		} );

		$this->emitForEachBreakpoint( $container[ 'direction' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$token = self::DIRECTION_TOKEN[ $value ] ?? null;
			if ( null !== $token ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-flex-' . $token;
			}
		} );

		$this->emitForEachBreakpoint( $container[ 'wrap' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$token = self::WRAP_TOKEN[ $value ] ?? null;
			if ( null !== $token ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-flex-' . $token;
			}
		} );

		$this->emitForEachBreakpoint( $container[ 'justifyContent' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$token = self::JUSTIFY_TOKEN[ $value ] ?? null;
			if ( null !== $token ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-justify-' . $token;
			}
		} );

		$this->emitForEachBreakpoint( $container[ 'alignItems' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$token = self::ALIGN_ITEMS_TOKEN[ $value ] ?? null;
			if ( null !== $token ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-items-' . $token;
			}
		} );

		$this->emitForEachBreakpoint( $container[ 'alignContent' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$token = self::ALIGN_CONTENT_TOKEN[ $value ] ?? null;
			if ( null !== $token ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-content-' . $token;
			}
		} );

		$this->emitForEachBreakpoint( $container[ 'placeContent' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$className                 = $this->prefix( $bp ) . 'ap-place-content-' . $this->bracket( (string) $value );
			$result[ 'classes' ][]     = $className;
			$result[ 'arbitraryRules' ][] = [
				'className'  => $className,
				'property'   => 'place-content',
				'value'      => (string) $value,
				'breakpoint' => $bp,
			];
		} );

		$gap = $container[ 'gap' ] ?? null;
		if ( is_array( $gap ) ) {
			$this->emitForEachBreakpoint( $gap[ 'row' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
				$className                 = $this->prefix( $bp ) . 'ap-gap-y-' . $this->bracket( (string) $value );
				$result[ 'classes' ][]     = $className;
				$result[ 'arbitraryRules' ][] = [
					'className'  => $className,
					'property'   => 'row-gap',
					'value'      => (string) $value,
					'breakpoint' => $bp,
				];
			} );

			$this->emitForEachBreakpoint( $gap[ 'column' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
				$className                 = $this->prefix( $bp ) . 'ap-gap-x-' . $this->bracket( (string) $value );
				$result[ 'classes' ][]     = $className;
				$result[ 'arbitraryRules' ][] = [
					'className'  => $className,
					'property'   => 'column-gap',
					'value'      => (string) $value,
					'breakpoint' => $bp,
				];
			} );
		}

		return $result;
	}

	/**
	 * Serialize a flex item attribute slice.
	 *
	 * @param  array<string, mixed>|null  $flex
	 *
	 * @return array{classes: array<int, string>, arbitraryRules: array<int, array<string, string>>}
	 */
	public function serializeItem( ?array $flex ): array
	{
		$item   = $flex[ 'item' ] ?? null;
		$result = [ 'classes' => [], 'arbitraryRules' => [] ];

		if ( ! is_array( $item ) ) {
			return $result;
		}

		$this->emitForEachBreakpoint( $item[ 'alignSelf' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$token = self::ALIGN_SELF_TOKEN[ $value ] ?? null;
			if ( null !== $token ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-self-' . $token;
			}
		} );

		$this->emitForEachBreakpoint( $item[ 'grow' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$this->emitNumericClass( 'flex-grow', 'ap-grow', $value, $bp, self::NUMERIC_GROW_SHRINK, $result );
		} );

		$this->emitForEachBreakpoint( $item[ 'shrink' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$this->emitNumericClass( 'flex-shrink', 'ap-shrink', $value, $bp, self::NUMERIC_GROW_SHRINK, $result );
		} );

		$this->emitForEachBreakpoint( $item[ 'basis' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			if ( in_array( (string) $value, self::BASIS_KEYWORDS, true ) ) {
				$result[ 'classes' ][] = $this->prefix( $bp ) . 'ap-basis-' . $value;
				return;
			}

			$className                 = $this->prefix( $bp ) . 'ap-basis-' . $this->bracket( (string) $value );
			$result[ 'classes' ][]     = $className;
			$result[ 'arbitraryRules' ][] = [
				'className'  => $className,
				'property'   => 'flex-basis',
				'value'      => (string) $value,
				'breakpoint' => $bp,
			];
		} );

		$this->emitForEachBreakpoint( $item[ 'order' ] ?? null, function ( $value, string $bp ) use ( &$result ): void {
			$this->emitNumericClass( 'order', 'ap-order', $value, $bp, self::NUMERIC_ORDER, $result );
		} );

		return $result;
	}

	/**
	 * Serialize both container + item slices and merge.
	 *
	 * @param  array<string, mixed>|null  $flex
	 *
	 * @return array{classes: array<int, string>, arbitraryRules: array<int, array<string, string>>}
	 */
	public function serialize( ?array $flex ): array
	{
		$container = $this->serializeContainer( $flex );
		$item      = $this->serializeItem( $flex );

		return [
			'classes'        => array_merge( $container[ 'classes' ], $item[ 'classes' ] ),
			'arbitraryRules' => array_merge( $container[ 'arbitraryRules' ], $item[ 'arbitraryRules' ] ),
		];
	}

	/**
	 * Build a scoped `<style>` snippet for arbitrary-value rules.
	 *
	 * @param  array<int, array<string, string>>  $rules
	 */
	public function buildArbitraryStyles( array $rules ): string
	{
		if ( [] === $rules ) {
			return '';
		}

		$grouped = [];
		foreach ( $rules as $rule ) {
			$grouped[ $rule[ 'breakpoint' ] ][] = $rule;
		}

		$out = '';
		foreach ( $this->registry->keysWithBase() as $bp ) {
			if ( ! isset( $grouped[ $bp ] ) ) {
				continue;
			}

			$body = '';
			foreach ( $grouped[ $bp ] as $rule ) {
				$selector = '.' . $this->escapeSelector( $rule[ 'className' ] );
				$body    .= sprintf( "%s { %s: %s; } ", $selector, $rule[ 'property' ], $rule[ 'value' ] );
			}

			if ( BreakpointRegistry::BASE_KEY === $bp ) {
				$out .= $body;
				continue;
			}

			$minWidth = $this->registry->get( $bp );
			if ( null === $minWidth ) {
				continue;
			}

			$out .= sprintf( "@media (min-width: %dpx) { %s} ", $minWidth, $body );
		}

		return trim( $out );
	}

	private function emitForEachBreakpoint( $attribute, callable $cb ): void
	{
		if ( null === $attribute || '' === $attribute ) {
			return;
		}

		$distinct = $this->resolver->distinctOverrides( $attribute );

		foreach ( $this->registry->keysWithBase() as $bp ) {
			if ( ! array_key_exists( $bp, $distinct ) ) {
				continue;
			}

			$value = $distinct[ $bp ];
			if ( null === $value ) {
				continue;
			}

			$cb( $value, $bp );
		}
	}

	private function emitNumericClass(
		string $property,
		string $prefix,
		$value,
		string $bp,
		array $canonical,
		array &$result,
	): void {
		// Match the TS serializer's `/^-?\d+$/` semantics — accept only
		// plain integer strings/ints so `"1.5"` no longer collapses to
		// `1` via `(int)` casting and emits a wrong canonical class.
		if (
			( is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', $value ) ) )
			&& in_array( (int) $value, $canonical, true )
		) {
			$result[ 'classes' ][] = $this->prefix( $bp ) . $prefix . '-' . (int) $value;
			return;
		}

		$raw       = is_numeric( $value ) ? (string) $value : (string) $value;
		$className = $this->prefix( $bp ) . $prefix . '-' . $this->bracket( $raw );

		$result[ 'classes' ][]        = $className;
		$result[ 'arbitraryRules' ][] = [
			'className'  => $className,
			'property'   => $property,
			'value'      => $raw,
			'breakpoint' => $bp,
		];
	}

	private function prefix( string $breakpoint ): string
	{
		return BreakpointRegistry::BASE_KEY === $breakpoint ? '' : $breakpoint . ':';
	}

	private function bracket( string $value ): string
	{
		return '[' . preg_replace( '/\s+/', '_', $value ) . ']';
	}

	private function escapeSelector( string $className ): string
	{
		// Escape `:`, `[`, `]`, `(`, `)`, `.`, `,`, `/`, `%` for CSS selectors.
		return preg_replace( '/([:\[\]\(\)\.,\/%])/', '\\\\$1', $className );
	}
}
